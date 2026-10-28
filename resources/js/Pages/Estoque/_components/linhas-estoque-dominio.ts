// Linhas de produto dos formulários de estoque (transferência e ajuste) — o que NÃO é tela.
//
// A linha nasce da resposta de `GET /stock-transfers/produto-linha` (StockTransferController@
// linhaDoProduto): produto, variação, saldo NAQUELE local, último custo de compra, unidades e
// lotes — os mesmos dados que a Blade punha na linha (`get_product_row`). A tela não inventa
// produto: sem essa resposta não existe linha.
//
// A tela não grava nem calcula estoque. Quem tira e põe saldo é o `store()`/`update()` do
// servidor. Aqui só se monta o pedido, e todo número vai por `paraNumUf` (vírgula decimal, sem
// milhar): é o único formato que o `num_uf` do servidor lê sem ambiguidade. `Number` cru manda
// "1.5", que o `num_uf` lê como milhar (incidente de 2026-06-05).

import { paraNumUf } from '@/Lib/numberPtBR';

/**
 * Casas que a tela mostra E envia — a mesma nos dois lados (ver `paraNumUf`): o que a tela
 * mostra é o que o banco grava. 2 casas, como a Blade (`@num_format`/`@format_quantity`).
 */
export const CASAS_QTD_ESTOQUE = 2;
export const CASAS_CUSTO_ESTOQUE = 2;

export interface SubUnidade {
  id: number;
  nome: string;
  multiplicador: number;
  permite_decimal: boolean;
}

export interface LoteDaLinha {
  purchase_line_id: number;
  lot_number: string | null;
  exp_date: string | null;
  qty_available: number;
}

/** Resposta de `GET /stock-transfers/produto-linha`. */
export interface DadosDaLinha {
  product_id: number;
  variation_id: number;
  nome: string;
  sub_sku: string;
  enable_stock: number;
  qty_available: number;
  unidade: string;
  unit_id: number;
  permite_decimal: boolean;
  custo: number;
  sub_unidades: SubUnidade[];
  usa_lote: boolean;
  usa_validade: boolean;
  lotes: LoteDaLinha[];
}

export interface LinhaEstoque {
  dados: DadosDaLinha;
  /** Quantidade na unidade escolhida. */
  quantidade: number;
  /** Custo unitário na unidade escolhida (base × multiplicador). */
  custo: number;
  /** null = unidade base. */
  sub_unit_id: number | null;
  lot_no_line_id: number | null;
  /** Só na edição de transferência: a linha já gravada. */
  transaction_sell_lines_id?: number | null;
}

export function multiplicadorDaLinha(l: LinhaEstoque): number {
  if (l.sub_unit_id === null) return 1;
  const s = l.dados.sub_unidades.find((u) => u.id === l.sub_unit_id);
  return s ? s.multiplicador : 1;
}

/** Linha nova: 1 unidade, custo do produto (o mesmo `last_purchased_price` da Blade). */
export function linhaNova(dados: DadosDaLinha): LinhaEstoque {
  return { dados, quantidade: 1, custo: dados.custo, sub_unit_id: null, lot_no_line_id: null };
}

/** Troca a unidade e reprecifica pelo custo base, como a Blade (`hidden_base_unit_price × multiplier`). */
export function trocarUnidade(l: LinhaEstoque, subUnitId: number | null): LinhaEstoque {
  const nova = { ...l, sub_unit_id: subUnitId };
  return { ...nova, custo: l.dados.custo * multiplicadorDaLinha(nova) };
}

/** Saldo disponível para a linha, na unidade base: o do lote escolhido, senão o do local. */
export function saldoDisponivel(l: LinhaEstoque): number {
  if (l.lot_no_line_id !== null) {
    const lote = l.dados.lotes.find((x) => x.purchase_line_id === l.lot_no_line_id);
    if (lote) return lote.qty_available;
  }
  return l.dados.qty_available;
}

/** Quantidade na unidade base (é o que sai do estoque). */
export function quantidadeBase(l: LinhaEstoque): number {
  return l.quantidade * multiplicadorDaLinha(l);
}

/**
 * Erro da linha, ou null. Mesmas recusas da Blade: quantidade maior que zero e, em produto que
 * controla estoque, não mais que o saldo do local (ou do lote). Na edição a Blade usa o mesmo
 * limite (`qty_available` do local, sem somar o já pedido): transferência pendente não baixa saldo.
 */
