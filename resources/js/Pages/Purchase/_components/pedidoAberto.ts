// US-COM-013 — tipos e a montagem das linhas do pedido de compra recebido em partes.
// Fica fora do ReceberDoPedido.tsx porque o arquivo de componente só exporta componente
// (react-refresh/only-export-components).

export interface LinhaDoPedido {
  purchase_order_line_id: number;
  product_id: number;
  variation_id: number;
  product_name: string;
  unit_name: string;
  pedido: number;
  recebido: number;
  a_receber: number;
  pp_without_discount: number;
  discount_percent: number;
  purchase_price: number;
  purchase_price_inc_tax: number;
  item_tax: number;
  tax_id: number | null;
}

export interface PedidoAberto {
  id: number;
  ref_no: string | null;
  transaction_date: string | null;
  delivery_date: string | null;
  status: string;
  linhas: LinhaDoPedido[];
}

/** Linhas da compra a partir do pedido: cada uma entra com o que falta receber e os preços do pedido. */
export function linhasDoPedido(pedido: PedidoAberto) {
  return pedido.linhas.map((l) => ({
    product_name: l.product_name,
    product_id: l.product_id,
    variation_id: l.variation_id,
    unit_id: null,
    unit_name: l.unit_name,
    quantity: l.a_receber,
    pp_without_discount: l.pp_without_discount,
    discount_percent: l.discount_percent,
    item_tax: l.item_tax,
    tax_id: l.tax_id,
    purchase_price: l.purchase_price,
    purchase_price_inc_tax: l.purchase_price_inc_tax,
    purchase_order_line_id: l.purchase_order_line_id,
    po_pedido: l.pedido,
    po_recebido: l.recebido,
  }));
}
