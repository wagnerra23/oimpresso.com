// US-SELL-016/017/019 — Grade Avançada (multiseleção + totalizador + agrupamento).
// Refs: ADR 0136 (Sells: split Lista vs Grade Avançada toggle).
//
// Roadmap progressivo:
//   ✅ US-SELL-015 — Toggle Lista|Grade Avançada (PR #691)
//   ✅ US-SELL-016 — Multiseleção + ações em lote (PR #694)
//   ✅ US-SELL-017 — Totalizador rodapé sticky (PR #694)
//   ✅ US-SELL-018 — Filtros multi-data presets + custom (este PR)
//   ✅ US-SELL-019 — Agrupamento por campo (TanStack getGroupedRowModel — este PR)
//   ⏸ US-SELL-022+ — sub-linha produtos, drag-to-group multi-level…
//
// Esta tela COMPARTILHA fetch/state com Index.tsx (props-driven). Não duplica
// useEffect de fetch — só layout (anti-pattern §ADR 0136 "Riscos").
//
// US-SELL-019: agrupamento via TanStack getGroupedRowModel client-side.
// Trade-off: client-side suficiente <500 vendas (per_page max 100). Pra escala
// maior considerar pré-agregação SQL backend (?group_by=...) em US futura.

import { useMemo, useState } from 'react';
import {
  AlertTriangle, ArrowDown, ArrowUp, ArrowUpDown,
  ChevronDown, ChevronRight,
  DollarSign,
  Layers, Loader2,
} from 'lucide-react';
import {
  useReactTable,
  getCoreRowModel,
  getGroupedRowModel,
  getExpandedRowModel,
  type ColumnDef,
  type GroupingState,
  type ExpandedState,
} from '@tanstack/react-table';
import { Checkbox } from '@/Components/ui/checkbox';
import SellsBulkActionsBar from './SellsBulkActionsBar';
import SellsTotalsRow, { type SellsTotals } from './SellsTotalsRow';
import SellsGroupByDropdown, { type GroupByField } from './SellsGroupByDropdown';

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
  // US-SELL-023 — stage_key FSM (badge produção). NULL = venda legacy sem FSM.
  current_stage_key:
    | 'quote_draft' | 'quote_sent' | 'quote_approved'
    | 'in_production' | 'ready_for_invoice'
    | 'invoiced' | 'paid'
    | 'delivered' | 'completed'
    | 'cancelled' | 'on_hold'
    | string | null;
  // US-SELL-024 — boolean explícito "venda agrupada" (Delphi CODFINANCEIRO_GRUPO).
  is_grouped_invoice: boolean;
  // US-SELL-041 — método de pagamento + parcelas (regressão Cowork KB-9.75:
  // info estava na grade Lista mas sumiu da Grade Avançada). Larissa @ ROTA
  // LIVRE biz=4 reportou 2026-05-21 (ADR 0105 sinal qualificado).
  // Backend popula via SellController::inertiaList (match cases linha 1287-1298).
  payment_method_label: string | null;
  installments: number;
}

// US-SELL-023 — Mapping stage_key FSM → badge PT-BR + cor semantic.
// 11 stages canônicos (FsmProcessoVendaComProducaoSeeder) — alinhado com seeder
// FSM Pipeline LIVE prod biz=1 desde 2026-05-12 (ADR 0143).
const PRODUCAO_STAGE_LABEL: Record<string, string> = {
  quote_draft: 'Aprovação',
  quote_sent: 'Aprovação',
  quote_approved: 'Aprovação',
  in_production: 'Em produção',
  ready_for_invoice: 'Pronto',
  invoiced: 'Faturada',
  paid: 'Faturada',
  delivered: 'Entregue',
  completed: 'Entregue',
  cancelled: 'Cancelada',
  on_hold: 'Em espera',
};

const PRODUCAO_STAGE_STYLE: Record<string, string> = {
  quote_draft: 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-950/40 dark:text-slate-300',
  quote_sent: 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-950/40 dark:text-slate-300',
  quote_approved: 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-950/40 dark:text-slate-300',
  in_production: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300',
  ready_for_invoice: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300',
  invoiced: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300',
  paid: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300',
  delivered: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300',
  completed: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300',
  cancelled: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300',
  on_hold: 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-950/40 dark:text-slate-300',
};

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
  // US-SELL-019 — agrupamento (state lifted up).
  groupBy: GroupByField;
  onGroupByChange: (g: GroupByField) => void;
  // US-SELL-042 — botão "Pagar" inline (opcional: views que querem expor a
  // ação rápida de registrar pagamento sem abrir o drawer). Quando ausente,
  // a coluna Ações simplesmente não renderiza o botão.
  onPayClick?: (saleId: number, invoiceNo: string, dueAmount: number) => void;
}

