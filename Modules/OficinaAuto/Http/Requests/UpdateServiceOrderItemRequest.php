<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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
            'product_id'     => ['nullable', 'integer'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
