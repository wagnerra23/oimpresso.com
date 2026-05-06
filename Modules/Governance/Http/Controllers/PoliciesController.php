<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD inline de policies (mcp_governance_rules) — Constituição Art. 8.
 *
 * Permite Wagner editar rules no painel /governance/policies sem precisar
 * tocar em código. Toda mudança vira INSERT em mcp_governance_rule_history
 * (a criar — Fase 5+1) pra audit.
 *
 * MVP: list + toggle enabled. Edit inline + create new ficam pra próxima
 * iteração quando frontend ganhar editor JSON.
 */
class PoliciesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $rules = DB::table('mcp_governance_rules')
            ->orderByDesc('enabled')
            ->orderBy('category')
            ->orderBy('rule_key')
            ->get();

        $byCategory = $rules->groupBy('category')->map(function ($group, $cat) {
            return [
                'category' => $cat ?: 'uncategorized',
                'rules' => $group->map(function ($r) {
                    return [
                        'id'              => $r->id,
                        'rule_key'        => $r->rule_key,
                        'name'            => $r->name,
                        'description'     => $r->description,
                        'enabled'         => (bool) $r->enabled,
                        'version'         => $r->version,
                        'triggered_count' => $r->triggered_count ?? 0,
                        'created_by'      => $r->created_by,
                        'updated_at'      => $r->updated_at,
                    ];
                })->values(),
            ];
        })->values();

        $kpis = [
            'total'      => $rules->count(),
            'enabled'    => $rules->where('enabled', 1)->count(),
            'triggered'  => (int) $rules->sum('triggered_count'),
            'categories' => $byCategory->count(),
        ];

        return Inertia::render('governance/Policies', [
            'rules_by_category' => $byCategory,
            'kpis'              => $kpis,
        ]);
    }

    public function toggle(Request $request, int $id): RedirectResponse
    {
        $enabled = (bool) $request->input('enabled', false);
        DB::table('mcp_governance_rules')
            ->where('id', $id)
            ->update([
                'enabled'    => $enabled ? 1 : 0,
                'updated_at' => now(),
            ]);

        return back()->with('status', "Policy #{$id} " . ($enabled ? 'ativada' : 'desativada'));
    }
}
