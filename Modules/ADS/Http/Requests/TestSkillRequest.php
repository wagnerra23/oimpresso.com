<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 saturação.
 *
 * FormRequest pra POST /ads/admin/skills/{slug}/test (SkillsController@runTest).
 * Wagner executa skill draft contra fixture inputs pra validar antes de aprovar.
 *
 * @see Modules\ADS\Http\Controllers\Admin\SkillsController::runTest
 */
class TestSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'input'        => ['required', 'string', 'max:20000'],
            'use_brain_b'  => ['sometimes', 'boolean'],
            'timeout_sec'  => ['sometimes', 'integer', 'min:5', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'input.required'    => 'Campo input é obrigatório (fixture pra testar a skill).',
            'input.max'         => 'Input deve ter no máximo 20.000 caracteres.',
            'timeout_sec.min'   => 'Timeout mínimo: 5 segundos.',
            'timeout_sec.max'   => 'Timeout máximo: 120 segundos.',
        ];
    }
}
