<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Listeners;

use App\Domain\Fsm\Models\SaleStageAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Events\NFeAutorizada;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Services\NfeService;
use Modules\RecurringBilling\Events\InvoicePaid;
use Modules\RecurringBilling\Models\Invoice;
use Throwable;

/**
 * US-RB-044 fase 2 · Listener InvoicePaid → emissão NF-e modelo 55 automática.
 *
 * **DIFERENCIAL VERTICAL gráfica** — Iugu/Asaas/Vindi/Pagar.me não têm.
 * Pedido recorrente de clientes do segmento gráfico: "boleto pago → NFe
 * emitida sozinha sem clique humano".
 *
 * Fluxo:
 *   1. Resolve Invoice via (business_id + numero_documento)
 *   2. Chama NfeService::emitirParaInvoice() — usa MotorTributarioService
 *      (US-NFE-043) pra cascade tributário e CertificadoService pro cert A1
 *   3. Idempotência: NfeEmissao com transaction_id = invoice.id é UNIQUE,
 *      segunda chamada retorna a emissão existente (sem duplicar fiscal)
 *   4. Se cStat 100/150 (autorizada) → dispara NFeAutorizada
 *   5. Se rejeitada → emissão fica salva com status='rejeitada' + cstat + motivo
 *      (sem retry automático — ajuste manual do business via UI)
 *
 * Falha de SEFAZ (timeout, cert vencido, etc.) NÃO derruba o pagamento —
 * Throwable é logado e re-throwado pra queue retry (3 tentativas, backoff 60s).
 *
 * Flag de ativação: `nfebrasil.auto_emission_on_invoice_paid` (default `false`).
 * Quando `false`: listener é no-op log only.
 *
 * Fila: 'nfe' (separada de rb_webhooks pra não competir).
 *
 * Pré-requisitos no business:
 *   - Cert A1 ativo (UI `/nfe-brasil/configuracao/certificado`)
 *   - `nfe_business_configs.tributacao_default.ncm_default` configurado
 *   - Cliente (Contact) com `tax_number` (CPF/CNPJ)
 *
 * Sem qualquer um desses → RuntimeException no service, queue retenta,
 * 3ª falha invoca `failed()` que loga.
 *
 * Referências:
 *   - ADR ARQ-0006 (cascade tributário)
 *   - SPEC.md US-RB-044 + US-NFE-001
 *   - Event upstream: Modules/RecurringBilling/Events/InvoicePaid
 *   - Event downstream: Modules/NfeBrasil/Events/NFeAutorizada
 */
