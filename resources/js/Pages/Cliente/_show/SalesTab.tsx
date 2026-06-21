// Wave C — US-CRM-065 Tab Vendas DataTable (MWART F3 paridade /contacts/{id} tab sales)
// Restrições Tier 0 (ADR 0093): backend SellController filtra business_id global scope.
// Endpoint legacy: GET /sells/datatables?customer_id={id}&start_date&end_date&status (SellController@index DT)
// Wave C entrega component que aceita paginator Inertia preferencialmente — fallback fetch quando standalone.
//
// Pattern reuse: Components/shared/DataTable.tsx (TanStack + server-side) — adaptado pra contexto inline da tab.
//
// DOIS MODOS DE DADOS:
//  - Modo Inertia (Show.tsx full-page): recebe `sales` via Inertia::defer (envolto
//    em <Deferred data="sales">). Filtros/paginação fazem `router.visit(only:['sales'])`.
//  - Modo self-fetch (drawer Cliente/Index → OssTab): recebe `jsonEndpoint`
//    (`/cliente/{id}/sales-json`) e busca os dados sozinho via fetch() no mount +
//    filtros/paginação. Sem isso, o drawer passava `sales=undefined` e o componente
//    ficava preso no skeleton — as vendas nunca apareciam (fix 2026-06-08).

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AlertTriangle, ChevronLeft, ChevronRight, ExternalLink, Filter, Search, ShoppingCart, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
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
  /** Paginador vindo via Inertia::defer no parent (modo Show.tsx full-page) */
  sales?: SalesPaginator;
  /** Filtros iniciais persistidos via URL ?customer_sales_*= */
  initialFilters?: {
    start_date?: string | null;
    end_date?: string | null;
    payment_status?: string | null;
    q?: string | null;
  };
  /** Endpoint pro router.visit (modo Inertia: rota legacy ou /cliente/{id}?tab=sales) */
  endpoint?: string;
  /**
   * Endpoint JSON pro modo self-fetch (drawer). Quando presente, o componente
   * busca os dados sozinho via fetch() em vez de depender do Inertia::defer do
   * parent. Ex: `/cliente/{id}/sales-json`.
   */
  jsonEndpoint?: string;
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
  paid: 'bg-success-soft text-success-fg border-success/20',
  due: 'bg-warning-soft text-warning-fg border-warning/20',
  partial: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900',
  overdue: 'bg-destructive-soft text-destructive-fg border-destructive/20',
};

