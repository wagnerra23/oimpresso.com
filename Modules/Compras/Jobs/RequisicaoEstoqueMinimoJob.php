<?php

declare(strict_types=1);

namespace Modules\Compras\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Compras\Services\AvisoDeFalhaDaAgenda;
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

    /**
     * D6 (auditoria de Compras, 2026-11-05). Fila `default`: em produção ela tem worker
     * (`queue:work database --queue=nfe,default,...` no Kernel, com QUEUE_BACKLOG_WORKER_ENABLED
     * ligado — medido em 2026-11-06). Até aqui `tries=1` e sem `failed()`: um erro de banco
     * passageiro perdia a requisição do dia sem aviso. Repetir é seguro: o serviço pula o item que
     * já tem requisição aberta no mesmo local (`RequisicaoEstoqueMinimoService::gerar`, `$jaAbertas`).
     */
    public int $tries = 3;

    /** @var list<int> segundos entre as tentativas */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $businessId)
    {
        $this->onQueue('default');
    }

    public function handle(RequisicaoEstoqueMinimoService $servico): void
    {
        $resumo = $servico->gerar($this->businessId);

        Log::info('compras.requisicao_estoque_minimo', ['business_id' => $this->businessId] + $resumo);
    }

    /** Esgotadas as tentativas: vira task no canal que o brief lê (uma por empresa, sem duplicar). */
    public function failed(\Throwable $e): void
    {
        app(AvisoDeFalhaDaAgenda::class)->avisar(
            'COMPRAS-REQ-ESTMIN-B'.$this->businessId,
            'Compras: a requisição pelo estoque mínimo falhou para a empresa '.$this->businessId,
            'O job `RequisicaoEstoqueMinimoJob` esgotou '.$this->tries.' tentativas ('.class_basename($e).").\n"
                ."A empresa pode ficar sem a requisição do dia. Rastro em storage/logs e em failed_jobs.\n\n"
                .'Fechar esta task (done/cancelled) silencia o aviso.',
            'RequisicaoEstoqueMinimoJob',
        );
    }
}
