// FinPillFrescor — Cowork KB-9.75 Financeiro Onda 5 R1 Curadoria
// (pill de SLA derivado de due/paid_at — 5 estados).
//
// Refs:
//  - prototipo-ui/financeiro-curation.jsx — finFrescorInfo + FinPillFrescor (canonical)
//  - resources/css/fin-curadoria.css — .fin-frescor tokens
//  - SaleSheet pattern (compact pill ao lado do total)
//
// Estados:
//  - paid    "pago Xd atrás" / "pago hoje"  ✓
//  - overdue "Xd em atraso"                  ✕
//  - today   "vence hoje"                    ●
//  - warning "Xd"  (≤3 dias)                 ▲
//  - soon    "Xd"  (≤7 dias)                 ○
//  - fresh   "Xd"  (>7 dias)                 ○
//
// Determinístico (não persiste, deriva de row). Sem backend.

import { useMemo } from 'react';

export type FinFrescorKind = 'paid' | 'overdue' | 'today' | 'warning' | 'soon' | 'fresh';

export interface FinFrescorInfo {
  kind: FinFrescorKind;
  label: string;
  ic: string;
}

interface LancamentoLike {
  paid_at?: string | Date | null;
  due?: string | Date | null;
  vencimento?: string | Date | null;
  vencimento_label?: string | null;
}

function asDate(v: string | Date | null | undefined): Date | null {
  if (!v) return null;
  if (v instanceof Date) return v;
  const d = new Date(v);
  return isNaN(d.getTime()) ? null : d;
}

function startOfDay(d: Date): Date {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

export function finFrescorInfo(row: LancamentoLike, today: Date = new Date()): FinFrescorInfo {
  const t0 = startOfDay(today);
  const paid = asDate(row.paid_at ?? null);
  const due = asDate(row.due ?? row.vencimento ?? null);

  if (paid) {
    const days = Math.round((t0.getTime() - startOfDay(paid).getTime()) / 86_400_000);
    if (days <= 0) return { kind: 'paid', label: 'pago hoje', ic: '✓' };
    return { kind: 'paid', label: `pago ${days}d atrás`, ic: '✓' };
  }

  if (!due) {
    return { kind: 'fresh', label: '—', ic: '○' };
  }

  const daysUntil = Math.round((startOfDay(due).getTime() - t0.getTime()) / 86_400_000);
  if (daysUntil < 0) return { kind: 'overdue', label: `${-daysUntil}d em atraso`, ic: '✕' };
  if (daysUntil === 0) return { kind: 'today', label: 'vence hoje', ic: '●' };
  if (daysUntil <= 3) return { kind: 'warning', label: `${daysUntil}d`, ic: '▲' };
  if (daysUntil <= 7) return { kind: 'soon', label: `${daysUntil}d`, ic: '○' };
  return { kind: 'fresh', label: `${daysUntil}d`, ic: '○' };
}

interface FinPillFrescorProps {
  row: LancamentoLike;
  compact?: boolean;
  today?: Date;
}

export function FinPillFrescor({ row, compact = false, today }: FinPillFrescorProps) {
  const info = useMemo(() => finFrescorInfo(row, today), [row.paid_at, row.due, row.vencimento, today]);
  return (
    <span className={`fin-frescor fin-frescor-${info.kind}`} title={info.label} data-kind={info.kind}>
      <span className="fin-frescor-ic">{info.ic}</span>
      {!compact && <span className="fin-frescor-lbl">{info.label}</span>}
    </span>
  );
}

export default FinPillFrescor;
