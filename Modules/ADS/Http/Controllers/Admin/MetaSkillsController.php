<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Http\Requests\StoreGovernanceMetaSkillRequest;
use Modules\ADS\Services\GovernanceRulesService;

class MetaSkillsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(GovernanceRulesService $service): Response
    {
        $rules = $service->listAll();

        $byCategory = collect($rules)
            ->groupBy('category')
            ->map(fn ($group, $cat) => [
                'category' => $cat,
                'rules'    => $group->values(),
            ])
            ->values();

        $kpis = [
            'total'      => count($rules),
            'enabled'    => count(array_filter($rules, fn ($r) => $r['enabled'])),
            'triggered'  => array_sum(array_column($rules, 'triggered_count')),
            'categories' => $byCategory->count(),
        ];

        return Inertia::render('ads/Admin/MetaSkills', [
            'rules_by_category' => $byCategory,
            'kpis'              => $kpis,
        ]);
    }

    public function toggle(Request $request, int $id, GovernanceRulesService $service): RedirectResponse
    {
        $service->toggle($id, (bool) $request->input('enabled', true));
        return back()->with('status', "Meta-skill #{$id} atualizada.");
    }

    /**
     * Cria nova meta-skill via editor UI.
     */
    public function store(StoreGovernanceMetaSkillRequest $request): RedirectResponse
    {
        // Wave 27 D8.c — validação extraída pra StoreGovernanceMetaSkillRequest.
        $data = $request->validated();

        \Illuminate\Support\Facades\DB::table('mcp_governance_rules')->insert([
            'rule_key'    => $data['rule_key'],
            'name'        => $data['name'],
            'description' => $data['description'],
            'category'    => $data['category'],
            'condition'   => json_encode($data['condition']),
            'action'      => json_encode($data['action']),
            'enabled'     => (bool) ($data['enabled'] ?? false),
            'version'     => 1,
            'created_by'  => 'wagner',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return back()->with('status', "Meta-skill {$data['rule_key']} criada (draft).");
    }

    /**
     * Testa condição contra mcp_decision_patterns reais.
     */
    public function validateRule(Request $request, GovernanceRulesService $service): \Illuminate\Http\JsonResponse
    {
        $condition = $request->input('condition', []);
        if (! is_array($condition)) {
            return response()->json(['ok' => false, 'error' => 'invalid_condition']);
        }

        $patterns = \Illuminate\Support\Facades\DB::table('mcp_decision_patterns')
            ->limit(50)
            ->get();

        $matched = 0;
        $sampleMatches = [];
        foreach ($patterns as $p) {
            $context = [
                'wilson_lower_bound' => 0.7, // simulado pra teste
                'success_count'      => (int) $p->success_count,
                'total_count'        => (int) $p->total_count,
                'success_rate'       => (float) $p->success_rate,
                'is_hardcoded'       => (bool) $p->is_hardcoded,
                'days_since_last_outcome' => 0,
            ];
            if ($service->evaluate($condition, $context)) {
                $matched++;
                if (count($sampleMatches) < 5) {
                    $sampleMatches[] = "{$p->domain} · {$p->event_type}";
                }
            }
        }

        return response()->json([
            'ok'              => true,
            'samples_total'   => $patterns->count(),
            'samples_matched' => $matched,
            'sample_matches'  => $sampleMatches,
        ]);
    }
}
