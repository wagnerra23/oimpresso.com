<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
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
}
