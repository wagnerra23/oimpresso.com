<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily health-check de Page Charters.
 *
 * Roda 06:30 BRT (após jana:health-check). Reusa CharterAuditCommand internamente
 * mas reporta SÓ pra log estruturado + mcp_audit_log (pra dashboard
 * /copiloto/admin/qualidade alimentar charts em F4).
 *
 * Diferença vs `charter:audit`:
 *   - audit é pra humano (interativo, tabela ou JSON sob demanda)
 *   - health é pra cron (silencioso, log estruturado, exit 0/1 pra alert)
 *
 * Sprint S6 F2 ([ADR 0101]). Métrica M6 (anti-hallucination ratchet) lê daqui.
 *
 * Uso:
 *   php artisan charter:health
 *   php artisan charter:health --notify (ALERT em log se algo falhou)
 */
class CharterHealthCommand extends Command
{
    protected $signature = 'charter:health
                            {--notify : Loga ALERT em governance channel se algo falhou}';

    protected $description = 'Health-check diário Page Charters (drift + cobertura)';

    public function handle(): int
    {
        $this->call('charter:audit', ['--json' => true]);

        $report = json_decode($this->output->fetch(), true) ?? [];

        $issues = [
            'stale' => count($report['stale'] ?? []),
            'invalid_frontmatter' => count($report['invalid_frontmatter'] ?? []),
            'missing_sections' => count($report['missing_sections'] ?? []),
        ];

        $allOk = array_sum($issues) === 0;

        Log::channel('single')->info('charter:health', [
            'ok' => $allOk,
            'total' => $report['total'] ?? 0,
            'by_tier' => $report['by_tier'] ?? [],
            'issues' => $issues,
            'tsx_without_charter' => count($report['tsx_without_charter'] ?? []),
        ]);

        if ($this->option('notify') && ! $allOk) {
            $detail = collect($issues)->filter()->map(fn ($v, $k) => "{$k}={$v}")->implode(', ');
            Log::channel('single')->error("charter:health ALERT — issues: {$detail}");
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }
}
