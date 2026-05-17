<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 RETRY (meta 97 module-grade).
 *
 * FormRequest pra POST /ads/admin/meta-skills/validate
 * (Admin\MetaSkillsController@validateRule). Valida sintaxe DSL antes de
 * persistir nova regra de governança.
 *
 * Payload: { rule: string, tier: 'A'|'B'|'C', context?: object }
 *
 * @see Modules\ADS\Http\Controllers\Admin\MetaSkillsController::validateRule
 */
class ValidateMetaSkillRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rule'    => ['required', 'string', 'min:3', 'max:5000'],
            'tier'    => ['sometimes', 'string', 'in:A,B,C'],
            'context' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'rule.required' => 'Regra DSL é obrigatória.',
            'rule.min'      => 'Regra deve ter pelo menos 3 caracteres.',
            'rule.max'      => 'Regra deve ter no máximo 5000 caracteres.',
            'tier.in'       => 'Tier deve ser A, B ou C.',
            'context.array' => 'Contexto deve ser objeto (key/value).',
        ];
    }
}
