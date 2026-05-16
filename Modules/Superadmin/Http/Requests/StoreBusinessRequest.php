<?php

declare(strict_types=1);

namespace Modules\Superadmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest — superadmin cria business novo (cliente SaaS novo).
 *
 * D8.c Security Wave 13 — extraído de BusinessController@store.
 * Cross-tenant intencional: superadmin provisiona novo tenant + cria owner user.
 *
 * Throttle 60/min aplicado em routes (RateLimiter 'superadmin').
 *
 * @see Modules/Superadmin/Http/Controllers/BusinessController.php@store
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class StoreBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        // SUPERADMIN: gate único — cria business novo cross-tenant.
        return $user->can('superadmin');
    }

    public function rules(): array
    {
        return [
            // Business
            'name'             => ['required', 'string', 'max:255'],
            'start_date'       => ['nullable', 'string'],
            'currency_id'      => ['required', 'integer', 'exists:currencies,id'],
            'tax_label_1'      => ['nullable', 'string', 'max:255'],
            'tax_number_1'     => ['nullable', 'string', 'max:255'],
            'tax_label_2'      => ['nullable', 'string', 'max:255'],
            'tax_number_2'     => ['nullable', 'string', 'max:255'],
            'time_zone'        => ['nullable', 'string', 'max:64'],
            'accounting_method' => ['nullable', 'string', 'in:fifo,lifo,avco'],
            'fy_start_month'   => ['nullable', 'integer', 'between:1,12'],

            // Owner user
            'surname'          => ['nullable', 'string', 'max:50'],
            'first_name'       => ['required', 'string', 'max:255'],
            'last_name'        => ['nullable', 'string', 'max:255'],
            'username'         => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', 'max:255'],
            'password'         => ['required', 'string', 'min:8', 'max:255'],

            // Subscription opcional
            'package_id'       => ['nullable', 'integer', 'exists:packages,id'],
            'paid_via'         => ['nullable', 'string', 'max:32'],
            'payment_transaction_id' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'Informe o nome do business.',
            'currency_id.required' => 'Selecione a moeda.',
            'username.required'    => 'Informe o username do owner.',
            'email.required'       => 'Informe o email do owner.',
            'email.email'          => 'Email inválido.',
            'password.required'    => 'Informe a senha do owner.',
            'password.min'         => 'Senha deve ter ao menos 8 caracteres.',
        ];
    }
}
