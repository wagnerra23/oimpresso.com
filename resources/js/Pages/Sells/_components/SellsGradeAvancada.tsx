// US-SELL-016 + US-SELL-017 — Grade Avançada (multiseleção + totalizador rodapé).
// Refs: ADR 0136 (Sells: split Lista vs Grade Avançada toggle).
//
// Roadmap progressivo (P0 entregue, P1+ aguarda sinal Firebird):
//   ✅ US-SELL-015 — Toggle Lista|Grade Avançada (PR #691)
//   ✅ US-SELL-016 — Multiseleção + ações em lote (este PR)
//   ✅ US-SELL-017 — Totalizador rodapé sticky (este PR)
//   ⏸ US-SELL-018+ — filtros multi-data, agrupamento drag, sub-linha produtos…
//
// Esta tela COMPARTILHA fetch/state com Index.tsx (props-driven). Não duplica
// useEffect de fetch — só layout (anti-pattern §ADR 0136 "Riscos").

import { useMemo } from 'react';
import { AlertTriangle, ArrowDown, ArrowUp, ArrowUpDown, Layers, Loader2 } from 'lucide-react';
import { Checkbox } from '@/Components/ui/checkbox';
import SellsBulkActionsBar from './SellsBulkActionsBar';
import SellsTotalsRow, { type SellsTotals } from './SellsTotalsRow';

interface SaleRow {
  id: number;
  transaction_date: string;
  display_date: string | null;
  invoice_no: string;
  final_total: number;
  total_paid: number;
  payment_status: 'paid' | 'due' | 'partial' | string;
  customer_name: string | null;
  customer_secondary: string | null;
  location_name: string | null;
  is_overdue: boolean;
  fiscal_status: 'pendente' | 'autorizada' | 'rejeitada' | 'denegada' | 'cancelada' | null;
  fiscal_modelo: '55' | '65' | null;
}

type SortKey = 'transaction_date' | 'invoice_no' | 'customer_name' | 'final_total' | 'payment_status';
type SortDir = 'asc' | 'desc';

interface SellsGradeAvancadaProps {
  rows: SaleRow[];
  loading: boolean;
  totals: SellsTotals | null;
  selectedIds: Set<number>;
  onToggleSelect: (id: number) => void;
  onToggleSelectAll: () => void;
  onClearSelection: () => void;
  onRowClick: (id: number) => void;
  openSaleId: number | null;
  totalFiltered: number;
  sortKey: SortKey;
  sortDir: SortDir;
  onSort: (key: SortKey) => void;
}

const formatBRL = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

const formatDate = (iso: string) => {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
  }).format(d);
};

const PAYMENT_STATUS_LABEL: Record<string, string> = {
  paid: 'Pago',
  due: 'A receber',
  partial: 'Parcial',
};

const PAYMENT_STATUS_STYLE: Record<string, string> = {
  paid: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300',
  due: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300',
  partial: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300',
};

