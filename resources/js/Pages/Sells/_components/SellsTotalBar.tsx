// SellsTotalBar — totais do filtro, abaixo da lista de vendas (US-SELL-017 · UC-SIDX-06).
//
// Forma: protótipo `prototipo-ui/cowork/Wagner/vendas-page.jsx`, bloco TOTALIZADORES (`vd-totalbar`),
// com os valores do `vendas.css` do protótipo escritos em classes do Tailwind sobre os mesmos tokens —
// sem CSS novo no bundle (MANUAL-CSS-JS passo 1, catraca css-size-baseline) e com os primitivos de
// layout (ADR 0253). `--s-4`/`--s-5` do protótipo não existem no app: viraram `gap={4}` (16px) e
// `gap={5}` (20px).
// Números: só os que o servidor devolve em `/sells-list-json` → `totals` (SellController clona o
// filtro antes de paginar, então a soma cobre todas as páginas). A barra não soma nada no
// navegador — REGRA MESTRE de valor (memory/proibicoes.md).
//
// Quando a barra não mostra números: três filtros da tela rodam só no navegador e sobre a página
// carregada (visão salva, pílulas Faturada/Cancelada, origem). Com um deles ligado, o total do
// servidor não confere com as linhas na tela; a barra diz isso em vez de mostrar um número que
// não bate. Em Rascunho não há barra: os totais de faturado são só de venda final (UC-SIDX-05).

import type { ReactNode } from 'react';
import { Inline, Stack } from '@/Components/layout';
import { motivoSemTotais, type FiltrosDaTela, type TotaisDoFiltro } from './totaisDoFiltro';

const BARRA =
  'mt-[10px] rounded-md border border-[color:var(--border)] bg-[var(--bg-2)] px-4 py-[11px]';
const ROTULO =
  'font-sans text-[length:var(--fs-1)] font-semibold uppercase leading-none tracking-[.06em] text-[color:var(--text-mute)]';
const VALOR =
  'font-mono text-[length:var(--fs-4)] font-semibold leading-none tabular-nums text-[color:var(--text-dim)]';
const VALOR_TOTAL =
  'font-mono text-[length:var(--fs-6)] font-bold leading-none tabular-nums tracking-[-0.01em] text-[color:var(--text)]';

const brl = (n: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);

function Metrica({ rotulo, valor, grande = false }: { rotulo: string; valor: number; grande?: boolean }): ReactNode {
  return (
    <Stack gap={0} align="end" className="gap-0.5 leading-[1.1]">
      <span className={ROTULO}>{rotulo}</span>
      <span className={grande ? VALOR_TOTAL : VALOR}>{brl(valor)}</span>
    </Stack>
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
      <div className={`${BARRA} text-[length:var(--fs-2)] text-[color:var(--text-mute)]`} role="note">
        {motivo}
      </div>
    );
  }

  return (
    <Inline wrap gap={4} className={`${BARRA} gap-y-2`} role="region" aria-label="Totais do filtro">
      <Inline gap={2} align="baseline" className="text-[length:var(--fs-3)] text-[color:var(--text-dim)]">
        <b className="font-sans text-[length:var(--fs-5)] font-bold leading-none text-[color:var(--text)]">
          {totais.count}
        </b>{' '}
        {totais.count === 1 ? 'venda' : 'vendas'}
        <span className="text-[length:var(--fs-2)] text-[color:var(--text-mute)]">soma de todas as páginas do filtro</span>
      </Inline>
      <Inline gap={5} className="ml-auto">
        <Metrica rotulo="Pago" valor={totais.sum_total_paid} />
        <Metrica rotulo="A receber" valor={totais.sum_due} />
        <Metrica rotulo="Total do filtro" valor={totais.sum_final_total} grande />
      </Inline>
    </Inline>
  );
}
