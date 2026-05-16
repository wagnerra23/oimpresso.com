<?php

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra atualizar Lead existente.
 *
 * Extraido de LeadController@update (Wave 15 D8 Security).
 * authorize() respeita pelo menos uma das permissoes access_all_leads / access_own_leads.
 *
 * @see Modules/Crm/Http/Controllers/LeadController.php
 */
class UpdateLeadRequest extends FormRequest
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

        $hasModule = (bool) $moduleUtil->hasThePermissionInSubscription($businessId, 'crm_module');
        $canAny = $user->can('crm.access_all_leads') || $user->can('crm.access_own_leads');

        return $hasModule && $canAny;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:50'],
            'prefix' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:191'],
            'middle_name' => ['nullable', 'string', 'max:191'],
            'last_name' => ['nullable', 'string', 'max:191'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'landline' => ['nullable', 'string', 'max:50'],
            'alternate_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:191'],
            'crm_source' => ['nullable', 'integer'],
            'crm_life_stage' => ['nullable', 'integer'],
            'dob' => ['nullable', 'string', 'max:30'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:30'],
            'user_id' => ['nullable', 'array'],
            'user_id.*' => ['integer'],
            'is_export' => ['nullable'],
        ];
    }
}