class EmitirNFeAoReceberPagamento implements ShouldQueue
{
    public string $queue = 'nfe';
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly ?NfeService $service = null,
    ) {}

    public function handle(InvoicePaid $event): void
    {
        Log::info('NFe emission requested', [
            'business_id' => $event->businessId,
            'invoice_ref' => $event->invoiceRef,
            'valor'       => $event->valor,
            'paid_at'     => $event->paidAt,
            'enabled'     => config('nfebrasil.auto_emission_on_invoice_paid', false),
        ]);

        if (! config('nfebrasil.auto_emission_on_invoice_paid', false)) {
            Log::info('NFe auto-emission DISABLED — listener no-op (global flag)', [
                'invoice_ref' => $event->invoiceRef,
            ]);
            return;
        }

        // Per-business gate (ADR 0093 multi-tenant Tier 0). Tenant precisa
        // ter opt-in explícito via nfe_business_configs.auto_emission_enabled=true.
        $bizConfig = NfeBusinessConfig::where('business_id', $event->businessId)->first();
        if (! $bizConfig || ! $bizConfig->auto_emission_enabled) {
            Log::info('NFe auto-emission DISABLED — listener no-op (per-business gate)', [
                'business_id' => $event->businessId,
                'invoice_ref' => $event->invoiceRef,
                'has_config'  => (bool) $bizConfig,
            ]);
            return;
        }

        $invoice = Invoice::where('business_id', $event->businessId)
            ->where('numero_documento', $event->invoiceRef)
            ->first();

        if (! $invoice) {
            Log::warning('NFe auto-emission: Invoice não encontrada — pulando', [
                'business_id' => $event->businessId,
                'invoice_ref' => $event->invoiceRef,
            ]);
            return;
        }

        // Gate FSM (US-SELL-012, ADR 0129): se Invoice está vinculada a uma
        // Transaction com process_id/current_stage_id (gate por venda), só
        // emite se o stage atual tiver action `emitir_nfe`. Caso contrário,
        // no-op silencioso ("venda sem nota é caminho feliz, não falha").
        //
        // Esse gate convive com os 2 gates anteriores (flag global +
        // per-business). Quando POS checkout escrever process_id na
        // transaction (US futura), este gate vira o canônico.
        $sale = $this->resolveSaleForInvoice($invoice);
        if ($sale && ! empty($sale->current_stage_id)) {
            $action = SaleStageAction::where('stage_id', $sale->current_stage_id)
                ->where('key', 'emitir_nfe')
                ->first();

            if (! $action) {
                Log::info('NFe auto-emission: gate FSM bloqueou (stage atual sem action emitir_nfe) — caminho feliz', [
                    'business_id'      => $event->businessId,
                    'invoice_ref'      => $event->invoiceRef,
                    'sale_id'          => $sale->id ?? null,
                    'current_stage_id' => $sale->current_stage_id,
                ]);
                return;
            }
        }

        $service = $this->service ?? app(NfeService::class);

        try {
            $emissao = $service->emitirParaInvoice($invoice);
        } catch (Throwable $e) {
            Log::error('NFe auto-emission falhou', [
                'business_id' => $event->businessId,
                'invoice_ref' => $event->invoiceRef,
                'error'       => $e->getMessage(),
            ]);
            throw $e; // queue retenta (3 tries, backoff 60s)
        }

        if ($emissao->status === 'autorizada') {
            event(new NFeAutorizada($emissao));
        }

        Log::info('NFe auto-emission processada', [
            'business_id'  => $event->businessId,
            'invoice_ref'  => $event->invoiceRef,
            'emissao_id'   => $emissao->id,
            'status'       => $emissao->status,
            'cstat'        => $emissao->cstat,
            'chave_44'     => $emissao->chave_44,
        ]);
    }

    public function failed(InvoicePaid $event, Throwable $e): void
    {
        Log::error('NFe emission failed após retries', [
            'invoice_ref' => $event->invoiceRef,
            'business_id' => $event->businessId,
            'error'       => $e->getMessage(),
        ]);

        // TODO: notificar admin do business via mcp_inbox_notifications
    }

    /**
     * Tenta resolver a Transaction (sale) associada à Invoice pra consultar
     * o gate FSM. Retorna `null` quando:
     *   - Invoice não tem `transaction_id` (fluxo recurring sem venda direta)
     *   - Tabela `transactions` não tem coluna `current_stage_id` ainda
     *     (migration `add_fsm_columns_to_transactions` não rodou)
     *   - Transaction não foi encontrada
     *
     * Em todos esses casos, o listener segue o fluxo legacy (3 gates anteriores).
     *
     * @return object|null Stub minimal `{id, current_stage_id, business_id}`.
     */
    private function resolveSaleForInvoice(Invoice $invoice): ?object
    {
        // Invoice precisa expor `transaction_id` (atributo direto ou relação).
        // Hoje (2026-05-11) Invoice NÃO tem essa coluna — isso vira gate cego
        // até US futura adicionar `transaction_id` em recurring_invoices ou
        // POS checkout vincular Sell.transaction_id ao recurring.
        $transactionId = $invoice->transaction_id ?? null;
        if (! $transactionId) {
            return null;
        }

        if (! Schema::hasColumn('transactions', 'current_stage_id')) {
            return null;
        }

        $row = \DB::table('transactions')
            ->select('id', 'business_id', 'current_stage_id')
            ->where('id', $transactionId)
            ->first();

        return $row ?: null;
    }
}
