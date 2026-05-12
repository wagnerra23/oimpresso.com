<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Jobs;

use App\Domain\Fsm\Models\TransactionDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\RecurringBilling\Services\Boleto\BoletoService;
use RuntimeException;
use Throwable;

/**
 * CancelarCobrancaAsaasJob — chama API Asaas pra cancelar boleto/cobrança real.
 *
 * US-CASCADE-BOLETO-003 (Asaas slot do hub EstornarBoletoJob).
 *
 * Despachado por `EstornarBoletoJob` quando `doc_type='boleto_asaas'`. Faz a
 * chamada HTTP `DELETE /api/v3/payments/{id}` via `BoletoService::cancelar()`
 * que já abstrai driver Asaas (`AsaasDriver`) + decrypt de credenciais
 * (ADR tech/0007) + roteamento por tenant.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - businessId vem do constructor (Job assíncrono não tem session)
 *   - Query usa withoutGlobalScope + where('business_id', $businessId)
 *   - Documento de outro tenant lança RuntimeException
 *
 * Idempotência:
 *   - Se `TransactionDocument.status === 'cancelled'` → log info + return
 *     (proteção contra retry / dispatch duplicado pelo hub)
 *
 * Retry policy: tries=3, backoff=120s (rede Asaas pode oscilar). Erro 4xx/5xx
 * do gateway lança RuntimeException → fila reagenda automaticamente.
 *
 * ⚠️ TODO US-CASCADE-BOLETO-004: estorno (refund) de cobrança JÁ PAGA. Hoje só
 * cobre cancelamento de cobrança pending. Pra paga, endpoint correto é
 * `POST /api/v3/payments/{id}/refund` — vira US separada (regra fiscal +
 * contábil de retorno de dinheiro pro pagador).
 */
class CancelarCobrancaAsaasJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Máximo de tentativas em falha. */
    public int $tries = 3;

    /** Segundos entre retries (rede Asaas estável → 120s razoável). */
    public int $backoff = 120;

    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionDocumentId,
        public readonly string $motivo,
    ) {}

    public function handle(BoletoService $service): void
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

        // 3) Validação de doc_type — só boleto_asaas é aceito por esse Job
        if ($document->doc_type !== TransactionDocument::DOC_BOLETO_ASAAS) {
            throw new RuntimeException(
                "doc_type inválido pra CancelarCobrancaAsaasJob: '{$document->doc_type}'. "
                . 'Esperado: boleto_asaas (doc id=' . $document->id . ')'
            );
        }

        // 4) Idempotência — já cancelado é no-op
        //    O hub EstornarBoletoJob marca status=cancelled otimisticamente
        //    após despachar este Job; rerun por retry/fila precisa ser seguro.
        if ($document->status === TransactionDocument::STATUS_CANCELLED) {
            Log::info('CancelarCobrancaAsaasJob: documento já cancelled, no-op idempotente', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'motivo' => $this->motivo,
            ]);

            return;
        }

        // 5) Resolve charge_id Asaas — vem do model concreto referenciado via
        //    doc_class+doc_id (MorphTo). Convenção: o model resolvido expõe
        //    `gateway_ref` (ex: Invoice) ou `nosso_numero` (ex: ChargeAttempt).
        $chargeId = $this->resolveChargeId($document);

        if ($chargeId === null || $chargeId === '') {
            throw new RuntimeException(
                "Não foi possível resolver charge_id Asaas pro TransactionDocument id={$document->id} "
                . "(doc_class={$document->doc_class}, doc_id={$document->doc_id}). "
                . 'Esperado campo gateway_ref/nosso_numero/asaas_charge_id populado.'
            );
        }

        // 6) Chama API Asaas via BoletoService (abstrai driver + credenciais)
        //    BoletoDriverContract::cancelar() faz DELETE /payments/{id} e
        //    lança RequestException em 4xx/5xx via Http::throw().
        try {
            $service->cancelar($this->businessId, $chargeId, $this->motivo);
        } catch (RequestException $e) {
            Log::error('CancelarCobrancaAsaasJob: falha HTTP no DELETE /payments/{id}', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'charge_id' => $chargeId,
                'status_code' => $e->response?->status(),
                'response_body' => $e->response?->body(),
                'motivo' => $this->motivo,
            ]);

            // Re-lança pra fila reagendar via $tries/$backoff
            throw new RuntimeException(
                "Asaas API retornou erro ao cancelar charge_id={$chargeId}: "
                . ($e->response?->status() ?? '?')
            );
        } catch (Throwable $e) {
            Log::error('CancelarCobrancaAsaasJob: erro inesperado', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'charge_id' => $chargeId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        // 7) Confirma status local — hub já marcou cancelled, aqui é só log
        //    de sucesso pra auditoria do gateway.
        Log::info('CancelarCobrancaAsaasJob: cobrança Asaas cancelada com sucesso', [
            'business_id' => $this->businessId,
            'document_id' => $document->id,
            'charge_id' => $chargeId,
            'motivo' => $this->motivo,
        ]);
    }

    /**
     * Resolve o ID da cobrança Asaas (`pay_xxx`) a partir do TransactionDocument.
     *
     * Convenção: o model concreto referenciado em doc_class expõe um dos campos:
     *   - `gateway_ref` (Invoice — preenchido após 1ª ChargeAttempt)
     *   - `nosso_numero` (legacy charge models)
     *   - `asaas_charge_id` (futuro AsaasCharge model dedicado)
     *
     * Se nenhum estiver populado retorna null e o handler lança exception.
     */
    private function resolveChargeId(TransactionDocument $document): ?string
    {
        // MorphTo resolve doc_class+doc_id pro Eloquent model concreto
        $charge = $document->document;

        if ($charge === null) {
            return null;
        }

        foreach (['asaas_charge_id', 'gateway_ref', 'nosso_numero'] as $field) {
            $value = $charge->{$field} ?? null;
            if (! empty($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
