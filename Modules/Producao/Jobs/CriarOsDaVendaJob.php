<?php

namespace Modules\Producao\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Producao\Services\CriarOsDaVenda;

/**
 * Cria as OS por item da venda aprovada (ADR 0436). Recebe o `$businessId` no construtor: em fila
 * não há sessão (ADR 0093). Repetir é inócuo — o serviço é idempotente.
 */
class CriarOsDaVendaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $businessId, public readonly int $transactionId, public readonly ?int $userId = null) {}

    public function handle(CriarOsDaVenda $servico): void
    {
        $servico->criar($this->businessId, $this->transactionId, $this->userId);
    }
}
