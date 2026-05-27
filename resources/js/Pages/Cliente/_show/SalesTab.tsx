// Wave C — US-CRM-065 Tab Vendas DataTable (MWART F3 paridade /contacts/{id} tab sales)
// Restrições Tier 0 (ADR 0093): backend SellController filtra business_id global scope.
// Endpoint legacy: GET /sells/datatables?customer_id={id}&start_date&end_date&status (SellController@index DT)
// Wave C entrega component que aceita paginator Inertia preferencialmente — fallback fetch quando standalone.
//
// Pattern reuse: Components/shared/DataTable.tsx (TanStack + server-side) — adaptado pra contexto inline da tab.
// Como SalesTab é nested em Show.tsx (não rota standalone), Inertia router.visit faz partial reload
// com `only: ['sales']` no parent. Wave C entrega lifting via callback `onFilterChange`.

import { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, ExternalLink, Filter, Search, ShoppingCart, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

export interface SaleRow {
  id: number;
  invoice_no: string;
  ref_no: string | null;
  transaction_date: string | null;
  final_total: number;
  total_paid: number;
  total_due: number; // final_total - total_paid
  payment_status: 'paid' | 'due' | 'partial' | 'overdue';
  status: 'final' | 'draft' | 'quotation';
  location_name: string | null;
}

export interface SalesPaginator {
  data: SaleRow[];
  total: number;
  current_page: number;
  last_page: number;
  from: number | null;
  to: number | null;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

export interface SalesTabProps {
  contactId: number;
  /** Paginador vindo via Inertia::defer no parent */
  sales?: SalesPaginator;
  /** Filtros iniciais persistidos via URL ?customer_sales_*= */
  initialFilters?: {
    start_date?: string | null;
    end_date?: string | null;
    payment_status?: string | null;
    q?: string | null;
  };
  /** Endpoint pro router.visit (parent decide: rota legacy ou /cliente/{id}?tab=sales) */
  endpoint?: string;
}

const formatBRL = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

const formatDate = (iso: string | null) => {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(d);
};

const PAYMENT_STATUS_LABELS: Record<string, string> = {
  paid: 'Pago',
  due: 'A receber',
  partial: 'Parcial',
  overdue: 'Vencido',
};

const PAYMENT_STATUS_STYLES: Record<string, string> = {
  paid: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900',
  due: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900',
  partial: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900',
  overdue: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900',
};

export default function SalesTab({
  contactId,
  sales,
  initialFilters = {},
  endpoint,
}: SalesTabProps) {
  const [startDate, setStartDate] = useState(initialFilters.start_date ?? '');
  const [endDate, setEndDate] = useState(initialFilters.end_date ?? '');
  const [paymentStatus, setPaymentStatus] = useState(initialFilters.payment_status ?? '');
  const [query, setQuery] = useState(initialFilters.q ?? '');

  const baseEndpoint = endpoint ?? `/contacts/${contactId}`;

  const visitWith = (overrides: Record<string, string | number | undefined>) => {
    const params: Record<string, string | number> = {
      tab: 'sales',
    };
    const merged = {
      customer_sales_start: startDate || undefined,
      customer_sales_end: endDate || undefined,
      customer_sales_status: paymentStatus || undefined,
      customer_sales_q: query || undefined,
      ...overrides,
    };
    Object.entries(merged).forEach(([k, v]) => {
      if (v !== undefined && v !== '') params[k] = v;
    });
    router.visit(baseEndpoint, {
      data: params,
      preserveScroll: true,
      preserveState: true,
      only: ['sales'],
    });
  };

  const applyFilters = () => visitWith({});
  const clearFilters = () => {
    setStartDate('');
    setEndDate('');
    setPaymentStatus('');
    setQuery('');
    router.visit(baseEndpoint, {
      data: { tab: 'sales' },
      preserveScroll: true,
      preserveState: true,
      only: ['sales'],
    });
  };

  const totals = useMemo(() => {
    if (!sales?.data) return null;
    return sales.data.reduce(
      (acc, s) => ({
        final_total: acc.final_total + s.final_total,
        total_paid: acc.total_paid + s.total_paid,
        total_due: acc.total_due + s.total_due,
      }),
      { final_total: 0, total_paid: 0, total_due: 0 },
    );
  }, [sales]);

  return (
    <div className="space-y-4" data-testid="sales-tab-root">
      {/* Filtros */}
      <div className="rounded-lg border border-border bg-background p-4">
        <div className="flex flex-wrap items-end gap-3">
          <div className="flex-1 min-w-[140px]">
            <label className="text-xs font-medium text-muted-foreground mb-1.5 block">Data inicial</label>
            <Input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} data-testid="sales-start-date" />
          </div>
          <div className="flex-1 min-w-[140px]">
            <label className="text-xs font-medium text-muted-foreground mb-1.5 block">Data final</label>
            <Input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} data-testid="sales-end-date" />
          </div>
          <div className="flex-1 min-w-[160px]">
            <label className="text-xs font-medium text-muted-foreground mb-1.5 block">Status pagamento</label>
            <select
              value={paymentStatus}
              onChange={(e) => setPaymentStatus(e.target.value)}
              className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
              data-testid="sales-payment-status"
            >
              <option value="">Todos</option>
              <option value="paid">Pago</option>
              <option value="due">A receber</option>
              <option value="partial">Parcial</option>
              <option value="overdue">Vencido</option>
            </select>
          </div>
          <div className="flex-1 min-w-[180px]">
            <label className="text-xs font-medium text-muted-foreground mb-1.5 block">Buscar</label>
            <div className="relative">
              <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden />
              <Input
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Nº fatura, ref…"
                className="pl-9"
                data-testid="sales-search"
              />
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Button onClick={applyFilters} data-testid="sales-apply-btn">
              <Filter className="mr-1.5 h-4 w-4" />
              Aplicar
            </Button>
            {(startDate || endDate || paymentStatus || query) && (
              <Button variant="ghost" onClick={clearFilters} data-testid="sales-clear-btn" aria-label="Limpar filtros">
                <X className="h-4 w-4" />
              </Button>
            )}
          </div>
        </div>
      </div>

      {/* Tabela */}
      {!sales ? (
        <SalesSkeleton />
      ) : sales.data.length === 0 ? (
        <div className="rounded-lg border border-border bg-background p-12 text-center" data-testid="sales-empty">
          <ShoppingCart className="mx-auto h-10 w-10 text-muted-foreground/40 mb-2" strokeWidth={1.5} aria-hidden />
          <p className="text-sm text-muted-foreground">Nenhuma venda encontrada.</p>
          <p className="text-xs text-muted-foreground/70 mt-1">Ajuste os filtros ou registre uma nova venda.</p>
        </div>
      ) : (
        <>
          <div className="rounded-lg border border-border bg-background overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-muted/50">
                  <tr className="border-b border-border">
                    <Th className="w-24">Data</Th>
                    <Th>Nº Fatura</Th>
                    <Th className="text-right w-32">Total</Th>
                    <Th className="text-right w-32">Pago</Th>
                    <Th className="text-right w-32">Pendente</Th>
                    <Th className="w-28">Status</Th>
                    <Th className="text-right w-20">Ações</Th>
                  </tr>
                </thead>
                <tbody>
                  {sales.data.map((s) => (
                    <tr key={s.id} className="border-b border-border hover:bg-muted/40" data-testid={`sales-row-${s.id}`}>
                      <td className="px-4 py-2.5 text-xs text-muted-foreground tabular-nums">{formatDate(s.transaction_date)}</td>
                      <td className="px-4 py-2.5">
                        <a href={`/sells/${s.id}`} className="font-medium text-foreground hover:underline">
                          {s.invoice_no}
                        </a>
                        {s.ref_no && <span className="ml-2 text-[10px] text-muted-foreground">({s.ref_no})</span>}
                        {s.location_name && (
                          <div className="text-[10px] text-muted-foreground mt-0.5">{s.location_name}</div>
                        )}
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums text-foreground">{formatBRL(s.final_total)}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-400">
                        {formatBRL(s.total_paid)}
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums text-rose-700 dark:text-rose-400">
                        {s.total_due > 0 ? formatBRL(s.total_due) : '—'}
                      </td>
                      <td className="px-4 py-2.5">
                        <PaymentBadge status={s.payment_status} />
                      </td>
                      <td className="px-4 py-2.5 text-right">
                        <Button variant="ghost" size="sm" asChild>
                          <a href={`/sells/${s.id}`} aria-label={`Ver venda ${s.invoice_no}`}>
                            <ExternalLink size={14} />
                          </a>
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
                {totals && (
                  <tfoot className="bg-muted/30 border-t border-border">
                    <tr>
                      <td colSpan={2} className="px-4 py-2.5 text-xs text-muted-foreground">
                        Totais ({sales.from ?? 0}–{sales.to ?? 0} de {sales.total})
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums font-semibold text-foreground">
                        {formatBRL(totals.final_total)}
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums font-semibold text-emerald-700 dark:text-emerald-400">
                        {formatBRL(totals.total_paid)}
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums font-semibold text-rose-700 dark:text-rose-400">
                        {formatBRL(totals.total_due)}
                      </td>
                      <td colSpan={2} />
                    </tr>
                  </tfoot>
                )}
              </table>
            </div>
          </div>

          {/* Paginação */}
          {sales.last_page > 1 && (
            <div className="flex items-center justify-between text-xs text-muted-foreground">
              <span>
                Página {sales.current_page} de {sales.last_page} · {sales.total} venda(s)
              </span>
              <div className="flex gap-1">
                {sales.links.map((link, i) => {
                  const isPrev = link.label.includes('Previous') || link.label.includes('&laquo;');
                  const isNext = link.label.includes('Next') || link.label.includes('&raquo;');
                  return (
                    <Button
                      key={i}
                      variant={link.active ? 'default' : 'outline'}
                      size="sm"
                      className="h-7 min-w-8 px-2 text-xs"
                      disabled={!link.url}
                      onClick={() =>
                        link.url &&
                        router.visit(link.url, {
                          preserveScroll: true,
                          preserveState: true,
                          only: ['sales'],
                        })
                      }
                      aria-label={isPrev ? 'Anterior' : isNext ? 'Próxima' : `Página ${link.label}`}
                    >
                      {isPrev ? <ChevronLeft size={12} /> : isNext ? <ChevronRight size={12} /> : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                    </Button>
                  );
                })}
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

function PaymentBadge({ status }: { status: string }) {
  const cls = PAYMENT_STATUS_STYLES[status] ?? 'bg-muted text-muted-foreground border-border';
  const label = PAYMENT_STATUS_LABELS[status] ?? status;
  return (
    <span className={'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium ' + cls}>
      {label}
    </span>
  );
}

function SalesSkeleton() {
  return (
    <div className="rounded-lg border border-border bg-background p-4 space-y-2" data-testid="sales-tab-skeleton">
      {[0, 1, 2, 3, 4].map((i) => (
        <div key={i} className="h-10 bg-muted/30 rounded animate-pulse" />
      ))}
    </div>
  );
}

function Th({ children, className = '' }: { children: React.ReactNode; className?: string }) {
  return (
    <th
      className={
        'text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' + className
      }
    >
      {children}
    </th>
  );
}
