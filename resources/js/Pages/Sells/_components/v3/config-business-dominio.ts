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
 *   A V3 ainda não escolhe comissionado (grupo 3), então com o select obrigatório ela não grava.
 * - Prazo obrigatório (`is_pay_term_required`): o Blade marca `pay_term_number` e `pay_term_type`
 *   como required, preenchidos com o prazo do cliente (sell/create.blade.php:177-187).
 */
export function bloqueioDaConfig(config: ConfigVenda, prazo: PrazoDoCliente): string | null {
  const { tipo, obrigatorio } = config.comissionado;
  if (obrigatorio && (tipo === 'user' || tipo === 'cmsn_agnt')) {
    return 'Esta empresa exige comissionado na venda, e a V3 ainda não escolhe comissionado.';
  }
  if (config.prazoObrigatorio && (!prazoPreenchido(prazo))) {
    return 'Esta empresa exige prazo de pagamento, e o cliente da venda não tem prazo no cadastro.';
  }
  return null;
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
