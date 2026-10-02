/**
 * Regras de VALOR da edição de venda pela tela React (Sells/Edit) — UC-SEDIT-09.
 *
 * O servidor que grava é o SellPosController@update (o mesmo do form Blade), e estas regras
 * seguem a semântica DELE (TransactionUtil::createOrUpdateSellLines):
 * - `unit_price` = preço ANTES do desconto da linha;
 * - desconto FIXO é POR UNIDADE (preço − desconto), desconto % é sobre o preço;
 * - `unit_price_inc_tax` = preço depois do desconto + imposto da linha (é o que soma no total).
 *
 * Linha que o operador NÃO mexeu volta com os valores GRAVADOS, intactos — vendas antigas
 * podem ter `unit_price_inc_tax` gravado sem o desconto (tela de criação React), e recalcular
 * "consertaria" a linha em silêncio. O total é calculado no servidor por diferença.
 *
 * Todo número vai como texto pt-BR com 4 casas ("1,1250"): o servidor lê com num_uf, e um
 * número com ponto e 3 casas ("1.125") seria lido como milhar — o vetor do incidente de
 * valor inflado de 2026-06-05.
 */

export type DescontoTipo = 'fixed' | 'percentage';

/** Linha gravada como a consulta da edição devolve (aliases do SellController@edit). */
export type LinhaGravada = {
  transaction_sell_lines_id?: number;
  id?: number;
  product_id?: number;
  variation_id?: number | string | null;
  product_name?: string;
  sub_sku?: string | null;
  quantity_ordered?: number | string;
  unit_price_before_discount?: number | string | null;
  sell_price_inc_tax?: number | string | null;
  default_sell_price?: number | string | null;
  line_discount_amount?: number | string | null;
  line_discount_type?: DescontoTipo | null;
  item_tax?: number | string | null;
  tax_id?: number | string | null;
};

export type LinhaEdicao = {
  sell_line_id: number | null;
  product_id: number;
  variation_id: number | null;
  name: string;
  sku: string;
  quantity: number;
  /** preço ANTES do desconto (o que o operador edita) */
  unit_price: number;
  discount: number;
  discount_type: DescontoTipo;
  imei_number?: string;
  tax_id: number | null;
  /** imposto da linha / preço com desconto — mantém a proporção se o preço mudar */
  taxa_imposto: number;
  /** valores GRAVADOS — devolvidos intactos se preço e desconto não mudarem */
  gravado: { unit_price: number; discount: number; discount_type: DescontoTipo; unit_price_inc_tax: number; item_tax: number } | null;
};

const num = (v: unknown): number => {
  const n = Number(v ?? 0);
  return Number.isFinite(n) ? n : 0;
};

/** Texto pt-BR com 4 casas, sem milhar: o num_uf lê vírgula como decimal sem ambiguidade. */
export function numeroParaEnvio(n: number): string {
  return num(n).toFixed(4).replace('.', ',');
}

/** Preço depois do desconto da linha, na regra do servidor (fixo = por unidade). */
export function precoComDesconto(preco: number, desconto: number, tipo: DescontoTipo): number {
  const p = tipo === 'percentage' ? (preco * (100 - desconto)) / 100 : preco - desconto;
  return Math.max(p, 0);
}

/** Linha gravada → linha da tela. Preço = ANTES do desconto (não o preço já descontado). */
export function linhaDoBanco(sl: LinhaGravada): LinhaEdicao {
  const tipo: DescontoTipo = sl.line_discount_type === 'percentage' ? 'percentage' : 'fixed';
  const preco = num(sl.unit_price_before_discount ?? sl.default_sell_price);
  const desconto = num(sl.line_discount_amount);
  const itemTax = num(sl.item_tax);
  const comDesconto = precoComDesconto(preco, desconto, tipo);
  return {
    sell_line_id: num(sl.transaction_sell_lines_id ?? sl.id) || null,
    product_id: num(sl.product_id),
    variation_id: sl.variation_id != null ? num(sl.variation_id) : null,
    name: sl.product_name ?? '—',
    sku: sl.sub_sku ?? '',
    quantity: num(sl.quantity_ordered ?? 1),
    unit_price: preco,
    discount: desconto,
    discount_type: tipo,
    imei_number: '',
    tax_id: sl.tax_id != null && sl.tax_id !== '' ? num(sl.tax_id) : null,
    taxa_imposto: comDesconto > 0 ? itemTax / comDesconto : 0,
    gravado: {
      unit_price: preco,
      discount: desconto,
      discount_type: tipo,
      unit_price_inc_tax: num(sl.sell_price_inc_tax),
      item_tax: itemTax,
    },
  };
}

function inalterada(l: LinhaEdicao): boolean {
  const g = l.gravado;
  return g !== null && g.unit_price === l.unit_price && g.discount === l.discount && g.discount_type === l.discount_type;
}

/** Preço unitário final (com desconto e imposto) — o que a linha soma no total. */
export function precoFinalUnitario(l: LinhaEdicao): number {
  const comDesconto = precoComDesconto(l.unit_price, l.discount, l.discount_type);
  return comDesconto + comDesconto * l.taxa_imposto;
}

/** Subtotal exibido da linha, na regra do servidor. */
export function subtotalLinha(l: LinhaEdicao): number {
  return l.quantity * precoFinalUnitario(l);
}

/** Linha → payload do SellPosController@update (TransactionUtil::createOrUpdateSellLines). */
export function linhaParaEnvio(l: LinhaEdicao) {
  const comDesconto = precoComDesconto(l.unit_price, l.discount, l.discount_type);
  const manter = inalterada(l) && l.gravado !== null;
  const itemTax = manter ? l.gravado!.item_tax : comDesconto * l.taxa_imposto;
  const incTax = manter ? l.gravado!.unit_price_inc_tax : comDesconto + itemTax;
  return {
    transaction_sell_lines_id: l.sell_line_id ?? undefined,
    product_id: l.product_id,
    variation_id: l.variation_id,
    quantity: numeroParaEnvio(l.quantity),
    unit_price: numeroParaEnvio(l.unit_price),
    unit_price_inc_tax: numeroParaEnvio(incTax),
    item_tax: numeroParaEnvio(itemTax),
    tax_id: l.tax_id,
    line_discount_amount: numeroParaEnvio(l.discount),
    line_discount_type: l.discount_type,
    imei_number: l.imei_number ?? '',
  };
}
