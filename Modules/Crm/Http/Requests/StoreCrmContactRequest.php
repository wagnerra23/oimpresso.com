<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra criar CrmContact (Customer ou Lead).
 *
 * Wave 18 RETRY D8.c: substitui validação inline de `ContactController::store`
 * (legacy UltimatePOS). Aplica mesma whitelist do `CrmLeadService` + extras
 * pra customer (`pay_term_number`, `credit_limit`).
 *
 * Tier 0 (ADR 0093): business_id NUNCA chega no input — vem de session no
 * Controller. PII (`tax_number`, `mobile`, `email`) é redacted pelo
 * `PiiRedactor` no LogsActivity (Crm entities tem `pii_fields_tracked`).
 */
class StoreCrmContactRequest extends FormRequest
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

        return (bool) $user->can('customer.create');
    }

    public function rules(): array
    {
        return [
            'first_name'    => ['required', 'string', 'max:255'],
            'last_name'     => ['nullable', 'string', 'max:255'],
            'email'         => ['nullable', 'email', 'max:255'],
            'mobile'        => ['nullable', 'string', 'max:25'],
            'type'          => ['required', 'string', 'in:lead,customer'],
            'tax_number'    => ['nullable', 'string', 'max:25'],
            'address_line_1' => ['nullable', 'string', 'max:500'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'zip_code'      => ['nullable', 'string', 'max:20'],
            'crm_source'    => ['nullable', 'integer', 'min:1'],
            'crm_life_stage' => ['nullable', 'integer', 'min:1'],
            'pay_term_number' => ['nullable', 'integer', 'min:0'],
            'credit_limit'  => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'O nome é obrigatório.',
            'type.in'             => 'Tipo deve ser lead ou customer.',
            'email.email'         => 'E-mail em formato inválido.',
        ];
    }
}