export function erroDaLinha(l: LinhaEstoque): string | null {
  if (!(l.quantidade > 0)) return 'Informe uma quantidade maior que zero.';
  const unidadeDecimal =
    l.sub_unit_id === null
      ? l.dados.permite_decimal
      : (l.dados.sub_unidades.find((u) => u.id === l.sub_unit_id)?.permite_decimal ?? l.dados.permite_decimal);
  if (!unidadeDecimal && !Number.isInteger(l.quantidade)) return 'Esta unidade não aceita fração.';
  if (l.dados.enable_stock === 1) {
    const disponivel = saldoDisponivel(l);
    if (quantidadeBase(l) > disponivel + 1e-9) {
      return `Só há ${disponivel.toLocaleString('pt-BR')} ${l.dados.unidade} disponível.`;
    }
  }
  return null;
}

/** Subtotal exibido. Não é enviado: o servidor grava quantidade e custo. */
export function subtotal(l: LinhaEstoque): number {
  return l.quantidade * l.custo;
}

export function somaLinhas(linhas: LinhaEstoque[]): number {
  return linhas.reduce((acc, l) => acc + subtotal(l), 0);
}

/** Item de `products[]` no formato que `StockTransferController@store/update` lê. */
export interface ItemTransferencia {
  product_id: number;
  variation_id: number;
  enable_stock: number;
  quantity: string;
  unit_price: string;
  product_unit_id: number;
  base_unit_multiplier: string;
  sub_unit_id?: number;
  lot_no_line_id?: number;
  transaction_sell_lines_id?: number;
}

export function itemTransferencia(l: LinhaEstoque): ItemTransferencia {
  const item: ItemTransferencia = {
    product_id: l.dados.product_id,
    variation_id: l.dados.variation_id,
    enable_stock: l.dados.enable_stock,
    quantity: paraNumUf(l.quantidade, CASAS_QTD_ESTOQUE),
    unit_price: paraNumUf(l.custo, CASAS_CUSTO_ESTOQUE),
    product_unit_id: l.dados.unit_id,
    // ⚠️ NÃO vai por `paraNumUf`: o servidor usa este campo CRU em conta PHP
    // (`$qtd * $product['base_unit_multiplier']`, StockTransferController@store/update), sem
    // `num_uf`. "0,5" viraria 0 ali e a baixa sairia zerada. A Blade manda o número com ponto
    // (`value="{{$multiplier}}"`); aqui também.
    base_unit_multiplier: String(multiplicadorDaLinha(l)),
  };
  if (l.sub_unit_id !== null) item.sub_unit_id = l.sub_unit_id;
  if (l.lot_no_line_id !== null) item.lot_no_line_id = l.lot_no_line_id;
  if (l.transaction_sell_lines_id) item.transaction_sell_lines_id = l.transaction_sell_lines_id;
  return item;
}

/** Item de `products[]` no formato que `StockAdjustmentController@store` lê (sem unidade: a Blade do ajuste não tinha). */
export interface ItemAjuste {
  product_id: number;
  variation_id: number;
  enable_stock: number;
  quantity: string;
  unit_price: string;
  lot_no_line_id?: number;
}

export function itemAjuste(l: LinhaEstoque): ItemAjuste {
  const item: ItemAjuste = {
    product_id: l.dados.product_id,
    variation_id: l.dados.variation_id,
    enable_stock: l.dados.enable_stock,
    quantity: paraNumUf(l.quantidade, CASAS_QTD_ESTOQUE),
    unit_price: paraNumUf(l.custo, CASAS_CUSTO_ESTOQUE),
  };
  if (l.lot_no_line_id !== null) item.lot_no_line_id = l.lot_no_line_id;
  return item;
}

/** A mesma variação duas vezes na mesma movimentação: o banco recusa (`uk_tsl_dup_prevent`). */
export function jaTem(linhas: LinhaEstoque[], variationId: number): boolean {
  return linhas.some((l) => l.dados.variation_id === variationId);
}

/** Busca os dados da linha no servidor. `null` quando o produto não tem linha naquele local. */
export async function buscarLinha(variationId: number, locationId: number): Promise<DadosDaLinha | null> {
  const q = new URLSearchParams({ variation_id: String(variationId), location_id: String(locationId) });
  const r = await fetch(`/stock-transfers/produto-linha?${q}`, {
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  });
  if (!r.ok) return null;
  return (await r.json()) as DadosDaLinha;
}
