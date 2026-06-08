<?php

declare(strict_types=1);

namespace Modules\Superadmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest — superadmin cria pacote SaaS (Modules/Superadmin/Entities/Package).
 *
 * D8 Wave 15 Security — extraído de PackagesController@store (Request genérico).
 * SUPERADMIN: Package é entity cross-tenant intencional (ADR 0093 §exceções).
 *
 * Throttle 60/min via RateLimiter 'superadmin' aplicado em routes.
 *
 * @see Modules/Superadmin/Http/Controllers/PackagesController.php@store
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        // SUPERADMIN: gate único — Package é cross-tenant intencional.
        return $user->can('superadmin');
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:5000'],
            'location_count'    => ['nullable', 'integer', 'min:0'],
            'user_count'        => ['nullable', 'integer', 'min:0'],
            'product_count'     => ['nullable', 'integer', 'min:0'],
            'invoice_count'     => ['nullable', 'integer', 'min:0'],
            'interval'          => ['required', 'string', 'in:days,months,years'],
            'interval_count'    => ['required', 'integer', 'min:1', 'max:365'],
            'trial_days'        => ['nullable', 'integer', 'min:0', 'max:365'],
            'price'             => ['required', 'string', 'max:32'],
            'sort_order'        => ['nullable', 'integer'],
            'is_active'         => ['nullable'],
            'custom_permissions' => ['nullable', 'array'],
            'is_private'        => ['nullable'],
            'is_one_time'       => ['nullable'],
            'enable_custom_link' => ['nullable'],
            'custom_link'       => ['nullable', 'string', 'max:512'],
            'custom_link_text'  => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'Informe o nome do pacote.',
            'interval.required'      => 'Selecione o intervalo (dias/meses/anos).',
            'interval.in'            => 'Intervalo deve ser days, months ou years.',
            'interval_count.required' => 'Informe a quantidade de intervalos.',
            'price.required'         => 'Informe o preço do pacote.',
        ];
    }
}
