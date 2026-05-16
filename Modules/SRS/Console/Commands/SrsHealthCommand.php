<?php

declare(strict_types=1);

namespace Modules\SRS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * srs:health — health-check diário do módulo SRS (D9 observability v3).
 *
 * Métricas: docs sources cadastrados, requirements catalogados, último DocValidationRun
 * (health_score), evidências indexadas.
 *
 * Default: silencioso + log estruturado (cron). `--detail`: tabela humano.
 * NÃO usa `--verbose` (Symfony reserved — ver .claude/rules/commands.md).
 *
 * Uso:
 *   php artisan srs:health
 *   php artisan srs:health --detail
 *   php artisan srs:health --notify
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class SrsHealthCommand extends Command
{
    protected $signature = 'srs:health
                            {--detail : Imprime tabela legível humano (default: log estruturado)}
                            {--notify : Loga ALERT em error channel se issue detectada}';

    protected $description = 'Health-check SRS (sources, requirements, último DocValidationRun).';

    public function handle(): int
    {
        // Tabelas obrigatórias do módulo SRS
        $tabelas = [
            'docs_sources',
            'docs_requirements',
            'docs_evidences',
            'docs_validation_runs',
        ];

        $tabelasAusentes = [];
        foreach ($tabelas as $t) {
            if (! Schema::hasTable($t)) {
                $tabelasAusentes[] = $t;
            }
        }

        if (! empty($tabelasAusentes)) {
            $msg = 'srs.health.tabelas_ausentes';
            $this->error('Tabelas ausentes: ' . implode(', ', $tabelasAusentes));
            Log::warning($msg, ['tabelas' => $tabelasAusentes]);
            return self::FAILURE;
        }

        // Métricas básicas
        $lastRun = DB::table('docs_validation_runs')->orderByDesc('id')->first();

        $report = [
            'sources_total'        => (int) DB::table('docs_sources')->count(),
            'requirements_total'   => (int) DB::table('docs_requirements')->count(),
            'evidences_total'      => (int) DB::table('docs_evidences')->count(),
            'validation_runs_total' => (int) DB::table('docs_validation_runs')->count(),
            'last_health_score'    => $lastRun ? (int) $lastRun->health_score : null,
            'last_issues_total'    => $lastRun ? (int) $lastRun->issues_total : null,
            'last_issues_critical' => $lastRun ? (int) $lastRun->issues_critical : null,
            'last_run_at'          => $lastRun ? (string) $lastRun->run_at : null,
        ];

        // Issue: health_score < 70 OR issues_critical > 0
        $hasIssue = ($report['last_health_score'] !== null && $report['last_health_score'] < 70)
            || ($report['last_issues_critical'] !== null && $report['last_issues_critical'] > 0);

        Log::info('srs.health', $report + ['ok' => ! $hasIssue]);

        if ($this->option('detail')) {
            $this->info('=== srs:health ===');
            foreach ($report as $k => $v) {
                $this->line(sprintf('  %-22s: %s', $k, $v ?? 'null'));
            }
            $this->line('  status                : ' . ($hasIssue ? 'ISSUE' : 'OK'));
        }

        if ($this->option('notify') && $hasIssue) {
            Log::error('srs.health ALERT', $report);
        }

        return $hasIssue ? self::FAILURE : self::SUCCESS;
    }
}
