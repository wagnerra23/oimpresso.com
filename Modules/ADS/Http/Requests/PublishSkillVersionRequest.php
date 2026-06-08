<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 RETRY (meta 97 module-grade).
 *
 * FormRequest pra POST /ads/admin/skills/versions/{versionId}/publish
 * (Admin\SkillsController@publish). Promove skill version aprovada pra
 * estado published (visível em runtime via SkillsService::listAll).
 *
 * Pré-req: version já foi `approve`d (status='approved') — publish
 * upstream verifica + transição approved→published.
 *
 * @see Modules\ADS\Http\Controllers\Admin\SkillsController::publish
 */
class PublishSkillVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note'         => ['sometimes', 'string', 'max:1000'],
            'force_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.max'              => 'Nota deve ter no máximo 1000 caracteres.',
            'force_active.boolean'  => 'Campo `force_active` deve ser booleano.',
        ];
    }
}
