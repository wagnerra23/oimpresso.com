<?php

declare(strict_types=1);

namespace Modules\ProductCatalogue\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8 Security — Wave 17 saturação (97% module-grade).
 *
 * FormRequest pra rota publica /show-catalogue/{business_id}/{product_id}.
 * Endpoint NAO tem auth (acessado via QR/link compartilhado). Atacante pode
 * varrer business_id × product_id cross-tenant. Defenses:
 *
 *  - business_id e product_id devem ser inteiros positivos
 *  - product DEVE existir e pertencer ao business_id da URL (anti-enumeration)
 *  - location_id (query string) é opcional mas se presente deve ser inteiro válido
 *
 * Equivalente ShowPublicCatalogueRequest mas pra rota /show-catalogue (detalhe
 * produto). Defense-in-depth alem do Product::where(business_id) que ja escopa
 * por tenant via Repository.
 *
 * @see Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController@show
 * @see Modules\ProductCatalogue\Http\Requests\ShowPublicCatalogueRequest (pattern referência)
 */
class ShowProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Rota publica via QR/link
    }

    public function rules(): array
    {
        return [
            'business_id' => ['required', 'integer', 'min:1', 'exists:business,id'],
            'product_id'  => ['required', 'integer', 'min:1'],
            'location_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'business_id.exists' => 'Business inválido.',
            'product_id.min'     => 'product_id deve ser positivo.',
        ];
    }

    /**
     * Cross-field: product DEVE pertencer ao business_id da URL.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $businessId = (int) $this->route('business_id');
            $productId  = (int) $this->route('product_id') ?: (int) $this->route('id');

            if ($businessId <= 0 || $productId <= 0) {
                return;
            }

            $exists = \DB::table('products')
                ->where('id', $productId)
                ->where('business_id', $businessId)
                ->where('is_inactive', 0)
                ->exists();

            if (! $exists) {
                $v->errors()->add('product_id', 'Produto nao encontrado pra este business.');
            }
        });
    }

    /**
     * URL params nao vem em $request->input — Laravel usa route()->parameters().
     */
    public function all($keys = null): array
    {
        $data = parent::all($keys);
        $data['business_id'] = $this->route('business_id');
        $data['product_id']  = $this->route('product_id') ?: $this->route('id');
        $data['location_id'] = $this->input('location_id');

        return $data;
    }
}
