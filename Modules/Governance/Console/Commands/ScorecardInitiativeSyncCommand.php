<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Governance\Services\InitiativeService;

/**
 * Wave 28 Agent 1 (2026-05-17) — governance:initiative-sync.
 *
 * Loop canônico Cortex/Port.io-style:
 *   1. Lê últimos scorecard_runs (último snapshot por módulo)
 *   2. Pra cada rule abaixo do peso target → cria Initiative open (idempotent)
 *   3. Pra cada Initiative open com score_after >= target → marca done
 *   4. Pra cada Initiative open com deadline passada → marca expired + alerta
 *
 * Schedule: daily 08:00 BRT (pareado com scorecard-snapshot 07:00 — depende dele).
 *
 * NÃO usa `--verbose` (Symfony reserved — vide rule path-scoped commands.md).
 *
 * Uso CLI:
 *   php artisan governance:initiative-sync                # sync completo
 *   php artisan governance:initiative-sync --detail       # log linha-por-initiative
 *   php artisan governance:initiative-sync --bucket=cross_cutting_infra
 *
 * @see Modules\Governance\Services\InitiativeService
 * @see Modules\Governance\Entities\Initiative
 * @see Modules/Governance/Database/Migrations/2026_05_17_000003_create_mcp_governance_initiatives_table.php
 */
class ScorecardInitiativeSyncCommand extends Command
{
    protected $signature = 'governance:initiative-sync
                            {--bucket= : Filtra Initiatives abertas exibidas por bucket}
                            {--detail : Log linha-por-linha das Initiatives abertas/fechadas/expiradas}';

    protected $description = 'Sync Initiatives ↔ scorecards (abre breach, fecha recuperadas, expira deadlines) — Wave 28';

    public function handle(InitiativeService $service): int
    {
        $startedAt = microtime(true);
        $detail = (bool) $this->option('detail');
        $bucket = $this->option('bucket');

        if (! Schema::hasTable('mcp_governance_initiatives')) {
            $this->error('Tabela mcp_governance_initiatives nao existe — rode `php artisan migrate` primeiro.');
            return self::FAILURE;
        }

        if (! Schema::hasTable('mcp_scorecard_runs')) {
            $this->error('Tabela mcp_scorecard_runs nao existe — rode `php artisan migrate` primeiro.');
            return self::FAILURE;
        }

        $this->info('Sincronizando Initiatives via scorecard_runs...');

        $stats = $service->syncFromScorecards();

        $this->info(sprintf(
            'Sync OK — abertas: %d / fechadas: %d / expiradas: %d / skipped: %d (%dms).',
            $stats['opened'],
            $stats['closed'],
            $stats['expired'],
            $stats['skipped'],
            (int) round((microtime(true) - $startedAt) * 1000),
        ));

        if ($detail) {
            $open = $service->listOpen($bucket ?: null);
            $this->line('');
            $this->line(sprintf('Initiatives abertas (%d)%s:', $open->count(), $bucket ? " bucket=`{$bucket}`" : ''));
            foreach ($open as $init) {
                $this->line(sprintf(
                    '  #%d [%s/%s] %s — deadline=%s (%d→%d)',
                    $init->id,
                    $init->module,
                    $init->rule_id,
                    $init->status,
                    $init->deadline?->toDateString(),
                    $init->score_before,
                    $init->score_target,
                ));
            }
        }

        return self::SUCCESS;
    }
}
