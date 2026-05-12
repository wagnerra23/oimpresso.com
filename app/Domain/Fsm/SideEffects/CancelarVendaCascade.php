<?php

declare(strict_types=1);

namespace App\Domain\Fsm\SideEffects;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use App\Domain\Fsm\Models\TransactionDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * US-SELL-034 — Side-effect canônico de cancelamento em cascata.
 *
 * Orquestra os efeitos colaterais necessários quando uma venda é cancelada
 * (transição FSM `cancelar_venda` em qualquer stage não-terminal):
 *
 *   1. Pra cada TransactionDocument doc_type=nfe55/nfce65/nfse56/...
 *      status=authorized: dispatch Modules\NfeBrasil\Jobs\CancelarNfeJob
 *      (idempotente — NFe já cancelada não duplica job)
 *   2. Side-effect síncrono: LiberarReserva (estoque)
 *   3. Dispatch Modules\Whatsapp\Jobs\NotificarClienteCancelamentoJob
 *
 * Best-effort com idempotência por job individual. Falha de um efeito
 * NÃO bloqueia os outros — todos rodam independentes.
 *
 * Multi-tenant Tier 0 (ADR 0093): cada job recebe $businessId no
 * constructor (nunca lê session()).
 *
 * Refs: ADR 0129 §SideEffects, CASOS-USO-PIPELINE-VENDAS.md §CU-05 G6
 */
class CancelarVendaCascade implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        $businessId = (int) $subject->business_id;
        $transactionId = (int) $subject->getKey();
        $motivo = (string) ($payload['motivo'] ?? 'Cancelamento via FSM');

        Log::info('CancelarVendaCascade: iniciando', [
            'business_id' => $businessId,
            'transaction_id' => $transactionId,
            'motivo' => $motivo,
        ]);

        // 1) Cancelar NFes autorizadas
        $this->cancelarNfes($businessId, $transactionId, $motivo);

        // 2) Liberar reservas de estoque (síncrono — reusa LiberarReserva)
        try {
            (new LiberarReserva)->execute($subject, $payload);
        } catch (\Throwable $e) {
            Log::warning('CancelarVendaCascade: LiberarReserva falhou (não bloqueia cascade)', [
                'business_id' => $businessId,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
        }

        // 3) Notificar cliente (Whatsapp/email)
        $this->notificarCliente($businessId, $transactionId, $motivo);
    }

    private function cancelarNfes(int $businessId, int $transactionId, string $motivo): void
    {
        $docs = TransactionDocument::query()
            ->where('business_id', $businessId)
            ->where('transaction_id', $transactionId)
            ->whereIn('doc_type', [
                TransactionDocument::DOC_NFE55,
                TransactionDocument::DOC_NFCE65,
                TransactionDocument::DOC_NFSE56,
            ])
            ->where('status', TransactionDocument::STATUS_AUTHORIZED)
            ->get();

        if ($docs->isEmpty()) {
            Log::info('CancelarVendaCascade: nenhuma NFe pra cancelar', [
                'transaction_id' => $transactionId,
            ]);
            return;
        }

        $jobClass = '\Modules\NfeBrasil\Jobs\CancelarNfeJob';
        if (! class_exists($jobClass)) {
            Log::warning('CancelarVendaCascade: CancelarNfeJob não existe — pulando dispatch', [
                'transaction_id' => $transactionId,
                'docs_count' => $docs->count(),
            ]);
            return;
        }

        foreach ($docs as $doc) {
            try {
                dispatch(new $jobClass($businessId, (int) $doc->id, $motivo));
            } catch (\Throwable $e) {
                Log::error('CancelarVendaCascade: falha ao dispatch CancelarNfeJob', [
                    'business_id' => $businessId,
                    'transaction_document_id' => $doc->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function notificarCliente(int $businessId, int $transactionId, string $motivo): void
    {
        $jobClass = '\Modules\Whatsapp\Jobs\NotificarClienteCancelamentoJob';
        if (! class_exists($jobClass)) {
            Log::info('CancelarVendaCascade: NotificarClienteCancelamentoJob não existe — pulando', [
                'transaction_id' => $transactionId,
            ]);
            return;
        }

        try {
            dispatch(new $jobClass($businessId, $transactionId, $motivo));
        } catch (\Throwable $e) {
            Log::error('CancelarVendaCascade: falha ao dispatch NotificarClienteCancelamentoJob', [
                'business_id' => $businessId,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
