// KPI canon do Quadro de OS (paridade Cowork Onda 1.5) — card branco/tonal,
// label uppercase 10px muted, número grande tabular-nums e SUBLINHA descritiva.
// Clicável vira filtro D-05 (clica filtra · clica de novo limpa · aria-pressed).
//
// Extraído do Board.tsx em 2026-06-11 (polish Lista/Fila): a Index passou a usar
// o MESMO vocabulário de KPI — um único componente, zero divergência visual.

import { kpiTone, type KpiTone } from './boardTone';

export interface BoardKpiCardProps {
  label: string;
  value: string;
  /** sublinha descritiva (paridade Cowork — ex.: "faturamento previsto") */
  sub?: string;
  tone: KpiTone;
  /** D-05 — KPI ativo como filtro (anel primary + aria-pressed) */
  active?: boolean;
  /** D-05 — outro KPI está filtrando (esmaece este) */
  dimmed?: boolean;
  /** presente = KPI filtrável (vira role=button); ausente = só leitura (ex.: valor) */
  onClick?: () => void;
}

export default function BoardKpiCard({ label, value, sub, tone, active = false, dimmed = false, onClick }: BoardKpiCardProps) {
  const t = kpiTone(tone);

  const inner = (
    <>
      <span className={`text-[10px] font-semibold uppercase tracking-wider truncate ${t.label}`}>{label}</span>
      <span className={`text-xl @[1100px]/board:text-2xl font-bold tabular-nums ${t.value}`}>{value}</span>
      {sub ? <span className="text-[10px] text-muted-foreground truncate leading-tight">{sub}</span> : null}
    </>
  );

  // KPI filtrável é <button> de verdade (a11y nativa: foco, Enter/Space, aria-pressed)
  if (onClick !== undefined) {
    return (
      <button
        type="button"
        className={
          `rounded-lg border px-3 py-2 flex flex-col gap-0.5 text-left w-full cursor-pointer select-none transition-all hover:shadow-sm ${t.wrapper}`
          + (active ? ' ring-2 ring-primary ring-offset-1' : '')
          + (dimmed ? ' opacity-50' : '')
        }
        aria-pressed={active}
        title={active ? 'Clique pra limpar o filtro' : `Filtrar: ${label}`}
        onClick={onClick}
      >
        {inner}
      </button>
    );
  }

  return (
    <div className={`rounded-lg border px-3 py-2 flex flex-col gap-0.5 ${t.wrapper}`}>
      {inner}
    </div>
  );
}