export default function SellsGradeAvancada({
  rows,
  loading,
  totals,
  selectedIds,
  onToggleSelect,
  onToggleSelectAll,
  onClearSelection,
  onRowClick,
  openSaleId,
  totalFiltered,
  sortKey,
  sortDir,
  onSort,
}: SellsGradeAvancadaProps) {
  const allRowsSelected = useMemo(() => {
    if (rows.length === 0) return false;
    return rows.every((r) => selectedIds.has(r.id));
  }, [rows, selectedIds]);

  const someRowsSelected = useMemo(() => {
    return rows.some((r) => selectedIds.has(r.id));
  }, [rows, selectedIds]);

  const selectedArray = useMemo(() => Array.from(selectedIds), [selectedIds]);

  return (
    <div className="space-y-3">
      {/* Barra ações em lote — slide-down quando há seleção */}
      <SellsBulkActionsBar
        selectedIds={selectedArray}
        totalFiltered={totalFiltered}
        onClearSelection={onClearSelection}
      />

      <div className="rounded-lg border border-border bg-background overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-muted/50">
              <tr className="border-b border-border">
                {/* Coluna checkbox header — "selecionar todas as N filtradas" */}
                <th className="w-10 px-3 py-2.5 text-left">
                  <Checkbox
                    checked={allRowsSelected}
                    onCheckedChange={onToggleSelectAll}
                    aria-label={
                      allRowsSelected
                        ? 'Desmarcar todas as vendas filtradas'
                        : `Selecionar todas as ${totalFiltered} vendas filtradas`
                    }
                    title={
                      allRowsSelected
                        ? 'Desmarcar todas'
                        : `Selecionar todas (${totalFiltered.toLocaleString('pt-BR')} no filtro)`
                    }
                    data-state={
                      allRowsSelected
                        ? 'checked'
                        : someRowsSelected
                          ? 'indeterminate'
                          : 'unchecked'
                    }
                  />
                </th>
                <SortableTh sortKey="transaction_date" current={sortKey} dir={sortDir} onSort={onSort} className="w-28">Data</SortableTh>
                <SortableTh sortKey="invoice_no" current={sortKey} dir={sortDir} onSort={onSort}>Nº fatura</SortableTh>
                <SortableTh sortKey="customer_name" current={sortKey} dir={sortDir} onSort={onSort}>Cliente</SortableTh>
                <Th className="w-32">Localização</Th>
                <SortableTh sortKey="final_total" current={sortKey} dir={sortDir} onSort={onSort} align="right" className="w-28">Total</SortableTh>
                <Th className="text-right w-28">Pago</Th>
                <Th className="text-right w-28">A receber</Th>
                <SortableTh sortKey="payment_status" current={sortKey} dir={sortDir} onSort={onSort} className="w-32">Status</SortableTh>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={9} className="text-center py-12 text-muted-foreground text-xs">
                    <Loader2 className="inline-block mr-2 h-3.5 w-3.5 animate-spin" />
                    Carregando…
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={9} className="text-center py-12 text-muted-foreground text-xs">
                    Nenhuma venda encontrada nesse filtro.
                  </td>
                </tr>
              ) : (
                rows.map((row) => {
                  const isOpen = openSaleId === row.id;
                  const isSelected = selectedIds.has(row.id);
                  const due = Math.max(0, row.final_total - row.total_paid);
                  return (
                    <tr
                      key={row.id}
                      className={
                        'border-b border-border cursor-pointer transition-colors ' +
                        (isOpen
                          ? 'bg-blue-50/60 dark:bg-blue-950/30'
                          : isSelected
                            ? 'bg-blue-50/30 dark:bg-blue-950/20 hover:bg-blue-50/50 dark:hover:bg-blue-950/40'
                            : 'hover:bg-muted/40')
                      }
                      onClick={() => onRowClick(row.id)}
                    >
                      <td className="px-3 py-2.5" onClick={(e) => e.stopPropagation()}>
                        <Checkbox
                          checked={isSelected}
                          onCheckedChange={() => onToggleSelect(row.id)}
                          aria-label={`Selecionar venda ${row.invoice_no}`}
                        />
                      </td>
                      <td className="px-3 py-2.5 text-xs text-muted-foreground tabular-nums whitespace-nowrap">
                        {row.display_date ? formatDate(row.display_date) : <span className="text-muted-foreground/50">—</span>}
                      </td>
                      <td className="px-3 py-2.5 font-medium text-foreground">
                        <span className="inline-flex items-center gap-2">
                          {row.is_overdue && (
                            <span
                              className="h-2 w-2 rounded-full bg-rose-500"
                              title="Atrasada"
                              aria-label="Venda atrasada"
                            />
                          )}
                          {row.invoice_no}
                        </span>
                      </td>
                      <td className="px-3 py-2.5">
                        <div className="text-foreground font-medium leading-tight">{row.customer_name ?? '—'}</div>
                        {row.customer_secondary && (
                          <div className="text-xs text-muted-foreground leading-tight mt-0.5">{row.customer_secondary}</div>
                        )}
                      </td>
                      <td className="px-3 py-2.5 text-xs text-muted-foreground">
                        {row.location_name ?? '—'}
                      </td>
                      <td className="px-3 py-2.5 text-right tabular-nums text-foreground">{formatBRL(row.final_total)}</td>
                      <td className="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{formatBRL(row.total_paid)}</td>
                      <td className="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300">{formatBRL(due)}</td>
                      <td className="px-3 py-2.5">
                        <PaymentStatusBadge status={row.payment_status} overdue={row.is_overdue} />
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Totalizador rodapé sticky-bottom (US-SELL-017) */}
        <SellsTotalsRow totals={totals} loading={loading} />
      </div>

      {/* Roadmap callout — features P1+ aguardando sinal Firebird (ADR 0105) */}
      <p className="inline-flex items-center gap-1.5 text-[11px] text-muted-foreground mt-2">
        <Layers size={12} />
        US-SELL-016/017 ativas · Próximas (aguardando sinal): SELL-018 (filtros multi-data), SELL-019 (agrupamento drag-to-group), SELL-022 (sub-linha produtos)
      </p>
    </div>
  );
}

function Th({ children, className = '' }: { children: React.ReactNode; className?: string }) {
  return (
    <th
      className={
        'text-left px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' +
        className
      }
    >
      {children}
    </th>
  );
}

function SortableTh({
  children,
  sortKey,
  current,
  dir,
  onSort,
  className = '',
  align = 'left',
}: {
  children: React.ReactNode;
  sortKey: SortKey;
  current: SortKey;
  dir: SortDir;
  onSort: (k: SortKey) => void;
  className?: string;
  align?: 'left' | 'right';
}) {
  const active = current === sortKey;
  const Icon = !active ? ArrowUpDown : dir === 'asc' ? ArrowUp : ArrowDown;
  return (
    <th
      className={
        (align === 'right' ? 'text-right ' : 'text-left ') +
        'px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' +
        className
      }
    >
      <button
        type="button"
        onClick={() => onSort(sortKey)}
        className={
          'inline-flex items-center gap-1 transition-colors hover:text-foreground ' +
          (active ? 'text-foreground' : '')
        }
        aria-sort={active ? (dir === 'asc' ? 'ascending' : 'descending') : 'none'}
      >
        {children}
        <Icon size={12} className={active ? '' : 'opacity-40'} />
      </button>
    </th>
  );
}

function PaymentStatusBadge({ status, overdue }: { status: string; overdue: boolean }) {
  if (overdue) {
    return (
      <span className="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[11px] font-medium text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300">
        <AlertTriangle size={11} />
        Atrasada
      </span>
    );
  }
  const cls = PAYMENT_STATUS_STYLE[status] ?? 'bg-muted text-muted-foreground border-border';
  const label = PAYMENT_STATUS_LABEL[status] ?? status;
  return (
    <span className={'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium ' + cls}>
      {label}
    </span>
  );
}
