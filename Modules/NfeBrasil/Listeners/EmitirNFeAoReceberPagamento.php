<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Services\NfeService;
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
            Log::info('NFe auto-emission DISABLED — flag nfebrasil.auto_emission_on_invoice_paid=false', [
                'invoice_ref' => $event->invoiceRef,
            ]);
            return;
        }

        // US-RB-044 fase 2 — emissão real via NfeService.
        // Invoice → $dadosNfe montado pelo InvoiceNfeDadosBuilder (US-NFE-043 Motor Tributário).
        // Por ora usa dados mínimos de serviço (NFC-e B2C equivalente fiscal).
        $invoice = \Modules\RecurringBilling\Models\Invoice::where('business_id', $event->businessId)
            ->where('numero_documento', $event->invoiceRef)
            ->first();

        if (! $invoice) {
            Log::warning('NfeService: invoice não encontrada para emissão automática', [
                'invoice_ref' => $event->invoiceRef,
                'business_id' => $event->businessId,
            ]);
            return;
        }

        $nfeService = app(NfeService::class);

        $dadosNfe = [
            'transaction_id' => null,
            'nat_op'         => 'Prestação de serviço',
            'dest'           => [
                'nome'        => $invoice->contact?->name ?? 'CONSUMIDOR FINAL',
                'cpf'         => preg_replace('/\D/', '', (string) ($invoice->contact?->cpf_cnpj ?? '00000000000')),
                'ind_ie_dest' => '9',
                'logradouro'  => 'NAO INFORMADO',
                'numero'      => 'SN',
                'bairro'      => 'NAO INFORMADO',
                'municipio'   => 'NAO INFORMADO',
                'cod_municipio' => '9999999',
                'uf'          => 'SP',
                'cep'         => '00000000',
            ],
            'dets' => [[
                'cprod'  => 'ASSINATURA',
                'xprod'  => 'Assinatura mensal — ' . $event->invoiceRef,
                'ncm'    => '00000000',
                'cfop'   => '5933',
                'ucm'    => 'UN',
                'qcom'   => 1.0,
                'vuncom' => (float) $event->valor,
                'vprod'  => (float) $event->valor,
                'utrib'  => 'UN',
                'qtrib'  => 1.0,
                'vuntrib' => (float) $event->valor,
                'ind_tot' => 1,
                'icms'   => ['cst_csosn' => '102', 'orig' => 0],
                'pis'    => ['cst' => '07', 'vbc' => 0, 'ppis' => 0, 'vpis' => 0],
                'cofins' => ['cst' => '07', 'vbc' => 0, 'pcofins' => 0, 'vcofins' => 0],
            ]],
            'total' => [
                'v_prod'    => (float) $event->valor,
                'v_bc_icms' => 0,
                'v_icms'    => 0,
                'v_pis'     => 0,
                'v_cofins'  => 0,
                'v_nf'      => (float) $event->valor,
                'v_desc'    => 0,
                'v_frete'   => 0,
            ],
            'pag'         => [['tpag' => '99', 'vpag' => (float) $event->valor]],
            'valor_total' => (float) $event->valor,
            'inf_cpl'     => 'Fatura: ' . $event->invoiceRef,
        ];

        $nfe = $nfeService->emitir($event->businessId, $dadosNfe);

        Log::info('NfeService: emissão automática concluída', [
            'business_id' => $event->businessId,
            'invoice_ref' => $event->invoiceRef,
            'status'      => $nfe->status,
            'emissao_id'  => $nfe->id,
        ]);
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
