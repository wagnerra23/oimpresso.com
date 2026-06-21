// VehicleStatusBadge — badge inline com cor semantic + label PT-BR.
//
// Refs: ADR 0110 (Cockpit V2 — semantic colors), ADR 0137 (OficinaAuto).
//       Mockup memory/requisitos/OficinaAuto/demo-martinho-2026-05-13/mockup.html
//
// Mapeamento status → label (ADR 0265 — vocabulário de REPARO; keys do enum
// vehicles.current_status são Tier 0 e ficam intactas, só o label muda):
//  disponivel   → "No pátio"            (slate)    [sem OS ativa]
//  locada       → "Em serviço"          (blue)     [OS ativa — current_rental_id]
//  locada+pago  → "Em serviço · pago"   (emerald)  [P2 — accessor isPaid futuro]
//  atrasada     → "Atrasada · cobrar"   (rose)     [is_overdue=true na OS]
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
  disponivel: 'No pátio',
  locada: 'Em serviço',
  manutencao: 'Em manutenção',
  indisponivel: 'Indisponível',
};

const STYLES: Record<string, string> = {
  disponivel:
    'bg-muted text-muted-foreground border-border',
  locada:
    'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900/40',
  locada_paga:
    'bg-success-soft text-success-fg border-success/20',
  manutencao:
    'bg-warning-soft text-warning-fg border-warning/20',
  indisponivel:
    'bg-muted text-muted-foreground border-border',
  atrasada:
    'bg-destructive-soft text-destructive-fg border-destructive/20',
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
        Em serviço · pago
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