const formatBRL = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

const formatCount = (n: number) =>
  new Intl.NumberFormat('pt-BR').format(n);

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

// US-SELL-019 — derive month YYYY-MM pra agrupar por mês emissão.
function emissionMonth(row: SaleRow): string {
  const src = row.transaction_date;
  if (!src) return '—';
  const d = new Date(src);
  if (Number.isNaN(d.getTime())) return '—';
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  return `${yyyy}-${mm}`;
}

// US-SELL-019 — Mapeia GroupByField → accessor TanStack.
function buildGroupedRows(rows: SaleRow[]): Array<SaleRow & { _emissionMonth: string }> {
  return rows.map((r) => ({ ...r, _emissionMonth: emissionMonth(r) }));
}

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
  groupBy,
  onGroupByChange,
  onPayClick,
}: SellsGradeAvancadaProps) {
  const allRowsSelected = useMemo(() => {
    if (rows.length === 0) return false;
    return rows.every((r) => selectedIds.has(r.id));
  }, [rows, selectedIds]);

  const someRowsSelected = useMemo(() => {
    return rows.some((r) => selectedIds.has(r.id));
  }, [rows, selectedIds]);

  const selectedArray = useMemo(() => Array.from(selectedIds), [selectedIds]);

  // US-SELL-019 — quando agrupado renderiza via TanStack; senão render legado.
  const isGrouped = groupBy !== 'none';

  if (isGrouped) {
    return (
      <div className="space-y-3">
        <SellsBulkActionsBar
          selectedIds={selectedArray}
          totalFiltered={totalFiltered}
          onClearSelection={onClearSelection}
          groupBy={groupBy}
          onGroupByChange={onGroupByChange}
        />

        {/* Toolbar agrupamento ativo (mostra dropdown + clear) */}
        <div className="flex items-center justify-between gap-2 px-1">
          <div className="text-xs text-muted-foreground">
            Agrupamento ativo. Clique no chevron de cada grupo pra expandir/recolher.
          </div>
          <SellsGroupByDropdown groupBy={groupBy} onChange={onGroupByChange} />
        </div>

        <GroupedTable
          rows={rows}
          loading={loading}
          groupBy={groupBy}
          selectedIds={selectedIds}
          onToggleSelect={onToggleSelect}
          onRowClick={onRowClick}
          openSaleId={openSaleId}
        />

        <SellsTotalsRow totals={totals} loading={loading} />

        <p className="inline-flex items-center gap-1.5 text-[11px] text-muted-foreground mt-2">
          <Layers size={12} />
          US-SELL-016/017/018/019 ativas · Próximas (aguardando sinal): SELL-022 (sub-linha produtos)
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {/* Barra ações em lote — slide-down quando há seleção (US-SELL-016)
          + dropdown agrupamento sempre visível (US-SELL-019) */}
      <SellsBulkActionsBar
        selectedIds={selectedArray}
        totalFiltered={totalFiltered}
        onClearSelection={onClearSelection}
        groupBy={groupBy}
        onGroupByChange={onGroupByChange}
      />

      {/* Toolbar sempre visível (mesmo sem seleção) — agrupamento à direita */}
      {selectedArray.length === 0 && (
        <div className="flex items-center justify-end gap-2 px-1">
          <SellsGroupByDropdown groupBy={groupBy} onChange={onGroupByChange} />
        </div>
      )}

      <div className="rounded-lg border border-border bg-background overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-muted/50">
              <tr className="border-b border-border">
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
                {/* US-SELL-041 follow-up #1314 — Larissa @ ROTA LIVRE biz=4 reportou
                    2026-05-21 (ADR 0105) método de pagamento sumindo da Grade Avançada.
                    PR #1314 cobriu o path TanStack (com agrupamento); aqui cobrimos
                    a tabela HTML default (sem agrupamento) — o caminho mais visto. */}
                <Th className="w-32">Pagamento</Th>
                <SortableTh sortKey="payment_status" current={sortKey} dir={sortDir} onSort={onSort} className="w-32">Status</SortableTh>
                {/* US-SELL-023 — coluna "Produção" badge FSM (Grade Avançada only,
                    Lista mode é enxuto e não mostra esta coluna). Default visível. */}
                <Th className="w-32">Produção</Th>
                {/* US-SELL-042 — coluna "Ações" rápidas (só renderiza se onPayClick passado). */}
                {onPayClick && <Th className="w-12 text-center">·</Th>}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={onPayClick ? 12 : 11} className="text-center py-12 text-muted-foreground text-xs">
                    <Loader2 className="inline-block mr-2 h-3.5 w-3.5 animate-spin" />
                    Carregando…
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={onPayClick ? 12 : 11} className="text-center py-12 text-muted-foreground text-xs">
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
                          {/* US-SELL-024 — badge "Agrupada" ao lado do nº fatura quando true.
                              Substitui a inferência confusa "ATIVO CRIADO" do Delphi por flag boolean explícita. */}
                          <GroupedInvoiceBadge isGrouped={row.is_grouped_invoice} />
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
                      {/* US-SELL-041 follow-up #1314 — método de pagamento + parcelas
                          (PIX/Cartão/Boleto/Dinheiro/...). NULL/empty cai em "—" silencioso. */}
                      <td className="px-3 py-2.5 text-xs text-muted-foreground whitespace-nowrap">
                        {row.payment_method_label ?? '—'}
                        {row.installments > 1 && (
                          <span className="ml-1 text-muted-foreground/70">· {row.installments}×</span>
                        )}
                      </td>
                      <td className="px-3 py-2.5">
                        <PaymentStatusBadge status={row.payment_status} overdue={row.is_overdue} />
                      </td>
                      {/* US-SELL-023 — badge produção (FSM stage). NULL = "—" silencioso pra legacy. */}
                      <td className="px-3 py-2.5">
                        <ProducaoStageBadge stageKey={row.current_stage_key} />
                      </td>
                      {/* US-SELL-042 — botão "Pagar" inline (Larissa biz=4 ADR 0105).
                          Só renderiza pra vendas com saldo devedor. stopPropagation
                          evita abrir o drawer ao clicar. */}
                      {onPayClick && (
                        <td className="px-2 py-2.5 text-center" onClick={(e) => e.stopPropagation()}>
                          {row.payment_status !== 'paid' && (
                            <button
                              type="button"
                              title="Registrar pagamento"
                              onClick={() => onPayClick(row.id, row.invoice_no, Math.max(0, row.final_total - row.total_paid))}
                              className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-background hover:bg-muted text-muted-foreground hover:text-foreground transition-colors"
                            >
                              <DollarSign size={13} />
                            </button>
                          )}
                        </td>
                      )}
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

      <p className="inline-flex items-center gap-1.5 text-[11px] text-muted-foreground mt-2">
        <Layers size={12} />
        US-SELL-016/017/018/019 ativas · Próximas (aguardando sinal): SELL-022 (sub-linha produtos)
      </p>
    </div>
  );
}

// ─── US-SELL-019 — GroupedTable (TanStack getGroupedRowModel) ────────────────

interface GroupedTableProps {
  rows: SaleRow[];
  loading: boolean;
  groupBy: GroupByField;
  selectedIds: Set<number>;
  onToggleSelect: (id: number) => void;
  onRowClick: (id: number) => void;
  openSaleId: number | null;
}

function GroupedTable({
  rows,
  loading,
  groupBy,
  selectedIds,
  onToggleSelect,
  onRowClick,
  openSaleId,
}: GroupedTableProps) {
  const data = useMemo(() => buildGroupedRows(rows), [rows]);

  // Mapeia groupBy → coluna TanStack que será agrupada.
  const groupingColumnId = useMemo(() => {
    switch (groupBy) {
      case 'customer_name': return 'customer_name';
      case 'payment_status': return 'payment_status';
      case 'emission_month': return 'emission_month';
      default: return null;
    }
  }, [groupBy]);

  const columns = useMemo<ColumnDef<SaleRow & { _emissionMonth: string }>[]>(() => [
    {
      id: 'customer_name',
      accessorFn: (row) => row.customer_name ?? '—',
      header: 'Cliente',
      cell: ({ getValue }) => String(getValue() ?? '—'),
      aggregationFn: 'count',
    },
    {
      id: 'payment_status',
      accessorFn: (row) => row.payment_status,
      header: 'Status',
      cell: ({ getValue }) => PAYMENT_STATUS_LABEL[String(getValue())] ?? String(getValue()),
      aggregationFn: 'count',
    },
    {
      // US-SELL-041 — Larissa @ ROTA LIVRE biz=4 reportou 2026-05-21 (ADR 0105)
      // que método de pagamento sumiu da Grade Avançada. A coluna existe na
      // Lista (Index.tsx <td vd-pay>) desde Cowork KB-9.75 mas faltou portar
      // pra esta view alternativa. Mostra label PT-BR (Dinheiro/PIX/Cartão/...)
      // + Nx quando parcelado (installments > 1).
      id: 'payment_method_label',
      accessorFn: (row) => row.payment_method_label ?? '—',
      header: 'Pagamento',
      cell: ({ row }) => {
        const label = row.original.payment_method_label ?? '—';
        const inst = row.original.installments;
        return inst > 1 ? `${label} · ${inst}×` : label;
      },
      aggregationFn: 'count',
    },
    {
      id: 'emission_month',
      accessorFn: (row) => row._emissionMonth,
      header: 'Mês emissão',
      cell: ({ getValue }) => String(getValue() ?? '—'),
      aggregationFn: 'count',
    },
    {
      id: 'invoice_no',
      accessorKey: 'invoice_no',
      header: 'Nº fatura',
    },
    {
      id: 'display_date',
      accessorFn: (row) => row.display_date ?? row.transaction_date,
      header: 'Data',
      cell: ({ getValue }) => {
        const v = getValue();
        return v ? formatDate(String(v)) : '—';
      },
    },
    {
      id: 'final_total',
      accessorKey: 'final_total',
      header: 'Total',
      aggregationFn: 'sum',
      cell: ({ getValue }) => formatBRL(Number(getValue() ?? 0)),
      aggregatedCell: ({ getValue }) => (
        <span className="font-semibold tabular-nums">{formatBRL(Number(getValue() ?? 0))}</span>
      ),
    },
    {
      id: 'total_paid',
      accessorKey: 'total_paid',
      header: 'Pago',
      aggregationFn: 'sum',
      cell: ({ getValue }) => formatBRL(Number(getValue() ?? 0)),
      aggregatedCell: ({ getValue }) => (
        <span className="font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">
          {formatBRL(Number(getValue() ?? 0))}
        </span>
      ),
    },
  ], []);

  // useMemo aqui é CRÍTICO — sem ele, [groupingColumnId] cria nova ref a cada
  // render → TanStack vê estado novo → recria internal state → re-render loop
  // (root cause prod incident 2026-05-12 quando Wagner filtrava/agrupava).
  const grouping = useMemo<GroupingState>(
    () => (groupingColumnId ? [groupingColumnId] : []),
    [groupingColumnId]
  );
  // Default expanded all groups (DX melhor — user vê tudo, depois recolhe se quiser).
  const [expanded, setExpanded] = useState<ExpandedState>(true);

  // useMemo no state object idem — { grouping, expanded } literal cria nova ref
  // a cada render se não cachear.
  const tableState = useMemo(() => ({ grouping, expanded }), [grouping, expanded]);

  const table = useReactTable({
    data,
    columns,
    state: tableState,
    onExpandedChange: setExpanded,
    getCoreRowModel: getCoreRowModel(),
    getGroupedRowModel: getGroupedRowModel(),
    getExpandedRowModel: getExpandedRowModel(),
  });

  if (loading) {
    return (
      <div className="rounded-lg border border-border bg-background p-12 text-center text-xs text-muted-foreground">
        <Loader2 className="inline-block mr-2 h-3.5 w-3.5 animate-spin" />
        Carregando…
      </div>
    );
  }
  if (rows.length === 0) {
    return (
      <div className="rounded-lg border border-border bg-background p-12 text-center text-xs text-muted-foreground">
        Nenhuma venda encontrada nesse filtro.
      </div>
    );
  }

  const groupedRows = table.getRowModel().rows;

  return (
    <div className="rounded-lg border border-border bg-background overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-muted/50">
            <tr className="border-b border-border">
              <Th className="w-8"><span className="sr-only">Expandir grupo</span></Th>
              <Th>Nº fatura</Th>
              <Th className="w-28">Data</Th>
              <Th>Cliente</Th>
              <Th className="text-right w-28">Total</Th>
              <Th className="text-right w-28">Pago</Th>
              <Th className="w-32">Status</Th>
            </tr>
          </thead>
          <tbody>
            {groupedRows.map((row) => {
              if (row.getIsGrouped()) {
                // Header de grupo — chevron expand + label + count + subtotal
                const groupValue = row.getValue(row.groupingColumnId!);
                const groupLabel =
                  groupBy === 'payment_status'
                    ? PAYMENT_STATUS_LABEL[String(groupValue)] ?? String(groupValue)
                    : String(groupValue ?? '—');
                const subTotalCell = row.getValue('final_total');
                const subPaidCell = row.getValue('total_paid');
                return (
                  <tr
                    key={row.id}
                    className="border-b border-border bg-muted/30 hover:bg-muted/50 cursor-pointer transition-colors"
                    onClick={row.getToggleExpandedHandler()}
                  >
                    <td className="px-3 py-2.5 w-8">
                      {row.getIsExpanded() ? (
                        <ChevronDown size={14} className="text-muted-foreground" />
                      ) : (
                        <ChevronRight size={14} className="text-muted-foreground" />
                      )}
                    </td>
                    <td colSpan={3} className="px-3 py-2.5 font-semibold text-foreground">
                      {groupLabel}
                      <span className="ml-2 inline-flex items-center rounded-full bg-background border border-border px-2 py-0.5 text-[10px] font-medium text-muted-foreground tabular-nums">
                        {formatCount(row.subRows.length)} {row.subRows.length === 1 ? 'venda' : 'vendas'}
                      </span>
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums text-foreground font-semibold">
                      {formatBRL(Number(subTotalCell ?? 0))}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums font-semibold text-emerald-700 dark:text-emerald-300">
                      {formatBRL(Number(subPaidCell ?? 0))}
                    </td>
                    <td className="px-3 py-2.5">{/* status placeholder no header */}</td>
                  </tr>
                );
              }
              // Linha normal (filha de grupo expandido)
              const original = row.original;
              const isOpen = openSaleId === original.id;
              const isSelected = selectedIds.has(original.id);
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
                  onClick={() => onRowClick(original.id)}
                >
                  <td className="px-3 py-2.5 pl-6" onClick={(e) => e.stopPropagation()}>
                    <Checkbox
                      checked={isSelected}
                      onCheckedChange={() => onToggleSelect(original.id)}
                      aria-label={`Selecionar venda ${original.invoice_no}`}
                    />
                  </td>
                  <td className="px-3 py-2.5 font-medium text-foreground">
                    <span className="inline-flex items-center gap-2">
                      {original.is_overdue && (
                        <span
                          className="h-2 w-2 rounded-full bg-rose-500"
                          title="Atrasada"
                          aria-label="Venda atrasada"
                        />
                      )}
                      {original.invoice_no}
                    </span>
                  </td>
                  <td className="px-3 py-2.5 text-xs text-muted-foreground tabular-nums whitespace-nowrap">
                    {original.display_date ? formatDate(original.display_date) : '—'}
                  </td>
                  <td className="px-3 py-2.5">
                    <div className="text-foreground font-medium leading-tight">{original.customer_name ?? '—'}</div>
                    {original.customer_secondary && (
                      <div className="text-xs text-muted-foreground leading-tight mt-0.5">{original.customer_secondary}</div>
                    )}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums text-foreground">{formatBRL(original.final_total)}</td>
                  <td className="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{formatBRL(original.total_paid)}</td>
                  <td className="px-3 py-2.5">
                    <PaymentStatusBadge status={original.payment_status} overdue={original.is_overdue} />
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
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

// US-SELL-023 — Badge produção FSM. NULL stage_key (legacy sem FSM) → "—" muted.
function ProducaoStageBadge({ stageKey }: { stageKey: string | null | undefined }) {
  if (!stageKey) {
    return <span className="text-xs text-muted-foreground/60" aria-label="Sem pipeline FSM">—</span>;
  }
  const cls = PRODUCAO_STAGE_STYLE[stageKey] ?? 'bg-muted text-muted-foreground border-border';
  const label = PRODUCAO_STAGE_LABEL[stageKey] ?? stageKey;
  return (
    <span
      className={'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium ' + cls}
      title={`Estágio FSM: ${stageKey}`}
    >
      {label}
    </span>
  );
}

// US-SELL-024 — Badge "Agrupada" ao lado do nº fatura quando is_grouped_invoice=true.
// Substitui inferência ambígua "ATIVO CRIADO" do Delphi.
function GroupedInvoiceBadge({ isGrouped }: { isGrouped: boolean }) {
  if (!isGrouped) return null;
  return (
    <span
      className="inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-1.5 py-0 text-[10px] font-semibold text-violet-700 dark:border-violet-900/40 dark:bg-violet-950/40 dark:text-violet-300"
      title="Venda agrupada (várias OS combinadas em 1 fatura)"
      aria-label="Venda agrupada"
    >
      Agrupada
    </span>
  );
}
