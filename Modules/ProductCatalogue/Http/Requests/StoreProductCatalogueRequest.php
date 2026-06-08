<?php

declare(strict_types=1);

namespace Modules\ProductCatalogue\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8 Security — Wave 18 saturação ProductCatalogue.
 *
 * FormRequest pra criar entrada em catálogo público. Endpoint admin (auth),
 * mas defense-in-depth: valida business_id matches sessão (anti-CSRF tenant
 * cross-mod), location_id pertence ao mesmo business, prefix sem XSS.
 *
 * @see Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController
 */
class StoreProductCatalogueRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permissao via middleware UltimatePOS (product_catalogue.create)
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:200'],
            'prefix'       => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'location_id'  => ['nullable', 'integer', 'min:1', 'exists:business_locations,id'],
            'category_id'  => ['nullable', 'integer', 'min:1'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'prefix.regex' => 'Prefix aceita apenas letras, digitos, hifens e underscores.',
            'name.max'     => 'Nome do catalogo nao pode passar de 200 caracteres.',
        ];
    }

    /**
     * Cross-field: location_id (se presente) deve pertencer ao business_id da sessao.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $sessionBiz = session('user.business_id') ?? session('business.id');
            $locationId = $this->input('location_id');

            if ($sessionBiz === null || $locationId === null) {
                return;
            }

            $belongs = \DB::table('business_locations')
                ->where('id', $locationId)
                ->where('business_id', $sessionBiz)
                ->exists();

            if (! $belongs) {
                $v->errors()->add('location_id', 'Location nao pertence ao business da sessao.');
            }
        });
    }
}
