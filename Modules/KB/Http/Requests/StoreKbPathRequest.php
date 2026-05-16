<?php

declare(strict_types=1);

namespace Modules\KB\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * StoreKbPathRequest — D8.c Security Wave S Batch 2 (2026-05-16).
 *
 * Extrai validation rules de KbPathController@store preservando contrato exato
 * documentado em memory/requisitos/KB/SCHEMA-DB-V1.md §11 (trilhas).
 *
 * Permissão: 'copiloto.mcp.memory.manage' (kb.publish.path no V2 Spatie rename).
 *
 * Grafo de conhecimento preservado (ADR 0150): steps[] referenciam kb_nodes.id
 * via exists rule — não duplica conteúdo, apenas ordena nós existentes.
 */
class StoreKbPathRequest extends FormRequest
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
            'title'             => 'required|string|max:180',
            'slug'              => 'nullable|string|max:120',
            'audience'          => 'nullable|string|max:180',
            'description'       => 'nullable|string|max:500',
            'hue'               => 'nullable|integer|min:0|max:360',
            'status'            => 'sometimes|string|in:draft,published',
            'steps'             => 'nullable|array',
            'steps.*.node_id'   => 'required_with:steps|integer|exists:kb_nodes,id',
            'steps.*.step_type' => 'sometimes|string|in:leitura,pratica,decisao',
            'steps.*.note'      => 'nullable|string|max:500',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'           => 'O título da trilha é obrigatório.',
            'status.in'                => 'Status inválido. Use draft ou published.',
            'steps.*.node_id.exists'   => 'Um dos nós referenciados não existe (kb_nodes).',
            'steps.*.step_type.in'     => 'Tipo de passo inválido. Use leitura, pratica ou decisao.',
        ];
    }
}
