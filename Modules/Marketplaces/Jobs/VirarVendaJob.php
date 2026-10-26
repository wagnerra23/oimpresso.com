<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Marketplaces\Entities\PedidoMarketplace;
use Modules\Marketplaces\Services\PedidoParaVenda;

/**
 * Tenta transformar UM pedido do marketplace em venda (US-MKT-007). `business_id` no construtor
 * e na consulta. Quando não dá (sem local, item sem vínculo, total que não bate), registra o
 * motivo e para: o pedido fica sem `transaction_id` e entra de novo na próxima notificação.
 */
class VirarVendaJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function __construct(public int $businessId, public int $pedidoId)
    {
    }

    public function uniqueId(): string
    {
        return $this->businessId.':'.$this->pedidoId;
    }

    public function handle(PedidoParaVenda $conversor): void
    {
        $pedido = PedidoMarketplace::withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('id', $this->pedidoId)
            ->first();
        if ($pedido === null) {
            return;
        }

        $resultado = $conversor->converter($pedido);
        if ($resultado !== PedidoParaVenda::OK) {
            Log::info('marketplaces: pedido não virou venda', [
                'business_id' => $this->businessId,
                'pedido_id' => $this->pedidoId,
                'motivo' => $resultado,
            ]);
        }
    }
}
