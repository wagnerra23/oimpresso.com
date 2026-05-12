// US-SELL-019 — Dropdown "Agrupar por…" pra Grade Avançada.
// Refs: ADR 0136 (Sells: Lista vs Grade Avançada).
//
// Substitui o botão "Agrupar por… (em breve)" disabled da SellsBulkActionsBar
// por dropdown funcional. Usa TanStack Table v8 getGroupedRowModel client-side
// (suficiente <500 vendas; pra escala maior considerar pré-agregação SQL backend).
//
// MVP: 1 nível de agrupamento. Multi-level (drag-to-group estilo Cowork) fica
// pra US futura quando dnd-kit instalado + sinal qualificado.
//
// Opções canon:
//  - none           → sem agrupamento (default)
//  - customer_name  → cliente (nome)
//  - payment_status → status pagamento (Pago/A receber/Parcial)
//  - emission_month → mês emissão (transaction_date YYYY-MM)
//
// PT-BR enforce: labels visíveis sempre PT-BR.

import { ChevronDown, Layers3, X } from 'lucide-react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

export type GroupByField =
  | 'none'
  | 'customer_name'
  | 'payment_status'
  | 'emission_month';

export const GROUP_BY_LABEL: Record<GroupByField, string> = {
  none: 'Sem agrupamento',
  customer_name: 'Cliente',
  payment_status: 'Status pagamento',
  emission_month: 'Mês emissão',
};

export const GROUP_BY_OPTIONS: GroupByField[] = [
  'none',
  'customer_name',
  'payment_status',
  'emission_month',
];

interface SellsGroupByDropdownProps {
  groupBy: GroupByField;
  onChange: (next: GroupByField) => void;
  /** Variante visual: "bar" (barra azul ações em lote) ou "default" (header). */
  variant?: 'bar' | 'default';
}

export default function SellsGroupByDropdown({
  groupBy,
  onChange,
  variant = 'default',
}: SellsGroupByDropdownProps) {
  const isGrouped = groupBy !== 'none';
  const label = GROUP_BY_LABEL[groupBy];

  // Variante "bar" — usado dentro da SellsBulkActionsBar (background azul).
  const triggerClass =
    variant === 'bar'
      ? 'inline-flex items-center gap-1.5 rounded-md border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted transition-colors'
      : 'inline-flex items-center gap-1.5 rounded-md border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted transition-colors';

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          className={triggerClass}
          aria-label={`Agrupamento atual: ${label}. Clique pra trocar.`}
          title={isGrouped ? `Agrupado por ${label.toLowerCase()}` : 'Sem agrupamento'}
        >
          <Layers3 size={13} />
          {isGrouped ? (
            <>
              <span className="text-[10px] uppercase tracking-wider text-muted-foreground">
                Agrupar:
              </span>
              <span>{label}</span>
            </>
          ) : (
            <span>Agrupar por…</span>
          )}
          <ChevronDown size={11} className="text-muted-foreground" />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-52">
        {GROUP_BY_OPTIONS.map((opt) => {
          const isActive = opt === groupBy;
          return (
            <DropdownMenuItem
              key={opt}
              onSelect={() => onChange(opt)}
              className={isActive ? 'font-medium text-foreground' : ''}
            >
              {isActive && <span className="mr-1.5 text-primary" aria-hidden>•</span>}
              {!isActive && <span className="mr-1.5 w-2 inline-block" aria-hidden />}
              {GROUP_BY_LABEL[opt]}
            </DropdownMenuItem>
          );
        })}
        {isGrouped && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onSelect={() => onChange('none')}
              className="text-muted-foreground"
            >
              <X size={12} className="mr-1.5" />
              Limpar agrupamento
            </DropdownMenuItem>
          </>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
