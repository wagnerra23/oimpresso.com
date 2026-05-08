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
  Eye,
  FileText,
  Layers,
  Loader2,
  Plus,
  Receipt,
  Send,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import SaleSheet from './_components/SaleSheet';

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

export interface SellsIndexPageProps {
  sellKpis: SellKpis;
  permissions: {
    create: boolean;
    view: boolean;
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
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('');
  const [rows, setRows] = useState<SaleRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [openSaleId, setOpenSaleId] = useState<number | null>(null);

  // Paginação + ordenação.
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [sortKey, setSortKey] = useState<SortKey>('transaction_date');
  const [sortDir, setSortDir] = useState<SortDir>('desc');
  const [meta, setMeta] = useState<ListMeta | null>(null);

  // Reseta pra página 1 quando muda filtro/ordem/per-page.
  useEffect(() => {
    setPage(1);
  }, [statusFilter, sortKey, sortDir, perPage]);

  // Fetch quando qualquer parâmetro muda.
  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    const params = new URLSearchParams();
    if (statusFilter) params.set('payment_status', statusFilter);
    params.set('per_page', String(perPage));
    params.set('page', String(page));
    params.set('sort', sortKey);
    params.set('dir', sortDir);
    fetch(`/sells-list-json?${params.toString()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((json) => {
        if (cancelled) return;
        setRows(Array.isArray(json.data) ? json.data : []);
        setMeta(json.meta ?? null);
      })
      .catch(() => {
        if (cancelled) return;
        setRows([]);
        setMeta(null);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [statusFilter, page, perPage, sortKey, sortDir]);

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
    params.set('per_page', String(perPage));
    params.set('page', String(page));
    params.set('sort', sortKey);
    params.set('dir', sortDir);
    fetch(`/sells-list-json?${params.toString()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((json) => {
        setRows(Array.isArray(json.data) ? json.data : []);
        setMeta(json.meta ?? null);
      });
  };

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
            {props.permissions.create && (
              <div className="flex-shrink-0">
                <Button asChild>
                  <Link href="/sells/create">
                    <Plus className="mr-1.5 h-4 w-4" />
                    Nova venda
                  </Link>
                </Button>
              </div>
            )}
          </div>

          {/* 3 KPIs cards (Abertas — neutra; Atrasadas — destaque rose; Total mês — neutra) */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
            <KpiCard label="Abertas" value={abertas} icon={Clock} />
            <KpiCard label="Atrasadas" value={kpis.overdue} icon={AlertTriangle} danger={kpis.overdue > 0} />
            <KpiCard label="Total" value={kpis.total} icon={Receipt} />
          </div>

          {/* Filter pills — pattern Cockpit canon (rounded-full + counter, ref exemplo OS) */}
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
        </div>
      </div>

      {/* Tabela — clean, sem widget wrapper, header bg cinza muito claro */}
      <div className="container mx-auto px-8 py-6 max-w-7xl">
        <div className="rounded-lg border border-border bg-background overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-muted/50">
                <tr className="border-b border-border">
                  <SortableTh sortKey="transaction_date" current={sortKey} dir={sortDir} onSort={handleSort} className="w-32">Data</SortableTh>
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
                          {formatDate(row.transaction_date)}
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
                        <td className="px-2 py-3 text-right pr-4">
                          <Eye size={14} className="text-muted-foreground inline-block" />
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

      {/* Drawer SaleSheet — abre ao clicar linha */}
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
