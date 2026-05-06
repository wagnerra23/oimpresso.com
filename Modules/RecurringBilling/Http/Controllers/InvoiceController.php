<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Services\Boleto\BoletoService;
use Spatie\Activitylog\Facades\Activity;

/**
 * Controller de operações sobre rb_invoices (foundation US-RB-043).
 *
 * US-RB-042: endpoint cancel() chama BoletoService → driver do gateway
 * + audit via Spatie Activity Log + permissão `recurringbilling.invoice.cancel`.
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly BoletoService $boletos) {}

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

        $businessId = $request->session()->get('business.id');

        $invoice = Invoice::where('business_id', $businessId)
            ->whereKey($invoiceId)
            ->firstOrFail();

        if ($invoice->status === 'canceled') {
            return response()->json(['ok' => true, 'skipped' => 'already_canceled']);
        }

        if ($invoice->status === 'paid') {
            return response()->json([
                'ok' => false,
                'error' => 'Invoice já paga. Use estorno em vez de cancelamento.',
            ], 422);
        }

        if (! $invoice->gateway || ! $invoice->gateway_ref) {
            // Nunca foi tentada cobrança no gateway — só marca local
            $invoice->update(['status' => 'canceled']);
            $this->logActivity($invoice, $request, 'local-only', null);

            return response()->json(['ok' => true, 'gateway_call' => false]);
        }

        $motivo = trim((string) $request->input('motivo', 'ACERTOS'));

        try {
            DB::transaction(function () use ($invoice, $motivo, $request) {
                $this->boletos->cancelar(
                    $invoice->business_id,
                    $invoice->gateway_ref,
                    $motivo,
                );
                $invoice->update(['status' => 'canceled']);
                $this->logActivity($invoice, $request, $invoice->gateway, $motivo);
            });
        } catch (\BadMethodCallException $e) {
            // C6Driver — cancelamento manual obrigatório
            return response()->json([
                'ok' => false,
                'gateway_call' => false,
                'error' => $e->getMessage(),
                'requires_manual_action' => true,
            ], 501);
        } catch (\Throwable $e) {
            $this->logActivity($invoice, $request, $invoice->gateway, $motivo, $e->getMessage());
            return response()->json([
                'ok' => false,
                'gateway' => $invoice->gateway,
                'error' => $e->getMessage(),
            ], 502);
        }

        return response()->json(['ok' => true, 'gateway_call' => true]);
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
