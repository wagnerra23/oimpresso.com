// SellsTotalBar — totais do filtro, abaixo da lista de vendas (US-SELL-017 · UC-SIDX-06).
//
// Forma: protótipo `prototipo-ui/cowork/Wagner/vendas-page.jsx`, bloco TOTALIZADORES (`vd-totalbar`).
// Números: só os que o servidor devolve em `/sells-list-json` → `totals` (SellController clona o
// filtro antes de paginar, então a soma cobre todas as páginas). A barra não soma nada no
// navegador — REGRA MESTRE de valor (memory/proibicoes.md).
//
// Quando a barra não mostra números: três filtros da tela rodam só no navegador e sobre a página
// carregada (visão salva, pílulas Faturada/Cancelada, origem). Com um deles ligado, o total do
// servidor não confere com as linhas na tela; a barra diz isso em vez de mostrar um número que
// não bate. Em Rascunho não há barra: os totais de faturado são só de venda final (UC-SIDX-05).

import type { ReactNode } from 'react';

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
  if (f.pill === 'faturada' || f.pill === 'cancelada') {
    const nome = f.pill === 'faturada' ? 'Faturada' : 'Cancelada';
    return `A pílula “${nome}” filtra só esta página — os totais aparecem em Todas, Paga e Pendente.`;
  }
  if (f.visaoOrigem) {
    return 'O filtro de origem vale só para esta página — os totais aparecem sem ele.';
  }
  return null;
}

const brl = (n: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);

function Metrica({ rotulo, valor, grande = false }: { rotulo: string; valor: number; grande?: boolean }): ReactNode {
  return (
    <div className="vd-totalbar-m">
      <span className="k">{rotulo}</span>
      <span className={grande ? 'v grand' : 'v'}>{brl(valor)}</span>
    </div>
  );
}

interface SellsTotalBarProps {
  totais: TotaisDoFiltro | null;
  filtros: FiltrosDaTela;
  carregando: boolean;
}

export default function SellsTotalBar({ totais, filtros, carregando }: SellsTotalBarProps): ReactNode {
  // Durante a busca os totais ainda são do filtro anterior: some até chegar o novo.
  if (filtros.pill === 'rascunho' || carregando || !totais || totais.count === 0) return null;

  const motivo = motivoSemTotais(filtros);
  if (motivo) {
    return (
      <div className="vd-totalbar vd-totalbar-off" role="note">
        {motivo}
      </div>
    );
  }

  return (
    <div className="vd-totalbar" role="region" aria-label="Totais do filtro">
      <div className="vd-totalbar-l">
        <b>{totais.count}</b> {totais.count === 1 ? 'venda' : 'vendas'}
        <span className="vd-totalbar-sub">soma de todas as páginas do filtro</span>
      </div>
      <div className="vd-totalbar-r">
        <Metrica rotulo="Pago" valor={totais.sum_total_paid} />
        <Metrica rotulo="A receber" valor={totais.sum_due} />
        <Metrica rotulo="Total do filtro" valor={totais.sum_final_total} grande />
      </div>
    </div>
  );
}
