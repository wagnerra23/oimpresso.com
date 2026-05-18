<?php

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\RecurringBilling\Http\Presenters\SubscriptionIndexPresenter;
use Modules\RecurringBilling\Http\Requests\CancelSubscriptionRequest;
use Modules\RecurringBilling\Http\Requests\PauseSubscriptionRequest;
use Modules\RecurringBilling\Http\Requests\StoreAssinaturaRequest;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Models\SubscriptionEvent;
use Modules\RecurringBilling\Repositories\SubscriptionRepository;

/**
 * Controller principal — Page Cobrança Recorrente (Inertia React).
 *
 * Refs: charter resources/js/Pages/RecurringBilling/Index.charter.md
 * Visual canon: prototipo-ui/prototipos/recurring/recurring-page.jsx
 * Skill `inertia-defer-default` Tier B: props caras via Inertia::defer.
 *
 * Onda 3 v9,75: store/cancel/pause/resume + SubscriptionPolicy + AuditLog
 * via LogsActivity (Subscription Model já tem trait).
 */
class RecurringBillingController extends Controller
{
    public function __construct(private readonly SubscriptionRepository $subscriptions)
    {
    }

    /**
     * GET /recurring-billing — Page Inertia 3-col Cowork.
     */
    public function index(Request $request): InertiaResponse
    {
        $businessId = (int) session('user.business_id');
        $filters = [
            'status_visual' => $request->string('status', 'all')->toString(),
            'when'          => $request->string('when', 'any')->toString(),
            'busca'         => $request->string('q', '')->toString(),
        ];
        $tab = $request->string('tab', 'assinaturas')->toString();

        return Inertia::render('RecurringBilling/Index', [
            'filters' => $filters,
            'tab'     => $tab,

            'kpis' => Inertia::defer(function () use ($businessId) {
                return SubscriptionIndexPresenter::computeKpis(
                    $this->subscriptions->allForKpis($businessId)
                );
            }),

            'subscriptions' => Inertia::defer(function () use ($businessId, $filters) {
                $paginator = $this->subscriptions->paginatedForIndex($businessId, $filters, 50);

                return [
                    'data' => collect($paginator->items())
                        ->map(fn ($sub) => SubscriptionIndexPresenter::toDrawerPayload($sub))
                        ->values()
                        ->toArray(),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page'    => $paginator->lastPage(),
                        'per_page'     => $paginator->perPage(),
                        'total'        => $paginator->total(),
                    ],
                ];
            }),

            'plans' => Inertia::defer(function () use ($businessId) {
                return Plan::query()
                    ->where('business_id', $businessId)
                    ->where('ativo', true)
                    ->orderBy('valor', 'desc')
                    ->get(['id', 'name', 'valor', 'ciclo', 'descricao_curta', 'fiscal_type'])
                    ->map(fn ($p) => [
                        'id'          => $p->id,
                        'name'        => $p->name,
                        'cycle'       => $p->ciclo,
                        'cycle_label' => match ($p->ciclo) {
                            'monthly'    => 'mensal',
                            'quarterly'  => 'trimestral',
                            'semiannual' => 'semestral',
                            'yearly'     => 'anual',
                            default      => $p->ciclo,
                        },
                        'price'       => (float) $p->valor,
                        'items'       => $p->descricao_curta,
                        'fiscal_type' => $p->fiscal_type,
                    ])
                    ->toArray();
            }),
        ]);
    }

    /**
     * POST /recurring-billing — cria Subscription (Onda 3 v9,75).
     *
     * Multi-tenant Tier 0: business_id da sessão SEMPRE, NUNCA do request.
     * AuditLog via LogsActivity automático.
     */
    public function store(StoreAssinaturaRequest $request): RedirectResponse
    {
        Gate::authorize('create', Subscription::class);

        $businessId = (int) session('user.business_id');
        $data = $request->validated();

        $plan = Plan::query()
            ->where('business_id', $businessId)
            ->whereKey($this->resolvePlanIdFromCiclo($businessId, $data))
            ->first();

        $sub = Subscription::create([
            'business_id'         => $businessId,
            'plan_id'             => $plan?->id,
            'contact_id'          => (int) $data['contact_id'],
            'status'              => 'active',
            'start_date'          => Carbon::today()->toDateString(),
            'next_due_date'       => $data['data_proxima_cobranca'],
            'billing_anchor_date' => $data['data_proxima_cobranca'],
            'payment_method'      => $this->mapFormaPagamento($data['forma_pagamento'] ?? 'boleto'),
            'metadata'            => [
                'valor'       => $data['valor'],
                'ciclo'       => $data['ciclo'],
                'gateway'     => $data['gateway'] ?? 'inter',
                'descricao'   => $data['descricao'] ?? null,
                'created_via' => 'recurring-billing.store',
            ],
        ]);

        return redirect()
            ->route('recurring-billing.index')
            ->with('success', sprintf('Assinatura #%d criada.', $sub->id));
    }

    /**
     * POST /recurring-billing/{id}/cancelar — cancela contrato (US-RB-005).
     *
     * Cancelamento imediato: status=canceled + canceled_at=now + churn_reason.
     * AuditLog automático via LogsActivity.
     */
    public function cancelar(CancelSubscriptionRequest $request, int $id): RedirectResponse|JsonResponse
    {
        $sub = $this->loadOwnedOrFail($id);
        Gate::authorize('cancel', $sub);

        if ($sub->status === 'canceled') {
            return $this->respondError($request, 'Assinatura já está cancelada.', 422);
        }

        $sub->update([
            'status'       => 'canceled',
            'canceled_at'  => now(),
            'churn_reason' => $request->string('churn_reason')->toString(),
        ]);

        $msg = sprintf('Assinatura #%d cancelada (%s).', $sub->id, $sub->churn_reason);

        return $this->respondOk($request, $msg, ['subscription' => $sub->fresh()]);
    }

    /**
     * POST /recurring-billing/{id}/pausar — pausa assinatura.
     *
     * Status=paused + paused_at=now + paused_until opcional. AuditLog automático.
     */
    public function pausar(PauseSubscriptionRequest $request, int $id): RedirectResponse|JsonResponse
    {
        $sub = $this->loadOwnedOrFail($id);
        Gate::authorize('pause', $sub);

        $sub->update([
            'status'       => 'paused',
            'paused_at'    => now(),
            'paused_until' => $request->date('paused_until'),
        ]);

        $msg = sprintf('Assinatura #%d pausada%s.',
            $sub->id,
            $sub->paused_until ? ' até '.$sub->paused_until->format('d/m/Y') : ' indefinidamente'
        );

        return $this->respondOk($request, $msg, ['subscription' => $sub->fresh()]);
    }

    /**
     * POST /recurring-billing/{id}/reativar — reativa subscription pausada.
     */
    public function reativar(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $sub = $this->loadOwnedOrFail($id);
        Gate::authorize('resume', $sub);

        $sub->update([
            'status'       => 'active',
            'paused_at'    => null,
            'paused_until' => null,
        ]);

        return $this->respondOk($request, sprintf('Assinatura #%d reativada.', $sub->id), ['subscription' => $sub->fresh()]);
    }

    /**
     * POST /recurring-billing/{id}/reenviar-nfe — Onda 20 wire.
     *
     * Reenvia DANFE da última NFe autorizada vinculada à última invoice paga
     * desta subscription. Delega 100% pra endpoint canônico NfeBrasil
     * `nfe-brasil.emissoes.reenviar-email` (NfeEmissaoController::reenviarEmail).
     *
     * Cross-module guard: se NfeBrasil module não responder ou não houver
     * NFe vinculada, retorna 503 com mensagem clara (fallback Constituição
     * v2 §8 Confiabilidade com fallback).
     *
     * Cadeia: Subscription → última paid Invoice → nfe_emissoes (lookup por
     * (business_id, contact_id) ordenado por emitido_em DESC quando metadata
     * não tiver `nfe_emissao_id` explícito — gateway listener US-RB-044
     * popula esse hint).
     */
    public function reenviarNfe(Request $request, int $id): JsonResponse
    {
        $sub = $this->loadOwnedOrFail($id);

        // 1. Acha última invoice paga (timeline business-correta).
        $lastPaid = Invoice::query()
            ->where('business_id', $sub->business_id)
            ->where('subscription_id', $sub->id)
            ->where('status', 'paid')
            ->orderByDesc('pago_em')
            ->orderByDesc('id')
            ->first();

        if ($lastPaid === null) {
            return response()->json([
                'ok'      => false,
                'error'   => 'sem_invoice_paga',
                'message' => 'Nenhuma fatura paga encontrada pra reenviar NFe.',
            ], 422);
        }

        // 2. Acha NFe emissão associada — hint em metadata OU lookup heurístico.
        $emissaoId = $this->resolveNfeEmissaoId($sub, $lastPaid);

        if ($emissaoId === null) {
            return response()->json([
                'ok'      => false,
                'error'   => 'sem_nfe_emitida',
                'message' => 'Última fatura paga não tem NFe emitida vinculada.',
            ], 422);
        }

        // 3. Delega pra endpoint canônico NfeBrasil (in-process call evita HTTP).
        $controllerFqcn = '\Modules\NfeBrasil\Http\Controllers\NfeEmissaoController';
        if (! class_exists($controllerFqcn)) {
            return response()->json([
                'ok'      => false,
                'error'   => 'nfe_module_indisponivel',
                'message' => 'Módulo NfeBrasil não está disponível neste ambiente.',
            ], 503);
        }

        try {
            /** @var \Illuminate\Http\JsonResponse $response */
            $response = app($controllerFqcn)->reenviarEmail($request, $emissaoId);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'error'   => 'nfe_reenvio_falhou',
                'message' => 'Falha ao reenviar DANFE: '.$e->getMessage(),
            ], 503);
        }

        // 4. Append-only event na timeline (Onda 16 integração).
        SubscriptionEvent::create([
            'business_id'     => $sub->business_id,
            'subscription_id' => $sub->id,
            'kind'            => SubscriptionEvent::KIND_NF,
            'by_actor'        => optional(auth()->user())->first_name ?? 'sistema',
            'body'            => sprintf('DANFE da NFe #%d reenviada por email.', $emissaoId),
            'occurred_at'     => now(),
        ]);

        return response()->json([
            'ok'         => true,
            'message'    => 'DANFE reenviada por email.',
            'emissao_id' => $emissaoId,
            'invoice_id' => $lastPaid->id,
            'upstream'   => $response->getData(true),
        ]);
    }

    public function create()
    {
        return view('recurringbilling::create');
    }

    public function show($id)
    {
        return view('recurringbilling::show');
    }

    public function edit($id)
    {
        return view('recurringbilling::edit');
    }

    public function update(Request $request, $id): RedirectResponse
    {
        //
    }

    public function destroy($id)
    {
        //
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    /**
     * Carrega Subscription scopada por business_id + 404 se não pertence.
     */
    private function loadOwnedOrFail(int $id): Subscription
    {
        $businessId = (int) session('user.business_id');
        $sub = $this->subscriptions->acharPorId($businessId, $id);

        abort_if($sub === null, 404, 'Assinatura não encontrada.');

        return $sub;
    }

    /**
     * Resposta unificada XHR (Inertia partial / fetch) OR redirect legado.
     */
    private function respondOk(Request $request, string $message, array $payload = []): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson() || $request->header('X-Inertia')) {
            return response()->json(['ok' => true, 'message' => $message] + $payload);
        }

        return redirect()->back()->with('success', $message);
    }

    private function respondError(Request $request, string $message, int $status): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson() || $request->header('X-Inertia')) {
            return response()->json(['ok' => false, 'message' => $message], $status);
        }

        return redirect()->back()->withErrors(['_error' => $message]);
    }

    /**
     * Resolve nfe_emissoes.id pra uma invoice paga.
     *
     * Estratégia (ordem):
     *  1. Hint explícito em `invoice.metadata.nfe_emissao_id` (gateway listener US-RB-044).
     *  2. Heurística: última nfe_emissoes status=autorizada do (business_id) — limitada
     *     a janela pago_em±7d quando disponível.
     *
     * Retorna null se NfeBrasil module ausente ou nada bater.
     */
    private function resolveNfeEmissaoId(Subscription $sub, Invoice $invoice): ?int
    {
        $meta = is_array($invoice->metadata) ? $invoice->metadata : [];
        if (! empty($meta['nfe_emissao_id'])) {
            return (int) $meta['nfe_emissao_id'];
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('nfe_emissoes')) {
            return null;
        }

        $query = \Illuminate\Support\Facades\DB::table('nfe_emissoes')
            ->where('business_id', $sub->business_id)
            ->where('status', 'autorizada')
            ->whereNull('deleted_at');

        if ($invoice->pago_em) {
            $query->whereBetween('emitido_em', [
                $invoice->pago_em->copy()->subDays(7),
                $invoice->pago_em->copy()->addDays(7),
            ]);
        }

        return (int) ($query->orderByDesc('emitido_em')->value('id') ?? 0) ?: null;
    }

    /**
     * Mapeia forma_pagamento PT (StoreAssinaturaRequest) → enum payment_method (DB).
     */
    private function mapFormaPagamento(string $forma): ?string
    {
        return match ($forma) {
            'cartao' => 'card',
            'pix'    => 'pix',
            'boleto' => 'boleto',
            default  => null,
        };
    }

    /**
     * Heurística pra resolver plan_id se request não tiver — busca primeiro plano
     * ativo cujo ciclo + valor batem. Se nada bater, retorna null (Subscription
     * aceita plan_id nullable em testes; em prod o front sempre manda plan_id).
     */
    private function resolvePlanIdFromCiclo(int $businessId, array $data): ?int
    {
        if (! empty($data['plan_id'])) {
            return (int) $data['plan_id'];
        }

        $cicloMap = [
            'mensal'     => 'monthly',
            'trimestral' => 'quarterly',
            'semestral'  => 'semiannual',
            'anual'      => 'yearly',
        ];
        $cicloDb = $cicloMap[$data['ciclo']] ?? null;
        if ($cicloDb === null) {
            return null;
        }

        return Plan::query()
            ->where('business_id', $businessId)
            ->where('ciclo', $cicloDb)
            ->where('valor', $data['valor'])
            ->value('id');
    }
}
