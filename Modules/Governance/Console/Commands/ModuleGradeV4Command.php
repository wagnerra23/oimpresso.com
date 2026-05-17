<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Avalia maturidade de Modules/<X>/ via rubrica V4 — scoped scorecards por bucket
 * (Wave 21 — coexiste com v3 ModuleGradeCommand).
 *
 * V4 difere de v3 por usar scorecards específicos do bucket do módulo:
 *   - vertical_client_facing  → Vestuario, ComunicacaoVisual, OficinaAuto, Repair, ProductCatalogue...
 *   - cross_cutting_infra     → Governance, Auditoria, TeamMcp, Superadmin, Admin, KB...
 *   - ai_central              → Jana, Brief
 *   - functional_horizontal   → Crm, Financeiro, NfeBrasil, RecurringBilling, Whatsapp...
 *
 * YAMLs em `memory/scorecards/<bucket>.yaml` (criados Wave 21+22).
 *
 * Uso:
 *   php artisan module:grade-v4 Vestuario                    # 1 módulo + tabela
 *   php artisan module:grade-v4 --all                        # todos 34 módulos
 *   php artisan module:grade-v4 --bucket=ai_central          # só Jana/Brief
 *   php artisan module:grade-v4 Vestuario --json             # JSON pra CI
 *   php artisan module:grade-v4 Vestuario --detail           # breakdown por rule
 *
 * NOTA: NÃO usa `--verbose` (reservado Symfony Console — colide). Usa `--detail`.
 *
 * @see Modules/Governance/Services/ScopedScorecardEvaluator.php (Wave 21 Agent A)
 * @see memory/governance/buckets/_INDEX.md (Wave 22)
 * @see memory/scorecards/*.yaml (Wave 22)
 */
class ModuleGradeV4Command extends Command
{
    protected $signature = 'module:grade-v4
                            {module? : Nome do módulo (Vestuario, Jana, etc). Vazio + --all avalia todos}
                            {--all : Avalia todos os módulos detectados em Modules/}
                            {--bucket= : Filtra por bucket (vertical_client_facing/cross_cutting_infra/ai_central/functional_horizontal)}
                            {--json : Output JSON (machine-readable, pra CI)}
                            {--detail : Mostra breakdown por rule (não só dimensão)}';

    protected $description = 'Avalia módulos via rubrica V4 — scoped scorecards por bucket (Wave 21)';

    public function handle(): int
    {
        $evaluatorClass = 'Modules\\Governance\\Services\\ScopedScorecardEvaluator';

        if (! class_exists($evaluatorClass)) {
            $this->error('ScopedScorecardEvaluator não encontrado. Wave 21 Agent A ainda não criou o Service.');
            $this->line('Expected: Modules/Governance/Services/ScopedScorecardEvaluator.php');
            return self::FAILURE;
        }

        $evaluator = app($evaluatorClass);

        $module = $this->argument('module');
        $all    = (bool) $this->option('all');
        $bucket = $this->option('bucket');
        $json   = (bool) $this->option('json');
        $detail = (bool) $this->option('detail');

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

        return $this->evaluateMany($evaluator, $modules, $json, $detail);
    }

