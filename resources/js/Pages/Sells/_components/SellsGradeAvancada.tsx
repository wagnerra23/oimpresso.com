// US-SELL-015 — Skeleton da Grade Avançada (placeholder).
// Refs: ADR 0136 (Sells: split Lista vs Grade Avançada toggle)
//       Roadmap progressivo: US-SELL-016 (multiseleção), US-SELL-017
//       (totalizador rodapé), US-SELL-018..026 (P1+ feature-wish).
//
// Esta tela é apenas a CASCA estrutural — preenchimento incremental por
// sinal qualificado (ADR 0105). Substituída em PRs subsequentes.

import { Construction, Layers3 } from 'lucide-react';

interface SellsGradeAvancadaProps {
  /** KPIs vindos do controller (mesmo payload que a Lista). */
  sellKpis: {
    total: number;
    paid: number;
    due: number;
    partial: number;
    overdue: number;
  };
}

export default function SellsGradeAvancada({ sellKpis }: SellsGradeAvancadaProps) {
  return (
    <div className="rounded-lg border border-dashed border-border bg-muted/20 px-8 py-16">
      <div className="mx-auto max-w-xl text-center">
        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400">
          <Construction size={28} strokeWidth={1.5} />
        </div>
        <h2 className="text-lg font-semibold text-foreground">
          Grade Avançada em construção
        </h2>
        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
          Em breve: 30+ colunas, multiseleção, agrupamento drag-to-group,
          totalizador no rodapé e impressão em lote — o grid denso que
          você usa há anos no OfficeImpresso, agora no oimpresso.
        </p>
        <p className="mt-2 text-xs text-muted-foreground">
          Enquanto isso, alterne pra <strong className="font-medium text-foreground">Lista</strong> no
          topo pra acessar todas as vendas.
        </p>

        {/* KPIs resumidos pra não deixar a tela vazia */}
        <div className="mt-8 grid grid-cols-3 gap-3 text-left">
          <SkeletonKpi label="Total" value={sellKpis.total} />
          <SkeletonKpi label="A receber" value={sellKpis.due + sellKpis.partial} />
          <SkeletonKpi label="Atrasadas" value={sellKpis.overdue} danger={sellKpis.overdue > 0} />
        </div>

        <p className="mt-8 inline-flex items-center gap-1.5 text-[11px] text-muted-foreground">
          <Layers3 size={12} />
          US-SELL-015 · Fundação · Próximas: SELL-016 (multiseleção), SELL-017 (totalizador)
        </p>
      </div>
    </div>
  );
}

function SkeletonKpi({
  label,
  value,
  danger = false,
}: {
  label: string;
  value: number;
  danger?: boolean;
}) {
  return (
    <div
      className={
        'rounded-md border bg-background px-3 py-2 ' +
        (danger ? 'border-rose-200 dark:border-rose-900/40' : 'border-border')
      }
    >
      <div className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
        {label}
      </div>
      <div
        className={
          'text-lg font-semibold tabular-nums ' +
          (danger ? 'text-rose-700 dark:text-rose-300' : 'text-foreground')
        }
      >
        {value}
      </div>
    </div>
  );
}
