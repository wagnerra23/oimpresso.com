<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra POST /api/sell (cria venda PDV via API external).
 *
 * Wave 18 RETRY D8.d: blindagem da rota mais sensível do Connector. POS
 * móvel + Delphi enviam payload denso de venda; aqui validamos só estrutura
 * top-level. Linhas de produto (`products[]`) + payments (`payments[]`) têm
 * sub-rules. `SellController` já roda lógica complexa (estoque, NFe trigger).
 *
 * Tier 0 (ADR 0093): business_id resolvido via token Passport. location_id
 * deve pertencer ao business (validado em queries scoped).
 */
class StoreSellPosApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'location_id'      => ['required', 'integer', 'min:1'],
            'contact_id'       => ['required', 'integer', 'min:1'],
            'transaction_date' => ['required', 'date'],
            'status'           => ['nullable', 'string', 'in:draft,final,quotation,proforma'],
            'discount_amount'  => ['nullable', 'numeric', 'min:0'],
            'discount_type'    => ['nullable', 'string', 'in:fixed,percentage'],
            'tax_id'           => ['nullable', 'integer', 'min:1'],
            'tax_amount'       => ['nullable', 'numeric', 'min:0'],
            'sale_note'        => ['nullable', 'string', 'max:5000'],
            'staff_note'       => ['nullable', 'string', 'max:5000'],
            'shipping_charges' => ['nullable', 'numeric', 'min:0'],
            'shipping_status'  => ['nullable', 'string', 'max:50'],

            'products'                   => ['required', 'array', 'min:1', 'max:500'],
            'products.*.product_id'      => ['required', 'integer', 'min:1'],
            'products.*.variation_id'    => ['required', 'integer', 'min:1'],
            'products.*.quantity'        => ['required', 'numeric', 'min:0.0001'],
            'products.*.unit_price'      => ['required', 'numeric', 'min:0'],
            'products.*.unit_price_inc_tax' => ['nullable', 'numeric', 'min:0'],

            'payments'             => ['nullable', 'array', 'max:20'],
            'payments.*.amount'    => ['required_with:payments', 'numeric', 'min:0'],
            'payments.*.method'    => ['required_with:payments', 'string', 'max:50'],
            'payments.*.paid_on'   => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'location_id.required' => 'Localização é obrigatória para PDV.',
            'contact_id.required'  => 'Cliente é obrigatório.',
            'products.required'    => 'Pelo menos 1 produto é obrigatório.',
            'products.max'         => 'Máximo 500 itens por venda.',
            'discount_type.in'     => 'Tipo de desconto deve ser fixed ou percentage.',
        ];
    }
}
