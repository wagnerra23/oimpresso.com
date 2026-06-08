<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 saturação.
 *
 * FormRequest pra POST /ads/admin/meta-skills (MetaSkillsController@store).
 * Wagner cria meta-skill (skill que gera outras skills via ScaffoldSkillFromMissionService).
 *
 * @see Modules\ADS\Http\Controllers\Admin\MetaSkillsController::store
 */
class StoreMetaSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mission'   => ['required', 'string', 'min:10', 'max:5000'],
            'tier'      => ['sometimes', 'string', 'in:A,B,C'],
            'autoload'  => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'mission.required' => 'Campo mission é obrigatório (descreve o objetivo da meta-skill).',
            'mission.min'      => 'Mission deve ter pelo menos 10 caracteres.',
            'tier.in'          => 'Tier deve ser A (always-on), B (auto-trigger) ou C (slash command).',
        ];
    }
}
