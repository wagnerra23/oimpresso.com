/**
 * Colunas da lista de vendas escolhidas pela pessoa (botão "Colunas", decisão [W] 2026-11-25,
 * item 08 da conferência lista × protótipo).
 *
 * O que entra: as colunas que a tabela já desenha (`ColumnId` da `SellsTabelaUnificada`). O
 * protótipo propõe colunas novas (Produção, Entrega, Arte); o [W] decidiu manter as atuais (item 10).
 *
 * Padrão: o conjunto da visão (Operacional, Financeira, Produção), como antes. Quem nunca abre o
 * botão vê exatamente a mesma tabela. Cada visão guarda a própria escolha, como no protótipo.
 *
 * Fixas: a caixinha de seleção e a coluna Venda ficam sempre, nessa ordem, no começo.
 * Comissão só existe se a empresa usa comissão (`business.sales_cmsn_agnt`).
 *
 * Sem cálculo de valor: decide só o que aparece e em que ordem.
 */
import {
  COLUMNS_FINANCEIRA,
  COLUMNS_OPERACIONAL,
  COLUMNS_PRODUCAO,
  type ColumnId,
} from './SellsTabelaUnificada';
import type { SellsVisao } from './SellsTabsVisao';

export type VisaoLista = SellsVisao;

export const FIXAS: readonly ColumnId[] = ['check', 'invoice'];

/** Todas as colunas que a tabela sabe desenhar, na ordem em que o catálogo as lista. */
export const TODAS: readonly ColumnId[] = [
  'check', 'invoice', 'date', 'client', 'seller', 'source', 'pipeline', 'fiscal',
  'payment', 'location', 'paid', 'due', 'total', 'status', 'commission',
];

export const chaveColunas = (visao: VisaoLista): string => `colunas.${visao}`;

const disponiveis = (comissao: boolean): ColumnId[] =>
  TODAS.filter((c) => comissao || c !== 'commission');

export function colunasPadrao(visao: VisaoLista, comissao: boolean): ColumnId[] {
  const preset =
    visao === 'financeira' ? COLUMNS_FINANCEIRA :
    visao === 'producao' ? COLUMNS_PRODUCAO :
    COLUMNS_OPERACIONAL;
  return preset.filter((c) => comissao || c !== 'commission');
}

/**
 * Lê a escolha gravada (JSON). Descarta id desconhecido ou repetido, põe as fixas no começo e
 * tira Comissão se a empresa não usa. Gravado inválido ou vazio → padrão da visão.
 */
export function colunasGravadas(gravado: string, visao: VisaoLista, comissao: boolean): ColumnId[] {
  let lista: unknown;
  try {
    lista = gravado ? JSON.parse(gravado) : null;
  } catch {
    lista = null;
  }
  if (!Array.isArray(lista)) return colunasPadrao(visao, comissao);
  const validas = disponiveis(comissao);
  const vistas = new Set<ColumnId>();
  const resto: ColumnId[] = [];
  for (const c of lista) {
    if (typeof c !== 'string' || !validas.includes(c as ColumnId)) continue;
    const id = c as ColumnId;
    if (vistas.has(id) || FIXAS.includes(id)) continue;
    vistas.add(id);
    resto.push(id);
  }
  if (resto.length === 0) return colunasPadrao(visao, comissao);
  return [...FIXAS, ...resto];
}

/** Liga ou desliga uma coluna. Fixa não sai; coluna nova entra no fim. */
export function alternar(ativas: ColumnId[], id: ColumnId): ColumnId[] {
  if (FIXAS.includes(id)) return ativas;
  return ativas.includes(id) ? ativas.filter((c) => c !== id) : [...ativas, id];
}

/** Move uma coluna uma posição (delta -1 ou +1). Não passa por cima das fixas. */
export function mover(ativas: ColumnId[], id: ColumnId, delta: number): ColumnId[] {
  const de = ativas.indexOf(id);
  const para = de + delta;
  if (de < 0 || FIXAS.includes(id) || para < FIXAS.length || para >= ativas.length) return ativas;
  const nova = [...ativas];
  nova.splice(de, 1);
  nova.splice(para, 0, id);
  return nova;
}

/** Colunas que a pessoa pode ligar e que estão fora da lista agora. */
export function foraDaLista(ativas: ColumnId[], comissao: boolean): ColumnId[] {
  return disponiveis(comissao).filter((c) => !ativas.includes(c));
}