    /**
     * Avalia 1 módulo.
     */
    private function evaluateOne($evaluator, string $module, bool $json, bool $detail): int
    {
        $scorecard = $evaluator->loadScorecardForModule($module);

        if (empty($scorecard)) {
            $this->error("Módulo `{$module}`: scorecard não encontrado em memory/scorecards/ ou módulo não classificado em bucket.");
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
     * Avalia N módulos (--all ou --bucket).
     */
    private function evaluateMany($evaluator, $modules, bool $json, bool $detail): int
    {
        $results = [];
        foreach ($modules as $module) {
            $scorecard = $evaluator->loadScorecardForModule($module);
            if (empty($scorecard)) {
                continue; // módulo sem scorecard (ainda não classificado em bucket)
            }
            $results[] = $evaluator->evaluateScorecard($module, $scorecard);
        }

        if ($json) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        if (empty($results)) {
            $this->warn('Nenhum módulo com scorecard classificado em bucket.');
            return self::SUCCESS;
        }

        $rows = array_map(function ($r) {
            $coreScore = $r['core']['score'] ?? 0;
            $coreMax   = $r['core']['max'] ?? 0;
            $bucketScore = $this->sumBucketDimensionsScore($r);
            $bucketMax   = $this->sumBucketDimensionsMax($r);
            $total = $r['score_total'] ?? 0;
            $meta = $r['meta_bucket'] ?? '—';
            $status = $total >= 60 ? '✓' : '✗';

            return [
                'Módulo'    => $r['module'] ?? '?',
                'Bucket'    => $r['bucket'] ?? '?',
                'Core'      => "{$coreScore}/{$coreMax}",
                'Bucket'    => "{$bucketScore}/{$bucketMax}",
                'Total'     => "{$total}/100",
                'Meta'      => $meta,
                'Status'    => $status,
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
        $valid = ['vertical_client_facing', 'cross_cutting_infra', 'ai_central', 'functional_horizontal'];
        if (! in_array($bucket, $valid, true)) {
            $this->warn("Bucket `{$bucket}` inválido. Válidos: " . implode(', ', $valid));
            return collect();
        }

        return $modules->filter(function ($module) use ($evaluator, $bucket) {
            $sc = $evaluator->loadScorecardForModule($module);
            return ($sc['metadata']['bucket'] ?? null) === $bucket;
        })->values();
    }

    private function printResult(array $result, bool $detail): void
    {
        $total = $result['score_total'] ?? 0;
        $meta  = $result['meta_bucket'] ?? '—';
        $color = $total >= 80 ? 'green' : ($total >= 60 ? 'cyan' : ($total >= 40 ? 'yellow' : 'red'));

        $this->newLine();
        $this->line("<fg=white;options=bold>Modules/{$result['module']}</> <fg=gray>(bucket: {$result['bucket']})</>");
        $this->line("Total: <fg={$color};options=bold>{$total}/100</> · Meta bucket: <fg={$color}>{$meta}</>");
        $this->newLine();

        $coreScore = $result['core']['score'] ?? 0;
        $coreMax   = $result['core']['max'] ?? 0;
        $this->line("Core (D1 multi-tenant + D8 segurança): {$coreScore}/{$coreMax}");

        if (! empty($result['bucket_dimensions'])) {
            $this->newLine();
            $this->line('<fg=cyan>Dimensões específicas do bucket:</>');
            $rows = [];
            foreach ($result['bucket_dimensions'] as $key => $dim) {
                $rows[] = [
                    $key,
                    ($dim['score'] ?? 0) . '/' . ($dim['max'] ?? 0),
                    $dim['weight'] ?? '—',
                ];
            }
            $this->table(['Dimensão', 'Score', 'Peso'], $rows);
        }

        if (! empty($result['paired_violations'])) {
            $this->newLine();
            $this->line('<fg=red>Paired violations (regressões detectadas):</>');
            foreach ($result['paired_violations'] as $v) {
                $this->line("  - {$v}");
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
            $items = $section === 'core' ? ($result['core']['rules'] ?? []) : $result['bucket_dimensions'];
            foreach ($items as $key => $item) {
                if (is_array($item) && isset($item['rules'])) {
                    foreach ($item['rules'] as $rk => $rule) {
                        $score = $rule['score'] ?? 0;
                        $max   = $rule['max'] ?? 0;
                        $color = $score === $max ? 'green' : ($score > 0 ? 'yellow' : 'red');
                        $ev    = $rule['evidence'] ?? '';
                        $this->line("    <fg={$color}>[{$score}/{$max}]</> {$key}.{$rk} — {$ev}");
                    }
                } elseif (is_array($item)) {
                    $score = $item['score'] ?? 0;
                    $max   = $item['max'] ?? 0;
                    $color = $score === $max ? 'green' : ($score > 0 ? 'yellow' : 'red');
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
            $sum += $dim['max'] ?? 0;
        }
        return $sum;
    }
}
