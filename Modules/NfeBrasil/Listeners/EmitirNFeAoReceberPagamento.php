<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Modules\RecurringBilling\Events\InvoicePaid;

/**
 * US-RB-044 · Listener InvoicePaid → emissão NFe modelo 55 automática.
 *
 * **DIFERENCIAL VERTICAL gráfica** — Iugu/Asaas/Vindi/Pagar.me não têm.
 * Larissa (ROTA LIVRE) pediu há tempos: "boleto pago → NFe emitida sozinha
 * sem clique humano".
 *
 * Estado atual: STUB registrado. SEFAZ real depende de NfeService completo
 * em Modules/NfeBrasil/ (módulo hoje é só scaffold). Quando NfeService for
 * implementado, este listener orquestra:
 *   1. Resolve produto/serviço da fatura → mapeia pra item NFe (CFOP/NCM/CST)
 *   2. Carrega certificado A1 do business (NfeCertificadoService)
 *   3. nfephp-org/sped-nfe → autoriza SEFAZ
 *   4. Renderiza DANFE PDF
 *   5. Envia e-mail pro pagador com DANFE anexado
 *
 * Falha de SEFAZ NÃO derruba o pagamento — retry job em fila `nfe_retry`.
 *
 * Flag de ativação: config('nfebrasil.auto_emission_on_invoice_paid') (default
 * false). Quando true e NfeService implementado, dispara fluxo real.
 *
 * Fila: 'nfe' (separada de rb_webhooks pra não competir).
 *
 * Referências:
 *   - ADR 0089 (Capterra-driven Module Evolution) — diferencial vertical
 *   - CAPTERRA-INVENTARIO RecurringBilling capacidade #6
 *   - SPEC.md US-RB-044
 *   - Event: Modules/RecurringBilling/Events/InvoicePaid.php
 */
class EmitirNFeAoReceberPagamento implements ShouldQueue
{
    public string $queue = 'nfe';
    public int $tries = 3;
    public int $backoff = 60;

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
            Log::info('NFe auto-emission DISABLED — listener registered but no-op', [
                'invoice_ref' => $event->invoiceRef,
                'todo'        => 'Implementar Modules/NfeBrasil/Services/NfeService antes de habilitar flag',
            ]);
            return;
        }

        // TODO US-RB-044 fase 2: emissão real via NfeService.
        // Manter stub até a foundation do NfeBrasil existir.
        // Pseudocódigo do que vai aqui:
        //
        // $nfeService = app(\Modules\NfeBrasil\Services\NfeService::class);
        // $invoice    = \Modules\RecurringBilling\Models\Invoice::where('business_id', $event->businessId)
        //                   ->where('numero_documento', $event->invoiceRef)
        //                   ->firstOrFail();
        // $nfe = $nfeService->emitirParaInvoice($invoice);
        // event(new \Modules\NfeBrasil\Events\NFeAutorizada($nfe));

        throw new \LogicException(
            'NfeService não implementado. Habilitar auto_emission_on_invoice_paid ' .
            'requer Modules/NfeBrasil com NfeService + NfeCertificadoService funcionais.'
        );
    }

    public function failed(InvoicePaid $event, \Throwable $e): void
    {
        Log::error('NFe emission failed após retries', [
            'invoice_ref' => $event->invoiceRef,
            'business_id' => $event->businessId,
            'error'       => $e->getMessage(),
        ]);

        // TODO: notificar admin do business via mcp_inbox_notifications
    }
}
