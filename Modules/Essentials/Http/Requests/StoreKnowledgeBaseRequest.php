<?php

declare(strict_types=1);

namespace Modules\Essentials\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D8 Security Wave 15 — FormRequest extraído de KnowledgeBaseController::store.
 *
 * KB tem 3 tipos hierárquicos (knowledge_base → section → article).
 * Share modes: public (todos do tenant) ou only_with (whitelist user_ids).
 */
class StoreKnowledgeBaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title'      => ['required', 'string', 'max:255'],
            'content'    => ['nullable', 'string'],
            'kb_type'    => ['nullable', Rule::in(['knowledge_base', 'section', 'article'])],
            'parent_id'  => ['nullable', 'integer', 'exists:essentials_kb,id'],
            'share_with' => ['nullable', Rule::in(['public', 'only_with'])],
            'user_ids'   => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'  => 'O título é obrigatório.',
            'kb_type.in'      => 'Tipo deve ser livro, seção ou artigo.',
            'share_with.in'   => 'Compartilhamento deve ser público ou restrito.',
        ];
    }
}
