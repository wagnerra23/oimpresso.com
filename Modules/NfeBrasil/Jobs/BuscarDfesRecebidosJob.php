<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Services\Manifestacao\DistribuicaoDfeService;

/**
 * US-NFE-051 · Job que dispara DistribuicaoDfeService::puxarLote por business.
 *
 * Multi-tenant: $businessId no constructor — `session()` não funciona em fila
 * (skill multi-tenant-patterns).
 *
 * Retry exponencial: 3 tentativas com backoff 30s, 60s, 120s.
 */
class BuscarDfesRecebidosJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly int $businessId,
    ) {}

    public function handle(DistribuicaoDfeService $service): void
    {
        $resultado = $service->puxarLote($this->businessId);

        Log::info('BuscarDfesRecebidosJob: completou', [
            'business_id' => $this->businessId,
            'resultado'   => $resultado,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('BuscarDfesRecebidosJob: falhou definitivamente', [
            'business_id' => $this->businessId,
            'erro'        => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['nfebrasil', 'dist-dfe', "biz:{$this->businessId}"];
    }
}
