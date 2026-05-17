<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra deletar leads em lote — anti-DoS via max:200 + sanity check.
 *
 * Wave 25 D8 polish — espelha pattern de MassDestroyCallLogRequest. Reforça
 * LGPD: motivo opcional pra log de auditoria (FinanceiroAuditLogger-like).
 *
 * Multi-tenant Tier 0 (ADR 0093) — Controller deve scopar IDs por business_id
 * antes de delete (defesa em profundidade).
 *
 * @see Modules\Crm\Http\Controllers\LeadController
 * @see Modules\Crm\Http\Requests\MassDestroyCallLogRequest (pattern irmão)
 */
class MassDestroyLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->can('superadmin')) {
            return true;
        }

        $businessId = $this->session()->get('user.business_id');
        $moduleUtil = app(ModuleUtil::class);

        return (bool) $moduleUtil->hasThePermissionInSubscription($businessId, 'crm_module');
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Selecione ao menos um lead.',
            'ids.max'      => 'Máximo 200 leads por lote (anti-DoS).',
            'motivo.max'   => 'Motivo até 500 caracteres.',
        ];
    }
}
