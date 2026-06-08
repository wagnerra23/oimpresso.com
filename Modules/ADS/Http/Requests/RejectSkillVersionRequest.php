<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 saturação.
 *
 * FormRequest pra POST /ads/admin/skills/versions/{versionId}/reject
 * (SkillsController@reject). Wagner rejeita version draft com razão —
 * version fica histórica, não vira active.
 *
 * @see Modules\ADS\Http\Controllers\Admin\SkillsController::reject
 */
class RejectSkillVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Razão da rejeição é obrigatória (Wagner aprende com feedback).',
            'reason.min'      => 'Razão deve ter pelo menos 5 caracteres.',
            'reason.max'      => 'Razão deve ter no máximo 2000 caracteres.',
        ];
    }
}
