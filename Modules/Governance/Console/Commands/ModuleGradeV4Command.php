<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Avalia maturidade de Modules/<X>/ via rubrica V4 — scoped scorecards por bucket
 * (Wave 21 — coexiste com v3 ModuleGradeCommand; Wave 27 — polish flags + agregação).
 *
 * V4 difere de v3 por usar scorecards específicos do bucket do módulo:
 *   - vertical_client_facing  → Vestuario, ComunicacaoVisual, OficinaAuto, Repair, ProductCatalogue...
 *   - cross_cutting_infra     → Governance, Auditoria, TeamMcp, Superadmin, Admin, KB...
 *   - ai_central              → Jana, Brief
 *   - functional_horizontal   → Crm, Financeiro, NfeBrasil, RecurringBilling, Whatsapp...
 *
 * YAMLs em `memory/governance/scorecards/<bucket>.yaml` (Wave 22+).
 *
 * Uso:
 *   php artisan module:grade-v4 Vestuario                    # 1 módulo + tabela
 *   php artisan module:grade-v4 --all                        # todos 34 módulos
 *   php artisan module:grade-v4 --bucket=ai_central          # só Jana/Brief
 *   php artisan module:grade-v4 --all --meta-only            # só os abaixo da meta
 *   php artisan module:grade-v4 --all --summary              # agrega por bucket
 *   php artisan module:grade-v4 --all --export-baseline      # JSON pra CI gate v4
 *   php artisan module:grade-v4 Vestuario --json             # JSON pra CI
 *   php artisan module:grade-v4 Vestuario --detail           # breakdown por rule
 *
 * NOTA: NÃO usa `--verbose` (reservado Symfony Console — colide). Usa `--detail`.
 *       Ver [.claude/rules/commands.md] §"--detail NUNCA --verbose" (lição PR #851).
 *
 * @see Modules/Governance/Services/ScopedScorecardEvaluator.php (Wave 21 Agent A)
 * @see memory/governance/buckets/_INDEX.md (Wave 22)
 * @see memory/governance/scorecards/*.yaml (Wave 22)
 */
class ModuleGradeV4Command extends Command
{
    /**
     * Path padrão pra baseline export (relativo a base_path).
     */
    private const BASELINE_RELATIVE_PATH = 'memory/governance/baselines/module-grade-v4-baseline.json';

    /**
     * Thresholds canônicos por bucket (espelho dos `target_score` em
     * memory/governance/buckets/<bucket>.yaml — fallback se YAML ausente).
     */
    private const BUCKET_META_FALLBACK = [
        'vertical_client_facing' => 85,
        'cross_cutting_infra'    => 80,
        'ai_central'             => 85,
        'functional_horizontal'  => 80,
    ];

    protected $signature = 'module:grade-v4
                            {module? : Nome do módulo (Vestuario, Jana, etc). Vazio + --all avalia todos}
                            {--all : Avalia todos os módulos detectados em Modules/}
                            {--bucket= : Filtra por bucket (vertical_client_facing/cross_cutting_infra/ai_central/functional_horizontal)}
                            {--meta-only : Mostra somente módulos abaixo da meta do bucket}
                            {--summary : Agrega por bucket (média + count Excelente/Bom/Médio/Crítico)}
                            {--export-baseline : Salva JSON em memory/governance/baselines/ pra gate CI v4 futuro}
                            {--json : Output JSON (machine-readable, pra CI)}
                            {--detail : Mostra breakdown por rule (não só dimensão)}';

    protected $description = 'Avalia módulos via rubrica V4 — scoped scorecards por bucket (Wave 21 + polish Wave 27)';

    public function handle(): int
    {
        $evaluatorClass = 'Modules\\Governance\\Services\\ScopedScorecardEvaluator';

        if (! class_exists($evaluatorClass)) {
            $this->error('ScopedScorecardEvaluator não encontrado. Wave 21 Agent A ainda não criou o Service.');
            $this->line('Expected: Modules/Governance/Services/ScopedScorecardEvaluator.php');
            return self::FAILURE;
        }

        $evaluator = app($evaluatorClass);

        $module        = $this->argument('module');
        $all           = (bool) $this->option('all');
        $bucket        = $this->option('bucket');
        $json          = (bool) $this->option('json');
        $detail        = (bool) $this->option('detail');
        $metaOnly      = (bool) $this->option('meta-only');
        $summary       = (bool) $this->option('summary');
        $exportBase    = (bool) $this->option('export-baseline');

        if (! $module && ! $all && ! $bucket) {
            $this->error('Forneça {module} OU --all OU --bucket=<nome>. Ex: `php artisan module:grade-v4 Vestuario`');
            return self::INVALID;
        }

        // Modo single
        if ($module && ! $all && ! $bucket) {
            return $this->evaluateOne($evaluator, $module, $json, $detail);
        }

        // Modo all/bucket
        $modules = $this->discoverModules();
        if ($bucket) {
            $modules = $this->filterByBucket($evaluator, $modules, $bucket);
        }

        if ($modules->isEmpty()) {
            $this->warn('Nenhum módulo encontrado pra avaliar.');
            return self::SUCCESS;
        }

        return $this->evaluateMany($evaluator, $modules, $json, $detail, $metaOnly, $summary, $exportBase);
    }

    /**
     * Avalia 1 módulo.
     */
    private function evaluateOne($evaluator, string $module, bool $json, bool $detail): int
    {
        $scorecard = $evaluator->loadScorecardForModule($module);

        if (empty($scorecard)) {
            $this->error("Módulo `{$module}`: scorecard não encontrado em memory/governance/scorecards/ ou módulo não classificado em bucket.");
            return self::FAILURE;
        }

        $result = $evaluator->evaluateScorecard($module, $scorecard);

        if ($json) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->printResult($result, $detail);
        return self::SUCCESS;
    }

    /**
     * Avalia N módulos (--all ou --bucket) + agregações opcionais.
     */
    private function evaluateMany(
        $evaluator,
        $modules,
        bool $json,
        bool $detail,
        bool $metaOnly,
        bool $summary,
        bool $exportBaseline
    ): int {
        $results = [];
        foreach ($modules as $module) {
            $scorecard = $evaluator->loadScorecardForModule($module);
            if (empty($scorecard)) {
                continue; // módulo sem scorecard (ainda não classificado em bucket)
            }
            $results[] = $evaluator->evaluateScorecard($module, $scorecard);
        }

        if (empty($results)) {
            $this->warn('Nenhum módulo com scorecard classificado em bucket.');
            return self::SUCCESS;
        }

        // Filtro --meta-only (após avaliar)
        if ($metaOnly) {
            $results = array_values(array_filter($results, function ($r) {
                $total = (int) ($r['score_total'] ?? 0);
                $meta = $this->metaForBucket($r['bucket'] ?? 'unknown');
                return $total < $meta;
            }));
        }

        // Export baseline antes de filtros visuais (baseline = ground truth completo)
        if ($exportBaseline) {
            $path = $this->exportBaseline($results);
            $this->info("Baseline exportado: {$path}");
        }

        if ($json) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        if ($summary) {
            $this->printSummary($results);
            return self::SUCCESS;
        }

        if (empty($results)) {
            $this->info('Nenhum módulo abaixo da meta. ✓');
            return self::SUCCESS;
        }

        $rows = array_map(function ($r) {
            $coreScore   = $r['core']['score'] ?? $this->sumCoreScore($r);
            $coreMax     = $r['core']['max']   ?? $this->sumCoreMax($r);
            $bucketScore = $this->sumBucketDimensionsScore($r);
            $bucketMax   = $this->sumBucketDimensionsMax($r);
            $total       = (int) ($r['score_total'] ?? 0);
            $bucket      = (string) ($r['bucket'] ?? '?');
            $meta        = $this->metaForBucket($bucket);
            $status      = $total >= $meta ? '✓' : '✗';

            return [
                'Módulo'     => $r['module'] ?? '?',
                'Bucket'     => $bucket,
                'Core'       => "{$coreScore}/{$coreMax}",
                'Bucket Dim' => "{$bucketScore}/{$bucketMax}",
                'Total'      => "{$total}/100",
                'Meta'       => $meta,
                'Status'     => $status,
            ];
        }, $results);

        $this->table(
            ['Módulo', 'Bucket', 'Core', 'Bucket Dim', 'Total', 'Meta', 'Status'],
            $rows
        );

        $count = count($results);
        $avg = $count > 0 ? round(array_sum(array_column($results, 'score_total')) / $count, 1) : 0;
        $this->newLine();
        $this->info("Média: {$avg} pts ({$count} módulos com scorecard)");

        if ($detail) {
            $this->newLine();
            $this->line('<fg=white;options=bold>Breakdown por módulo:</>');
            foreach ($results as $r) {
                $this->printDetail($r);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Agrega resultados por bucket (média, count Excelente/Bom/Médio/Crítico).
     *
     * Faixas:
     *   Excelente ≥ 90  · Bom 70-89  · Médio 50-69  · Crítico < 50
     */
    private function printSummary(array $results): void
    {
        $buckets = [];
        foreach ($results as $r) {
            $b = (string) ($r['bucket'] ?? 'unknown');
            $total = (int) ($r['score_total'] ?? 0);
            if (! isset($buckets[$b])) {
                $buckets[$b] = [
                    'count' => 0,
                    'sum' => 0,
                    'excelente' => 0,
                    'bom' => 0,
                    'medio' => 0,
                    'critico' => 0,
                    'abaixo_meta' => 0,
                ];
            }
            $buckets[$b]['count']++;
            $buckets[$b]['sum'] += $total;
            if ($total >= 90) {
                $buckets[$b]['excelente']++;
            } elseif ($total >= 70) {
                $buckets[$b]['bom']++;
            } elseif ($total >= 50) {
                $buckets[$b]['medio']++;
            } else {
                $buckets[$b]['critico']++;
            }
            $meta = $this->metaForBucket($b);
            if ($total < $meta) {
                $buckets[$b]['abaixo_meta']++;
            }
        }

        ksort($buckets);

        $rows = [];
        foreach ($buckets as $name => $agg) {
            $avg = $agg['count'] > 0 ? round($agg['sum'] / $agg['count'], 1) : 0;
            $meta = $this->metaForBucket($name);
            $rows[] = [
                'Bucket'       => $name,
                'Módulos'      => $agg['count'],
                'Média'        => "{$avg}/100",
                'Meta'         => $meta,
                'Excelente'    => $agg['excelente'],
                'Bom'          => $agg['bom'],
                'Médio'        => $agg['medio'],
                'Crítico'      => $agg['critico'],
                'Abaixo meta'  => $agg['abaixo_meta'],
            ];
        }

        $this->newLine();
        $this->line('<fg=white;options=bold>Sumário agregado por bucket (Wave 27):</>');
        $this->table(
            ['Bucket', 'Módulos', 'Média', 'Meta', 'Excelente', 'Bom', 'Médio', 'Crítico', 'Abaixo meta'],
            $rows
        );

        $totalModules = array_sum(array_column($buckets, 'count'));
        $totalSum     = array_sum(array_column($buckets, 'sum'));
        $globalAvg    = $totalModules > 0 ? round($totalSum / $totalModules, 1) : 0;
        $abaixoMeta   = array_sum(array_column($buckets, 'abaixo_meta'));

        $this->newLine();
        $this->info("Média projeto: {$globalAvg} pts · Total módulos: {$totalModules} · Abaixo meta: {$abaixoMeta}");
    }

    /**
     * Exporta resultado completo em JSON (machine-readable) pra futuros gates CI v4.
     * Path: memory/governance/baselines/module-grade-v4-baseline.json
     */
    private function exportBaseline(array $results): string
    {
        $relPath = self::BASELINE_RELATIVE_PATH;
        $absPath = base_path($relPath);
        $dir = dirname($absPath);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0775, true, true);
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'rubrica'      => 'module-grade-v4',
            'wave'         => 27,
            'count'        => count($results),
            'modules'      => $results,
        ];

        File::put($absPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $relPath;
    }

    /**
     * Discover todos módulos via Glob Modules/*\/module.json
     *
     * @return \Illuminate\Support\Collection<int,string>
     */
    private function discoverModules(): \Illuminate\Support\Collection
    {
        $modulesPath = base_path('Modules');
        if (! is_dir($modulesPath)) {
            return collect();
        }

        return collect(File::directories($modulesPath))
            ->map(fn ($path) => basename($path))
            ->filter(fn ($name) => file_exists($modulesPath . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'module.json'))
            ->sort()
            ->values();
    }

    /**
     * Filtra módulos por bucket via loadScorecardForModule (verifica metadata.bucket).
     */
    private function filterByBucket($evaluator, $modules, string $bucket): \Illuminate\Support\Collection
    {
        $valid = array_keys(self::BUCKET_META_FALLBACK);
        if (! in_array($bucket, $valid, true)) {
            $this->warn("Bucket `{$bucket}` inválido. Válidos: " . implode(', ', $valid));
            return collect();
        }

        $validBuckets = array_keys(self::BUCKET_META_FALLBACK);
        return $modules->filter(function ($module) use ($evaluator, $bucket, $validBuckets) {
            $sc = $evaluator->loadScorecardForModule($module);
            // Ordem: metadata.bucket (canon novo) → bucket (legado, ignora se for faixa
            // tipo "Bom"/"Excelente"/"Médio"/"Crítico") → fallback module.json governance.bucket.
            $scBucketField = $sc['bucket'] ?? null;
            $scBucket = $sc['metadata']['bucket']
                ?? (in_array($scBucketField, $validBuckets, true) ? $scBucketField : null)
                ?? $evaluator->resolveBucketForModule($module);
            return $scBucket === $bucket;
        })->values();
    }

    /**
     * Resolve meta-score do bucket (consulta YAML real via Evaluator se houver, fallback const).
     */
    private function metaForBucket(string $bucket): int
    {
        $evaluatorClass = 'Modules\\Governance\\Services\\ScopedScorecardEvaluator';
        if (class_exists($evaluatorClass)) {
            $evaluator = app($evaluatorClass);
            $config = $evaluator->loadBucketConfig($bucket);
            if (! empty($config['target_score'])) {
                return (int) $config['target_score'];
            }
        }
        return self::BUCKET_META_FALLBACK[$bucket] ?? 60;
    }

    private function printResult(array $result, bool $detail): void
    {
        $total = $result['score_total'] ?? 0;
        $bucket = (string) ($result['bucket'] ?? '?');
        $meta  = $this->metaForBucket($bucket);
        $color = $total >= 80 ? 'green' : ($total >= 60 ? 'cyan' : ($total >= 40 ? 'yellow' : 'red'));

        $this->newLine();
        $this->line("<fg=white;options=bold>Modules/{$result['module']}</> <fg=gray>(bucket: {$bucket})</>");
        $this->line("Total: <fg={$color};options=bold>{$total}/100</> · Meta bucket: <fg={$color}>{$meta}</>");
        $this->newLine();

        $coreScore = $result['core']['score'] ?? $this->sumCoreScore($result);
        $coreMax   = $result['core']['max'] ?? $this->sumCoreMax($result);
        $this->line("Core (D1 multi-tenant + D8 segurança): {$coreScore}/{$coreMax}");

        if (! empty($result['bucket_dimensions'])) {
            $this->newLine();
            $this->line('<fg=cyan>Dimensões específicas do bucket:</>');
            $rows = [];
            foreach ($result['bucket_dimensions'] as $key => $dim) {
                $rows[] = [
                    $key,
                    ($dim['score'] ?? 0) . '/' . ($dim['peso'] ?? $dim['max'] ?? 0),
                    $dim['target'] ?? $dim['weight'] ?? '—',
                ];
            }
            $this->table(['Dimensão', 'Score', 'Target'], $rows);
        }

        if (! empty($result['paired_violations'])) {
            $this->newLine();
            $this->line('<fg=red>Paired violations (regressões detectadas):</>');
            foreach ($result['paired_violations'] as $v) {
                $label = is_array($v) ? ($v['pair'] ?? json_encode($v)) : (string) $v;
                $this->line("  - {$label}");
            }
        }

        if ($detail) {
            $this->printDetail($result);
        }
    }

    private function printDetail(array $result): void
    {
        $this->newLine();
        $this->line("<fg=white;options=bold>Breakdown {$result['module']}:</>");

        foreach (['core', 'bucket_dimensions'] as $section) {
            if (! isset($result[$section])) {
                continue;
            }
            $this->line("<fg=cyan>  {$section}:</>");
            $items = $section === 'core' ? ($result['core']['rules'] ?? $result['core'] ?? []) : $result['bucket_dimensions'];
            foreach ($items as $key => $item) {
                if (is_array($item) && isset($item['rules'])) {
                    foreach ($item['rules'] as $rk => $rule) {
                        $score = $rule['score'] ?? 0;
                        $max   = $rule['max'] ?? $rule['peso'] ?? 0;
                        $color = $score === $max ? 'green' : ($score > 0 ? 'yellow' : 'red');
                        $ev    = $rule['evidence'] ?? '';
                        $this->line("    <fg={$color}>[{$score}/{$max}]</> {$key}.{$rk} — {$ev}");
                    }
                } elseif (is_array($item)) {
                    $score = $item['score'] ?? 0;
                    $max   = $item['max'] ?? $item['peso'] ?? 0;
                    $color = ($max > 0 && $score === $max) ? 'green' : ($score > 0 ? 'yellow' : 'red');
                    $this->line("    <fg={$color}>[{$score}/{$max}]</> {$key}");
                }
            }
        }
    }

    private function sumBucketDimensionsScore(array $r): int
    {
        $sum = 0;
        foreach ($r['bucket_dimensions'] ?? [] as $dim) {
            $sum += $dim['score'] ?? 0;
        }
        return $sum;
    }

    private function sumBucketDimensionsMax(array $r): int
    {
        $sum = 0;
        foreach ($r['bucket_dimensions'] ?? [] as $dim) {
            $sum += $dim['peso'] ?? $dim['max'] ?? 0;
        }
        return $sum;
    }

    private function sumCoreScore(array $r): int
    {
        $sum = 0;
        foreach ($r['core'] ?? [] as $k => $dim) {
            if (! is_array($dim)) {
                continue;
            }
            $sum += $dim['score'] ?? 0;
        }
        return $sum;
    }

    private function sumCoreMax(array $r): int
    {
        $sum = 0;
        foreach ($r['core'] ?? [] as $k => $dim) {
            if (! is_array($dim)) {
                continue;
            }
            $sum += $dim['peso'] ?? $dim['max'] ?? 0;
        }
        return $sum;
    }
}
