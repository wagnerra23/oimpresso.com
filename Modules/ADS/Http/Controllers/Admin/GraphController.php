<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\PolicyEngine;
use Modules\ADS\Services\GovernanceRulesService;
use Modules\ADS\Services\ToolRegistry;

/**
 * Knowledge Graph (Cognitive Control Panel #3).
 *
 * Visualiza relações entre Memory ↔ Skills ↔ Meta-skills ↔ Policy ↔ Tools.
 * Cada nó tem dado real do sistema. Edges representam:
 *   - "uses": skill usa tool / meta-skill rege skill
 *   - "derived_from": pattern derivado de execuções de domínio
 *   - "applies_to": policy aplica-se a event_type
 */
class GraphController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(
        Request $request,
        PolicyEngine $policy,
        GovernanceRulesService $metaSkills,
        ToolRegistry $tools,
    ): Response {
        $businessId = (int) $request->session()->get('user.business_id', 1);

        $nodes = [];
        $edges = [];

        // ─── Nodes Skills (mcp_decision_patterns) ───
        $patterns = DB::table('mcp_decision_patterns')
            ->where('business_id', $businessId)
            ->orderByDesc('total_count')
            ->limit(15)
            ->get();

        foreach ($patterns as $p) {
            $nodes[] = [
                'id'       => "skill-{$p->id}",
                'type'     => 'skill',
                'data'     => [
                    'label'         => "{$p->domain}.{$p->event_type}",
                    'success_rate'  => (float) $p->success_rate,
                    'total_count'   => (int) $p->total_count,
                    'is_hardcoded'  => (bool) $p->is_hardcoded,
                ],
                'position' => ['x' => 0, 'y' => 0], // dagre layout client-side
            ];
        }

        // ─── Nodes Meta-skills ───
        foreach ($metaSkills->listAll() as $rule) {
            $nodes[] = [
                'id'       => "meta-{$rule['id']}",
                'type'     => 'metaskill',
                'data'     => [
                    'label'           => $rule['name'],
                    'category'        => $rule['category'],
                    'enabled'         => $rule['enabled'],
                    'triggered_count' => $rule['triggered_count'],
                ],
                'position' => ['x' => 0, 'y' => 0],
            ];
        }

        // ─── Nodes Tools ───
        foreach ($tools->all() as $tool) {
            $nodes[] = [
                'id'       => "tool-{$tool->name()}",
                'type'     => 'tool',
                'data'     => [
                    'label'        => $tool->name(),
                    'category'     => $tool->category(),
                    'is_read_only' => $tool->isReadOnly(),
                ],
                'position' => ['x' => 0, 'y' => 0],
            ];
        }

        // ─── Nodes Policy categorias ───
        $policyCategories = ['BLOCK_ALWAYS', 'REQUIRE_HUMAN_REVIEW', 'REQUIRE_BRAIN_B', 'ALLOW_BRAIN_A'];
        foreach ($policyCategories as $cat) {
            $count = count($policy->getAllRules()[$cat] ?? []);
            $nodes[] = [
                'id'       => "policy-{$cat}",
                'type'     => 'policy',
                'data'     => [
                    'label' => $cat,
                    'count' => $count,
                ],
                'position' => ['x' => 0, 'y' => 0],
            ];
        }

        // ─── Node Memory central ───
        $memoryDocs = DB::table('mcp_memory_documents')
            ->where('business_id', $businessId)
            ->count();
        $nodes[] = [
            'id'       => 'memory-mcp',
            'type'     => 'memory',
            'data'     => [
                'label' => 'MCP Memory',
                'count' => $memoryDocs,
            ],
            'position' => ['x' => 0, 'y' => 0],
        ];

        // ─── Edges ───
        // Skills derivam de Memory (todas → memória central)
        foreach ($patterns as $p) {
            $edges[] = [
                'id'     => "edge-mem-skill-{$p->id}",
                'source' => 'memory-mcp',
                'target' => "skill-{$p->id}",
                'label'  => 'derived_from',
                'animated' => false,
            ];
        }

        // Meta-skills regem skills (promotion category aponta pra ALLOW_BRAIN_A policy)
        foreach ($metaSkills->listAll() as $rule) {
            if ($rule['category'] === 'promotion') {
                $edges[] = [
                    'id'     => "edge-meta-policy-{$rule['id']}",
                    'source' => "meta-{$rule['id']}",
                    'target' => 'policy-ALLOW_BRAIN_A',
                    'label'  => 'promotes_to',
                    'animated' => true,
                ];
            }
            if ($rule['category'] === 'archival') {
                foreach ($patterns->take(3) as $p) {
                    $edges[] = [
                        'id'     => "edge-meta-skill-{$rule['id']}-{$p->id}",
                        'source' => "meta-{$rule['id']}",
                        'target' => "skill-{$p->id}",
                        'label'  => 'archives',
                    ];
                }
            }
        }

        return Inertia::render('ads/Admin/Graph', [
            'nodes' => $nodes,
            'edges' => $edges,
            'kpis'  => [
                'skills'      => $patterns->count(),
                'metaskills'  => count($metaSkills->listAll()),
                'tools'       => count($tools->all()),
                'memory_docs' => $memoryDocs,
            ],
        ]);
    }
}
