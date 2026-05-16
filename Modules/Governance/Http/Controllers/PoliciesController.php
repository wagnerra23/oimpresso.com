<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
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
        $rules = $this->service->listPolicies();
        $byCategory = $this->service->groupByCategory($rules);
        $kpis = $this->service->kpisFor($rules, $byCategory);

        return Inertia::render('governance/Policies', [
            'rules_by_category' => $byCategory,
            'kpis'              => $kpis,
        ]);
    }

    public function toggle(Request $request, int $id): RedirectResponse
    {
        $enabled = (bool) $request->input('enabled', false);
        $this->service->togglePolicy($id, $enabled);

        return back()->with('status', "Policy #{$id} " . ($enabled ? 'ativada' : 'desativada'));
    }
}
