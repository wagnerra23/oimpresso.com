<?php

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra criar CrmCallLog (registro manual de chamada).
 *
 * Wave 18 D8.a: introduz validação centralizada no flow CallLog. Antes: a
 * persistência via UI usava DataTables AJAX direto sem FormRequest dedicado —
 * passa a validar contact_id (FK), start_time/end_time (datetime ordenados) e
 * duration (segundos não-negativos).
 *
 * Tier 0 (ADR 0093): authorize() bloqueia se módulo CRM não habilitado na
 * subscription do business. business_id NUNCA chega nas rules — vem de session.
 *
 * @see Modules\Crm\Http\Controllers\CallLogController
 * @see Modules\Crm\Entities\CrmCallLog
 */
class StoreCallLogRequest extends FormRequest
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
        if (! config('constants.enable_crm_call_log')) {
            return false;
        }
        // CRM module habilitado na subscription.
        $businessId = $this->session()->get('user.business_id');
        if (! app(ModuleUtil::class)->hasThePermissionInSubscription($businessId, 'crm_module')) {
            return false;
        }

        return (bool) ($user->can('crm.view_all_call_log') || $user->can('crm.view_own_call_log'));
    }

    public function rules(): array
    {
        return [
            'contact_id'   => ['required', 'integer', 'min:1'],
            'user_id'      => ['nullable', 'integer', 'min:1'],
            'start_time'   => ['required', 'date'],
            'end_time'     => ['required', 'date', 'after_or_equal:start_time'],
            'duration'     => ['nullable', 'integer', 'min:0', 'max:86400'], // ≤24h
            'call_type'    => ['nullable', 'string', 'in:incoming,outgoing,missed,internal'],
            'description'  => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_time.after_or_equal' => 'O horário de término deve ser igual ou posterior ao início.',
            'duration.max'            => 'Duração máxima é 24h (86400 segundos).',
        ];
    }
}
