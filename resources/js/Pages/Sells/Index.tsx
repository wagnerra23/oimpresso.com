// US-SELL-008 — Lista de vendas Inertia/React (PR único: shell + drawer).
// Refs: ADR 0107 (visual gate), ADR 0109 (Claude Design plugin canon)
//        Pages/ProjectMgmt/Board (pattern Cockpit list+detail)
//        Exemplo Anthropic claude.ai/design Officeimpresso/OS (gold-standard Wagner aprovou).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import {
  AlertTriangle,
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  CheckCircle2,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Clock,
  CreditCard,
  Edit,
  Eye,
  FileText,
  Layers,
  Loader2,
  MoreVertical,
  Plus,
  Printer,
  Receipt,
  Search,
  Send,
  Trash2,
  Undo2,
  X,
} from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import SaleSheet from './_components/SaleSheet';
import SellsToggleViewMode, { type SellsViewMode } from './_components/SellsToggleViewMode';
import SellsGradeAvancada from './_components/SellsGradeAvancada';
import SellsTotalsRow, { type SellsTotals } from './_components/SellsTotalsRow';

interface SellKpis {
  total: number;
  paid: number;
  due: number;
  partial: number;
  overdue: number;
}

interface SaleRow {
  id: number;
  transaction_date: string;
  // US-SELL-021 — valor da data conforme `date_field` escolhido pelo user no header dropdown.
  // Pode ser null se a venda não tem o campo (ex: nfe_issued_at sem NF emitida).
  display_date: string | null;
  invoice_no: string;
  final_total: number;
  total_paid: number;
  payment_status: 'paid' | 'due' | 'partial' | string;
  shipping_status: string | null;
  customer_name: string | null;
  customer_secondary: string | null;
  location_name: string | null;
  is_overdue: boolean;
  // US-NFE-MANUAL — fiscal status badge na lista.
  fiscal_status: 'pendente' | 'autorizada' | 'rejeitada' | 'denegada' | 'cancelada' | null;
  fiscal_modelo: '55' | '65' | null;
}

// US-SELL-021 — 7 datas que user pode escolher exibir na coluna Data.
// Mapping canônico Delphi → Laravel: memory/research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md §5
type DateField =
  | 'transaction_date'  // DT_EMISSAO (default)
  | 'updated_at'        // DT_ALTERACAO
  | 'nfe_issued_at'     // NF_DT_EMISSAO (JOIN nfe_emissoes)
  | 'invoiced_at'       // DT_FATURAMENTO
  | 'invoice_sent_at'   // FATURAMENTO_DT_ENVIO
  | 'competence_date'   // DT_COMPETENCIA
  | 'due_date';         // PROJETO_DT_FIM (data prometida)

const DATE_FIELD_LABEL: Record<DateField, string> = {
  transaction_date: 'Emissão',
  updated_at: 'Última alteração',
  nfe_issued_at: 'Emissão NF',
  invoiced_at: 'Faturamento',
  invoice_sent_at: 'Envio do faturamento',
  competence_date: 'Competência',
  due_date: 'Prometido',
};

const DATE_FIELD_OPTIONS: DateField[] = [
  'transaction_date',
  'updated_at',
  'nfe_issued_at',
  'invoiced_at',
  'invoice_sent_at',
  'competence_date',
  'due_date',
];

const DATE_FIELD_STORAGE_KEY = 'oimpresso.sells.dateField';
const STATUS_FILTER_STORAGE_KEY = 'oimpresso.sells.lastStatus';
const STATUS_FILTER_VALUES = ['', 'paid', 'due', 'partial', 'overdue'] as const;

// US-SELL-015 — viewMode persist (ADR 0136). Default vem do controller via
// HandleInertiaRequests share (`sells.viewMode.default`) — 'grade-avancada'
// pra business com legacy_origin='officeimpresso', 'lista' pros demais.
// Toggle manual do user precede sempre.
const VIEW_MODE_STORAGE_KEY = 'oimpresso.sells.viewMode';
const VIEW_MODE_VALUES = ['lista', 'grade-avancada'] as const;

