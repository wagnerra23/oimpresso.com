<?php

namespace Modules\Woocommerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * MapTaxRatesRequest — validação payload de mapeamento entre tax rates Woocommerce ↔ oimpresso.
 *
 * Wave 10 D8 Security — endpoint admin POST /woocommerce/map-taxrates.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id NUNCA aceito do input — resolvido via session no Controller.
 */
class MapTaxRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // Map associativo woocommerce_tax_rate_id => oimpresso_tax_rate_id
            'tax_rate_mapping'   => ['nullable', 'array', 'max:500'],
            'tax_rate_mapping.*' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
