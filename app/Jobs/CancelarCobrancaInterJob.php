<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Fsm\Models\TransactionDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\RecurringBilling\Services\Boleto\BoletoService;
use RuntimeException;
use Throwable;

/**
 * CancelarCobrancaInterJob — chamada HTTP real ao Inter PJ pra cancelar boleto.
 *
 * US-CASCADE-BOLETO-004 (gateway concreto Inter PJ). Disparado por
 * `EstornarBoletoJob::despacharGateway()` quando `doc_type='boleto_inter'`.
 *
 * Fluxo:
 *   1. Carrega TransactionDocument com guard multi-tenant (ADR 0093)
 *   2. Valida doc_type = boleto_inter
 *   3. Resolve charge real via doc_class+doc_id (MorphTo poly)
 *   4. Idempotência: charge já cancelado → log + return (sem chamar Inter)
 *   5. Chama Inter via BoletoService::cancelar() — usa InterDriver mTLS + OAuth2
 *      (motivo Inter PJ: APEDIDODOCLIENTE — cancelamento solicitado pelo cliente)
 *   6. Sucesso → marca charge.status='cancelled' + cancelled_at + cancellation_reason
 *   7. Falha Inter → log error + re-throw (Job retry tries=3 backoff=120s)
 *
 * NÃO escreve em TransactionDocument.status — quem faz isso é o caller
 * (EstornarBoletoJob, otimisticamente). Aqui só atualiza o charge local
 * + chama Inter HTTP. SoC ADR 0094 §5.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - businessId vem do constructor (Job assíncrono não enxerga session)
 *   - Query usa withoutGlobalScope + where('business_id', $businessId)
 *   - Documento de outro tenant lança RuntimeException
 *
 * Retry policy: tries=3, backoff=120s (Inter PJ pode oscilar em pico).
 *
 * ⚠️ TODO (US-CASCADE-BOLETO-004b): refund parcial/total de boleto JÁ PAGO.
 * Inter PJ não tem endpoint nativo de reembolso de boleto pago — exige
 * PIX manual ou TED. Hoje cancelamento só funciona pra boleto pending
 * (open / overdue). Boleto pago + venda cancelada = caso de borda pra
 * tratamento manual financeiro.
 */
class CancelarCobrancaInterJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Máximo de tentativas em falha. */
    public int $tries = 3;

    /** Segundos entre retries (Inter PJ pode oscilar). */
    public int $backoff = 120;

    /** Motivo Inter PJ default — cancelamento a pedido do cliente. */
    private const MOTIVO_INTER_DEFAULT = 'APEDIDODOCLIENTE';

    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionDocumentId,
        public readonly string $motivo,
    ) {}

    public function handle(?BoletoService $boletoService = null): void
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

        // 3) Validação de doc_type — só boleto_inter é aceito aqui
        if ($document->doc_type !== TransactionDocument::DOC_BOLETO_INTER) {
            throw new RuntimeException(
                "doc_type inválido pra CancelarCobrancaInterJob: '{$document->doc_type}'. "
                . 'Esperado: boleto_inter (doc id=' . $document->id . ')'
            );
        }

        // 4) Resolve charge real via MorphTo (doc_class+doc_id)
        $charge = $document->document; // Eloquent MorphTo resolve

        if ($charge === null) {
            throw new RuntimeException(
                'Charge polimórfica não encontrada (doc_class=' . $document->doc_class
                . ' doc_id=' . $document->doc_id . ' td_id=' . $document->id . ')'
            );
        }

        // 5) Idempotência — charge já cancelado é no-op (não chama Inter de novo)
        if (isset($charge->status) && $charge->status === 'cancelled') {
            Log::info('CancelarCobrancaInterJob: charge já cancelada, no-op', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'charge_class' => $document->doc_class,
                'charge_id' => $document->doc_id,
                'motivo' => $this->motivo,
            ]);

            return;
        }

        // 6) Resolve identificador Inter (nossoNumero / codigoSolicitacao).
        //    Convenção Inter PJ: campo gravado no momento da emissão pela
        //    InterDriver::emitir() → BoletoResult.nossoNumero. O charge model
        //    real (a ser landed em US futura) deve expor um desses campos.
        $nossoNumero = $charge->nosso_numero
            ?? $charge->codigo_solicitacao
            ?? $charge->gateway_id
            ?? null;

        if ($nossoNumero === null || $nossoNumero === '') {
            throw new RuntimeException(
                'Charge sem identificador Inter (nosso_numero/codigo_solicitacao/gateway_id) — '
                . 'impossível cancelar via API Inter PJ (td_id=' . $document->id . ')'
            );
        }

        // 7) Resolve service Inter (existente) — se infra ainda não landed,
        //    loga TODO gracioso (Agent BOLETO-003 stub pattern) e retorna.
        $service = $boletoService ?? $this->resolverService();

        if ($service === null) {
            Log::warning('CancelarCobrancaInterJob: BoletoService ausente — TODO stub', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'nosso_numero' => $nossoNumero,
                'motivo' => $this->motivo,
                'todo' => 'US-CASCADE-BOLETO-004b: implementar quando InterService landed',
            ]);

            return;
        }

        // 8) Chama Inter PJ via service canônico (mTLS + OAuth2 + Bearer).
        //    BoletoService.cancelar() → InterDriver.cancelar() → InterApi.cancelNossoNumero()
        //    Motivo Inter PJ: APEDIDODOCLIENTE (cancelamento via FSM cancelar_venda
        //    é a pedido cliente — ADR 0129 §FSM transitions).
        try {
            $service->cancelar(
                $this->businessId,
                (string) $nossoNumero,
                self::MOTIVO_INTER_DEFAULT,
            );
        } catch (Throwable $e) {
            Log::error('CancelarCobrancaInterJob: chamada Inter PJ falhou', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'nosso_numero' => $nossoNumero,
                'motivo' => $this->motivo,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                // Body NUNCA logado — pode conter PII (CPF/CNPJ do pagador)
            ]);

            // Re-lança pra retry policy do Queue (tries=3 backoff=120s)
            throw $e;
        }

        // 9) Atualiza charge local — status cancelled + auditoria
        $this->atualizarChargeCancelada($charge);

        Log::info('CancelarCobrancaInterJob: charge cancelada com sucesso Inter PJ', [
            'business_id' => $this->businessId,
            'document_id' => $document->id,
            'charge_class' => $document->doc_class,
            'charge_id' => $document->doc_id,
            'nosso_numero' => $nossoNumero,
            'motivo' => $this->motivo,
        ]);
    }

    /**
     * Atualiza atributos de cancelamento na charge real, defensivo —
     * só seta campos que o model realmente tem (charge pode ser de
     * múltiplos models polimórficos com schema variável).
     */
    private function atualizarChargeCancelada(object $charge): void
    {
        $dirty = false;

        if ($this->chargeHasAttribute($charge, 'status')) {
            $charge->status = 'cancelled';
            $dirty = true;
        }
        if ($this->chargeHasAttribute($charge, 'cancelled_at')) {
            $charge->cancelled_at = now();
            $dirty = true;
        }
        if ($this->chargeHasAttribute($charge, 'cancellation_reason')) {
            $charge->cancellation_reason = $this->motivo;
            $dirty = true;
        }

        if ($dirty && method_exists($charge, 'save')) {
            $charge->save();
        }
    }

    /**
     * Resolve BoletoService via container; retorna null se classe ausente
     * (stub gracioso até infra Inter Service estar landed em prod — pattern
     * Agent BOLETO-003 do EstornarBoletoJob hub).
     */
    private function resolverService(): ?BoletoService
    {
        if (! class_exists(BoletoService::class)) {
            return null;
        }

        return app(BoletoService::class);
    }

    /**
     * Verifica se charge tem o atributo nomeado (sem disparar exception
     * pra "Indirect modification of overloaded property"). Funciona em
     * Eloquent Model (fillable/attributes) e em objects genéricos.
     */
    private function chargeHasAttribute(object $charge, string $attr): bool
    {
        if (property_exists($charge, $attr)) {
            return true;
        }

        // Eloquent Model — verifica via array de atributos
        if (method_exists($charge, 'getAttributes')) {
            $attrs = $charge->getAttributes();

            return array_key_exists($attr, $attrs) || $this->columnExistsOnTable($charge, $attr);
        }

        return false;
    }

    /**
     * Permite escrita em atributo novo pra Eloquent Model (mesmo que ainda
     * não esteja em $attributes). Faz best-effort via Schema check.
     */
    private function columnExistsOnTable(object $charge, string $attr): bool
    {
        if (! method_exists($charge, 'getTable')) {
            return false;
        }

        try {
            return \Illuminate\Support\Facades\Schema::hasColumn(
                $charge->getTable(),
                $attr,
            );
        } catch (Throwable) {
            return false;
        }
    }
}
