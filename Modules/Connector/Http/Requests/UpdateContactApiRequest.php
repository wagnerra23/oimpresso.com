<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra PUT/PATCH /api/contactapi/{id}.
 *
 * Wave 18 RETRY D8.b: PATCH parcial (`sometimes`) — POS móvel atualiza
 * contato sem reenviar payload inteiro. Validação espelha
 * `StoreContactApiRequest` mas com `sometimes`. `ContactPayloadValidatorService`
 * roda DEPOIS no Service pra validar formato CPF/CNPJ/email/mobile.
 *
 * Tier 0 (ADR 0093): business_id resolvido via token Passport. Ownership
 * checada no Controller (`Contact::where('business_id', $businessId)`).
 */
class UpdateContactApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'first_name'     => ['sometimes', 'string', 'max:255'],
            'middle_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'type'           => ['sometimes', 'string', 'in:supplier,customer,both'],
            'email'          => ['sometimes', 'nullable', 'email', 'max:255'],
            'mobile'         => ['sometimes', 'nullable', 'string', 'max:25'],
            'tax_number'     => ['sometimes', 'nullable', 'string', 'max:25'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:500'],
            'city'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'state'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'zip_code'       => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in'      => 'Tipo deve ser supplier, customer ou both.',
            'email.email'  => 'E-mail em formato inválido.',
        ];
    }
}
