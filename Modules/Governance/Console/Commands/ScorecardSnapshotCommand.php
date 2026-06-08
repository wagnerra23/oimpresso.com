<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Governance\Services\ScopedScorecardEvaluator;
use Symfony\Component\Yaml\Yaml;

/**
 * governance:scorecard-snapshot — snapshot scorecards bucket-scoped (Wave 24 full impl).
 *
 * Wave 23 (shell): preview YAML scorecards `memory/governance/scorecards/*.yaml` (`--json`).
 * Wave 24 Agent A (2026-05-16): persistência diária + drift detection + paired enforcement.
 *
 * Comportamento:
 *   - DEFAULT: persiste 1 row/módulo/dia em `mcp_scorecard_runs` + drift vs ontem.
 *   - `--json`: preview YAML scorecards (modo W23 mantido pra backward-compat).
 *   - `--alert`: drifts >=5pts viram entry em `mcp_alertas` kind=scorecard_drift.
 *   - `--bucket=`: filtra módulos pelo `governance.bucket` declarado em module.json.
 *   - `--detail`: log linha-por-módulo durante execução.
 *
 * Cap 50% paired indicators canônico — vide ScopedScorecardEvaluator::checkPairedViolation().
 *
 * Schedule: daily 07:00 BRT pareado com `module:grade-snapshot` (06:05).
 *
 * NÃO usa `--verbose` (Symfony reserved — vide rule path-scoped commands.md).
 *
 * Uso CLI:
 *   php artisan governance:scorecard-snapshot                              # persist all
 *   php artisan governance:scorecard-snapshot --bucket=vertical_client_facing
 *   php artisan governance:scorecard-snapshot --alert                      # registra drift
 *   php artisan governance:scorecard-snapshot --detail
 *   php artisan governance:scorecard-snapshot --json                       # W23 preview legacy
 *
 * @see Modules\Governance\Services\ScopedScorecardEvaluator
 * @see Modules/Governance/Database/Migrations/2026_05_17_000001_create_mcp_scorecard_runs_table.php
 * @see memory/governance/scorecards/_template.yaml
 */
class ScorecardSnapshotCommand extends Command
{
    protected $signature = 'governance:scorecard-snapshot
                            {--bucket= : Filtra módulos pelo bucket declarado em module.json}
                            {--alert : Registra drifts >=5pts em mcp_alertas (kind=scorecard_drift)}
                            {--detail : Log linha-por-módulo durante snapshot}
                            {--json : Preview YAML scorecards (modo W23 — sem persistência)}';

    protected $description = 'Snapshot scorecards bucket-scoped + drift detection (cron daily 07:00 BRT) — Wave 24 full impl';

    public function handle(ScopedScorecardEvaluator $eval): int
    {
        $startedAt = microtime(true);

        // Modo W23 legacy: preview YAML scorecards (read-only).
        if ($this->option('json')) {
            return $this->runJsonPreview($startedAt);
        }

        $bucketFilter = $this->option('bucket');
        $shouldAlert  = (bool) $this->option('alert');
        $detail       = (bool) $this->option('detail');

        if (! Schema::hasTable('mcp_scorecard_runs')) {
            $this->error('Tabela mcp_scorecard_runs nao existe — rode `php artisan migrate` primeiro.');
            return self::FAILURE;
        }

        $modules = $this->listModulesByBucket($bucketFilter, $eval);
        if (empty($modules)) {
            $this->warn(sprintf(
                'Nenhum modulo detectado%s.',
                $bucketFilter ? " no bucket=`{$bucketFilter}`" : ''
            ));
            return self::SUCCESS;
        }

        $today     = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');
        $alerts    = [];
        $persisted = 0;
        $skipped   = 0;

        foreach ($modules as $module) {
            $scorecard = $eval->loadScorecardForModule($module);
            if (empty($scorecard)) {
                $skipped++;
                if ($detail) {
                    $this->line(sprintf('  %-30s [sem scorecard YAML — pulado]', $module));
                }
                continue;
            }

            $result = $eval->evaluateScorecard($module, $scorecard);
            $score  = (int) ($result['score_total'] ?? 0);
            $bucket = (string) ($result['bucket'] ?? 'unknown');

            DB::table('mcp_scorecard_runs')->insert([
                'module'         => $module,
                'bucket'         => $bucket,
                'score'          => max(0, min(65535, $score)), // unsignedSmallInt safety
                'breakdown_json' => json_encode($result, JSON_UNESCAPED_UNICODE),
                'snapshot_date'  => $today,
                'created_at'     => now(),
            ]);
            $persisted++;

            // Drift detection vs ontem (último snapshot do dia anterior).
            $yesterdayScore = DB::table('mcp_scorecard_runs')
                ->where('module', $module)
                ->where('snapshot_date', $yesterday)
                ->orderByDesc('id')
                ->value('score');

            if ($yesterdayScore !== null && abs($score - (int) $yesterdayScore) >= 5) {
                $delta = $score - (int) $yesterdayScore;
                $sign  = $delta > 0 ? '+' : '';
                $alerts[] = [
                    'module'    => $module,
                    'message'   => "{$module}: {$yesterdayScore} -> {$score} (delta {$sign}{$delta})",
                    'delta'     => $delta,
                    'previous'  => (int) $yesterdayScore,
                    'current'   => $score,
                ];
            }

            if ($detail) {
                $violations = count($result['paired_violations'] ?? []);
                $this->line(sprintf(
                    '  %-30s %3d/100 - bucket=%s - paired_violations=%d',
                    $module,
                    $score,
                    $bucket,
                    $violations,
                ));
            }
        }

        if (! empty($alerts) && $shouldAlert) {
            $this->persistAlerts($alerts);
            $this->warn('Drifts >=5pts: ' . count($alerts) . ' registrados em mcp_alertas (kind=scorecard_drift)');
            foreach ($alerts as $a) {
                $this->line('  ! ' . $a['message']);
            }
        } elseif (! empty($alerts)) {
            $this->warn('Drifts >=5pts detectados (sem --alert, nao persistidos): ' . count($alerts));
        }

        $elapsed = (int) round((microtime(true) - $startedAt) * 1000);
        $this->info(sprintf(
            'Snapshot OK — %d modulos persistidos (%d pulados sem YAML) em mcp_scorecard_runs (%dms).',
            $persisted,
            $skipped,
            $elapsed,
        ));

        return self::SUCCESS;
    }

