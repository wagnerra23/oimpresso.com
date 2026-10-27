<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use Illuminate\Support\Facades\DB;

/**
 * US-COM-013 — pedidos de compra ainda abertos de um fornecedor, com o que falta receber por linha.
 *
 * O recebimento parcial vai pelo pedido de compra (decisão coordenação 2026-10-26): cada entrega
 * vira uma compra ligada ao pedido (`purchase_order_ids` + `purchases.*.purchase_order_line_id`),
 * o `ProductUtil::updatePurchaseOrderLine` soma em `po_quantity_purchased` e o
 * `TransactionUtil::updatePurchaseOrderStatus` leva o pedido a `partial`/`completed`. Este serviço
 * só LÊ: devolve o que a tela de compra precisa para mostrar Pedido / Já recebido / A receber.
 *
 * A soma "restante = quantity − po_quantity_purchased" é a mesma da lista de pedidos
 * (`PurchaseOrderController`, R5 do charter de PurchaseOrder/Index).
 *
 * Tier 0 (ADR 0093): pedido filtrado pela empresa; fornecedor e local vêm do request mas só
 * restringem dentro dela.
 */
class PedidosAbertosDoFornecedor
{
    /**
     * @return list<array{id:int, ref_no:?string, transaction_date:?string, delivery_date:?string, status:string, linhas:list<array<string,mixed>>}>
     */
    public function listar(int $businessId, int $contactId, ?int $locationId = null): array
    {
        $pedidos = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', 'purchase_order')
            ->where('contact_id', $contactId)
            ->whereIn('status', ['ordered', 'partial'])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->orderBy('transaction_date')
            ->get(['id', 'ref_no', 'transaction_date', 'delivery_date', 'status']);

        if ($pedidos->isEmpty()) {
            return [];
        }

        $linhas = DB::table('purchase_lines as pl')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->join('variations as v', 'v.id', '=', 'pl.variation_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->whereIn('pl.transaction_id', $pedidos->pluck('id')->all())
            ->where('p.business_id', $businessId)
            ->whereRaw('pl.quantity > pl.po_quantity_purchased')
            ->orderBy('pl.id')
            ->get([
                'pl.id', 'pl.transaction_id', 'pl.product_id', 'pl.variation_id', 'p.name as produto',
                'p.type as tipo', 'v.name as variacao', 'u.short_name as unidade', 'pl.quantity',
                'pl.po_quantity_purchased', 'pl.pp_without_discount', 'pl.discount_percent',
                'pl.purchase_price', 'pl.purchase_price_inc_tax', 'pl.item_tax', 'pl.tax_id',
            ])
            ->groupBy('transaction_id');

        $saida = [];
        foreach ($pedidos as $pedido) {
            $doPedido = $linhas->get($pedido->id, collect());
            if ($doPedido->isEmpty()) {
                continue; // tudo recebido, mas o status ainda não andou
            }
            $saida[] = [
                'id' => (int) $pedido->id,
                'ref_no' => $pedido->ref_no,
                'transaction_date' => $pedido->transaction_date,
                'delivery_date' => $pedido->delivery_date,
                'status' => (string) $pedido->status,
                'linhas' => $doPedido->map(fn ($l) => [
                    'purchase_order_line_id' => (int) $l->id,
                    'product_id' => (int) $l->product_id,
                    'variation_id' => (int) $l->variation_id,
                    'product_name' => $l->tipo === 'variable' ? "{$l->produto} {$l->variacao}" : (string) $l->produto,
                    'unit_name' => $l->unidade ?: 'un',
                    'pedido' => (float) $l->quantity,
                    'recebido' => (float) $l->po_quantity_purchased,
                    'a_receber' => (float) $l->quantity - (float) $l->po_quantity_purchased,
                    'pp_without_discount' => (float) $l->pp_without_discount,
                    'discount_percent' => (float) $l->discount_percent,
                    'purchase_price' => (float) $l->purchase_price,
                    'purchase_price_inc_tax' => (float) $l->purchase_price_inc_tax,
                    'item_tax' => (float) $l->item_tax,
                    'tax_id' => $l->tax_id !== null ? (int) $l->tax_id : null,
                ])->values()->all(),
            ];
        }

        return $saida;
    }
}
