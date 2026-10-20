<?php

declare(strict_types=1);

namespace Modules\Compras\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Compras\Services\RequisicaoEstoqueMinimoService;

/**
 * Estoque mínimo vira requisição de compra para UM business — US-COM-027 · UC-FLX-19.
 * `$businessId` no constructor (job não tem session — ADR 0093). Só cria requisição; nenhum
 * estoque se move e nenhum pedido de compra nasce. A regra mora no serviço.
 */
class RequisicaoEstoqueMinimoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $businessId) {}

    public function handle(RequisicaoEstoqueMinimoService $servico): void
    {
        $resumo = $servico->gerar($this->businessId);

        Log::info('compras.requisicao_estoque_minimo', ['business_id' => $this->businessId] + $resumo);
    }
}
