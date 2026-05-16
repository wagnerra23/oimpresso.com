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
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\RecurringBilling\Services\Boleto\BoletoService;
use RuntimeException;
use Throwable;

/**
 * RefundCobrancaAsaasJob — chama API Asaas pra ESTORNAR cobrança JÁ PAGA.
 *
 * US-CASCADE-BOLETO-005 (refund Asaas — espelha CancelarCobrancaAsaasJob mas
 * pra cobrança paga). Diferente do cancelamento (DELETE /payments/{id} em
 * charge pending), refund mexe em charge com status RECEIVED/CONFIRMED e
 * devolve dinheiro pro pagador via PIX/TED automático Asaas.
 *
 * Endpoint:  POST /api/v3/payments/{id}/refund
 * Body:      { "description": "Cancelamento via FSM — motivo: {motivo}" }
 *            (sem "value" = refund total; com "value" < total = parcial)
 * Response:  { "id": "pay_xxx", "status": "REFUNDED", "value": ... }
 *
 * 🛡️ FLAG DE SEGURANÇA: ASAAS_REFUND_ENABLED (config: services.asaas.refund_enabled)
 *   - Default FALSE — em prod o estorno mexe com dinheiro real do pagador
 *   - Quando false, NÃO chama API, só loga "TODO ativar"
 *   - Wagner ativa em US futura após validação em homologação
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - businessId vem do constructor (Job assíncrono não tem session)
 *   - Query usa withoutGlobalScope + where('business_id', $businessId)
 *   - Documento de outro tenant lança RuntimeException
 *
 * Idempotência crítica (charge paga = real money):
 *   - Se TransactionDocument.status já === 'cancelled' → log + return
 *   - TODO: quando charge model concreto landed, checar campo charge.status
 *     === 'REFUNDED' antes de tentar refund de novo
 *
 * Retry policy: tries=3, backoff=300s (cobrança paga = sensível; espaça retries
 * mais que cancelamento pra evitar storm em caso de degradação Asaas).
 *
 * Despachado por `EstornarBoletoJob` quando `doc_type='boleto_asaas'` e charge
 * está com status paid/received (decisão tomada pelo hub).
 *
 * ⚠️ NÃO confundir com CancelarCobrancaAsaasJob (cancela pending). Os 2 jobs
 * coexistem: o hub roteia baseado no status atual da cobrança.
 */
class RefundCobrancaAsaasJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Máximo de tentativas em falha. */
    public int $tries = 3;

    /** Segundos entre retries (refund = real money → mais espaçado). */
    public int $backoff = 300;

    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionDocumentId,
        public readonly string $motivo,
    ) {}

    public function handle(BoletoService $service): void
    {
        // 1) Carrega documento ignorando global scope (Job sem session auth)
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
                "doc_type inválido pra RefundCobrancaAsaasJob: '{$document->doc_type}'. "
                . 'Esperado: boleto_asaas (doc id=' . $document->id . ')'
            );
        }

        // 4) Idempotência local — TransactionDocument já cancelled é no-op
        //    (o hub EstornarBoletoJob marca cancelled otimisticamente).
        if ($document->status === TransactionDocument::STATUS_CANCELLED) {
            Log::info('RefundCobrancaAsaasJob: documento já cancelled, no-op idempotente', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'motivo' => $this->motivo,
            ]);

            return;
        }

        // 5) Resolve charge_id Asaas — convenção igual CancelarCobrancaAsaasJob
        $chargeId = $this->resolveChargeId($document);

        if ($chargeId === null || $chargeId === '') {
            throw new RuntimeException(
                "Não foi possível resolver charge_id Asaas pro TransactionDocument id={$document->id} "
                . "(doc_class={$document->doc_class}, doc_id={$document->doc_id}). "
                . 'Esperado campo gateway_ref/nosso_numero/asaas_charge_id populado.'
            );
        }

        // 6) Idempotência remota — checa Asaas: se já REFUNDED, no-op
        //    Critical: charge paga = real money; rerun por retry deve ser seguro.
        try {
            $current = $service->fetchPaymentAsaas($this->businessId, $chargeId);
            $remoteStatus = strtoupper((string) ($current['status'] ?? ''));

            if (in_array($remoteStatus, ['REFUNDED', 'PARTIALLY_REFUNDED'], true)) {
                Log::info('RefundCobrancaAsaasJob: charge Asaas já REFUNDED, no-op idempotente', [
                    'business_id' => $this->businessId,
                    'document_id' => $document->id,
                    'charge_id' => $chargeId,
                    'remote_status' => $remoteStatus,
                    'motivo' => $this->motivo,
                ]);

                return;
            }
        } catch (Throwable $e) {
            // Falha de fetch não bloqueia refund — apenas loga warning.
            // Em prod retries vão eventualmente atingir o GET ou já passar
            // direto pro POST /refund (Asaas retorna 400 se já refunded).
            // LGPD (Wave 10 D7): redact defensivo na mensagem.
            $redactor = app(PiiRedactor::class);
            Log::warning('RefundCobrancaAsaasJob: fetch status Asaas falhou (segue pro refund)', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'charge_id' => $chargeId,
                'exception' => get_class($e),
                'message' => $redactor->redact($e->getMessage()),
            ]);
        }

        // 7) FLAG DE SEGURANÇA — em prod refund desligado por default
        if (! (bool) config('services.asaas.refund_enabled', false)) {
            Log::warning('RefundCobrancaAsaasJob: ASAAS_REFUND_ENABLED=false — refund NÃO chamado (TODO)', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'charge_id' => $chargeId,
                'motivo' => $this->motivo,
                'todo' => 'Ativar ASAAS_REFUND_ENABLED=true no .env (CT 100 / Hostinger) após validação homolog',
                'config_path' => 'services.asaas.refund_enabled',
            ]);

            return;
        }

        // 8) Chama POST /payments/{id}/refund
        try {
            $descricao = "Cancelamento via FSM — motivo: {$this->motivo}";
            $response = $service->refundAsaas($this->businessId, $chargeId, $descricao);

            $remoteStatus = strtoupper((string) ($response['status'] ?? ''));

            Log::info('RefundCobrancaAsaasJob: refund Asaas executado com sucesso', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'charge_id' => $chargeId,
                'remote_status' => $remoteStatus,
                'value' => $response['value'] ?? null,
                'motivo' => $this->motivo,
            ]);
        } catch (RequestException $e) {
            // LGPD (Wave 10 D7): response_body Asaas pode incluir customer.cpfCnpj /
            // email / name em erros 400/422 (validation). Redact obrigatório.
            $redactor = app(PiiRedactor::class);
            $responseBody = $e->response?->body() ?? '';
            Log::error('RefundCobrancaAsaasJob: falha HTTP no POST /payments/{id}/refund', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'charge_id' => $chargeId,
                'status_code' => $e->response?->status(),
                'response_body' => $redactor->redact($responseBody),
                'motivo' => $this->motivo,
            ]);

            throw new RuntimeException(
                "Asaas API retornou erro ao fazer refund charge_id={$chargeId}: "
                . ($e->response?->status() ?? '?')
            );
        } catch (Throwable $e) {
            // LGPD: redact defensivo — exception pode citar payload Asaas raw.
            $redactor = app(PiiRedactor::class);
            Log::error('RefundCobrancaAsaasJob: erro inesperado', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'charge_id' => $chargeId,
                'exception' => get_class($e),
                'message' => $redactor->redact($e->getMessage()),
            ]);
            throw $e;
        }
    }

    /**
     * Resolve o ID da cobrança Asaas (`pay_xxx`) a partir do TransactionDocument.
     * Convenção idêntica à CancelarCobrancaAsaasJob — mantém alinhamento.
     */
    private function resolveChargeId(TransactionDocument $document): ?string
    {
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
