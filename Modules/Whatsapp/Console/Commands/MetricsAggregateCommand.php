<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Services\Metrics\MetricsAggregator;

/**
 * `whatsapp:metrics-aggregate` — agrega métricas de atendimento omnichannel
 * em snapshot diário (US-WA-021/041, CYCLE-07 PR-3).
 *
 * Uso:
 *   php artisan whatsapp:metrics-aggregate                       # ontem, todos businesses
 *   php artisan whatsapp:metrics-aggregate --date=2026-05-11     # data específica
 *   php artisan whatsapp:metrics-aggregate --business=1          # smoke biz=1
 *   php artisan whatsapp:metrics-aggregate --business=1 --date=2026-05-11
 *
 * Schedule canônico: 02:30 BRT daily (após scan-drift FSM 03:00 ... wait,
 * antes — 02:30 < 03:00). Roda ANTES de health-check 06:00 pra dashboard
 * já estar fresh quando time chega.
 *
 * Idempotente — re-rodar mesma data substitui rows (UPSERT). Seguro pra
 * backfill manual também.
 *
 * @see Modules\Whatsapp\Services\Metrics\MetricsAggregator
 */
class MetricsAggregateCommand extends Command
{
    protected $signature = 'whatsapp:metrics-aggregate
                            {--date= : Data alvo no formato YYYY-MM-DD (default: ontem)}
                            {--business=all : business_id alvo (default: all)}';

    protected $description = 'Agrega métricas conversation/messages em snapshot diário (UPSERT idempotente)';

    public function handle(MetricsAggregator $aggregator): int
    {
        // 1) Valida --date
        $dateOpt = $this->option('date');
        if ($dateOpt) {
            try {
                $date = Carbon::createFromFormat('Y-m-d', (string) $dateOpt)->startOfDay();
            } catch (\Throwable $e) {
                $this->error("--date={$dateOpt} inválido (esperado YYYY-MM-DD).");
                return self::FAILURE;
            }
        } else {
            $date = Carbon::yesterday();
        }

        // 2) Valida --business
        $businessOpt = (string) $this->option('business');
        $businessIds = $this->resolveBusinessIds($businessOpt);

        if ($businessIds === null) {
            return self::FAILURE;
        }

        if ($businessIds === []) {
            $this->info('Nenhum business encontrado pra agregar.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Agregando métricas %s (%d business%s)...',
            $date->toDateString(),
            count($businessIds),
            count($businessIds) === 1 ? '' : 'es',
        ));

        // 3) Itera businesses → aggregator
        $totalRows = 0;
        foreach ($businessIds as $businessId) {
            $rows = $aggregator->aggregateBusinessForDate($businessId, $date);
            $totalRows += $rows;
            $this->line(sprintf('  biz=%d → %d row(s) UPSERT', $businessId, $rows));
        }

        $this->newLine();
        $this->info(sprintf('✓ %d row(s) UPSERT total.', $totalRows));

        Log::info('[whatsapp.metrics.command.completed]', [
            'date' => $date->toDateString(),
            'business_filter' => $businessOpt,
            'businesses_processed' => count($businessIds),
            'rows_upserted' => $totalRows,
        ]);

        return self::SUCCESS;
    }

    /**
     * Resolve `--business=all|N` em array de IDs. Retorna null em erro
     * pra caller distinguir de "sem businesses" (array vazio).
     *
     * @return ?array<int>
     */
    private function resolveBusinessIds(string $businessOpt): ?array
    {
        if ($businessOpt === 'all') {
            // SUPERADMIN: cron CLI cross-business — escaneia conversations
            // pra descobrir businesses ativos (tem mensagens). Evita
            // processar business sem nenhuma atividade.
            return DB::table('conversations')
                ->distinct()
                ->pluck('business_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $businessId = (int) $businessOpt;
        if ($businessId <= 0) {
            $this->error("--business={$businessOpt} inválido (esperado inteiro > 0 ou 'all').");
            return null;
        }

        return [$businessId];
    }
}
