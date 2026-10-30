// Gravar a venda da V3 — monta o MESMO payload que `Sells/Create.tsx` posta em `/pos`.
//
// POR QUE NÃO HÁ CÁLCULO NOVO NO SERVIDOR
// `SellPosController@store` já é o caminho de produção (ROTA LIVRE, biz=4). A V3 só
// troca a tela: o pedido sai no formato do `transform()` do Create.tsx (linhas
// 699-779 em main @ e322a8117a) e o servidor faz o resto — linhas, estoque, pagamento.
//
// ⚠️ O `final_total` NÃO é recalculado pelo servidor: `TransactionUtil::createSellTransaction`
// grava `num_uf($input['final_total'])`. Por isso o total daqui segue a fórmula do
// Create.tsx (linhas 323-360) ao pé da letra, e sai arredondado a 2 casas
// (incidente `num_uf` de 2026-06-05: float de 5 casas virou milhar e inflou o total).
//
// Escopo desta primeira versão: venda NOVA, final, simples. O que a tela permite e o
// Create não sabe gravar (acréscimo, imposto do pedido, PIX/boleto) RECUSA com motivo
// em vez de sumir em silêncio — perder um acréscimo seria gravar valor errado.

import { parseBR, submitSafe } from './numeros';

export type ItemParaGravar = {
  productId: number | null;
  variationId: number | null;
  nome: string;
  sku: string;
  qtd: string; // texto pt-BR, como na tela
  preco: string;
  desc: string; // % da linha
  acr: string; // % da linha — não suportado pelo store
  enableStock: boolean;
  tipoProduto: string;
};

export type VendaParaGravar = {
  locationId: number;
  contactId: number;
  transactionDate: string; // d/m/Y H:i — o formato que o store parseia explicitamente
  itens: ItemParaGravar[];
  descTipo: 'percentual' | 'fixo';
  descVal: string;
  acr: string;
  frete: string;
  pagamentos: { m: string; v: string }[];
};

/** Rótulo da tela → chave de `Util::payment_types`. Só o que é à vista sem integração. */
export const METODO_DO_PAGAMENTO: Record<string, string> = {
  Dinheiro: 'cash',
  'Cartão de crédito': 'card',
};

/** Total da linha como o Create.tsx: bruto − desconto da linha, nunca negativo. */
function linhaCreate(qtd: number, preco: number, descPct: number): number {
  return Math.max(qtd * preco - (qtd * preco * descPct) / 100, 0);
}

/** Total da venda pela fórmula do Create.tsx (`totalGeral`), arredondado a 2 casas. */
export function totalParaGravar(v: Pick<VendaParaGravar, 'itens' | 'descTipo' | 'descVal' | 'frete'>): number {
  const subtotal = v.itens.reduce((s, l) => s + linhaCreate(parseBR(l.qtd), parseBR(l.preco), parseBR(l.desc)), 0);
  const desconto = v.descTipo === 'percentual' ? (subtotal * parseBR(v.descVal)) / 100 : parseBR(v.descVal);
  return submitSafe(Math.max(subtotal - desconto + parseBR(v.frete), 0));
}

export type ResultadoGravacao =
  | { ok: true; total: number; payload: Record<string, unknown> }
  | { ok: false; motivo: string };

export function montarPayloadVenda(v: VendaParaGravar): ResultadoGravacao {
  if (v.itens.length === 0) return { ok: false, motivo: 'A venda não tem item.' };
  const semId = v.itens.find((l) => !l.productId || !l.variationId);
  if (semId) return { ok: false, motivo: `${semId.nome} não veio do cadastro de produtos.` };
  const invalida = v.itens.find((l) => parseBR(l.qtd) <= 0 || parseBR(l.preco) < 0);
  if (invalida) return { ok: false, motivo: `${invalida.nome} está com quantidade ou preço inválido.` };
  if (v.itens.some((l) => parseBR(l.acr || '0') !== 0) || parseBR(v.acr) !== 0) {
    return { ok: false, motivo: 'Acréscimo ainda não é gravado — zere o acréscimo para salvar.' };
  }
  const metodoDesconhecido = v.pagamentos.find((p) => !METODO_DO_PAGAMENTO[p.m]);
  if (metodoDesconhecido) {
    return { ok: false, motivo: `${metodoDesconhecido.m} ainda não é gravado — use Dinheiro ou Cartão.` };
  }

  const total = totalParaGravar(v);
  const frete = submitSafe(parseBR(v.frete));
  const pagamentos = v.pagamentos.length
    ? v.pagamentos.map((p) => ({
        amount: submitSafe(parseBR(p.v)),
        method: METODO_DO_PAGAMENTO[p.m],
        paid_on: '',
        account_id: null,
        note: '',
      }))
    : [{ amount: 0, method: 'cash', paid_on: '', account_id: null, note: '' }];

  const products = v.itens.map((l) => {
    // Sem arredondar: o total usa o valor cheio, e a linha gravada tem de ser a mesma conta
    // (3,333% arredondado a 3,33 na linha divergiria do final_total). Só o total arredonda.
    const descPct = parseBR(l.desc);
    const preco = parseBR(l.preco);
    return {
      product_id: l.productId,
      variation_id: l.variationId,
      name: l.nome,
      variation: null,
      sku: l.sku,
      quantity: parseBR(l.qtd),
      unit_price: preco,
      discount: descPct,
      discount_type: 'percentage',
      imei_number: '',
      unit_price_inc_tax: preco,
      item_tax: 0,
      tax_id: null,
      line_discount_type: 'percentage',
      line_discount_amount: descPct,
      enable_stock: l.enableStock ? 1 : 0,
      product_type: l.tipoProduto,
    };
  });

  const discountType = v.descTipo === 'percentual' ? 'percentage' : 'fixed';
  const discountAmount = submitSafe(parseBR(v.descVal));

  return {
    ok: true,
    total,
    payload: {
      location_id: v.locationId,
      contact_id: v.contactId,
      transaction_date: v.transactionDate,
      status: 'final',
      invoice_no: '',
      pay_term_number: '',
      pay_term_type: 'days',
      sinal_minimo_pct: null,
      commission_agent: null,
      price_group: null,
      default_price_group: null,
      tax_rate_id: null,
      vehicle_id: null,
      products,
      payment: pagamentos,
      discount_type: discountType,
      discount_amount: discountAmount,
      rp_redeemed: 0,
      rp_redeemed_amount: '0.00',
      sale_note: '',
      additional_notes: '',
      staff_note: '',
      is_recurring: 0,
      customer_secondary_address: '',
      shipping_details: '',
      shipping_address: '',
      shipping_charges: frete,
      shipping_status: '',
      delivered_to: '',
      is_direct_sale: 1,
      is_save_and_print: 0,
      final_total: total,
    },
  };
}
