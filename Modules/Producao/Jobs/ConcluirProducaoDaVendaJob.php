<?php

namespace Modules\Producao\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Producao\Services\ProducaoDaVendaService;

/**
 * Conclusão automática da produção da venda (decisão [W] 2026-10-16). Em fila para a baixa de
 * estoque não pesar na resposta do apontamento; `business_id` no construtor (ADR 0093).
 * Idempotente: o serviço trava a venda e só age em `in_production`.
 */
class ConcluirProducaoDaVendaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionId,
        public readonly int $userId,
        public readonly ?int $programacaoId = null,
    ) {}

    public function handle(ProducaoDaVendaService $svc): void
    {
        $svc->concluirSeTerminou($this->businessId, $this->transactionId, $this->userId, $this->programacaoId);
    }
}
