<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\RecurringBilling\Http\Requests\StorePlanRequest;
use Modules\RecurringBilling\Http\Requests\UpdatePlanRequest;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wave 6 — CRUD Inertia de Planos de assinatura.
 *
 * US-RB-001 Cadastrar plano + edição + soft delete protegido.
 *
 * Charter: resources/js/Pages/RecurringBilling/Planos/Index.charter.md
 * Refs: ADR 0093 multi-tenant Tier 0 · ADR 0104 MWART · ADR 0107 visual gate
 *
 * Skill `inertia-defer-default` Tier B: props caras via Inertia::defer.
 * Skill `multi-tenant-patterns` Tier A: business_id sempre da sessão.
 */
class PlanController extends Controller
{
    /**
     * GET /recurring-billing/planos
     *
     * Lista planos do business com:
     *   - assinaturas_count (subquery — defer)
     *   - kpis agregados (defer)
     */
    public function index(Request $request): InertiaResponse
    {
        $this->ensurePermission();
        $businessId = $this->businessId();

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = max(10, min(100, $perPage));
        $search  = trim((string) $request->string('q', ''));

        return Inertia::render('RecurringBilling/Planos/Index', [
            'filters' => [
                'q'        => $search,
                'per_page' => $perPage,
            ],

            // Lista paginada — defer (paginate é caro + soma cross-joins de subscriptions_count).
            'plans' => Inertia::defer(fn () => $this->buildPlansPayload($businessId, $search, $perPage)),

            // KPIs agregados — defer (count separados por status).
            'kpis' => Inertia::defer(fn () => $this->buildKpisPayload($businessId)),
        ]);
    }

    /**
     * GET /recurring-billing/planos/novo
     */
    public function create(): InertiaResponse
    {
        $this->ensurePermission();

        return Inertia::render('RecurringBilling/Planos/Create', [
            'defaults' => [
                'ciclo'       => 'monthly',
                'trial_days'  => 0,
                'ativo'       => true,
                'fiscal_type' => 'none',
            ],
        ]);
    }

    /**
     * POST /recurring-billing/planos
     */
    public function store(StorePlanRequest $request): RedirectResponse
    {
        $this->ensurePermission();
        $businessId = $this->businessId();

        $data = $request->validated();
        $data['business_id'] = $businessId;

        // ciclo_dias só faz sentido quando ciclo=custom — limpa nos outros casos.
        if (($data['ciclo'] ?? null) !== 'custom') {
            $data['ciclo_dias'] = null;
        }

        // Defaults fiscal vazio quando none.
        if (($data['fiscal_type'] ?? 'none') !== 'nfe') {
            $data['fiscal_cfop'] = null;
        }
        if (($data['fiscal_type'] ?? 'none') !== 'nfse') {
            $data['fiscal_servico'] = null;
        }

        $plan = Plan::create($data);

        return redirect()
            ->route('recurring-billing.planos.index')
            ->with('success', "Plano \"{$plan->name}\" criado.");
    }

    /**
     * GET /recurring-billing/planos/{id}/editar
     */
    public function edit(int $id): InertiaResponse
    {
        $this->ensurePermission();
        $businessId = $this->businessId();

        // HasBusinessScope automático em Plan — esta query JÁ scopa por session('user.business_id'),
        // mas explicitamos pra deixar audível (skill multi-tenant-patterns).
        $plan = Plan::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        return Inertia::render('RecurringBilling/Planos/Edit', [
            'plan' => [
                'id'              => $plan->id,
                'name'            => $plan->name,
                'slug'            => $plan->slug,
                'description'     => $plan->description,
                'descricao_curta' => $plan->descricao_curta,
                'valor'           => (float) $plan->valor,
                'ciclo'           => $plan->ciclo,
                'ciclo_dias'      => $plan->ciclo_dias,
                'trial_days'      => $plan->trial_days,
                'ativo'           => (bool) $plan->ativo,
                'fiscal_type'     => $plan->fiscal_type ?? 'none',
                'fiscal_cfop'     => $plan->fiscal_cfop,
                'fiscal_servico'  => $plan->fiscal_servico,
            ],
        ]);
    }

    /**
     * PUT /recurring-billing/planos/{id}
     */
    public function update(UpdatePlanRequest $request, int $id): RedirectResponse
    {
        $this->ensurePermission();
        $businessId = $this->businessId();

        $plan = Plan::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $data = $request->validated();

        if (($data['ciclo'] ?? null) !== 'custom') {
            $data['ciclo_dias'] = null;
        }
        if (($data['fiscal_type'] ?? 'none') !== 'nfe') {
            $data['fiscal_cfop'] = null;
        }
        if (($data['fiscal_type'] ?? 'none') !== 'nfse') {
            $data['fiscal_servico'] = null;
        }

        $plan->update($data);

        return redirect()
            ->route('recurring-billing.planos.index')
            ->with('success', "Plano \"{$plan->name}\" atualizado.");
    }

