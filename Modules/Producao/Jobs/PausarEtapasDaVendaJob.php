<?php

namespace Modules\Producao\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Producao\Services\PedidoEmEsperaService;

/** Venda entrou em espera: pausa o que está rodando dela no PCP. `business_id` no construtor (ADR 0093). */
class PausarEtapasDaVendaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $businessId, public readonly int $transactionId) {}

    public function handle(PedidoEmEsperaService $svc): void
    {
        $svc->pausarEtapas($this->businessId, $this->transactionId);
    }
}
