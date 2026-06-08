<?php

declare(strict_types=1);

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\KB\Entities\KbEdge;
use Modules\KB\Http\Requests\StoreKbEdgeRequest;

/**
 * KbEdgeController — CRUD básico de arestas manuais + leitura do grafo.
 *
 * Contrato: SCHEMA-DB-V1.md §11
 *
 * Edges auto-derivadas (bridge_job/tag_overlap/ai_embed) NÃO podem ser deletadas
 * por usuário — só admin com override flag. V1 não expõe DELETE.
 *
 * GET /kb/edges?from=NNN|to=NNN|type=XXX  lista
 * POST /kb/edges                          cria manual (kb.write)
 */
class KbEdgeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.memory.manage');
    }

    public function index(Request $request): JsonResponse
    {
        $q = KbEdge::query()->with(['fromNode:id,slug,title,type', 'toNode:id,slug,title,type']);

        if ($from = $request->integer('from')) {
            $q->where('from_node_id', $from);
        }
        if ($to = $request->integer('to')) {
            $q->where('to_node_id', $to);
        }
        if ($type = $request->string('type')->toString()) {
            $q->ofType($type);
        }

        $edges = $q->orderByDesc('weight')->limit(500)->get();

        return response()->json(['edges' => $edges]);
    }

    public function store(StoreKbEdgeRequest $request): JsonResponse
    {
        // D8.c Wave 17: validation extraída pra StoreKbEdgeRequest.
        $data = $request->validated();

        $edge = KbEdge::updateOrCreate(
            [
                'from_node_id' => $data['from_node_id'],
                'to_node_id'   => $data['to_node_id'],
                'edge_type'    => $data['edge_type'],
            ],
            [
                'weight'       => $data['weight'] ?? 1.000,
                'payload'      => $data['payload'] ?? null,
                'generated_by' => 'user_action',
            ],
        );

        return response()->json(['edge' => $edge], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $edge = KbEdge::query()->findOrFail($id);

        if ($edge->generated_by !== 'manual' && $edge->generated_by !== 'user_action') {
            return response()->json([
                'ok' => false,
                'error' => 'AUTO_GENERATED_EDGE',
                'message' => 'Esta edge foi gerada automaticamente. Re-rode o bridge job pra recalcular.',
            ], 422);
        }

        $edge->delete();

        return response()->json(['ok' => true]);
    }
}
