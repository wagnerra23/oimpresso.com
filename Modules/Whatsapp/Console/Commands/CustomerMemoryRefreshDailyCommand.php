<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Whatsapp\Entities\CustomerMemory;
use Modules\Whatsapp\Jobs\RebuildCustomerMemoryJob;

/**
 * US-WA-VOZ-001 — Cron daily refresh customer_memory.
 *
 * Roda diário 02h BRT via `app/Console/Kernel.php`. Re-dispatcha jobs
 * pra customers com `last_rebuilt_at < NOW() - 24h` OU NULL.
 *
 * Idempotente — jobs idempotentes via UPSERT.
 *
 * Tier 0: itera por business explícito (sem session). Sem `--business=N`
 * processa TODOS businesses ativos.
 *
 * Capping: --limit por business (default 1000) — evita queue explosion.
 *
 * @see Modules/Whatsapp/Jobs/RebuildCustomerMemoryJob.php
 */
class CustomerMemoryRefreshDailyCommand extends Command
{
    protected $signature = 'customer-memory:refresh-daily
        {--business= : business_id alvo (default: todos com customer_memory existente)}
        {--limit=1000 : máximo customers por business}
        {--stale-hours=24 : threshold last_rebuilt_at em horas (default 24h)}
        {--detail : log breakdown por business}';

    protected $description = 'Cron daily — re-dispatcha rebuild de customer_memory stale (US-WA-VOZ-001).';

    public function handle(): int
    {
        $businessOpt = $this->option('business');
        $limit = max(1, (int) $this->option('limit'));
        $staleHours = max(1, (int) $this->option('stale-hours'));
        $detail = (bool) $this->option('detail');

        $staleCutoff = now()->subHours($staleHours);

        // Resolve businesses alvo
        if ($businessOpt !== null) {
            $businessIds = [(int) $businessOpt];
        } else {
            $businessIds = DB::table('customer_memory')
                ->select('business_id')
                ->distinct()
                ->orderBy('business_id')
                ->pluck('business_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if (empty($businessIds)) {
            $this->info('Nenhum business com customer_memory — nada a fazer.');
            return Command::SUCCESS;
        }

        $grandTotal = 0;
        $rows = [];

        foreach ($businessIds as $bizId) {
            $stale = DB::table('customer_memory')
                ->where('business_id', $bizId)
                ->where(function ($q) use ($staleCutoff) {
                    $q->whereNull('last_rebuilt_at')
                      ->orWhere('last_rebuilt_at', '<', $staleCutoff);
                })
                ->orderBy('last_rebuilt_at') // mais antigos primeiro (NULL first)
                ->limit($limit)
                ->pluck('customer_external_id');

            $count = $stale->count();
            $grandTotal += $count;

            foreach ($stale as $extId) {
                RebuildCustomerMemoryJob::dispatch(
                    $bizId,
                    (string) $extId,
                    CustomerMemory::REBUILT_VIA_CRON_DAILY,
                );
            }

            $rows[] = ['biz' => $bizId, 'dispatched' => $count];

            if ($detail) {
                $this->info("biz={$bizId}: {$count} jobs dispatched");
            }
        }

        if (! $detail && count($rows) > 0) {
            $this->table(['biz', 'dispatched'], $rows);
        }

        $this->info("Total dispatched: {$grandTotal}");
        return Command::SUCCESS;
    }
}
