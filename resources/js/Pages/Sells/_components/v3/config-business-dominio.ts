// Configurações do business na Venda V3 — as mesmas que a tela de venda do Blade obedece.
//
// O servidor (`SellsV3Controller::configDaVenda`) só lê `pos_settings` e as colunas de
// `business`; a regra mora aqui, pura, para o vitest provar. Cada função cita de onde a
// regra veio no Blade/pos.js — nenhuma foi inventada para a V3.
//
// Vale só gravando (flag `useV3SellsGravar`): sem a flag a tela é preview de cena e
// nada daqui é consultado.

import { parseBR } from './numeros';

export type ConfigVenda = {
  dataEditavel: boolean;
  descontoDaVenda: boolean;
  descontoPadrao: number;
  impostoDoPedido: boolean;
  precoMinimo: boolean;
  venderSemEstoque: boolean;
  /** `business.sales_cmsn_agnt`: null = desligado; 'logged_in_user' = o store põe o usuário. */
  comissionado: { tipo: 'logged_in_user' | 'user' | 'cmsn_agnt' | null; obrigatorio: boolean };
  prazoObrigatorio: boolean;
  rascunho: boolean;
  aPrazo: boolean;
  recorrente: boolean;
  /** `amount_rounding_method`: múltiplo para arredondar o total; 0 = não arredonda. */
  arredondamento: number;
  somaNaLinha: boolean;
};

export type PrazoDoCliente = { numero: number | string | null; tipo: string | null };

/**
 * O que a configuração impede de gravar — motivo para a tela, ou null.
 *
 * - Comissionado obrigatório (`is_commission_agent_required`): no Blade o select só existe com
 *   `sales_cmsn_agnt` = 'user' ou 'cmsn_agnt' (sell/create.blade.php:194-200). Com
 *   'logged_in_user' o store põe o próprio usuário (SellPosController@store), então não bloqueia.
 *   Com o select obrigatório, a V3 só grava com um comissionado escolhido (UC-V395).
 * - Prazo obrigatório (`is_pay_term_required`): o Blade marca `pay_term_number` e `pay_term_type`
 *   como required, preenchidos com o prazo do cliente (sell/create.blade.php:177-187).
 */
export function bloqueioDaConfig(config: ConfigVenda, prazo: PrazoDoCliente, comissionadoId: number | null = null): string | null {
  if (temSelectDeComissionado(config) && config.comissionado.obrigatorio && !(comissionadoId && comissionadoId > 0)) {
    return 'Esta empresa exige comissionado na venda — escolha o comissionado.';
  }
  if (config.prazoObrigatorio && (!prazoPreenchido(prazo))) {
    return 'Esta empresa exige prazo de pagamento, e o cliente da venda não tem prazo no cadastro.';
  }
  return null;
}

/** O Blade só mostra o select de comissionado com `sales_cmsn_agnt` = user ou cmsn_agnt. */
export function temSelectDeComissionado(config: ConfigVenda): boolean {
  return config.comissionado.tipo === 'user' || config.comissionado.tipo === 'cmsn_agnt';
}

export function prazoPreenchido(prazo: PrazoDoCliente): boolean {
  return prazo.numero !== null && String(prazo.numero).trim() !== '' && !!prazo.tipo;
}

export type LinhaLancavel = {
  variationId: number | null | undefined;
  medidas: string | null;
  qtd: string;
  preco: string;
  desc: string;
  acr: string;
};

/**
 * Lançar um produto que já está na venda: com `item_addition_method` = 1 soma a quantidade na
 * linha existente; com 0 abre linha nova (pos.js `pos_product_row`, linhas 1617-1650).
 *
 * Só soma quando a linha é o MESMO pedido: mesma variação, sem medidas (item dimensional tem a
 * quantidade derivada das medidas, e somar perderia as medidas) e mesmo preço, desconto e
 * acréscimo — o lançamento da V3 deixa mudar preço, e somar numa linha de outro preço gravaria
 * a quantidade nova pelo preço antigo. Nesses casos abre linha nova, como o método 0.
 *
 * Devolve o índice da linha que recebe a soma, ou -1 para linha nova.
 */
export function linhaParaSomar(itens: LinhaLancavel[], novo: LinhaLancavel, somaNaLinha: boolean): number {
  if (!somaNaLinha || !novo.variationId || novo.medidas) return -1;
  return itens.findIndex(
    (l) =>
      l.variationId === novo.variationId &&
      !l.medidas &&
      parseBR(l.preco) === parseBR(novo.preco) &&
      parseBR(l.desc) === parseBR(novo.desc) &&
      parseBR(l.acr) === parseBR(novo.acr),
  );
}

