// totaisDoFiltro — tipos dos totais da consulta de vendas e a regra de quando eles valem para as
// linhas na tela (US-SELL-017 · UC-SIDX-06). Fica fora do SellsTotalBar.tsx porque arquivo de
// componente React só exporta componente (regra react-refresh/only-export-components).

export interface TotaisDoFiltro {
  count: number;
  sum_final_total: number;
  sum_total_paid: number;
  sum_due: number;
}

export interface FiltrosDaTela {
  pill: string;
  savedViewId: string;
  savedViewLabel: string;
  visaoOrigem: string | null;
}

/** Por que os totais do servidor não valem para as linhas na tela; `null` quando valem. */
export function motivoSemTotais(f: FiltrosDaTela): string | null {
  if (f.savedViewId !== 'todas') {
    return `A visão “${f.savedViewLabel}” filtra só esta página — os totais aparecem na visão Todas.`;
  }
  // Faturada e Cancelada filtram no servidor desde UC-SIDX-07: os totais valem para elas.
  if (f.visaoOrigem) {
    return 'O filtro de origem vale só para esta página — os totais aparecem sem ele.';
  }
  return null;
}
