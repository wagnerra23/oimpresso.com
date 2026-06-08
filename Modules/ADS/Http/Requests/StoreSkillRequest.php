<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 saturação (meta 97 module-grade).
 *
 * FormRequest pra POST /ads/admin/skills/{slug} (SkillsController@store).
 * Wagner edita skill (system prompt + frontmatter YAML) via UI; Service
 * cria version draft em DB (Skills MD-first + DB primary ADR 0076).
 *
 * Multi-tenant Tier 0: Skills são repo-wide (todo business consome o mesmo
 * prompt), portanto sem business_id scope nesse FormRequest.
 *
 * @see Modules\ADS\Http\Controllers\Admin\SkillsController::store
 * @see memory/decisions/0076-skills-md-first-db-primary.md
 */
class StoreSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content'      => ['required', 'string', 'max:50000'],
            'change_note'  => ['sometimes', 'string', 'max:1000'],
            'frontmatter'  => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Campo content (system prompt) é obrigatório.',
            'content.max'      => 'System prompt deve ter no máximo 50.000 caracteres.',
            'change_note.max'  => 'Nota de alteração deve ter no máximo 1000 caracteres.',
        ];
    }
}
