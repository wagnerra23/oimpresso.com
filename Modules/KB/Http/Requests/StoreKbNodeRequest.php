<?php

declare(strict_types=1);

namespace Modules\KB\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * StoreKbNodeRequest — D8.c Security Wave S Batch 2 (2026-05-16).
 *
 * Extrai validation rules de KbNodeController@store preservando contrato exato
 * documentado em memory/requisitos/KB/SCHEMA-DB-V1.md §11.
 *
 * Permissão: 'copiloto.mcp.memory.manage' (V1 reusa permission canon; rename
 * Spatie pra kb.write fica em PR separado — ver KbNodeController docblock).
 *
 * Grafo de conhecimento preservado (ADR 0150 KB Unificado): regras de
 * type/category/subcategory/tags/status NÃO alteradas — apenas migradas.
 */
class StoreKbNodeRequest extends FormRequest
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
            'title'          => 'required|string|max:255',
            'type'           => 'sometimes|string|in:article,external_file',
            'slug'           => 'nullable|string|max:180',
            'excerpt'        => 'nullable|string|max:500',
            'body_blocks'    => 'nullable|array',
            'category_id'    => 'nullable|integer|exists:kb_categories,id',
            'subcategory_id' => 'nullable|integer|exists:kb_subcategories,id',
            'nivel'          => 'nullable|string|in:iniciante,intermediario,avancado',
            'equip'          => 'nullable|string|max:80',
            'tags'           => 'nullable|array',
            'pinned'         => 'sometimes|boolean',
            'status'         => 'sometimes|string|in:draft,ok,outdated',
            'read_time_min'  => 'nullable|integer|min:1|max:600',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'O título do artigo é obrigatório.',
            'type.in'        => 'Tipo inválido. Use article ou external_file.',
            'nivel.in'       => 'Nível inválido. Use iniciante, intermediario ou avancado.',
            'status.in'      => 'Status inválido. Use draft, ok ou outdated.',
        ];
    }
}
