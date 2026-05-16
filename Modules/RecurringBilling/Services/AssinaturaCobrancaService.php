<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services;

use Illuminate\Support\Facades\DB;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Services\Boleto\BoletoService;

/**
 * Service thin de orquestração de cobrança de assinatura (US-RB-042 extraído).
 *
 * Extrai lógica de cancelamento de invoice antes inline no InvoiceController.
 * SoC brutal (Constituição v2 princípio §5) — controller fica thin HTTP-only;
 * service orquestra gateway + persistência + audit.
 *
 * Multi-tenant Tier 0 (ADR 0093): toda operação recebe businessId explícito.
 * Tests biz=1 (ADR 0101): nunca biz=4 (cliente ROTA LIVRE).
 */
class AssinaturaCobrancaService
{
    public function __construct(
        private readonly BoletoService $boletos,
    ) {}

    /**
     * Cancela invoice no gateway + marca status=canceled idempotente.
     *
     * Retorno:
     *   ['ok' => true,  'gateway_call' => bool, 'skipped' => 'already_canceled'?]
     *   ['ok' => false, 'error' => string, 'http_status' => int]
     *
     * Idempotência: se já canceled, retorna ok sem repetir.
     * Guard: invoice paga retorna 422 (use estorno em vez de cancelamento).
     * Fallback: sem gateway_ref, cancela só local.
     */
    public function cancelInvoice(
        int $businessId,
        int $invoiceId,
        string $motivo = 'ACERTOS',
    ): array {
        $invoice = Invoice::where('business_id', $businessId)
            ->whereKey($invoiceId)
            ->firstOrFail();

        if ($invoice->status === 'canceled') {
            return [
                'ok' => true,
                'gateway_call' => false,
                'skipped' => 'already_canceled',
                'invoice' => $invoice,
            ];
        }

        if ($invoice->status === 'paid') {
            return [
                'ok' => false,
                'error' => 'Invoice já paga. Use estorno em vez de cancelamento.',
                'http_status' => 422,
                'invoice' => $invoice,
            ];
        }

        if (! $invoice->gateway || ! $invoice->gateway_ref) {
            // Nunca foi tentada cobrança no gateway — só marca local
            $invoice->update(['status' => 'canceled']);

            return [
                'ok' => true,
                'gateway_call' => false,
                'gateway_used' => null,
                'invoice' => $invoice->refresh(),
            ];
        }

        try {
            DB::transaction(function () use ($invoice, $motivo) {
                $this->boletos->cancelar(
                    $invoice->business_id,
                    $invoice->gateway_ref,
                    $motivo,
                );
                $invoice->update(['status' => 'canceled']);
            });
        } catch (\BadMethodCallException $e) {
            // C6Driver — cancelamento manual obrigatório no portal banco
            return [
                'ok' => false,
                'gateway_call' => false,
                'error' => $e->getMessage(),
                'requires_manual_action' => true,
                'http_status' => 501,
                'invoice' => $invoice,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'gateway' => $invoice->gateway,
                'error' => $e->getMessage(),
                'http_status' => 502,
                'invoice' => $invoice,
            ];
        }

        return [
            'ok' => true,
            'gateway_call' => true,
            'gateway_used' => $invoice->gateway,
            'invoice' => $invoice->refresh(),
        ];
    }
}
