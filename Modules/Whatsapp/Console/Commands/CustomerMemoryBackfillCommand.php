<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Whatsapp\Entities\CustomerMemory;
use Modules\Whatsapp\Services\CustomerMemory\CustomerMemoryRebuilder;

/**
 * US-WA-VOZ-001 — Backfill one-shot de `customer_memory` por business.
 *
 * Uso típico (após migração + deploy):
 *   php artisan customer-memory:backfill --business=1 --dry-run    # conta + amostra
 *   php artisan customer-memory:backfill --business=1              # roda síncrono
 *   php artisan customer-memory:backfill --business=1 --queue       # dispatcha jobs
 *
 * Itera DISTINCT customer_external_id de `conversations` no business
 * filtrado, e pra cada um chama Rebuilder::rebuild(). Idempotente —
 * re-rodar não duplica (UPSERT por (business_id, customer_external_id)).
 *
 * Tier 0: --business obrigatório. Sem ele, falha INVALID.
 *
 * @see Modules/Whatsapp/Services/CustomerMemory/CustomerMemoryRebuilder.php
 */
class CustomerMemoryBackfillCommand extends Command
{
    protected $signature = 'customer-memory:backfill
        {--business= : business_id obrigatório (Tier 0 multi-tenant)}
        {--channel= : filtra customers de 1 channel específico (opcional)}
        {--limit=10000 : máximo customers processados (default 10000)}
        {--dry-run : só conta + lista 10 amostras, NÃO grava}
        {--queue : dispatcha RebuildCustomerMemoryJob em vez de rodar síncrono}
        {--detail : log linha-a-linha de cada rebuild}';

    protected $description = 'Backfill customer_memory pra todos os clientes que já mandaram msg no business (US-WA-VOZ-001).';

    public function handle(CustomerMemoryRebuilder $rebuilder): int
    {
        $businessId = (int) $this->option('business');
        if ($businessId <= 0) {
            $this->error('--business=N obrigatório (Tier 0 multi-tenant ADR 0093).');
            return Command::INVALID;
        }

        $channelId = $this->option('channel');
        $channelId = $channelId !== null ? (int) $channelId : null;

        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $useQueue = (bool) $this->option('queue');
        $detail = (bool) $this->option('detail');

        // Query — DISTINCT customer_external_id das conversations do business
        $query = DB::table('conversations')
            ->where('business_id', $businessId)
            ->whereNotNull('customer_external_id')
            ->where('customer_external_id', '!=', '');

        if ($channelId !== null) {
            $query->where('channel_id', $channelId);
        }

        $customers = $query->select('customer_external_id')
            ->groupBy('customer_external_id')
            ->orderBy('customer_external_id')
            ->limit($limit)
            ->pluck('customer_external_id');

        $total = $customers->count();
        $this->info("Customers únicos elegíveis biz={$businessId}" .
            ($channelId ? " channel={$channelId}" : '') .
            ": {$total}");

        if ($total === 0) {
            $this->info('Nada a fazer.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->info('[DRY-RUN] Amostras (até 10):');
            $samples = $customers->take(10)->map(function ($ext) {
                $masked = substr($ext, 0, 4) . '***' . substr($ext, -2);
                return [$masked];
            })->all();
            $this->table(['customer_external_id (redacted)'], $samples);
            return Command::SUCCESS;
        }

        if ($useQueue) {
            $this->info("Modo --queue: dispatchando {$total} jobs pra queue 'customer-memory'.");
            $bar = $this->output->createProgressBar($total);
            $bar->start();
            foreach ($customers as $extId) {
                $cleaned = ltrim((string) $extId, '+');
                if ($cleaned === '') {
                    continue;
                }
                \Modules\Whatsapp\Jobs\RebuildCustomerMemoryJob::dispatch(
                    $businessId,
                    $cleaned,
                    CustomerMemory::REBUILT_VIA_BACKFILL,
                );
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info("Dispatched. Rode: php artisan queue:work database --queue=customer-memory --stop-when-empty");
            return Command::SUCCESS;
        }

        // Modo síncrono — rebuild inline (útil pra biz pequenos / dev)
        $this->info('Modo síncrono — rebuild inline (use --queue pra biz grandes).');
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        $ok = 0;
        $err = 0;

        foreach ($customers as $extId) {
            $cleaned = ltrim((string) $extId, '+');
            if ($cleaned === '') {
                $bar->advance();
                continue;
            }
            try {
                $memory = $rebuilder->rebuild($businessId, $cleaned, CustomerMemory::REBUILT_VIA_BACKFILL);
                $ok++;
                if ($detail) {
                    $this->line("  ✓ memory_id={$memory->id} contact_id=" .
                        ($memory->contact_id ?? 'NULL') .
                        " name=\"{$memory->display_name}\" msgs={$memory->n_msgs_total}");
                }
            } catch (\Throwable $e) {
                $err++;
                if ($detail) {
                    $this->error("  ✗ {$cleaned}: " . $e->getMessage());
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("Resultado: {$ok} ok · {$err} erros · {$total} processados.");

        return Command::SUCCESS;
    }
}
