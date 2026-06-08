<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 RETRY (meta 97 module-grade).
 *
 * FormRequest pra POST /ads/admin/skills/{slug}/move-label
 * (Admin\SkillsController@moveLabel). Move skill entre labels Tier A/B/C
 * (Always-on / Auto-trigger / Slash command).
 *
 * Multi-tenant Tier 0 (ADR 0093): label muda só no business_id da session
 * — outras tenants mantêm label original.
 *
 * @see Modules\ADS\Http\Controllers\Admin\SkillsController::moveLabel
 * @see memory/decisions/0095-skills-tiers-convencao-interna.md
 */
class MoveSkillLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'  => ['required', 'string', 'in:A,B,C'],
            'reason' => ['sometimes', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'Label de destino é obrigatória (A, B ou C).',
            'label.in'       => 'Label deve ser A (always-on), B (auto-trigger) ou C (slash command).',
            'reason.max'     => 'Motivo deve ter no máximo 500 caracteres.',
        ];
    }
}
