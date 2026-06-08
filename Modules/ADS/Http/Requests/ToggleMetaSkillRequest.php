<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 RETRY (meta 97 module-grade).
 *
 * FormRequest pra POST /ads/admin/meta-skills/{id}/toggle
 * (Admin\MetaSkillsController@toggle). Toggle on/off de regra de governança.
 *
 * Defense-in-depth: aceita campo opcional `active` (bool) — Controller pode
 * passar valor explícito ou inverter o atual.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` é resolvido da session pelo
 * Controller; query UPDATE inclui `where business_id` upstream.
 *
 * @see Modules\ADS\Http\Controllers\Admin\MetaSkillsController::toggle
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ToggleMetaSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission enforced via middleware `auth` + CheckUserLogin upstream.
        return true;
    }

    public function rules(): array
    {
        return [
            'active' => ['sometimes', 'boolean'],
            'reason' => ['sometimes', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'active.boolean' => 'Campo `active` deve ser booleano (true/false).',
            'reason.max'     => 'Motivo deve ter no máximo 500 caracteres.',
        ];
    }
}
