<?php

declare(strict_types=1);

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\KB\Entities\KbEdge;
use Modules\KB\Entities\KbNode;

/**
 * KbGraphController — backend do GRAFO de conhecimento (ONDA 6, ADR 0150).
 *
 * Dois consumidores, MESMO payload (buildGraph):
 *   GET /kb/graph       → page()  → Inertia::render('kb/Graph', {nodes,edges,kpis})
 *   GET /kb/graph/data  → data()  → JSON {nodes,edges,kpis}
 *
 * O payload segue EXATAMENTE o contrato TS em resources/js/Pages/kb/_lib/graphTypes.ts
 * (KbGraphNode/KbGraphEdge/KbGraphKpis) — id no formato "<type>-<id>", data{} aninhado,
 * kpis com total_nodes/total_edges/by_type/by_edge_type/outdated_count/draft_count/
 * last_bridge_at. Quando props.nodes vem populado, Graph.tsx larga o MOCK e mostra real.
 *
 * Multi-tenant Tier 0 (ADR 0093): KbNode/KbEdge usam BelongsToBusinessTrait, que aplica
 * o global scope `business_id` (session('user.business_id') ?? session('business.id')).
 * NÃO filtramos business_id manualmente — o scope garante que biz=1 nunca veja biz=99.
 *
 * @see memory/requisitos/KB/SCHEMA-DB-V1.md §3 (kb_nodes) + §4 (kb_edges) + §11
 * @see resources/js/Pages/kb/_lib/graphTypes.ts
 */
class KbGraphController extends Controller
{
    /**
     * Teto de nós devolvidos. Acima disso o canvas fica ilegível e o payload pesa.
     * Pegamos os mais recentes (orderByDesc updated_at) — clamp defensivo.
     */
    private const NODE_LIMIT = 300;

    public function __construct()
    {
        $this->middleware('auth');
        // Dívida técnica: mesma permission canônica do KbController (rename pra
        // kb.graph.view em PR Spatie separado — SCHEMA §12).
        $this->middleware('can:copiloto.mcp.memory.manage');
    }

    /** GET /kb/graph — página Inertia do grafo com dados reais (sai do MOCK). */
    public function page(Request $request): InertiaResponse
    {
        return Inertia::render('kb/Graph', $this->buildGraph());
    }

    /** GET /kb/graph/data — mesmo payload em JSON (ReactFlow/embeds externos). */
    public function data(Request $request): JsonResponse
    {
        return response()->json($this->buildGraph());
    }

    /**
     * Monta {nodes, edges, kpis} no contrato KbGraphPageProps.
     *
     * Edges são filtradas pros nós retornados (ambos extremos no top-N) pra não
     * gerar arestas soltas no canvas — o filtro roda dentro do tenant scope.
     *
     * @return array{nodes: list<array<string,mixed>>, edges: list<array<string,mixed>>, kpis: array<string,mixed>}
     */
    protected function buildGraph(): array
    {
        $nodes = KbNode::query()
            ->orderByDesc('updated_at')
            ->limit(self::NODE_LIMIT)
            ->get([
                'id', 'type', 'slug', 'title', 'excerpt', 'status', 'pinned', 'tags',
                'reads_count', 'helpful_count', 'outdated_votes', 'last_verified_at', 'updated_at',
            ]);

        // db-int id → string id "<type>-<id>" (contrato KbGraphNode.id)
        $strIdByDb = [];
        foreach ($nodes as $n) {
            $strIdByDb[$n->id] = $n->type . '-' . $n->id;
        }
        $nodeIds = array_keys($strIdByDb);

        $edges = KbEdge::query()
            ->whereIn('from_node_id', $nodeIds)
            ->whereIn('to_node_id', $nodeIds)
            ->get(['id', 'from_node_id', 'to_node_id', 'edge_type', 'weight', 'generated_by']);

        return [
            'nodes' => $nodes->map(fn (KbNode $n): array => [
                'id'   => $strIdByDb[$n->id],
                'type' => $n->type,
                'data' => [
                    'label'            => $n->title,
                    'slug'             => $n->slug,
                    'excerpt'          => $n->excerpt,
                    'status'           => $n->status,
                    'pinned'           => (bool) $n->pinned,
                    'tags'             => $n->tags,
                    'reads_count'      => (int) $n->reads_count,
                    'helpful_count'    => (int) $n->helpful_count,
                    'outdated_votes'   => (int) $n->outdated_votes,
                    'last_verified_at' => optional($n->last_verified_at)->toIso8601String(),
                    'updated_at'       => optional($n->updated_at)->toIso8601String(),
                ],
            ])->values()->all(),

            'edges' => $edges->map(fn (KbEdge $e): array => [
                'id'           => 'edge-' . $e->from_node_id . '-' . $e->to_node_id . '-' . $e->edge_type,
                'source'       => $strIdByDb[$e->from_node_id],
                'target'       => $strIdByDb[$e->to_node_id],
                'edge_type'    => $e->edge_type,
                'weight'       => $e->weight !== null ? (float) $e->weight : null,
                'generated_by' => $e->generated_by,
            ])->values()->all(),

            'kpis' => [
                'total_nodes'    => KbNode::query()->count(),
                'total_edges'    => KbEdge::query()->count(),
                'by_type'        => KbNode::query()
                    ->select('type')->selectRaw('COUNT(*) as c')
                    ->groupBy('type')->pluck('c', 'type')->all(),
                'by_edge_type'   => KbEdge::query()
                    ->select('edge_type')->selectRaw('COUNT(*) as c')
                    ->groupBy('edge_type')->pluck('c', 'edge_type')->all(),
                'outdated_count' => KbNode::query()->where('status', 'outdated')->count(),
                'draft_count'    => KbNode::query()->where('status', 'draft')->count(),
                'last_bridge_at' => optional(KbNode::query()->max('updated_at'))
                    ? (string) KbNode::query()->max('updated_at')
                    : null,
            ],
        ];
    }
}
