<?php

declare(strict_types=1);

namespace Modules\KB\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * StoreKbCommentRequest — D8.c Security Wave 17 Batch 1 (2026-05-16).
 *
 * Extrai validation rules de KbCommentController@store preservando contrato exato
 * documentado em memory/requisitos/KB/SCHEMA-DB-V1.md §11.
 *
 * Permissão: 'copiloto.mcp.memory.manage' (V1 reusa permission canon — ver
 * StoreKbNodeRequest pra contexto sobre rename Spatie futuro).
 *
 * Comentários inline ancorados em block_idx; max 5000 chars por comentário.
 */
class StoreKbCommentRequest extends FormRequest
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
            'block_idx' => 'required|integer|min:0',
            'text'      => 'required|string|max:5000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'block_idx.required' => 'O índice do bloco é obrigatório.',
            'block_idx.integer'  => 'O índice do bloco deve ser numérico.',
            'block_idx.min'      => 'O índice do bloco não pode ser negativo.',
            'text.required'      => 'O texto do comentário é obrigatório.',
            'text.max'           => 'O comentário não pode ultrapassar 5000 caracteres.',
        ];
    }
}