function readStoredViewMode(serverDefault: SellsViewMode): SellsViewMode {
  if (typeof window === 'undefined') return serverDefault;
  try {
    const v = window.localStorage.getItem(VIEW_MODE_STORAGE_KEY);
    if (v && (VIEW_MODE_VALUES as readonly string[]).includes(v)) {
      return v as SellsViewMode;
    }
  } catch (_) { /* localStorage indisponível */ }
  return serverDefault;
}

function readStoredDateField(): DateField {
  if (typeof window === 'undefined') return 'transaction_date';
  // URL query (deep-link) tem precedência se válida.
  try {
    const params = new URLSearchParams(window.location.search);
    const fromUrl = params.get('date_field');
    if (fromUrl && (DATE_FIELD_OPTIONS as string[]).includes(fromUrl)) {
      return fromUrl as DateField;
    }
  } catch (_) { /* SSR */ }
  try {
    const v = window.localStorage.getItem(DATE_FIELD_STORAGE_KEY);
    if (v && (DATE_FIELD_OPTIONS as string[]).includes(v)) {
      return v as DateField;
    }
  } catch (_) { /* localStorage indisponível */ }
  return 'transaction_date';
}

function readStoredStatusFilter(): StatusFilter {
  if (typeof window === 'undefined') return '';
  // URL query (deep-link) tem precedência: usuário entrou via link explícito → respeitar.
  try {
    const params = new URLSearchParams(window.location.search);
    const fromUrl = params.get('payment_status');
    if (fromUrl !== null && (STATUS_FILTER_VALUES as readonly string[]).includes(fromUrl)) {
      return fromUrl as StatusFilter;
    }
  } catch (_) { /* SSR */ }
  try {
    const v = window.localStorage.getItem(STATUS_FILTER_STORAGE_KEY);
    if (v !== null && (STATUS_FILTER_VALUES as readonly string[]).includes(v)) {
      return v as StatusFilter;
    }
  } catch (_) { /* localStorage indisponível */ }
  return '';
}

export interface SellsIndexPageProps {
  sellKpis: SellKpis;
  permissions: {
    create: boolean;
    view: boolean;
  };
  // US-SELL-015 — default vindo de HandleInertiaRequests share
  // (sells.viewMode.default). Pode ser ausente em backwards-compat ou se
  // share lazy não foi solicitado por outra page — fallback 'lista'.
  sells?: {
    viewMode?: {
      default?: SellsViewMode;
    };
  };
}

type StatusFilter = '' | 'paid' | 'due' | 'partial' | 'overdue';
type SortKey = 'transaction_date' | 'invoice_no' | 'customer_name' | 'final_total' | 'payment_status';
type SortDir = 'asc' | 'desc';

interface ListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  sort: SortKey;
  dir: SortDir;
}

const PER_PAGE_OPTIONS = [10, 25, 50, 100];

const formatBRL = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

