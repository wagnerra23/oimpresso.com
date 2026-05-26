<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\OficinaAuto\Entities\ServiceOrderItem;

/**
 * Wave 1.3 US-OFICINA-027 — FormRequest pra criar item de OS (peça / mão-de-obra / terceiro).
 *
 * Multi-tenant Tier 0 [ADR 0093]: business_id NUNCA vem do request — Controller passa
 * `auth()->user()->business_id` explicitamente pro Service. Request só valida shape +
 * permission. Service ainda faz dupla-checagem cross-tenant (OS pertence ao biz).
 *
 * @see Modules\OficinaAuto\Http\Controllers\ServiceOrderItemController::store
 * @see Modules\OficinaAuto\Services\ServiceOrderItemService::addItem
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-027
 */
class StoreServiceOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        // Reusa permission oficinaauto.service_order.update — quem edita OS pode lançar items.
        return $user->can('superadmin') || $user->can('oficinaauto.service_order.update');
    }

    public function rules(): array
    {
        return [
            'tipo'           => ['required', 'string', 'in:' . implode(',', ServiceOrderItem::TIPOS_VALIDOS)],
            'descricao'      => ['required', 'string', 'max:255'],
            'quantidade'     => ['nullable', 'numeric', 'min:0.001', 'max:9999999.999'],
            'valor_unitario' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'valor_total'    => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'product_id'     => ['nullable', 'integer'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required'      => 'Selecione o tipo (peça, mão-de-obra ou serviço terceiro).',
            'tipo.in'            => 'Tipo inválido. Permitidos: peca, mao_obra, servico_terceiro.',
            'descricao.required' => 'Informe a descrição do item.',
            'quantidade.min'     => 'Quantidade deve ser maior que zero.',
        ];
    }
}
