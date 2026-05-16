<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Governance\Http\Requests\TogglePolicyRequest;
use Modules\Governance\Services\PolicyToggleService;

/**
 * CRUD inline de policies (mcp_governance_rules) — Constituição Art. 8.
 *
 * Permite Wagner editar rules no painel /governance/policies sem precisar
 * tocar em código. Toda mudança vira INSERT em mcp_governance_rule_history
 * (a criar — Fase 5+1) pra audit.
 *
 * MVP: list + toggle enabled. Edit inline + create new ficam pra próxima
 * iteração quando frontend ganhar editor JSON.
 *
 * Refator Wave H (#947): list + group + toggle DB extraídos pra PolicyToggleService —
 * Controller só responde HTTP + delega Service + render Inertia (mesma response shape).
 */
class PoliciesController extends Controller
{
    public function __construct(private readonly PolicyToggleService $service)
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        // Inertia::defer pra props com query DB (skill inertia-defer-default).
        return Inertia::render('governance/Policies', [
            'rules_by_category' => Inertia::defer(fn () => $this->buildRulesByCategoryPayload()),
            'kpis'              => Inertia::defer(fn () => $this->buildKpisPayload()),
        ]);
    }

    private function buildRulesByCategoryPayload(): mixed
    {
        $rules = $this->service->listPolicies();

        return $this->service->groupByCategory($rules);
    }

    private function buildKpisPayload(): mixed
    {
        $rules = $this->service->listPolicies();
        $byCategory = $this->service->groupByCategory($rules);

        return $this->service->kpisFor($rules, $byCategory);
    }

    public function toggle(TogglePolicyRequest $request, int $id): RedirectResponse
    {
        // FormRequest valida enabled boolean (Wave S Batch 2 D8.c).
        $enabled = (bool) $request->validated('enabled', false);
        $this->service->togglePolicy($id, $enabled);

        return back()->with('status', "Policy #{$id} " . ($enabled ? 'ativada' : 'desativada'));
    }
}
