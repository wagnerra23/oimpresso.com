<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;

/**
 * Saída JSON com 6 métricas charter pra alimentar dashboard
 * /copiloto/admin/qualidade (futuro F5/S7).
 *
 * M2 + M3 reais (computados via charter:audit reusado);
 * M1, M4, M5, M6 retornam null (stubs honestos — depende telemetria).
 *
 * Spec em memory/sprints/s6-charter-capterra/16-six-metrics-spec.md.
 *
 * Uso:
 *   php artisan charter:metrics            # texto resumido
 *   php artisan charter:metrics --json     # struct pra dashboard
 */
class CharterMetricsCommand extends Command
{
    protected $signature = 'charter:metrics {--json : Output JSON estruturado}';

    protected $description = 'Apura 6 métricas charter (M2+M3 reais; M1/M4/M5/M6 stubs)';

    public function handle(): int
    {
        $this->call('charter:audit', ['--json' => true]);
        $auditOutput = $this->output->fetch();
        $audit = json_decode($auditOutput, true) ?? [];

        $total = (int) ($audit['total'] ?? 0);
        $invalid = count($audit['invalid_frontmatter'] ?? []);
        $missingSections = count($audit['missing_sections'] ?? []);

        $tierAExpected = 5;
        $tierAFound = (int) ($audit['by_tier']['A'] ?? 0);

        $coverage = $tierAExpected > 0 ? $tierAFound / $tierAExpected : 0.0;

        $guardFails = $invalid + $missingSections;
        $passRate = $total > 0 ? ($total - $guardFails) / $total : 1.0;

        $metrics = [
            'computed_at' => now()->toIso8601String(),
            'sprint' => 'S6 F4',
            'M1_token_economy' => [
                'value' => null,
                'status' => 'stub',
                'reason' => 'Depende telemetria mcp_audit_log.charter_present (S7).',
            ],
            'M2_guard_pass_rate' => [
                'value' => round($passRate, 4),
                'value_pct' => round($passRate * 100, 1),
                'status' => $passRate >= 0.95 ? 'green' : 'red',
                'alvo_pct' => 95.0,
                'sample_size' => $total,
            ],
            'M3_charter_coverage_tier_a' => [
                'value' => round($coverage, 4),
                'value_pct' => round($coverage * 100, 1),
                'status' => $coverage >= 0.80 ? 'green' : 'red',
                'alvo_pct' => 80.0,
                'found' => $tierAFound,
                'expected' => $tierAExpected,
            ],
            'M4_goal_drift_rate' => [
                'value' => null,
                'status' => 'stub',
                'reason' => 'Depende mcp_audit_log.tools_used + heurística (S7).',
            ],
            'M5_detector_latency_p95_seconds' => [
                'value' => null,
                'status' => 'stub',
                'reason' => 'Depende cron que agrega GitHub Actions API (S7).',
            ],
            'M6_anti_hallucination_ratchet' => [
                'value' => null,
                'status' => 'stub',
                'reason' => 'Depende baseline.json populado + Pest GUARD em prod canary 7d (F5).',
            ],
        ];

        if ($this->option('json')) {
            $this->line(json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info("Charter Metrics — {$metrics['computed_at']}");
            $this->line("M2 GUARD pass rate: {$metrics['M2_guard_pass_rate']['value_pct']}% ({$metrics['M2_guard_pass_rate']['status']})");
            $this->line("M3 Coverage Tier A: {$metrics['M3_charter_coverage_tier_a']['value_pct']}% ({$metrics['M3_charter_coverage_tier_a']['status']})");
            $this->line('M1/M4/M5/M6: stubs (ver --json pra detalhes)');
        }

        $allOk = $metrics['M2_guard_pass_rate']['status'] === 'green'
            && $metrics['M3_charter_coverage_tier_a']['status'] === 'green';

        return $allOk ? self::SUCCESS : self::FAILURE;
    }
}
