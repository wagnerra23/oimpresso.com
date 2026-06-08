<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra POST /api/product (cria Produto via API external).
 *
 * Wave 18 RETRY D8.c: substitui validação inline de
 * `ProductController::store` (Connector API). POS móvel + Delphi sync usam
 * mesmo endpoint — payload comum: `name`, `unit_id`, `category_id`, `type`,
 * `barcode_type`, `sku`.
 *
 * Tier 0 (ADR 0093): business_id resolvido via token Passport (NÃO chega no
 * payload). FK validation (unit/category/brand existência) acontece no
 * Controller via Eloquent firstOrFail() scoped por businessId.
 */
class StoreProductApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:191'],
            'sku'          => ['nullable', 'string', 'max:191'],
            'barcode_type' => ['nullable', 'string', 'max:25'],
            'unit_id'      => ['required', 'integer', 'min:1'],
            'category_id'  => ['nullable', 'integer', 'min:1'],
            'sub_category_id' => ['nullable', 'integer', 'min:1'],
            'brand_id'     => ['nullable', 'integer', 'min:1'],
            'tax_id'       => ['nullable', 'integer', 'min:1'],
            'tax_type'     => ['nullable', 'string', 'in:inclusive,exclusive'],
            'type'         => ['required', 'string', 'in:single,variable,combo,modifier'],
            'enable_stock' => ['nullable', 'boolean'],
            'alert_quantity' => ['nullable', 'numeric', 'min:0'],
            'product_description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Nome do produto é obrigatório.',
            'unit_id.required' => 'Unidade é obrigatória.',
            'type.in'          => 'Tipo deve ser single, variable, combo ou modifier.',
        ];
    }
}
