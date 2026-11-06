<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\OficinaAuto\Entities\ServiceOrderItem;

/**
 * Wave 1.3 US-OFICINA-027 — FormRequest pra atualizar item existente.
 *
 * Mais permissivo que Store: todos campos opcionais (PATCH-like). Multi-tenant
 * Tier 0 garantido por Policy `update` no Controller + global scope do Model.
 *
 * @see Modules\OficinaAuto\Http\Controllers\ServiceOrderItemController::update
 */
class UpdateServiceOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('superadmin') || $user->can('oficinaauto.service_order.update');
    }

    public function rules(): array
    {
        return [
            'tipo'           => ['sometimes', 'required', 'string', 'in:' . implode(',', ServiceOrderItem::TIPOS_VALIDOS)],
            'descricao'      => ['sometimes', 'required', 'string', 'max:255'],
            'quantidade'     => ['sometimes', 'numeric', 'min:0.001', 'max:9999999.999'],
            'valor_unitario' => ['sometimes', 'numeric', 'min:0', 'max:9999999.99'],
            'valor_total'    => ['sometimes', 'numeric', 'min:0', 'max:9999999.99'],
            // exists ESCOPADO por business (Tier 0 ADR 0093): o store já recusa produto de
            // outra empresa no ServiceOrderItemService::addItem, mas o update faz fill() direto
            // e gravava o product_id alheio no item.
            'product_id'     => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn ($query) => $query->where(
                        'business_id',
                        (int) (session('user.business_id') ?? session('business.id') ?? 0)
                    )
                ),
            ],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
