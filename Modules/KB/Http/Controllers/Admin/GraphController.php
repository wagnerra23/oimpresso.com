<?php

namespace Modules\KB\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
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

        // Wave 13 — OTel span (ADR 0155 D9.a) — Knowledge Graph carrega 5 fontes
        // (mcp_decision_patterns + meta-skills + tools + policy + mcp_memory_documents)
        // + monta edges; útil pra correlate spike de patterns/skills com latency.
        return OtelHelper::span('kb.graph.index', [
            'module'      => 'KB',
            'business_id' => $businessId,
        ], function () use ($businessId, $policy, $metaSkills, $tools) {
            // D6.a thin SoC (Wave 13): builders privados extraídos pra preparar futura
            // Inertia::defer (assim que Page Graph.tsx ganhar wrapper <Deferred> — hoje
            // usa useMemo no top-level, rollback PR #963 documenta regressão).
            $patterns   = $this->buildPatternsRows($businessId);
            $memoryDocs = $this->buildMemoryDocsCount($businessId);

            $nodes = array_merge(
                $this->buildSkillNodes($patterns),
                $this->buildMetaSkillNodes($metaSkills),
                $this->buildToolNodes($tools),
                $this->buildPolicyNodes($policy),
                [$this->buildMemoryCentralNode($memoryDocs)],
            );

            $edges = array_merge(
                $this->buildMemoryToSkillEdges($patterns),
                $this->buildMetaSkillEdges($metaSkills, $patterns),
            );

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
        });
    }

    /**
     * Wave 13 — top-15 mcp_decision_patterns por uso (skills do KG).
     */
    private function buildPatternsRows(int $businessId): Collection
    {
        return DB::table('mcp_decision_patterns')
            ->where('business_id', $businessId)
            ->orderByDesc('total_count')
            ->limit(15)
            ->get();
    }

    /**
     * Wave 13 — count atomic de docs MCP do business (Memory central node).
     */
    private function buildMemoryDocsCount(int $businessId): int
    {
        return (int) DB::table('mcp_memory_documents')
            ->where('business_id', $businessId)
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSkillNodes(Collection $patterns): array
    {
        $nodes = [];
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
        return $nodes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMetaSkillNodes(GovernanceRulesService $metaSkills): array
    {
        $nodes = [];
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
        return $nodes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildToolNodes(ToolRegistry $tools): array
    {
        $nodes = [];
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
        return $nodes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPolicyNodes(PolicyEngine $policy): array
    {
        $nodes = [];
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
        return $nodes;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMemoryCentralNode(int $memoryDocs): array
    {
        return [
            'id'       => 'memory-mcp',
            'type'     => 'memory',
            'data'     => [
                'label' => 'MCP Memory',
                'count' => $memoryDocs,
            ],
            'position' => ['x' => 0, 'y' => 0],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMemoryToSkillEdges(Collection $patterns): array
    {
        $edges = [];
        foreach ($patterns as $p) {
            $edges[] = [
                'id'     => "edge-mem-skill-{$p->id}",
                'source' => 'memory-mcp',
                'target' => "skill-{$p->id}",
                'label'  => 'derived_from',
                'animated' => false,
            ];
        }
        return $edges;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMetaSkillEdges(GovernanceRulesService $metaSkills, Collection $patterns): array
    {
        $edges = [];
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
        return $edges;
    }
}