export default function SalesTab({
  contactId,
  sales,
  initialFilters = {},
  endpoint,
  jsonEndpoint,
}: SalesTabProps) {
  const [startDate, setStartDate] = useState(initialFilters.start_date ?? '');
  const [endDate, setEndDate] = useState(initialFilters.end_date ?? '');
  const [paymentStatus, setPaymentStatus] = useState(initialFilters.payment_status ?? '');
  const [query, setQuery] = useState(initialFilters.q ?? '');

  // Modo self-fetch (drawer): busca própria via fetch() quando jsonEndpoint existe.
  const selfFetch = jsonEndpoint != null;
  const [fetched, setFetched] = useState<SalesPaginator | null>(null);
  const [fetchError, setFetchError] = useState<string | null>(null);

  const baseEndpoint = endpoint ?? `/contacts/${contactId}`;

  // Dados efetivos: no modo self-fetch vêm do estado interno; senão da prop Inertia.
  const salesData = selfFetch ? fetched : sales;

  const buildParams = useCallback(
    (overrides: Record<string, string | number | undefined> = {}): Record<string, string> => {
      const params: Record<string, string> = {};
      const merged: Record<string, string | number | undefined> = {
        customer_sales_start: startDate || undefined,
        customer_sales_end: endDate || undefined,
        customer_sales_status: paymentStatus || undefined,
        customer_sales_q: query || undefined,
        ...overrides,
      };
      Object.entries(merged).forEach(([k, v]) => {
        if (v !== undefined && v !== '') params[k] = String(v);
      });
      return params;
    },
    [startDate, endDate, paymentStatus, query],
  );

  // ----- Modo self-fetch (fetch direto) -----
  const loadJson = useCallback(async (url: string) => {
    setFetchError(null);
    try {
      const res = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = (await res.json()) as SalesPaginator;
      setFetched(json);
    } catch (err) {
      console.error('[SalesTab] self-fetch falhou', err);
      setFetchError('Não foi possível carregar as vendas.');
    }
  }, []);

  // Carga inicial (mount) + recarrega quando o drawer troca de contato (jsonEndpoint muda).
  useEffect(() => {
    if (!selfFetch || !jsonEndpoint) return;
    setFetched(null);
    const qs = new URLSearchParams(buildParams()).toString();
    loadJson(qs ? `${jsonEndpoint}?${qs}` : jsonEndpoint);
    // Deliberado: só refaz no mount/troca de contato, não a cada tecla.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [jsonEndpoint, selfFetch]);

  // ----- Modo Inertia (router.visit partial reload) -----
  const visitWith = (overrides: Record<string, string | number | undefined>) => {
    const params: Record<string, string> = { tab: 'sales', ...buildParams(overrides) };
    router.visit(baseEndpoint, {
      data: params,
      preserveScroll: true,
      preserveState: true,
      only: ['sales'],
    });
  };

  const applyFilters = () => {
    if (selfFetch && jsonEndpoint) {
      const qs = new URLSearchParams(buildParams()).toString();
      loadJson(qs ? `${jsonEndpoint}?${qs}` : jsonEndpoint);
      return;
    }
    visitWith({});
  };

  const clearFilters = () => {
    setStartDate('');
    setEndDate('');
    setPaymentStatus('');
    setQuery('');
    if (selfFetch && jsonEndpoint) {
      setFetched(null);
      loadJson(jsonEndpoint);
      return;
    }
    router.visit(baseEndpoint, {
      data: { tab: 'sales' },
      preserveScroll: true,
      preserveState: true,
      only: ['sales'],
    });
  };

  const goToLink = (url: string) => {
    if (selfFetch) {
      loadJson(url);
      return;
    }
    router.visit(url, { preserveScroll: true, preserveState: true, only: ['sales'] });
  };

  const totals = useMemo(() => {
    if (!salesData?.data) return null;
    return salesData.data.reduce(
      (acc, s) => ({
        final_total: acc.final_total + s.final_total,
        total_paid: acc.total_paid + s.total_paid,
        total_due: acc.total_due + s.total_due,
      }),
      { final_total: 0, total_paid: 0, total_due: 0 },
    );
  }, [salesData]);

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
            <Select value={paymentStatus || '__none__'} onValueChange={(v) => setPaymentStatus(v === '__none__' ? '' : v)}>
              <SelectTrigger className="w-full" aria-label="Status pagamento" data-testid="sales-payment-status">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none__">Todos</SelectItem>
                <SelectItem value="paid">Pago</SelectItem>
                <SelectItem value="due">A receber</SelectItem>
                <SelectItem value="partial">Parcial</SelectItem>
                <SelectItem value="overdue">Vencido</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="flex-1 min-w-[180px]">
            <label className="text-xs font-medium text-muted-foreground mb-1.5 block">Buscar</label>
            <div className="relative">
              <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden />
              <Input
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                placeholder="Nº fatura, ref…"
                className="cw-input-icon-left"
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
      {!salesData ? (
        selfFetch && fetchError ? (
          <div className="rounded-lg border border-border bg-background p-8 text-center" data-testid="sales-error">
            <AlertTriangle className="mx-auto h-9 w-9 text-muted-foreground/50 mb-2" strokeWidth={1.5} aria-hidden />
            <p className="text-sm text-foreground">{fetchError}</p>
            <Button variant="outline" size="sm" className="mt-3" onClick={applyFilters} data-testid="sales-retry-btn">
              Tentar de novo
            </Button>
          </div>
        ) : (
          <SalesSkeleton />
        )
      ) : salesData.data.length === 0 ? (
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
                  {salesData.data.map((s) => (
                    <tr
                      key={s.id}
                      className="border-b border-border hover:bg-muted/40 cursor-pointer select-none"
                      data-testid={`sales-row-${s.id}`}
                      onDoubleClick={() => router.visit(`/sells/${s.id}`)}
                      title="Duplo-clique para abrir a venda"
                    >
                      <td className="px-4 py-2.5 text-xs text-muted-foreground tabular-nums">{formatDate(s.transaction_date)}</td>
                      <td className="px-4 py-2.5">
                        <Link href={`/sells/${s.id}`} className="font-medium text-foreground hover:underline">
                          {s.invoice_no}
                        </Link>
                        {s.ref_no && <span className="ml-2 text-[10px] text-muted-foreground">({s.ref_no})</span>}
                        {s.location_name && (
                          <div className="text-[10px] text-muted-foreground mt-0.5">{s.location_name}</div>
                        )}
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums text-foreground">{formatBRL(s.final_total)}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums text-success-fg">
                        {formatBRL(s.total_paid)}
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums text-destructive-fg">
                        {s.total_due > 0 ? formatBRL(s.total_due) : '—'}
                      </td>
                      <td className="px-4 py-2.5">
                        <PaymentBadge status={s.payment_status} />
                      </td>
                      <td className="px-4 py-2.5 text-right">
                        <Button variant="ghost" size="sm" asChild>
                          <Link href={`/sells/${s.id}`} aria-label={`Ver venda ${s.invoice_no}`}>
                            <ExternalLink size={14} />
                          </Link>
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
                {totals && (
                  <tfoot className="bg-muted/30 border-t border-border">
                    <tr>
                      <td colSpan={2} className="px-4 py-2.5 text-xs text-muted-foreground">
                        Totais ({salesData.from ?? 0}–{salesData.to ?? 0} de {salesData.total})
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums font-semibold text-foreground">
                        {formatBRL(totals.final_total)}
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums font-semibold text-success-fg">
                        {formatBRL(totals.total_paid)}
                      </td>
                      <td className="px-4 py-2.5 text-right tabular-nums font-semibold text-destructive-fg">
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
          {salesData.last_page > 1 && (
            <div className="flex items-center justify-between text-xs text-muted-foreground">
              <span>
                Página {salesData.current_page} de {salesData.last_page} · {salesData.total} venda(s)
              </span>
              <div className="flex gap-1">
                {salesData.links.map((link, i) => {
                  const isPrev = link.label.includes('Previous') || link.label.includes('&laquo;');
                  const isNext = link.label.includes('Next') || link.label.includes('&raquo;');
                  return (
                    <Button
                      key={i}
                      variant={link.active ? 'default' : 'outline'}
                      size="sm"
                      className="h-7 min-w-8 px-2 text-xs"
                      disabled={!link.url}
                      onClick={() => link.url && goToLink(link.url)}
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
