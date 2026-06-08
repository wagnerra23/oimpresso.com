<?php

declare(strict_types=1);

namespace Modules\KB\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * StoreDecisionTreeRequest — D8.c Security Wave S Batch 2 (2026-05-16).
 *
 * Extrai validation rules de KbDecisionTreeController@store preservando contrato
 * exato documentado em memory/requisitos/KB/SCHEMA-DB-V1.md §11 (troubleshooters).
 *
 * Permissão: 'copiloto.mcp.memory.manage' (kb.publish.tree no V2 Spatie rename).
 *
 * Grafo de conhecimento preservado (ADR 0150): steps formam DAG via
 * yes_next_position/no_next_position (1-based, linkado em 2ª passe pelo
 * Controller) ou fix terminal (yes_fix/no_fix + opcional yes_fix_node_id/no_fix_node_id
 * apontando pra kb_nodes existentes).
 */
class StoreDecisionTreeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->can('copiloto.mcp.memory.manage');
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title'                     => 'required|string|max:180',
            'slug'                      => 'nullable|string|max:120',
            'equip'                     => 'nullable|string|max:80',
            'when_to_use'               => 'nullable|string|max:500',
            'hue'                       => 'nullable|integer|min:0|max:360',
            'status'                    => 'sometimes|string|in:draft,published',
            'steps'                     => 'required|array|min:1',
            'steps.*.question'          => 'required|string|max:500',
            'steps.*.yes_next_position' => 'nullable|integer|min:1',
            'steps.*.yes_fix'           => 'nullable|string',
            'steps.*.yes_fix_node_id'   => 'nullable|integer|exists:kb_nodes,id',
            'steps.*.no_next_position'  => 'nullable|integer|min:1',
            'steps.*.no_fix'            => 'nullable|string',
            'steps.*.no_fix_node_id'    => 'nullable|integer|exists:kb_nodes,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'                 => 'O título da árvore de decisão é obrigatório.',
            'steps.required'                 => 'A árvore precisa ter pelo menos 1 passo (pergunta).',
            'steps.min'                      => 'A árvore precisa ter pelo menos 1 passo (pergunta).',
            'steps.*.question.required'      => 'Cada passo precisa de uma pergunta.',
            'status.in'                      => 'Status inválido. Use draft ou published.',
            'steps.*.yes_fix_node_id.exists' => 'O nó de fix (sim) referenciado não existe (kb_nodes).',
            'steps.*.no_fix_node_id.exists'  => 'O nó de fix (não) referenciado não existe (kb_nodes).',
        ];
    }
}