    /**
     * DELETE /recurring-billing/planos/{id}
     *
     * Soft delete protegido — planos com Subscription ativa NÃO podem ser deletados
     * (status active/trialing/past_due conforme Subscription::scopeAtivas).
     * Retorna 422 com mensagem clara nesse caso.
     */
    public function destroy(int $id): RedirectResponse|Response
    {
        $this->ensurePermission();
        $businessId = $this->businessId();

        $plan = Plan::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $ativasCount = Subscription::query()
            ->where('business_id', $businessId)
            ->where('plan_id', $plan->id)
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->count();

        if ($ativasCount > 0) {
            return redirect()
                ->route('recurring-billing.planos.index')
                ->with('error', "Plano \"{$plan->name}\" possui {$ativasCount} assinatura(s) ativa(s) — cancele ou migre antes de excluir.")
                ->setStatusCode(422);
        }

        $name = $plan->name;
        $plan->delete(); // soft delete (Plan usa SoftDeletes trait)

        return redirect()
            ->route('recurring-billing.planos.index')
            ->with('success', "Plano \"{$name}\" excluído.");
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /**
     * business_id da sessão (Tier 0 IRREVOGÁVEL — ADR 0093).
     */
    private function businessId(): int
    {
        return (int) session('user.business_id');
    }

    /**
     * Gate de permissão — superadmin OU recurringbilling.access.
     *
     * Aborta 403 se nenhum dos dois — skill multi-tenant-patterns.
     */
    protected function ensurePermission(): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403, 'Não autenticado.');
        }

        $can = false;
        if (method_exists($user, 'can')) {
            $can = $user->can('superadmin') || $user->can('recurringbilling.access');
        }

        if (! $can) {
            abort(403, 'Sem permissão pra acessar planos de cobrança recorrente.');
        }
    }

    /**
     * Payload paginado pra Page Planos/Index — busca por nome/slug, count assinaturas
     * via subquery (mais barato que loadCount + escapa N+1).
     */
    private function buildPlansPayload(int $businessId, string $search, int $perPage): array
    {
        $assinaturasCountSub = Subscription::query()
            ->selectRaw('count(*)')
            ->whereColumn('rb_subscriptions.plan_id', 'rb_plans.id')
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->whereNull('deleted_at');

        $query = Plan::query()
            ->where('rb_plans.business_id', $businessId)
            ->select('rb_plans.*')
            ->selectSub($assinaturasCountSub, 'assinaturas_ativas_count');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('rb_plans.name', 'like', "%{$search}%")
                  ->orWhere('rb_plans.slug', 'like', "%{$search}%");
            });
        }

        $paginator = $query
            ->orderBy('rb_plans.ativo', 'desc')
            ->orderBy('rb_plans.valor', 'desc')
            ->orderBy('rb_plans.name')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'data' => collect($paginator->items())->map(fn (Plan $p) => [
                'id'                       => $p->id,
                'name'                     => $p->name,
                'slug'                     => $p->slug,
                'descricao_curta'          => $p->descricao_curta,
                'valor'                    => (float) $p->valor,
                'ciclo'                    => $p->ciclo,
                'ciclo_label'              => $this->cicloLabel($p->ciclo),
                'ciclo_dias'               => $p->ciclo_dias,
                'trial_days'               => (int) $p->trial_days,
                'ativo'                    => (bool) $p->ativo,
                'fiscal_type'              => $p->fiscal_type ?? 'none',
                'assinaturas_ativas_count' => (int) ($p->assinaturas_ativas_count ?? 0),
                'created_at'               => optional($p->created_at)->toIso8601String(),
            ])->values()->toArray(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ];
    }

    /**
     * KPIs agregados do business — total planos, ativos, MRR potencial,
     * distribuição de ciclos.
     */
    private function buildKpisPayload(int $businessId): array
    {
        $base = Plan::query()->where('business_id', $businessId);

        $total       = (clone $base)->count();
        $ativos      = (clone $base)->where('ativo', true)->count();

        // MRR potencial = soma de (valor convertido pra mensal × assinaturas ativas).
        // Converte cada ciclo pra equivalente mensal pra somar consistente.
        $planosAtivos = (clone $base)
            ->where('ativo', true)
            ->select('id', 'valor', 'ciclo', 'ciclo_dias')
            ->get();

        $mrrPotencial = 0.0;
        foreach ($planosAtivos as $p) {
            $assinaturas = Subscription::query()
                ->where('business_id', $businessId)
                ->where('plan_id', $p->id)
                ->whereIn('status', ['active', 'trialing', 'past_due'])
                ->count();

            $mensal = $this->valorMensalEquivalente((float) $p->valor, (string) $p->ciclo, (int) ($p->ciclo_dias ?? 30));
            $mrrPotencial += $mensal * $assinaturas;
        }

        // Distribuição de ciclos (só ativos pra ser sinal útil).
        $distribuicao = (clone $base)
            ->where('ativo', true)
            ->select('ciclo', DB::raw('count(*) as total'))
            ->groupBy('ciclo')
            ->pluck('total', 'ciclo')
            ->toArray();

        return [
            'total_planos'    => $total,
            'total_ativos'    => $ativos,
            'mrr_potencial'   => round($mrrPotencial, 2),
            'distribuicao'    => [
                'monthly'    => (int) ($distribuicao['monthly']    ?? 0),
                'quarterly'  => (int) ($distribuicao['quarterly']  ?? 0),
                'semiannual' => (int) ($distribuicao['semiannual'] ?? 0),
                'yearly'     => (int) ($distribuicao['yearly']     ?? 0),
                'custom'     => (int) ($distribuicao['custom']     ?? 0),
            ],
        ];
    }

    private function cicloLabel(string $ciclo): string
    {
        return match ($ciclo) {
            'monthly'    => 'mensal',
            'quarterly'  => 'trimestral',
            'semiannual' => 'semestral',
            'yearly'     => 'anual',
            'custom'     => 'customizado',
            default      => $ciclo,
        };
    }

    private function valorMensalEquivalente(float $valor, string $ciclo, int $cicloDias): float
    {
        return match ($ciclo) {
            'monthly'    => $valor,
            'quarterly'  => $valor / 3,
            'semiannual' => $valor / 6,
            'yearly'     => $valor / 12,
            'custom'     => $cicloDias > 0 ? ($valor * 30 / $cicloDias) : $valor,
            default      => $valor,
        };
    }
}
