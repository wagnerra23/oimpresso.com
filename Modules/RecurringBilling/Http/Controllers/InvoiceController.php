<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Repositories\InvoiceRepository;
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
        private readonly InvoiceRepository $invoices,
    ) {}

    /**
     * GET /recurring-billing/faturas — Page Inertia Faturas (Onda 7 v9,75).
     *
     * Reusa charter Cowork pattern de RecurringBilling/Index.tsx (4 KPIs · filter bar ·
     * tabela). Cancelar via POST /financeiro/rb-invoices/{invoice}/cancelar (existente).
     *
     * Multi-tenant Tier 0: session('user.business_id') + HasBusinessScope auto em Invoice.
     * Inertia::defer em kpis + invoices (props caras — agregação + paginate eager).
     *
     * @see resources/js/Pages/RecurringBilling/Faturas/Index.tsx
     * @see resources/js/Pages/RecurringBilling/Faturas/Index.charter.md
     */
    public function index(Request $request): InertiaResponse
    {
        $businessId = (int) session('user.business_id');

        $filters = [
            'status'  => $request->string('status', 'all')->toString(),
            'gateway' => $request->string('gateway', 'all')->toString(),
            'periodo' => $request->string('periodo', 'all')->toString(),
            'busca'   => $request->string('q', '')->toString(),
        ];

        return Inertia::render('RecurringBilling/Faturas/Index', [
            'filters' => $filters,

            'kpis' => Inertia::defer(function () use ($businessId) {
                return $this->invoices->kpisForIndex($businessId);
            }),

            'invoices' => Inertia::defer(function () use ($businessId, $filters) {
                $paginator = $this->invoices->paginatedForIndex($businessId, $filters, 50);
                $hoje = Carbon::now()->startOfDay();

                return [
                    'data' => collect($paginator->items())->map(function (Invoice $inv) use ($hoje) {
                        $venc = $inv->vencimento ? Carbon::parse($inv->vencimento)->startOfDay() : null;
                        $diasDeltaVenc = $venc ? $hoje->diffInDays($venc, false) : null;
                        $isOverdue = $inv->status === 'overdue'
                            || ($inv->status === 'open' && $venc && $venc->lt($hoje));

                        return [
                            'id'                 => $inv->id,
                            'numero_documento'   => $inv->numero_documento,
                            'cliente_nome'       => $inv->contact?->name ?? '—',
                            'cliente_cnpj'       => $inv->contact?->tax_number,
                            'subscription_id'    => $inv->subscription_id,
                            'plano_nome'         => $inv->subscription?->plan?->name,
                            'valor'              => (float) $inv->valor,
                            'vencimento'         => $inv->vencimento?->toDateString(),
                            'dias_delta_venc'    => $diasDeltaVenc,
                            'is_overdue'         => $isOverdue,
                            'pago_em'            => $inv->pago_em?->toDateTimeString(),
                            'status'             => $isOverdue && $inv->status === 'open'
                                ? 'overdue' // derivado
                                : $inv->status,
                            'gateway'            => $inv->gateway,
                            'gateway_ref'        => $inv->gateway_ref,
                            'is_cancelavel'      => in_array($inv->status, ['open', 'overdue'], true)
                                || ($inv->status === 'open' && $isOverdue),
                        ];
                    })->values()->toArray(),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page'    => $paginator->lastPage(),
                        'per_page'     => $paginator->perPage(),
                        'total'        => $paginator->total(),
                    ],
                ];
            }),
        ]);
    }

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