    /**
     * Modo W23 legacy — preview YAML scorecards sem persistência (--json).
     */
    private function runJsonPreview(float $startedAt): int
    {
        $dir = base_path('memory/governance/scorecards');
        if (! is_dir($dir)) {
            $this->error("Diretorio {$dir} nao existe — crie scorecards canonicos primeiro.");
            return self::FAILURE;
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.yaml') ?: [];
        $files = array_filter($files, fn ($f) => basename($f) !== '_template.yaml');

        $snapshot = [];
        foreach ($files as $path) {
            try {
                $data = Yaml::parseFile($path);
            } catch (\Throwable $e) {
                $this->error("Falha lendo {$path}: " . $e->getMessage());
                continue;
            }
            $snapshot[] = [
                'module'          => (string) ($data['module'] ?? basename($path, '.yaml')),
                'type'            => (string) ($data['type'] ?? 'domain'),
                'fsm_n_a'         => (bool) ($data['fsm_n_a'] ?? false),
                'last_grade'      => $data['last_grade'] ?? null,
                'bucket'          => (string) ($data['bucket'] ?? 'N/A'),
                'target_score'    => (int) ($data['target_score'] ?? 90),
                'delta_vs_target' => is_numeric($data['last_grade'] ?? null)
                    ? ((int) $data['last_grade'] - (int) ($data['target_score'] ?? 90))
                    : null,
                'file'            => $path,
            ];
        }

        $elapsed = (int) round((microtime(true) - $startedAt) * 1000);
        $this->line(json_encode([
            'snapshot_at' => now()->toIso8601String(),
            'count'       => count($snapshot),
            'elapsed_ms'  => $elapsed,
            'scorecards'  => $snapshot,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /**
     * Lista módulos filtrando por bucket declarado em `module.json`.
     *
     * @return array<int, string>
     */
    private function listModulesByBucket(?string $bucket, ScopedScorecardEvaluator $eval): array
    {
        $modulesPath = base_path('Modules');
        if (! is_dir($modulesPath)) {
            return [];
        }
        $modules = [];
        foreach (scandir($modulesPath) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (! is_dir($modulesPath . DIRECTORY_SEPARATOR . $entry)) {
                continue;
            }
            if ($bucket !== null && $bucket !== '') {
                $modBucket = $eval->resolveBucketForModule($entry);
                if ($modBucket !== $bucket) {
                    continue;
                }
            }
            $modules[] = $entry;
        }
        sort($modules);
        return $modules;
    }

    /**
     * Persiste drifts em `mcp_alertas` kind=scorecard_drift.
     *
     * mcp_alertas é tabela CONFIG (user-defined triggers), mas Wave 24 amplia uso
     * pra registrar EVENTOS de drift (config_extra carrega a mensagem).
     * Multi-tenant Tier 0: scorecards são repo-wide, mas mcp_alertas é per-business —
     * persistimos com business_id=1 (superadmin Wagner) por convenção mcp_* meta.
     *
     * @param  array<int, array{module: string, message: string, delta: int, previous: int, current: int}>  $alerts
     */
    private function persistAlerts(array $alerts): void
    {
        if (! Schema::hasTable('mcp_alertas')) {
            $this->warn('mcp_alertas nao existe — alerts nao persistidos.');
            return;
        }

        foreach ($alerts as $alert) {
            DB::table('mcp_alertas')->insert([
                'business_id'  => 1, // mcp_* meta: superadmin Wagner repo-wide
                'kind'         => 'scorecard_drift',
                'threshold'    => 5.0,
                'canal'        => 'log',
                'ativo'        => true,
                'config_extra' => json_encode([
                    'module'      => $alert['module'],
                    'message'     => $alert['message'],
                    'delta'       => $alert['delta'],
                    'previous'    => $alert['previous'],
                    'current'     => $alert['current'],
                    'detected_at' => now()->toIso8601String(),
                    'source'      => 'governance:scorecard-snapshot',
                ], JSON_UNESCAPED_UNICODE),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }
}
