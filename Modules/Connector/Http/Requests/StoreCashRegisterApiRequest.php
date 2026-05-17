<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreCashRegisterApiRequest — Wave 18 D8.f.
 *
 * Extrai validation rules de `Connector\Api\CashRegisterController::store` (POS
 * Android/Delphi/Woo via Passport token). Antes: validação inline no Controller
 * com `$request->validate([...])` — virá pra FormRequest na próxima Wave que
 * tocar o Controller.
 *
 * Tier 0 (ADR 0093): business_id NÃO chega no input — token Passport carrega
 * `user.business_id` em session. CashRegister.business_id é preenchido a partir
 * disso no Controller.
 *
 * Refs: ADR 0093 (multi-tenant) · skill `como-integrar` (FormRequest pattern).
 */
class StoreCashRegisterApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth ja garantida pelo middleware Passport (`auth:api`). Permission
        // `cash_register.create` opcional — Controller pode validar adicional.
        $user = $this->user();

        return $user !== null;
    }

    public function rules(): array
    {
        return [
            'location_id'       => ['required', 'integer', 'min:1'],
            'initial_amount'    => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'created_at'        => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'location_id.required'   => 'O ID da localização é obrigatório.',
            'initial_amount.numeric' => 'O valor inicial deve ser numérico.',
        ];
    }
}
