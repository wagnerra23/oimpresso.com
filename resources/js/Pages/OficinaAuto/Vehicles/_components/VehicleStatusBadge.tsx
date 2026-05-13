// VehicleStatusBadge — badge inline com cor semantic + label PT-BR.
//
// Refs: ADR 0110 (Cockpit V2 — semantic colors), ADR 0137 (OficinaAuto).
//       Mockup memory/requisitos/OficinaAuto/demo-martinho-2026-05-13/mockup.html
//
// Mapeamento status → label:
//  disponivel   → "Disponível"          (slate)
//  locada       → "Locada"              (blue)
//  locada+pago  → "Locada · pago"       (emerald)  [P2 — accessor isPaid futuro]
//  atrasada     → "Atrasada · cobrar"   (rose)     [is_overdue=true em rental]
//  manutencao   → "Em manutenção"       (amber)
//  indisponivel → "Indisponível"        (slate)

import { AlertTriangle } from 'lucide-react';

export type VehicleStatus = 'disponivel' | 'locada' | 'manutencao' | 'indisponivel' | string;

interface Props {
  status: VehicleStatus;
  isOverdue?: boolean;
  /** Quando true e status='locada', exibe variant "Locada · pago" (emerald). */
  isPaid?: boolean;
}

const LABELS: Record<string, string> = {
  disponivel: 'Disponível',
  locada: 'Locada',
  manutencao: 'Em manutenção',
  indisponivel: 'Indisponível',
};

const STYLES: Record<string, string> = {
  disponivel:
    'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-900/40 dark:text-slate-300 dark:border-slate-800',
  locada:
    'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900/40',
  locada_paga:
    'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/40',
  manutencao:
    'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900/40',
  indisponivel:
    'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-900/60 dark:text-slate-400 dark:border-slate-800',
  atrasada:
    'bg-rose-100 text-rose-700 border-rose-300 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900/50',
};

export default function VehicleStatusBadge({ status, isOverdue = false, isPaid = false }: Props) {
  if (isOverdue && status === 'locada') {
    return (
      <span
        className={
          'inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[11px] font-medium ' +
          STYLES.atrasada
        }
      >
        <AlertTriangle size={11} />
        Atrasada · cobrar
      </span>
    );
  }

  if (isPaid && status === 'locada') {
    return (
      <span
        className={
          'inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium ' +
          STYLES.locada_paga
        }
      >
        Locada · pago
      </span>
    );
  }

  const label = LABELS[status] ?? status;
  const cls = STYLES[status] ?? STYLES.indisponivel;
  return (
    <span className={'inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium ' + cls}>
      {label}
    </span>
  );
}
