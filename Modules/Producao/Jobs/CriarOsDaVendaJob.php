<?php

namespace Modules\Producao\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Producao\Services\CriarOsDaVenda;
use Modules\Producao\Services\PrometerPrazoDaVenda;

/**
 * Cria as OS por item da venda aprovada (ADR 0436). Recebe o `$businessId` no construtor: em fila
 * não há sessão (ADR 0093). Repetir é inócuo — os dois serviços são idempotentes.
 *
 * Depois das OS, a data prometida (UC-FLX-06): a estimativa pela fila vai para a venda se ela não
 * tem data, e a data da venda vai para as OS (`PrometerPrazoDaVenda`).
 *
 * Auditoria lote 2.3: as 3 tentativas esperam 30 s e 2 min entre si (lock ou deadlock passageiro
 * não queima as tentativas de uma vez), e a falha final fica no log de erro com a empresa e a venda —
 * sem isso a venda aprovada ficava sem OS e ninguém sabia.
 */
class CriarOsDaVendaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $businessId, public readonly int $transactionId, public readonly ?int $userId = null) {}

    public function handle(CriarOsDaVenda $servico, PrometerPrazoDaVenda $prazo): void
    {
        $servico->criar($this->businessId, $this->transactionId, $this->userId);
        $prazo->gravar($this->businessId, $this->transactionId);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('producao.criar-os: a venda aprovada ficou sem OS', [
            'business_id' => $this->businessId, 'transaction_id' => $this->transactionId, 'erro' => $e::class,
        ]);
    }
}
