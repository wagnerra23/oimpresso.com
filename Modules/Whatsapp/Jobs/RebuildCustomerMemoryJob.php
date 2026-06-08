<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\CustomerMemory;
use Modules\Whatsapp\Services\CustomerMemory\CustomerMemoryRebuilder;

/**
 * US-WA-VOZ-001 — Async rebuild de 1 customer_memory.
 *
 * Pattern: Listener real-time chama `Rebuilder::touch()` (cheap),
 * dispatcha este Job pra rebuild completo em background (queue=`customer-memory`).
 *
 * Use cases:
 *   - Listener cliente novo (sem customer_memory ainda) → cria + rebuild full
 *   - Cron daily → re-rebuild todos com `last_rebuilt_at < NOW() - 24h`
 *   - Webhook quando Contact CRM é editado → re-resolve identidade
 *
 * Backoff: 30s/120s. 3 tries.
 * Timeout: 30s (rebuild 1 cliente = 2 queries + 1 cache lookup).
 *
 * @see Modules/Whatsapp/Services/CustomerMemory/CustomerMemoryRebuilder.php
 */
class RebuildCustomerMemoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(
        public readonly int $businessId,
        public readonly string $customerExternalId,
        public readonly string $via = CustomerMemory::REBUILT_VIA_LISTENER,
    ) {
        $this->onConnection('database');
        $this->onQueue('customer-memory');
    }

    public function handle(CustomerMemoryRebuilder $rebuilder): void
    {
        $rebuilder->rebuild($this->businessId, $this->customerExternalId, $this->via);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('single')->error('[customer_memory.rebuild_job_failed]', [
            'metric_name' => 'customer_memory_rebuild_job_failed',
            'business_id' => $this->businessId,
            'customer_external_id_redacted' => substr($this->customerExternalId, 0, 4) . '***' . substr($this->customerExternalId, -2),
            'via' => $this->via,
            'error' => $exception->getMessage(),
        ]);
    }
}
