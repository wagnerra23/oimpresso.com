<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra PATCH/PUT CrmCallLog.
 *
 * Wave 18 RETRY D8.b: PATCH parcial (`sometimes`) pra editar log de chamada
 * existente — uso típico: revisor ajusta `description` / `call_type` após
 * conversa real ser transcrita. NÃO permite trocar `contact_id` (audit trail).
 *
 * Tier 0 (ADR 0093): authorize() valida ownership via permission `view_own`
 * ou `view_all`. business_id NUNCA chega no input.
 */
class UpdateCallLogRequest extends FormRequest
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
        if (! app(ModuleUtil::class)->hasThePermissionInSubscription($businessId, 'crm_module')) {
            return false;
        }

        return (bool) ($user->can('crm.view_all_call_log') || $user->can('crm.view_own_call_log'));
    }

    public function rules(): array
    {
        return [
            'start_time'  => ['sometimes', 'date'],
            'end_time'    => ['sometimes', 'date', 'after_or_equal:start_time'],
            'duration'    => ['sometimes', 'integer', 'min:0', 'max:86400'],
            'call_type'   => ['sometimes', 'string', 'in:incoming,outgoing,missed,internal'],
            'description' => ['sometimes', 'string', 'max:2000'],
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
