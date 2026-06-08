<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * comvis:health — health-check diário do módulo ComunicacaoVisual (D9 observability v3).
 *
 * Roda em cron schedule (ex: 06:30 BRT após jana:health-check). Reporta pra log estruturado
 * (canal padrão) métricas chave do módulo: orçamentos ativos, apontamentos drift>20%,
 * tabelas presentes.
 *
 * Diferença vs --detail human-readable:
 *   - default: silencioso, log estruturado, exit 0 (ok) ou 1 (issue)
 *   - --detail: imprime tabela na tela pra debug humano
 *
 * Multi-tenant Tier 0 ([ADR 0093]): default agrega cross-business (--business=N pra escopar).
 * NÃO usa `--verbose` (Symfony reserved — ver .claude/rules/commands.md).
 *
 * Uso:
 *   php artisan comvis:health
 *   php artisan comvis:health --business=1
 *   php artisan comvis:health --detail
 *   php artisan comvis:health --notify (ALERT em log se issue)
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ComvisHealthCommand extends Command
{
    protected $signature = 'comvis:health
                            {--business= : Escopa por business_id (default: cross-business agregado)}
                            {--detail : Imprime tabela legível humano (default: log estruturado)}
                            {--notify : Loga ALERT em error channel se algo falhou}';

    protected $description = 'Health-check ComunicacaoVisual (orçamentos, apontamentos, drift).';

    public function handle(): int
    {
        // Verifica tabelas obrigatórias antes de query
        $tabelas = ['comvis_orcamentos', 'comvis_os', 'comvis_apontamentos'];
        foreach ($tabelas as $t) {
            if (! Schema::hasTable($t)) {
                $this->error("Tabela '{$t}' ausente — rode migrations.");
                Log::warning('comvis.health.tabela_ausente', ['tabela' => $t]);
                return self::FAILURE;
            }
        }

        $bizId = $this->option('business') !== null ? (int) $this->option('business') : null;

        $qOrc = DB::table('comvis_orcamentos');
        $qOs  = DB::table('comvis_os');
        $qApt = DB::table('comvis_apontamentos');

        if ($bizId !== null) {
            $qOrc->where('business_id', $bizId);
            $qOs->where('business_id', $bizId);
            $qApt->where('business_id', $bizId);
        }

        $report = [
            'business_id'           => $bizId,
            'orcamentos_total'      => (int) $qOrc->count(),
            'os_total'              => (int) $qOs->count(),
            'apontamentos_total'    => (int) $qApt->count(),
            'apontamentos_em_aberto' => (int) (clone $qApt)->whereNull('finalizado_em')->count(),
            'apontamentos_drift_alto' => (int) (clone $qApt)
                ->whereNotNull('drift_percent')
                ->where(function ($w) {
                    $w->where('drift_percent', '>', 20)
                      ->orWhere('drift_percent', '<', -20);
                })
                ->count(),
        ];

        // Métrica de saúde: issue se >5 apontamentos em aberto ou drift alto >10
        $hasIssue = $report['apontamentos_em_aberto'] > 5 || $report['apontamentos_drift_alto'] > 10;

        Log::info('comvis.health', $report + ['ok' => ! $hasIssue]);

        if ($this->option('detail')) {
            $this->info('=== comvis:health ===');
            foreach ($report as $k => $v) {
                $this->line(sprintf('  %-26s: %s', $k, $v ?? 'null'));
            }
            $this->line('  status                    : ' . ($hasIssue ? 'ISSUE' : 'OK'));
        }

        if ($this->option('notify') && $hasIssue) {
            Log::error('comvis.health ALERT', $report);
        }

        return $hasIssue ? self::FAILURE : self::SUCCESS;
    }
}
