<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Services\AssinaturaCobrancaService;
use Spatie\Activitylog\Facades\Activity;

/**
 * Controller thin de operações sobre rb_invoices (foundation US-RB-043).
 *
 * US-RB-042: endpoint cancel() delega pra AssinaturaCobrancaService (SoC brutal)
 * + audit via Spatie Activity Log + permissão `recurringbilling.invoice.cancel`.
 *
 * Wave J 2026-05-16: lógica de gateway/transaction movida pro Service —
 * controller fica HTTP-only (auth/session/audit/response shape).
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly AssinaturaCobrancaService $cobrancas,
    ) {}

    /**
     * POST /financeiro/rb-invoices/{invoice}/cancelar
     *
     * Cancela invoice no gateway e marca status=canceled.
     * Idempotente — se já canceled, retorna 200 sem repetir cancelamento.
     */
    public function cancel(Request $request, int $invoiceId): JsonResponse
    {
        if (! auth()->user()?->can('recurringbilling.invoice.cancel')) {
            return response()->json([
                'ok' => false,
                'error' => 'Sem permissão recurringbilling.invoice.cancel',
            ], 403);
        }

        $businessId = (int) $request->session()->get('business.id');
        $motivo = trim((string) $request->input('motivo', 'ACERTOS'));

        $result = $this->cobrancas->cancelInvoice($businessId, $invoiceId, $motivo);

        // Audit always (success or failure) — usa invoice retornado pelo Service
        if (isset($result['invoice']) && $result['invoice'] instanceof Invoice) {
            $this->logActivity(
                $result['invoice'],
                $request,
                $result['gateway_used'] ?? ($result['gateway'] ?? null),
                $motivo,
                $result['error'] ?? null,
            );
        }

        if ($result['ok']) {
            return response()->json(array_filter([
                'ok' => true,
                'gateway_call' => $result['gateway_call'] ?? false,
                'skipped' => $result['skipped'] ?? null,
            ], fn ($v) => $v !== null));
        }

        return response()->json(array_filter([
            'ok' => false,
            'gateway_call' => $result['gateway_call'] ?? false,
            'gateway' => $result['gateway'] ?? null,
            'error' => $result['error'] ?? null,
            'requires_manual_action' => $result['requires_manual_action'] ?? null,
        ], fn ($v) => $v !== null), $result['http_status'] ?? 500);
    }

    private function logActivity(
        Invoice $invoice,
        Request $request,
        ?string $gateway,
        ?string $motivo,
        ?string $error = null,
    ): void {
        $properties = [
            'invoice_id'       => $invoice->id,
            'numero_documento' => $invoice->numero_documento,
            'gateway'          => $gateway,
            'gateway_ref'      => $invoice->gateway_ref,
            'motivo'           => $motivo,
            'business_id'      => $invoice->business_id,
        ];
        if ($error) {
            $properties['error'] = $error;
        }

        activity('recurringbilling.invoice')
            ->performedOn($invoice)
            ->causedBy($request->user())
            ->withProperties($properties)
            ->log($error ? 'invoice.cancel.failed' : 'invoice.canceled');
    }
}
