<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\Concerns\PersistsDriftAlert;
use Modules\Governance\Services\Concerns\PublishesDriftToCentrifugo;
use Modules\Governance\Services\DriftCheckerRegistry;
use Modules\Governance\Services\DriftCheckResult;

/**
 * Orchestrator canônico do Drift Framework — ADR 0216.
 *
 * Substitui (gradualmente, canary 7d):
 *   - secrets:scan + secrets:audit (ADR 0215)
 *   - governance:detect-drift (Modules/Governance)
 *   - (futuros) jana:health-check, jana:system-audit, charter:health
 *
 * Filtros (mutuamente exclusivos, exceto --tag que combina):
 *   --check=<name>       executa 1 checker
 *   --all                executa todos (default se nenhum filtro)
 *   --tag=<tag>          executa todos com a tag
 *   --cadence=<cadence>  executa todos com a cadência (usado por cron)
 *
 * Modos:
 *   --diff-only          passa pra checker.check(['diff_only'=>true]) — pre-commit mode
 *   --auto-pr            passa pra checker.check(['auto_pr'=>true]) — orchestrator abre PR
 *   --notify             publica Centrifugo governance:drift
 *   --json               output JSON (CI consumer)
 *   --fail-on-drift      exit 1 se qualquer drift_count > 0
 *   --fail-on=block      exit 1 só quando finding com enforcement=block detectado
 *
 * Exit codes:
 *   0 = clean (ou warn-level só)
 *   1 = drift (semântica configurável via --fail-on*)
 *   2 = erro fatal (registry vazio, exception não capturada)
 */
class GovernanceAuditCommand extends Command
{
    use PersistsDriftAlert;
    use PublishesDriftToCentrifugo;

    protected $signature = 'governance:audit
                            {--check= : Run 1 checker by name (mutually exclusive with --all/--tag/--cadence)}
                            {--all : Run all registered checkers (default if no filter)}
                            {--tag= : Run checkers with tag}
                            {--cadence= : Run checkers with cadence (hourly|daily|weekly|on_commit|on_pr)}
                            {--diff-only : Diff-only mode (pre-commit, staged files)}
                            {--auto-pr : Allow checkers to propose PRs}
                            {--notify : Publish drift to Centrifugo}
                            {--json : Output structured JSON}
                            {--fail-on-drift : Exit 1 if ANY drift detected}
                            {--fail-on=block : Exit 1 only when finding with given enforcement level}
                            {--no-persist : Skip mcp_alertas_eventos persist (testing)}';

    protected $description = 'Run DriftCheckers via registry — ADR 0216 governance framework';

    public function handle(DriftCheckerRegistry $registry): int
    {
        if (! config('governance.drift_framework_enabled', true)) {
            $this->warn('governance.drift_framework_enabled=false — skipping. Set GOVERNANCE_DRIFT_FRAMEWORK_ENABLED=true to enable.');

            return self::SUCCESS;
        }

        $checkers = $this->selectCheckers($registry);

        if (count($checkers) === 0) {
            $this->error('No DriftCheckers matched filter or registry is empty.');
            $this->line('Available: ' . implode(', ', $registry->names()) ?: '(none)');

            return self::INVALID;
        }

        $results = [];
        $checkOpts = $this->buildCheckOpts();

        foreach ($checkers as $checker) {
            $results[] = $this->runChecker($checker, $checkOpts);
        }

        return $this->finalize($results);
    }

    /**
     * @return array<int, DriftChecker>
     */
    private function selectCheckers(DriftCheckerRegistry $registry): array
    {
        if ($name = $this->option('check')) {
            $checker = $registry->get($name);
            if (! $checker) {
                $this->error("Checker '{$name}' não registrado.");

                return [];
            }

            return [$checker];
        }

        if ($tag = $this->option('tag')) {
            return array_values($registry->byTag($tag));
        }

        if ($cadence = $this->option('cadence')) {
            return array_values($registry->byCadence($cadence));
        }

        return array_values($registry->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCheckOpts(): array
    {
        return [
            'diff_only' => (bool) $this->option('diff-only'),
            'auto_pr' => (bool) $this->option('auto-pr'),
        ];
    }

    /**
     * @param array<string, mixed> $opts
     */
    private function runChecker(DriftChecker $checker, array $opts): DriftCheckResult
    {
        $name = $checker->name();
        $start = microtime(true);

        try {
            $result = $checker->check($opts);
        } catch (\Throwable $e) {
            Log::channel('single')->error("governance:audit — checker '{$name}' threw", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error("✗ {$name} — exception: {$e->getMessage()}");

            return DriftCheckResult::drifted(
                name: $name,
                findings: [],
                duration_ms: (int) ((microtime(true) - $start) * 1000),
                metadata: ['exception' => $e->getMessage()],
            );
        }

        if (! $this->option('json')) {
            $emoji = $result->ok ? '✓' : '⚠';
            $this->line(sprintf(
                '%s %s — %d findings (%dms)',
                $emoji,
                $name,
                $result->drift_count,
                $result->duration_ms,
            ));
        }

        if (! $this->option('no-persist') && ! $result->ok) {
            foreach ($result->findings as $finding) {
                $this->persistirDriftAlert($name, $finding);
            }
        }

        if ($this->option('notify') && ! $result->ok) {
            $channel = config('governance.drift_centrifugo_channel', 'governance:drift');
            $this->publishDriftToCentrifugo($result, $channel);
        }

        return $result;
    }

    /**
     * @param array<int, DriftCheckResult> $results
     */
    private function finalize(array $results): int
    {
        $totalDrift = array_sum(array_map(static fn (DriftCheckResult $r) => $r->drift_count, $results));
        $totalCheckers = count($results);
        $cleanCheckers = count(array_filter($results, static fn (DriftCheckResult $r) => $r->ok));

        if ($this->option('json')) {
            $this->line(json_encode([
                'summary' => [
                    'total_checkers' => $totalCheckers,
                    'clean' => $cleanCheckers,
                    'drifted' => $totalCheckers - $cleanCheckers,
                    'total_drift_findings' => $totalDrift,
                    'scanned_at' => now()->toIso8601String(),
                ],
                'results' => array_map(static fn (DriftCheckResult $r) => $r->toArray(), $results),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info(sprintf(
                'Governance audit: %d checkers · %d clean · %d drifted · %d findings total',
                $totalCheckers,
                $cleanCheckers,
                $totalCheckers - $cleanCheckers,
                $totalDrift,
            ));
        }

        // Exit code semântica
        if ($this->option('fail-on-drift') && $totalDrift > 0) {
            return self::FAILURE;
        }

        if ($failOn = $this->option('fail-on')) {
            foreach ($results as $r) {
                foreach ($r->findings as $f) {
                    $checker = app(DriftCheckerRegistry::class)->get($r->name);
                    if ($checker && $checker->enforcement() === $failOn) {
                        return self::FAILURE;
                    }
                }
            }
        }

        return self::SUCCESS;
    }
}