/* ─── Regras que mexem em VALOR ou ESTOQUE (UC-V389..V392 · REGRA MESTRE de proibicoes.md) ─── */

/**
 * Desconto da venda que vale (UC-V389). Com `disable_discount` o Blade esconde o campo
 * (pos_details.blade.php:81) e o desconto não existe: vai 0. Sem a regra, vale o digitado.
 */
export function descontoDaVendaEfetivo(
  config: ConfigVenda,
  descTipo: 'percentual' | 'fixo',
  descVal: string,
): { descTipo: 'percentual' | 'fixo'; descVal: string } {
  return config.descontoDaVenda ? { descTipo, descVal } : { descTipo: 'percentual', descVal: '0' };
}

/**
 * Desconto com que a venda NASCE (UC-V389): `business.default_sales_discount`, em %, como o
 * Blade preenche `discount_amount` (pos_form_totals.blade.php:33, discount_type percentage).
 * Com o desconto da venda desligado, nasce 0.
 */
export function descontoInicial(config: ConfigVenda): string {
  if (!config.descontoDaVenda || !(config.descontoPadrao > 0)) return '0,00';
  return config.descontoPadrao.toFixed(2).replace('.', ',');
}

export type LinhaComPrecoDeVenda = { nome: string; preco: string; precoVenda?: number | null };

/**
 * Preço mínimo (UC-V390). Com `enable_msp` o Blade valida o preço unitário da linha contra o
 * preço de venda do cadastro (row_edit_product_price_modal.blade.php:14,
 * `data-rule-min-value = pos_unit_price`); sem a regra não há piso nenhum. O desconto da
 * linha é campo à parte e não entra na comparação, como no Blade.
 */
export function abaixoDoPrecoMinimo(config: ConfigVenda, itens: LinhaComPrecoDeVenda[]): string | null {
  if (!config.precoMinimo) return null;
  const abaixo = itens.find((l) => l.precoVenda != null && centavos(parseBR(l.preco)) < centavos(l.precoVenda));
  return abaixo ? `${abaixo.nome} está abaixo do preço mínimo de venda desta empresa.` : null;
}

export type LinhaComEstoque = {
  nome: string;
  variationId: number | null | undefined;
  enableStock: boolean;
  estoque: number | null;
  qtd: string;
};

/**
 * Vender sem estoque (UC-V391). Sem `allow_overselling` o Blade limita a quantidade ao saldo
 * (pos.js:440, `data-allow-overselling`) e o store recusa a baixa sem saldo. Soma as linhas da
 * mesma variação, porque a baixa é do total. Produto que não controla estoque não entra.
 */
export function estoqueInsuficiente(config: ConfigVenda, itens: LinhaComEstoque[]): string | null {
  if (config.venderSemEstoque) return null;
  const porVariacao = new Map<number, { nome: string; qtd: number; estoque: number }>();
  for (const l of itens) {
    if (!l.enableStock || l.estoque === null || !l.variationId) continue;
    const atual = porVariacao.get(l.variationId) ?? { nome: l.nome, qtd: 0, estoque: l.estoque };
    atual.qtd += parseBR(l.qtd);
    porVariacao.set(l.variationId, atual);
  }
  for (const v of porVariacao.values()) {
    if (v.qtd > v.estoque + 1e-9) return `${v.nome} pede mais do que o estoque, e esta empresa não vende sem estoque.`;
  }
  return null;
}

/**
 * Arredondamento do total (UC-V392): `amount_rounding_method` é o múltiplo (1 · 0,5 · 0,1 ·
 * 0,05) e o Blade faz `Decimal(total).toNearest(múltiplo)` (functions.js `__round`), que no
 * decimal.js arredonda metade para cima. Conta em centavos inteiros para não herdar o erro de
 * float. Devolve o total arredondado e a diferença (`round_off_amount`).
 */
export function arredondarTotal(total: number, multiplo: number): { total: number; diferenca: number } {
  const c = centavos(total);
  const m = centavos(multiplo);
  if (!(m > 0)) return { total: c / 100, diferenca: 0 };
  const r = Math.floor(c / m + 0.5) * m;
  return { total: r / 100, diferenca: (r - c) / 100 };
}

function centavos(v: number): number {
  return Math.round(v * 100);
}
