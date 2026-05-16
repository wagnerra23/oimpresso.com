<?php

declare(strict_types=1);

namespace Modules\KB\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Modules\KB\Entities\KbEdge;

/**
 * StoreKbEdgeRequest — D8.c Security Wave 17 Batch 1 (2026-05-16).
 *
 * Extrai validation rules de KbEdgeController@store preservando contrato exato
 * documentado em memory/requisitos/KB/SCHEMA-DB-V1.md §11.
 *
 * Permissão: 'copiloto.mcp.memory.manage'.
 *
 * Grafo KB (ADR 0150): edges manuais respeitam EDGE_TYPES enum + weight 0..1
 * + payload arbitrário JSON. Edges auto-derivadas (bridge_job/tag_overlap/
 * ai_embed) NÃO podem ser criadas via API user — generated_by é forçado a
 * 'user_action' no controller.
 */
class StoreKbEdgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->can('copiloto.mcp.memory.manage');
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'from_node_id' => 'required|integer|exists:kb_nodes,id|different:to_node_id',
            'to_node_id'   => 'required|integer|exists:kb_nodes,id',
            'edge_type'    => 'required|string|in:'.implode(',', KbEdge::EDGE_TYPES),
            'weight'       => 'nullable|numeric|min:0|max:1',
            'payload'      => 'nullable|array',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_node_id.required'  => 'Selecione o nó de origem.',
            'from_node_id.exists'    => 'Nó de origem inválido.',
            'from_node_id.different' => 'O nó de origem deve ser diferente do destino.',
            'to_node_id.required'    => 'Selecione o nó de destino.',
            'to_node_id.exists'      => 'Nó de destino inválido.',
            'edge_type.required'     => 'Selecione o tipo da aresta.',
            'edge_type.in'           => 'Tipo de aresta inválido.',
            'weight.min'             => 'O peso deve ser ≥ 0.',
            'weight.max'             => 'O peso deve ser ≤ 1.',
        ];
    }
}
