<?php

declare(strict_types=1);

namespace App\Domain\Fsm\SideEffects;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use App\Domain\Fsm\Models\TransactionDocument;
use App\Jobs\EstornarBoletoJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * US-SELL-034 — Side-effect canônico de cancelamento em cascata.
 *
 * Orquestra os efeitos colaterais necessários quando uma venda é cancelada
 * (transição FSM `cancelar_venda` em qualquer stage não-terminal):
 *
 *   1. Pra cada TransactionDocument com status=authorized, bifurca por doc_type:
 *      - nfe55/nfce65 → dispatch Modules\NfeBrasil\Jobs\CancelarNfeJob (SEFAZ tpEvento=110111)
 *      - nfse56       → dispatch Modules\NfeBrasil\Jobs\CancelarNfseJob (resolve driver per-município)
 *      (ambos idempotentes — emissão já cancelada não duplica job)
 *   2. Pra cada TransactionDocument doc_type=boleto_asaas/boleto_inter
 *      status=pending: dispatch App\Jobs\EstornarBoletoJob (hub que roteia
 *      pro gateway — US-CASCADE-BOLETO-002)
 *   3. Side-effect síncrono: LiberarReserva (estoque)
 *   4. Dispatch Modules\Whatsapp\Jobs\NotificarClienteCancelamentoJob
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

        // 2) Cancelar boletos pending (Asaas/Inter) — hub EstornarBoletoJob
        //    roteia pro Cancelar*Job específico do gateway (US-CASCADE-BOLETO-002).
        $this->cancelarBoletos($businessId, $transactionId, $motivo);

        // 3) Liberar reservas de estoque (síncrono — reusa LiberarReserva)
        try {
            (new LiberarReserva)->execute($subject, $payload);
        } catch (\Throwable $e) {
            Log::warning('CancelarVendaCascade: LiberarReserva falhou (não bloqueia cascade)', [
                'business_id' => $businessId,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
        }

        // 4) Notificar cliente (Whatsapp/email)
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

        // Bifurcação por modelo fiscal:
        //   - nfe55/nfce65 → CancelarNfeJob (evento SEFAZ tpEvento=110111 padronizado)
        //   - nfse56       → CancelarNfseJob (resolve driver per-município —
        //                    ABRASF/GINFES/IPM/Tiplan/nfse.gov.br/sefin)
        $nfeJobClass = '\Modules\NfeBrasil\Jobs\CancelarNfeJob';
        $nfseJobClass = '\Modules\NfeBrasil\Jobs\CancelarNfseJob';

        $nfeJobExists = class_exists($nfeJobClass);
        $nfseJobExists = class_exists($nfseJobClass);

        if (! $nfeJobExists && ! $nfseJobExists) {
            Log::warning('CancelarVendaCascade: nem CancelarNfeJob nem CancelarNfseJob existem — pulando dispatch', [
                'transaction_id' => $transactionId,
                'docs_count' => $docs->count(),
            ]);
            return;
        }

        foreach ($docs as $doc) {
            try {
                if ($doc->doc_type === TransactionDocument::DOC_NFSE56) {
                    if (! $nfseJobExists) {
                        Log::warning('CancelarVendaCascade: CancelarNfseJob não existe — pulando NFSe', [
                            'transaction_document_id' => $doc->id,
                        ]);
                        continue;
                    }
                    dispatch(new $nfseJobClass($businessId, (int) $doc->id, $motivo));
                } else {
                    if (! $nfeJobExists) {
                        Log::warning('CancelarVendaCascade: CancelarNfeJob não existe — pulando NFe/NFCe', [
                            'transaction_document_id' => $doc->id,
                        ]);
                        continue;
                    }
                    dispatch(new $nfeJobClass($businessId, (int) $doc->id, $motivo));
                }
            } catch (\Throwable $e) {
                Log::error('CancelarVendaCascade: falha ao dispatch job de cancelamento', [
                    'business_id' => $businessId,
                    'transaction_document_id' => $doc->id,
                    'doc_type' => $doc->doc_type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Cancela boletos pending (Asaas/Inter) via hub EstornarBoletoJob.
     *
     * O hub `EstornarBoletoJob` valida multi-tenant, garante idempotência
     * (no-op se já cancelled) e roteia pro Cancelar*Job específico do
     * gateway (US-CASCADE-BOLETO-002). Best-effort: falha em 1 doc não
     * bloqueia os demais — espelha pattern de `cancelarNfes`.
     */
    private function cancelarBoletos(int $businessId, int $transactionId, string $motivo): void
    {
        $docs = TransactionDocument::query()
            ->where('business_id', $businessId)
            ->where('transaction_id', $transactionId)
            ->whereIn('doc_type', [
                TransactionDocument::DOC_BOLETO_ASAAS,
                TransactionDocument::DOC_BOLETO_INTER,
            ])
            ->where('status', TransactionDocument::STATUS_PENDING)
            ->get();

        if ($docs->isEmpty()) {
            Log::info('CancelarVendaCascade: nenhum boleto pra cancelar', [
                'transaction_id' => $transactionId,
            ]);
            return;
        }

        foreach ($docs as $doc) {
            try {
                dispatch(new EstornarBoletoJob($businessId, (int) $doc->id, $motivo));
            } catch (\Throwable $e) {
                Log::warning('CancelarVendaCascade: falha ao dispatch EstornarBoletoJob (não bloqueia cascade)', [
                    'business_id' => $businessId,
                    'transaction_document_id' => $doc->id,
                    'doc_type' => $doc->doc_type,
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
