<?php

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra atualizar follow-up (Schedule) no Crm.
 *
 * Extraido de ScheduleController@update (D8.c Security — Onda 3).
 * Authorize cobre permissoes crm.access_all_schedule / crm.access_own_schedule
 * (validacao de ownership do recurso continua no Controller — depende do $id).
 */
class UpdateScheduleRequest extends FormRequest
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

        if (! $moduleUtil->hasThePermissionInSubscription($businessId, 'crm_module')) {
            return false;
        }

        return $user->can('crm.access_all_schedule') || $user->can('crm.access_own_schedule');
    }

    public function rules(): array
    {
        return [
            'start_datetime' => ['nullable', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'contact_id' => ['nullable', 'integer'],
            'schedule_type' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'followup_category_id' => ['nullable', 'integer'],
            'follow_up_by' => ['nullable', 'string', 'max:50'],
            'schedule_for' => ['nullable', 'in:customer,lead'],
            'user_id' => ['nullable', 'array'],
            'user_id.*' => ['integer'],
        ];
    }
}
