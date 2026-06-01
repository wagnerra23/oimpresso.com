<?php

declare(strict_types=1);

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\KB\Entities\KbEdge;
use Modules\KB\Entities\KbNode;

/**
 * KbGraphController — backend do GRAFO de conhecimento (ONDA 6, ADR 0150).
 *
 * Substitui o placeholder closure de /kb/graph/data (que devolvia
 * {nodes:[], edges:[], kpis:null} e fazia o frontend cair no mockGraphData.ts).
 *
 * Endpoint:
 *   GET /kb/graph/data → {nodes, edges, kpis} no formato consumido pelo
 *   ReactFlow/Cytoscape (resources/js/Pages/kb/Graph.tsx).
 *
 * Multi-tenant Tier 0 (ADR 0093): KbNode e KbEdge usam BelongsToBusinessTrait,
 * que aplica o global scope `business_id` automaticamente a partir da sessão
 * (session('user.business_id') ?? session('business.id')). NÃO filtramos
 * business_id manualmente — o scope garante que biz=1 nunca enxergue biz=99.
 *
 * @see memory/requisitos/KB/SCHEMA-DB-V1.md §3 (kb_nodes) + §4 (kb_edges)
 * @see Modules/KB/Entities/Concerns/BelongsToBusinessTrait.php
 */
class KbGraphController extends Controller
{
    /**
     * Teto de nós devolvidos pro grafo. Acima disso o ReactFlow/Cytoscape
     * fica ilegível e o payload pesa — clamp defensivo (ONDA 6).
     */
    private const NODE_LIMIT = 300;

    public function __construct()
    {
        $this->middleware('auth');
        // Dívida técnica: mesma permissão canônica do KbController (rename pra
        // kb.graph.view em PR separado — SCHEMA §12).
        $this->middleware('can:copiloto.mcp.memory.manage');
    }

    /**
     * GET /kb/graph/data — payload do grafo (nós + arestas + KPIs).
     *
     * Contrato (ReactFlow/Cytoscape):
     *   nodes: [{id, label, type, category_id, group}]
     *   edges: [{id, from, to, type, weight}]
     *   kpis:  {nodes, edges, por_tipo: {<type>: count}}
     *
     * As arestas só apontam pros nós devolvidos: quando há mais de NODE_LIMIT
     * nós, filtramos edges cujos dois extremos estão no conjunto retornado pra
     * não gerar arestas "soltas" (dangling) no canvas. Ambos os models são
     * tenant-scoped via global scope, então o filtro nunca cruza business.
     */
    public function data(Request $request): JsonResponse
    {
        $nodes = KbNode::query()
            ->orderByDesc('updated_at')
            ->limit(self::NODE_LIMIT)
            ->get(['id', 'title', 'type', 'category_id']);

        $nodeIds = $nodes->pluck('id')->all();

        $edges = KbEdge::query()
            ->when(
                $nodes->count() >= self::NODE_LIMIT,
                fn ($q) => $q->whereIn('from_node_id', $nodeIds)
                    ->whereIn('to_node_id', $nodeIds)
            )
            ->get(['id', 'from_node_id', 'to_node_id', 'edge_type', 'weight']);

        return response()->json([
            'nodes' => $nodes->map(fn (KbNode $n) => [
                'id'          => $n->id,
                'label'       => $n->title,
                'type'        => $n->type,
                'category_id' => $n->category_id,
                'group'       => $n->type,
            ])->all(),

            'edges' => $edges->map(fn (KbEdge $e) => [
                'id'     => $e->id,
                'from'   => $e->from_node_id,
                'to'     => $e->to_node_id,
                'type'   => $e->edge_type,
                // weight é cast decimal:3 (string) — devolve float pro canvas.
                'weight' => (float) $e->weight,
            ])->all(),

            'kpis' => [
                'nodes'    => KbNode::query()->count(),
                'edges'    => KbEdge::query()->count(),
                'por_tipo' => KbNode::query()
                    ->select('type')
                    ->selectRaw('COUNT(*) as c')
                    ->groupBy('type')
                    ->pluck('c', 'type')
                    ->all(),
            ],
        ]);
    }
}
