<?php

declare(strict_types=1);

namespace Modules\Superadmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest — superadmin atualiza pacote SaaS.
 *
 * D8 Wave 15 Security — extraído de PackagesController@update (Request genérico).
 * SUPERADMIN: Package é entity cross-tenant intencional (ADR 0093 §exceções).
 *
 * @see Modules/Superadmin/Http/Controllers/PackagesController.php@update
 */
class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

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
            'update_subscriptions' => ['nullable'],
        ];
    }
}
