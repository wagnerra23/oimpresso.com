<?php

declare(strict_types=1);

namespace Modules\ProductCatalogue\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8 Security — Wave 18 saturação ProductCatalogue.
 *
 * Update de entrada em catalogo. Mesmas validacoes do Store + verifica que
 * o registro pertence ao business_id da sessao (anti-IDOR).
 *
 * @see Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController@update
 */
class UpdateProductCatalogueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:200'],
            'prefix'       => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'location_id'  => ['nullable', 'integer', 'min:1'],
            'category_id'  => ['nullable', 'integer', 'min:1'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    /**
     * IDOR defense: registro (id da rota) tem que pertencer ao business da sessao.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $sessionBiz = session('user.business_id') ?? session('business.id');
            $catalogId  = (int) $this->route('id') ?: (int) $this->route('product_catalogue');

            if ($sessionBiz === null || $catalogId <= 0) {
                return;
            }

            $belongs = \DB::table('product_catalogues')
                ->where('id', $catalogId)
                ->where('business_id', $sessionBiz)
                ->exists();

            if (! $belongs) {
                $v->errors()->add('id', 'Catalogo nao pertence ao business da sessao.');
            }
        });
    }
}
