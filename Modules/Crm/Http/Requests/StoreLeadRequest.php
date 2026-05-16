<?php

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra criar Lead (CrmContact com type=lead) no Crm.
 *
 * Extraido de LeadController@store (Wave 15 D8 Security).
 * Antes: abort(403) inline + zero validate(); aceitava qualquer payload via $request->only(...).
 * Agora: authorize() centraliza permissão + rules() formaliza campos esperados.
 *
 * NUNCA fixar business_id em rules (multi-tenant scope automatico via session — ADR 0093).
 *
 * @see Modules/Crm/Http/Controllers/LeadController.php
 */
class StoreLeadRequest extends FormRequest
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

    /**
     * Regras tolerantes — preserva back-compat com formulario contact.create (UltimatePOS herdado).
     * Maioria dos campos é opcional (custom_fields, shipping, etc); strings limit 191 evita overflow.
     */
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
            'city' => ['nullable', 'string', 'max:191'],
            'state' => ['nullable', 'string', 'max:191'],
            'country' => ['nullable', 'string', 'max:191'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'contact_id' => ['nullable', 'string', 'max:191'],
            'crm_source' => ['nullable', 'integer'],
            'crm_life_stage' => ['nullable', 'integer'],
            'dob' => ['nullable', 'string', 'max:30'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:30'],
            'supplier_business_name' => ['nullable', 'string', 'max:191'],
            'user_id' => ['nullable', 'array'],
            'user_id.*' => ['integer'],
            'is_export' => ['nullable'],
        ];
    }
}
