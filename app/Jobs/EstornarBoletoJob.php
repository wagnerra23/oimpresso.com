<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Fsm\Models\TransactionDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use RuntimeException;

/**
 * EstornarBoletoJob — Hub de cancelamento / estorno de cobrança (Asaas / Inter PJ).
 *
 * US-CASCADE-BOLETO-001 (PR foundational): este Job é o ponto único de entrada
 * pra processar refund de um TransactionDocument do tipo boleto_*. Ele resolve
 * 2 dimensões: (a) gateway (Asaas vs Inter PJ), (b) ação (cancelar pending vs
 * refund paid). Despacha pro Job específico que faz a chamada real.
 *
 * Roteamento por status (US-CASCADE-BOLETO-005/006):
 *
 *   ┌────────────────────┬──────────────────────────────────────┐
 *   │ charge.status      │ Job despachado                       │
 *   ├────────────────────┼──────────────────────────────────────┤
 *   │ pending / null     │ CancelarCobrancaAsaasJob / Inter     │
 *   │                    │ (DELETE /payments/{id} pra Asaas)    │
 *   │ paid / received    │ RefundCobrancaAsaasJob / Inter       │
 *   │ confirmed          │ (POST /payments/{id}/refund Asaas;   │
 *   │                    │  manual TED/PIX pra Inter)           │
 *   └────────────────────┴──────────────────────────────────────┘
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - businessId vem do constructor (Job assíncrono não enxerga session)
 *   - Query usa withoutGlobalScope + where('business_id', $businessId)
 *   - Documento de outro tenant lança RuntimeException
 *
 * Idempotência: documento já com status='cancelled' loga info e retorna
 * sem despachar gateway (proteção contra retry / dispatch duplicado).
 *
 * Retry policy: tries=3, backoff=60s (rede do gateway pode oscilar).
 */
class EstornarBoletoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Máximo de tentativas em falha. */
    public int $tries = 3;

    /** Segundos entre retries. */
    public int $backoff = 60;

    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionDocumentId,
        public readonly string $motivo,
    ) {}

    public function handle(): void
    {
        // 1) Carrega documento ignorando global scope (Job não tem session auth)
        $document = TransactionDocument::withoutGlobalScope(ScopeByBusiness::class)
            ->find($this->transactionDocumentId);

        if ($document === null) {
            throw new RuntimeException(
                "TransactionDocument id={$this->transactionDocumentId} não encontrado"
            );
        }

        // 2) Multi-tenant guard — businessId do Job DEVE bater com o do documento
        if ((int) $document->business_id !== $this->businessId) {
            throw new RuntimeException(
                "Cross-tenant violation: Job businessId={$this->businessId} != "
                . "document.business_id={$document->business_id} (doc id={$document->id})"
            );
        }

        // 3) Validação de doc_type — só boleto_* é aceito
        $tiposValidos = [
            TransactionDocument::DOC_BOLETO_ASAAS,
            TransactionDocument::DOC_BOLETO_INTER,
        ];

        if (! in_array($document->doc_type, $tiposValidos, true)) {
            throw new RuntimeException(
                "doc_type inválido pra estorno: '{$document->doc_type}'. "
                . 'Esperado: boleto_asaas|boleto_inter (doc id=' . $document->id . ')'
            );
        }

        // 4) Idempotência — já cancelado é no-op
        if ($document->status === TransactionDocument::STATUS_CANCELLED) {
            Log::info('EstornarBoletoJob: documento já cancelado, no-op', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'doc_type' => $document->doc_type,
                'motivo' => $this->motivo,
            ]);

            return;
        }

        // 5) Roteamento por gateway — despacha Job específico se existir,
        //    senão loga TODO (ainda não implementado — ver header).
        $this->despacharGateway($document);

        // 6) Marca como cancelled localmente
        //    Observação: o status real do gateway é confirmado quando o
        //    Cancelar*Job concreto processar o webhook de retorno. Aqui
        //    marcamos otimisticamente — a fonte da verdade fiscal/financeira
        //    fica do lado do gateway, e o status final é reconciliado por
        //    webhook listener (US-CASCADE-BOLETO-003+).
        $document->status = TransactionDocument::STATUS_CANCELLED;
        $document->save();

        Log::info('EstornarBoletoJob: documento marcado como cancelled', [
            'business_id' => $this->businessId,
            'document_id' => $document->id,
            'doc_type' => $document->doc_type,
            'motivo' => $this->motivo,
        ]);
    }

    /**
     * Despacha Job específico por gateway + ação (cancel vs refund).
     *
     * Decisão em 2 passos:
     *   1. Resolver status atual da charge polimórfica (campo charge.status)
     *   2. Mapear (doc_type, action) → FQCN Job
     *
     * Se charge tiver status 'paid' / 'received' / 'confirmed' (case-insensitive)
     * → refund. Caso contrário → cancel (caminho default já existente, preserva
     * comportamento original pra charges pending).
     */
    private function despacharGateway(TransactionDocument $document): void
    {
        $charge = $document->document; // MorphTo
        $chargeStatus = $charge?->status ?? null;
        $action = $this->resolverAcao($chargeStatus);

        Log::info('EstornarBoletoJob: roteando gateway por status', [
            'business_id' => $this->businessId,
            'document_id' => $document->id,
            'doc_type' => $document->doc_type,
            'charge_status' => $chargeStatus,
            'action' => $action,
            'motivo' => $this->motivo,
        ]);

        // Matriz (doc_type, action) → FQCN. RefundCobranca*Job só roteia
        // quando charge.status indica pago. Mantém preservação do caminho
        // original cancelar pending (back-compat do US-CASCADE-BOLETO-001).
        $mapaJobs = [
            'cancel' => [
                TransactionDocument::DOC_BOLETO_ASAAS => '\\Modules\\RecurringBilling\\Jobs\\CancelarCobrancaAsaasJob',
                TransactionDocument::DOC_BOLETO_INTER => '\\App\\Jobs\\CancelarCobrancaInterJob',
            ],
            'refund' => [
                TransactionDocument::DOC_BOLETO_ASAAS => '\\Modules\\RecurringBilling\\Jobs\\RefundCobrancaAsaasJob',
                TransactionDocument::DOC_BOLETO_INTER => '\\App\\Jobs\\RefundCobrancaInterJob',
            ],
        ];

        $jobClass = $mapaJobs[$action][$document->doc_type] ?? null;

        if ($jobClass === null) {
            // Não deveria chegar aqui — validação acima já protege.
            return;
        }

        if (! class_exists($jobClass)) {
            Log::warning('EstornarBoletoJob: gateway Job ainda não implementado (TODO)', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'doc_type' => $document->doc_type,
                'action' => $action,
                'expected_class' => $jobClass,
                'motivo' => $this->motivo,
                'todo' => 'Implementar Job concreto (US-CASCADE-BOLETO-003+ cancel; 005/006 refund)',
            ]);

            return;
        }

        // Dispatch real — Job concreto faz a chamada HTTP ao gateway.
        Bus::dispatch(new $jobClass(
            $this->businessId,
            $document->id,
            $this->motivo,
        ));
    }

    /**
     * Decide 'cancel' vs 'refund' a partir do status atual da charge polimórfica.
     *
     * Statuses considerados "pago" (qualquer case):
     *   - paid           (convenção local Eloquent)
     *   - received       (Asaas: cobrança quitada, valor já creditado)
     *   - confirmed      (Asaas: pagamento confirmado, ainda em D+1)
     *
     * Statuses null/desconhecido → 'cancel' (back-compat com charges legacy
     * que não populam status — preserva comportamento pré-refactor).
     */
    private function resolverAcao(?string $chargeStatus): string
    {
        if ($chargeStatus === null || $chargeStatus === '') {
            return 'cancel';
        }

        $normalized = strtolower(trim($chargeStatus));

        return in_array($normalized, ['paid', 'received', 'confirmed'], true)
            ? 'refund'
            : 'cancel';
    }
}