const formatDate = (iso: string) => {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
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

export default function SellsIndex(props: SellsIndexPageProps) {
  // US-SELL-015 — viewMode (lista | grade-avancada). Lê localStorage com
  // fallback pro default vindo do server (legacy_origin-aware).
  const serverViewModeDefault: SellsViewMode = props.sells?.viewMode?.default ?? 'lista';
  const [viewMode, setViewMode] = useState<SellsViewMode>(() => readStoredViewMode(serverViewModeDefault));
  useEffect(() => {
    try {
      window.localStorage.setItem(VIEW_MODE_STORAGE_KEY, viewMode);
    } catch (_) { /* localStorage indisponível */ }
  }, [viewMode]);

  const [statusFilter, setStatusFilter] = useState<StatusFilter>(() => readStoredStatusFilter());
  const [rows, setRows] = useState<SaleRow[]>([]);
  const [totals, setTotals] = useState<SellsTotals | null>(null);
  const [loading, setLoading] = useState(true);
  const [openSaleId, setOpenSaleId] = useState<number | null>(null);

  // US-SELL-016 — Multiseleção. State lifted up pra ser shared entre Lista e Grade.
  // Set<number> via state functional updates pra evitar mutação acidental.
  const [selectedIds, setSelectedIds] = useState<Set<number>>(() => new Set());

  // US-SELL-017 — Toggle "Mostrar totais" em modo Lista (off por default — não polui).
  const [showTotalsLista, setShowTotalsLista] = useState<boolean>(() => {
    if (typeof window === 'undefined') return false;
    try {
      return window.localStorage.getItem('oimpresso.sells.showTotalsLista') === '1';
    } catch (_) {
      return false;
    }
  });
  useEffect(() => {
    try {
      window.localStorage.setItem('oimpresso.sells.showTotalsLista', showTotalsLista ? '1' : '0');
    } catch (_) { /* localStorage indisponível */ }
  }, [showTotalsLista]);

  // Busca livre (cliente / nº fatura) — debounce 300ms.
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  useEffect(() => {
    const t = setTimeout(() => setSearch(searchInput.trim()), 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  // Paginação + ordenação.
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [sortKey, setSortKey] = useState<SortKey>('transaction_date');
  const [sortDir, setSortDir] = useState<SortDir>('desc');
  const [meta, setMeta] = useState<ListMeta | null>(null);

  // US-SELL-021 — campo de data exibido na coluna Data (7 opções via header dropdown).
  const [dateField, setDateField] = useState<DateField>(() => readStoredDateField());
  useEffect(() => {
    try {
      window.localStorage.setItem(DATE_FIELD_STORAGE_KEY, dateField);
    } catch (_) { /* localStorage indisponível */ }
    // Atualiza URL pra deep-link sem disparar Inertia visit (preserva state local).
    try {
      const url = new URL(window.location.href);
      if (dateField === 'transaction_date') {
        url.searchParams.delete('date_field');
      } else {
        url.searchParams.set('date_field', dateField);
      }
      window.history.replaceState({}, '', url.toString());
    } catch (_) { /* SSR */ }
  }, [dateField]);

  // Persistência da última aba financeira escolhida pelo user (pattern oi.* localStorage).
  useEffect(() => {
    try {
      window.localStorage.setItem(STATUS_FILTER_STORAGE_KEY, statusFilter);
    } catch (_) { /* localStorage indisponível */ }
  }, [statusFilter]);

  // Reseta pra página 1 quando muda filtro/busca/ordem/per-page/date_field.
  useEffect(() => {
    setPage(1);
  }, [statusFilter, search, sortKey, sortDir, perPage, dateField]);

  // US-SELL-016 — Limpa seleção quando muda filtro/busca/date_field — IDs
  // selecionados podem não ser mais visíveis no escopo atual, evita
  // ações em lote sobre vendas "fora do filtro" (confunde user).
  useEffect(() => {
    setSelectedIds(new Set());
  }, [statusFilter, search, dateField]);

  // Fetch quando qualquer parâmetro muda.
  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    const params = new URLSearchParams();
    if (statusFilter) params.set('payment_status', statusFilter);
    if (search) params.set('q', search);
    params.set('per_page', String(perPage));
    params.set('page', String(page));
    params.set('sort', sortKey);
    params.set('dir', sortDir);
    params.set('date_field', dateField);
    fetch(`/sells-list-json?${params.toString()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((json) => {
        if (cancelled) return;
        setRows(Array.isArray(json.data) ? json.data : []);
        setMeta(json.meta ?? null);
        setTotals(json.totals ?? null);
      })
      .catch(() => {
        if (cancelled) return;
        setRows([]);
        setMeta(null);
        setTotals(null);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [statusFilter, search, page, perPage, sortKey, sortDir, dateField]);

  const handleSort = (key: SortKey) => {
    if (key === sortKey) {
      setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
    } else {
      setSortKey(key);
      setSortDir(key === 'transaction_date' || key === 'final_total' ? 'desc' : 'asc');
    }
  };

  const refetch = () => {
    const params = new URLSearchParams();
    if (statusFilter) params.set('payment_status', statusFilter);
    if (search) params.set('q', search);
    params.set('per_page', String(perPage));
    params.set('page', String(page));
    params.set('sort', sortKey);
    params.set('dir', sortDir);
    params.set('date_field', dateField);
    fetch(`/sells-list-json?${params.toString()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((json) => {
        setRows(Array.isArray(json.data) ? json.data : []);
        setMeta(json.meta ?? null);
        setTotals(json.totals ?? null);
      });
  };

  // US-SELL-016 — Handlers de seleção (memo callbacks pra estabilidade React 19).
  const handleToggleSelect = (id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const handleToggleSelectAll = () => {
    setSelectedIds((prev) => {
      // Se TODAS as rows da página atual já estão marcadas → desmarca todas.
      const allMarked = rows.length > 0 && rows.every((r) => prev.has(r.id));
      if (allMarked) {
        const next = new Set(prev);
        rows.forEach((r) => next.delete(r.id));
        return next;
      }
      // Caso contrário marca todas as rows visíveis.
      const next = new Set(prev);
      rows.forEach((r) => next.add(r.id));
      return next;
    });
  };

  const handleClearSelection = () => setSelectedIds(new Set());

  // KPIs cards (3 principais — Abertas, Atrasadas com destaque rose, Total).
  const kpis = props.sellKpis;
  const abertas = kpis.due + kpis.partial;

  // Filter pills (5 — Todas, Pago, A receber, Parcial, Atrasadas).
  const pills: Array<{ key: StatusFilter; label: string; icon: typeof Layers; count: number; danger?: boolean }> = [
    { key: '',        label: 'Todas',     icon: Layers,         count: kpis.total },
    { key: 'paid',    label: 'Pago',      icon: CheckCircle2,   count: kpis.paid },
    { key: 'due',     label: 'A receber', icon: Clock,          count: kpis.due },
    { key: 'partial', label: 'Parcial',   icon: CreditCard,     count: kpis.partial },
    { key: 'overdue', label: 'Atrasadas', icon: AlertTriangle,  count: kpis.overdue, danger: true },
  ];

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      {/* Header Cockpit canon — h1 + subtitle + KPIs + filter pills */}
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-7xl">
          <div className="flex items-start gap-4">
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">Vendas</h1>
              <p className="text-sm text-muted-foreground mt-1 leading-relaxed">
                Lista de vendas com filtros por status e drawer de detalhes ao clicar na linha.
              </p>
            </div>
            <div className="flex-shrink-0 flex items-center gap-3">
              {/* US-SELL-015 — toggle Lista | Grade Avançada (ADR 0136) */}
              <SellsToggleViewMode viewMode={viewMode} onChange={setViewMode} />
              {props.permissions.create && (
                <Button asChild>
                  <Link href="/sells/create">
                    <Plus className="mr-1.5 h-4 w-4" />
                    Nova venda
                  </Link>
                </Button>
              )}
            </div>
          </div>

          {/* 3 KPIs cards (Abertas — neutra; Atrasadas — destaque rose; Total mês — neutra) */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
            <KpiCard label="Abertas" value={abertas} icon={Clock} />
            <KpiCard label="Atrasadas" value={kpis.overdue} icon={AlertTriangle} danger={kpis.overdue > 0} />
            <KpiCard label="Total" value={kpis.total} icon={Receipt} />
          </div>

          {/* Filter pills — pattern Cockpit canon (rounded-full + counter, ref exemplo OS).
              US-SELL-015: pills só em modo Lista — Grade Avançada terá filtros próprios (US-SELL-018+). */}
          {viewMode === 'lista' && (
          <nav className="flex items-center gap-2 mt-6 flex-wrap" aria-label="Filtro de status de pagamento">
            {pills.map((pill) => {
              const isActive = statusFilter === pill.key;
              const Icon = pill.icon;
              const danger = pill.danger && pill.count > 0;
              return (
                <button
                  key={pill.key || 'all'}
                  type="button"
                  onClick={() => setStatusFilter(pill.key)}
                  className={
                    'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors ' +
                    (isActive
                      ? danger
                        ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-200'
                        : 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                      : danger
                        ? 'bg-rose-50/60 text-rose-700 hover:bg-rose-100 dark:bg-rose-950/30 dark:text-rose-300'
                        : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground')
                  }
                  aria-current={isActive ? 'true' : undefined}
                >
                  <Icon size={13} />
                  {pill.label}
                  {pill.count > 0 && (
                    <span
                      className={
                        'ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] tabular-nums ' +
                        (isActive
                          ? danger
                            ? 'bg-rose-200/80 dark:bg-rose-900/60'
                            : 'bg-blue-100 dark:bg-blue-900/60'
                          : 'bg-background')
                      }
                    >
                      {pill.count}
                    </span>
                  )}
                </button>
              );
            })}
          </nav>
          )}
        </div>
      </div>

      {/* US-SELL-015 — render condicional. Default 'lista' = tela existente
          intacta (Cockpit V2 canon). 'grade-avancada' = skeleton com mensagem
          "em construção" — preenchido por US-SELL-016/017/018+. */}
      {viewMode === 'grade-avancada' ? (
        <div className="container mx-auto px-8 py-6 max-w-7xl">
          <SellsGradeAvancada
            rows={rows}
            loading={loading}
            totals={totals}
            selectedIds={selectedIds}
            onToggleSelect={handleToggleSelect}
            onToggleSelectAll={handleToggleSelectAll}
            onClearSelection={handleClearSelection}
            onRowClick={setOpenSaleId}
            openSaleId={openSaleId}
            totalFiltered={totals?.count ?? meta?.total ?? 0}
            sortKey={sortKey}
            sortDir={sortDir}
            onSort={handleSort}
          />
          {meta && meta.total > 0 && (
            <Pagination
              meta={meta}
              perPage={perPage}
              onPageChange={setPage}
              onPerPageChange={setPerPage}
            />
          )}
        </div>
      ) : (
      <div className="container mx-auto px-8 py-6 max-w-7xl">
        {/* Busca livre — cliente ou nº fatura */}
        <div className="mb-4 flex items-center gap-2">
          <div className="relative flex-1 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
            <Input
              type="search"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Buscar por cliente ou nº fatura…"
              className="pl-9 pr-9 h-9"
              aria-label="Buscar venda"
            />
            {searchInput && (
              <button
                type="button"
                onClick={() => setSearchInput('')}
                aria-label="Limpar busca"
                className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground hover:text-foreground"
              >
                <X className="h-4 w-4" />
              </button>
            )}
          </div>
          {loading && search && (
            <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
          )}
          {/* US-SELL-017 — Toggle "Mostrar totais" em modo Lista (default off — não polui Lista limpa) */}
          <button
            type="button"
            onClick={() => setShowTotalsLista((v) => !v)}
            className={
              'ml-auto inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium transition-colors ' +
              (showTotalsLista
                ? 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:border-blue-900/40 dark:bg-blue-950/40 dark:text-blue-300'
                : 'border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground')
            }
            aria-pressed={showTotalsLista}
            title={showTotalsLista ? 'Esconder totais' : 'Mostrar totais do filtro'}
          >
            {showTotalsLista ? 'Esconder totais' : 'Mostrar totais'}
          </button>
        </div>

        {/* US-SELL-017 — totais opt-in em modo Lista (compact single-line) */}
        {showTotalsLista && (
          <div className="mb-3">
            <SellsTotalsRow totals={totals} loading={loading} compact />
          </div>
        )}

        <div className="rounded-lg border border-border bg-background overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-muted/50">
                <tr className="border-b border-border">
                  <DateColumnHeader
                    dateField={dateField}
                    onDateFieldChange={setDateField}
                    sortDir={sortDir}
                    isSortActive={sortKey === 'transaction_date'}
                    onSort={() => handleSort('transaction_date')}
                  />
                  <SortableTh sortKey="invoice_no" current={sortKey} dir={sortDir} onSort={handleSort}>Nº fatura</SortableTh>
                  <SortableTh sortKey="customer_name" current={sortKey} dir={sortDir} onSort={handleSort}>Cliente</SortableTh>
                  <SortableTh sortKey="final_total" current={sortKey} dir={sortDir} onSort={handleSort} align="right" className="w-28">Total</SortableTh>
                  <Th className="text-right w-28">Pago</Th>
                  <SortableTh sortKey="payment_status" current={sortKey} dir={sortDir} onSort={handleSort} className="w-32">Status</SortableTh>
                  <Th className="w-32">Fiscal</Th>
                  <Th className="w-12 text-right pr-4">&nbsp;</Th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={8} className="text-center py-12 text-muted-foreground text-xs">
                      Carregando…
                    </td>
                  </tr>
                ) : rows.length === 0 ? (
                  <tr>
                    <td colSpan={8} className="text-center py-12 text-muted-foreground text-xs">
                      Nenhuma venda encontrada nesse filtro.
                    </td>
                  </tr>
                ) : (
                  rows.map((row) => {
                    const isOpen = openSaleId === row.id;
                    return (
                      <tr
                        key={row.id}
                        className={
                          'border-b border-border cursor-pointer transition-colors ' +
                          (isOpen
                            ? 'bg-blue-50/60 dark:bg-blue-950/30'
                            : 'hover:bg-muted/40')
                        }
                        onClick={() => setOpenSaleId(row.id)}
                      >
                        <td className="px-4 py-3 text-xs text-muted-foreground tabular-nums whitespace-nowrap">
                          {row.display_date ? formatDate(row.display_date) : <span className="text-muted-foreground/50">—</span>}
                        </td>
                        <td className="px-4 py-3 font-medium text-foreground">
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
                        <td className="px-4 py-3">
                          <div className="text-foreground font-medium leading-tight">{row.customer_name ?? '—'}</div>
                          {row.customer_secondary && (
                            <div className="text-xs text-muted-foreground leading-tight mt-0.5">{row.customer_secondary}</div>
                          )}
                        </td>
                        <td className="px-4 py-3 text-right tabular-nums text-foreground">{formatBRL(row.final_total)}</td>
                        <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">{formatBRL(row.total_paid)}</td>
                        <td className="px-4 py-3">
                          <PaymentStatusBadge status={row.payment_status} overdue={row.is_overdue} />
                        </td>
                        <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
                          <FiscalCell row={row} onEmitted={refetch} />
                        </td>
                        <td className="px-2 py-3 text-right pr-4" onClick={(e) => e.stopPropagation()}>
                          <ActionsMenu
                            row={row}
                            onView={() => setOpenSaleId(row.id)}
                            onChange={refetch}
                          />
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>

        {meta && meta.total > 0 && (
          <Pagination
            meta={meta}
            perPage={perPage}
            onPageChange={setPage}
            onPerPageChange={setPerPage}
          />
        )}
      </div>
      )}

      {/* Drawer SaleSheet — abre ao clicar linha (só em modo Lista — Grade Avançada terá interação própria) */}
      <SaleSheet
        saleId={openSaleId}
        open={openSaleId !== null}
        onOpenChange={(open) => {
          if (!open) setOpenSaleId(null);
        }}
        onSaleChanged={refetch}
      />
    </div>
  );
}

SellsIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

// ─── Subcomponents ───────────────────────────────────────────────────────────

function KpiCard({
  label,
  value,
  icon: Icon,
  danger,
}: {
  label: string;
  value: number;
  icon: typeof Receipt;
  danger?: boolean;
}) {
  return (
    <div
      className={
        'rounded-xl border p-5 shadow-sm ' +
        (danger
          ? 'border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/30'
          : 'border-border bg-background')
      }
    >
      <div
        className={
          'text-[11px] font-semibold uppercase tracking-widest ' +
          (danger ? 'text-rose-700 dark:text-rose-400' : 'text-muted-foreground')
        }
      >
        {label}
      </div>
      <div className="flex items-end justify-between mt-3">
        <div
          className={
            'text-4xl font-semibold tabular-nums ' +
            (danger ? 'text-rose-700 dark:text-rose-300' : 'text-foreground')
          }
        >
          {value}
        </div>
        <Icon
          size={28}
          className={danger ? 'text-rose-400' : 'text-muted-foreground/60'}
          strokeWidth={1.5}
        />
      </div>
    </div>
  );
}

function Th({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <th
      className={
        'text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' +
        className
      }
    >
      {children}
    </th>
  );
}

// US-SELL-021 — Header da coluna "Data" híbrido: sort + dropdown de 7 datas.
// Click no label faz sort por transaction_date (mantém UX), click no chevron abre dropdown.
function DateColumnHeader({
  dateField,
  onDateFieldChange,
  sortDir,
  isSortActive,
  onSort,
}: {
  dateField: DateField;
  onDateFieldChange: (f: DateField) => void;
  sortDir: SortDir;
  isSortActive: boolean;
  onSort: () => void;
}) {
  const SortIcon = !isSortActive ? ArrowUpDown : sortDir === 'asc' ? ArrowUp : ArrowDown;
  const label = DATE_FIELD_LABEL[dateField];
  return (
    <th
      className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground w-32"
      aria-sort={isSortActive ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'}
    >
      <div className="inline-flex items-center gap-0.5">
        <button
          type="button"
          onClick={onSort}
          className={
            'inline-flex items-center gap-1 transition-colors hover:text-foreground ' +
            (isSortActive ? 'text-foreground' : '')
          }
          title={`Ordenar por data · Data exibida: ${label}`}
        >
          Data
          <SortIcon size={12} className={isSortActive ? '' : 'opacity-40'} />
        </button>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button
              type="button"
              className="inline-flex h-5 items-center rounded px-1 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
              aria-label={`Trocar campo de data exibido. Atual: ${label}`}
              title={`Data exibida: ${label}. Clique pra trocar.`}
            >
              <ChevronDown size={11} />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start" className="w-56">
            {DATE_FIELD_OPTIONS.map((opt) => {
              const isActive = opt === dateField;
              return (
                <DropdownMenuItem
                  key={opt}
                  onSelect={() => onDateFieldChange(opt)}
                  className={isActive ? 'font-medium text-foreground' : ''}
                >
                  {isActive && <span className="mr-1.5 text-primary" aria-hidden>•</span>}
                  {!isActive && <span className="mr-1.5 w-2 inline-block" aria-hidden />}
                  {DATE_FIELD_LABEL[opt]}
                </DropdownMenuItem>
              );
            })}
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
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
  children: ReactNode;
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
        'px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' +
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

function Pagination({
  meta,
  perPage,
  onPageChange,
  onPerPageChange,
}: {
  meta: ListMeta;
  perPage: number;
  onPageChange: (p: number) => void;
  onPerPageChange: (n: number) => void;
}) {
  const { current_page, last_page, total, from, to } = meta;
  const canPrev = current_page > 1;
  const canNext = current_page < last_page;
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 mt-3 px-1">
      <div className="text-xs text-muted-foreground">
        {total === 0
          ? 'Nenhuma venda'
          : `Mostrando ${from ?? 0}–${to ?? 0} de ${total.toLocaleString('pt-BR')}`}
      </div>
      <div className="flex items-center gap-3">
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <span>Por página</span>
          <select
            value={perPage}
            onChange={(e) => onPerPageChange(Number(e.target.value))}
            className="h-7 rounded border border-border bg-background px-2 text-xs text-foreground"
            aria-label="Itens por página"
          >
            {PER_PAGE_OPTIONS.map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-1">
          <PageBtn onClick={() => onPageChange(1)} disabled={!canPrev} aria-label="Primeira página">
            <ChevronsLeft size={14} />
          </PageBtn>
          <PageBtn onClick={() => onPageChange(current_page - 1)} disabled={!canPrev} aria-label="Página anterior">
            <ChevronLeft size={14} />
          </PageBtn>
          <span className="px-2 text-xs tabular-nums text-foreground">
            {current_page} <span className="text-muted-foreground">/ {last_page}</span>
          </span>
          <PageBtn onClick={() => onPageChange(current_page + 1)} disabled={!canNext} aria-label="Próxima página">
            <ChevronRight size={14} />
          </PageBtn>
          <PageBtn onClick={() => onPageChange(last_page)} disabled={!canNext} aria-label="Última página">
            <ChevronsRight size={14} />
          </PageBtn>
        </div>
      </div>
    </div>
  );
}

function PageBtn({
  children,
  onClick,
  disabled,
  ...rest
}: React.ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      {...rest}
      className="inline-flex h-7 w-7 items-center justify-center rounded border border-border bg-background text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-background"
    >
      {children}
    </button>
  );
}

// Dropdown "Ações" por linha — porta o menu legado do Blade pra React.
function ActionsMenu({
  row,
  onView,
  onChange,
}: {
  row: SaleRow;
  onView: () => void;
  onChange: () => void;
}) {
  const isPaid = row.payment_status === 'paid';

  const handleDelete = async () => {
    if (!confirm(`Excluir a venda ${row.invoice_no}? Essa ação não pode ser desfeita.`)) return;
    try {
      const meta = document.querySelector('meta[name="csrf-token"]');
      const csrf = meta?.getAttribute('content') ?? '';
      const res = await fetch(`/sells/${row.id}`, {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
      });
      if (!res.ok) {
        const json = await res.json().catch(() => null);
        alert(json?.msg ?? 'Falha ao excluir venda.');
        return;
      }
      onChange();
    } catch (e) {
      alert('Erro ao excluir: ' + String((e as Error)?.message || e));
    }
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          className="inline-flex h-8 w-8 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
          aria-label="Ações da venda"
          onClick={(e) => e.stopPropagation()}
        >
          <MoreVertical size={16} />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-52">
        <DropdownMenuItem onClick={onView}>
          <Eye size={14} className="mr-2" />
          Ver detalhes
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <a href={`/sells/${row.id}/edit`} target="_blank" rel="noopener noreferrer">
            <Edit size={14} className="mr-2" />
            Editar
          </a>
        </DropdownMenuItem>
        {!isPaid && (
          <DropdownMenuItem onClick={onView}>
            <CreditCard size={14} className="mr-2" />
            Adicionar pagamento
          </DropdownMenuItem>
        )}
        <DropdownMenuItem asChild>
          <a href={`/sells/${row.id}/print`} target="_blank" rel="noopener noreferrer">
            <Printer size={14} className="mr-2" />
            Imprimir nota
          </a>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <a href={`/sell-return/add/${row.id}`}>
            <Undo2 size={14} className="mr-2" />
            Devolução
          </a>
        </DropdownMenuItem>
        <DropdownMenuItem
          onClick={handleDelete}
          className="text-rose-600 focus:bg-rose-50 focus:text-rose-700 dark:text-rose-400 dark:focus:bg-rose-950/40"
        >
          <Trash2 size={14} className="mr-2" />
          Excluir
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
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

// US-NFE-MANUAL — célula Fiscal: badge se já emitida; dropdown "Emitir" se não.
function FiscalCell({ row, onEmitted }: { row: SaleRow; onEmitted: () => void }) {
  const [emitting, setEmitting] = useState<'55' | '65' | null>(null);

  // Já tem emissão — mostra badge.
  if (row.fiscal_status === 'autorizada') {
    const label = row.fiscal_modelo === '65' ? 'NFC-e' : 'NFe';
    return (
      <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/40">
        <CheckCircle2 size={11} />
        {label}
      </span>
    );
  }
  if (row.fiscal_status === 'pendente') {
    return (
      <span className="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[11px] font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900/40">
        <Loader2 size={11} className="animate-spin" />
        Processando
      </span>
    );
  }
  if (row.fiscal_status === 'rejeitada' || row.fiscal_status === 'denegada') {
    return (
      <span className="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[11px] font-medium text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900/40">
        <AlertTriangle size={11} />
        {row.fiscal_status === 'rejeitada' ? 'Rejeitada' : 'Denegada'}
      </span>
    );
  }

  // Sem emissão → dropdown "Emitir ▾".
  async function emitir(modelo: '55' | '65') {
    setEmitting(modelo);
    try {
      const res = await fetch(`/nfe-brasil/transactions/${row.id}/emitir`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ modelo }),
      });
      if (!res.ok) {
        const json = await res.json().catch(() => ({}));
        alert(json?.message || 'Falha ao emitir');
      }
    } finally {
      setEmitting(null);
      onEmitted();
    }
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          className="inline-flex items-center gap-1 rounded-full border border-border bg-muted/40 px-2.5 py-0.5 text-[11px] font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
          disabled={emitting !== null}
        >
          {emitting !== null ? (
            <>
              <Loader2 size={11} className="animate-spin" />
              Emitindo…
            </>
          ) : (
            <>
              <Send size={11} />
              Emitir
              <ChevronDown size={10} />
            </>
          )}
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start">
        <DropdownMenuItem onSelect={() => emitir('65')}>
          <FileText className="mr-2 h-4 w-4" />
          NFC-e (modelo 65)
        </DropdownMenuItem>
        <DropdownMenuItem onSelect={() => emitir('55')}>
          <Send className="mr-2 h-4 w-4" />
          NFe (modelo 55)
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
