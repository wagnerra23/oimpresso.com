// Wave 1 W1-A — MWART /sells/subscriptions (Assinaturas).
// US-SELL-SUBSCRIPTIONS-COWORK marker — Onda Cowork Sells/Subscriptions (visual reuse família Index).
// Refs: ADR 0104, ADR 0149 (pattern reuse Sells/Index), ADR 0110, ADR 0143 (FSM), ADR 0093.
//
// Vendas recorrentes (status=final + is_recurring=1) com start/stop toggle inline.
//
// Visual: wrapper outer reusa família .sells-cowork (tokens canon + filtros + paginação)
// e adiciona .sells-cowork-subscriptions (extensões mínimas: badge Assinatura, chip
// frequência, chip próxima fatura, status badge ativa/pausada). Sem mudar Controller /
// props / funcionalidade.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { ArrowLeft, Pause, Play, Plus, Receipt, Repeat, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';

interface SubscriptionRow {
  id: number;
  transaction_date: string;
  invoice_no: string;
  subscription_no: string | null;
  customer_name: string | null;
  business_location: string;
  recur_stopped_on: string | null;
  recur_interval: number;
  recur_interval_type: 'days' | 'months' | 'years' | string;
  recur_repetitions: number | null;
  invoices_count: number;
  last_generated: string | null;
  upcoming_invoice: string | null;
}

export interface SellsSubscriptionsPageProps {
  kpis: { total: number; active: number; stopped: number };
  filters: { customers?: Record<number, string> };
  permissions: { update: boolean; delete: boolean };
  urls: { datatable: string; toggle: string; back: string };
}

function formatDateTime(input: string | null): string {
  if (!input) return '—';
  const d = new Date(input);
  if (Number.isNaN(d.getTime())) return input;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
  }).format(d);
}

function intervalLabel(value: number, type: string): string {
  const labels: Record<string, [string, string]> = {
    days: ['dia', 'dias'],
    months: ['mês', 'meses'],
    years: ['ano', 'anos'],
  };
  const [singular, plural] = labels[type] ?? ['?', '?'];
  return `${value} ${value === 1 ? singular : plural}`;
}

export default function SellsSubscriptions(props: SellsSubscriptionsPageProps) {
  const { kpis, urls, permissions } = props;
  const [rows, setRows] = useState<SubscriptionRow[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [search, setSearch] = useState<string>('');
  const [togglingId, setTogglingId] = useState<number | null>(null);

  const fetchSubscriptions = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch(urls.datatable, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('Falha ao carregar');
      const json = await res.json();
      const items: SubscriptionRow[] = (json.data ?? []).map((r: Record<string, unknown>) => ({
        id: Number(r.id ?? r.DT_RowId ?? 0),
        transaction_date: String(r.transaction_date ?? ''),
        invoice_no: String(r.invoice_no ?? ''),
        subscription_no: r.subscription_no ? String(r.subscription_no) : null,
        customer_name: r.name ? String(r.name) : null,
        business_location: String(r.business_location ?? ''),
        recur_stopped_on: r.recur_stopped_on ? String(r.recur_stopped_on) : null,
        recur_interval: Number(r.recur_interval ?? 1),
        recur_interval_type: String(r.recur_interval_type ?? 'months'),
        recur_repetitions: r.recur_repetitions != null ? Number(r.recur_repetitions) : null,
        invoices_count: 0,  // backend retorna como subscription_invoices HTML — não usar count exato aqui
        last_generated: null,
        upcoming_invoice: typeof r.upcoming_invoice === 'string' ? r.upcoming_invoice : null,
      }));
      setRows(items);
    } catch {
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [urls.datatable]);

  useEffect(() => {
    fetchSubscriptions();
  }, [fetchSubscriptions]);

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (
        e.target instanceof HTMLInputElement ||
        e.target instanceof HTMLTextAreaElement
      ) {
        return;
      }
      if (e.key === 'n') {
        e.preventDefault();
        router.visit('/sells/create');
      }
      if (e.key === 'Escape') {
        e.preventDefault();
        router.visit(urls.back);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [urls.back]);

  const toggleRecurring = async (id: number) => {
    if (!permissions.update || togglingId !== null) return;
    setTogglingId(id);
    try {
      const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
      const res = await fetch(`${urls.toggle}/${id}`, {
        method: 'GET',  // controller toggleRecurringInvoices é GET legacy
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
      });
      if (res.ok) {
        await fetchSubscriptions();
      }
    } catch {
      // silencioso
    } finally {
      setTogglingId(null);
    }
  };

  const filteredRows = search.trim()
    ? rows.filter(
        (r) =>
          r.invoice_no.toLowerCase().includes(search.toLowerCase()) ||
          (r.subscription_no ?? '').toLowerCase().includes(search.toLowerCase()) ||
          (r.customer_name ?? '').toLowerCase().includes(search.toLowerCase()),
      )
    : rows;

  return (
    <>
      <Head title="Assinaturas" />

      <div className="sells-cowork sells-cowork-subscriptions container mx-auto px-6 py-6 space-y-6">
        {/* Header */}
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div className="flex items-start gap-3">
            <Button variant="ghost" size="icon" asChild aria-label="Voltar">
              <Link href={urls.back}>
                <ArrowLeft className="h-4 w-4" />
              </Link>
            </Button>
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-2xl font-semibold tracking-tight">Assinaturas</h1>
                <span className="vd-sub-badge" aria-label="Tipo: Assinatura">Assinatura</span>
              </div>
              <p className="text-sm text-muted-foreground mt-1">
                Cobranças recorrentes — start/stop e acompanhar próxima fatura.
              </p>
            </div>
          </div>
          <Button asChild>
            <Link href="/sells/create">
              <Plus className="h-4 w-4 mr-2" />
              Nova venda
            </Link>
          </Button>
        </div>

        {/* KPIs */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <KpiCard label="Total" value={kpis.total} icon="repeat" tone="default" />
          <KpiCard label="Ativas" value={kpis.active} icon="play" tone="success" />
          <KpiCard label="Pausadas" value={kpis.stopped} icon="pause" tone="warning" />
        </div>

        {/* Search */}
        <div className="relative max-w-md">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Buscar por nº ou cliente…"
            className="pl-9"
          />
        </div>

        {/* Tabela */}
        <section className="rounded-lg border border-border bg-card overflow-hidden">
          {loading ? (
            <div className="p-8 text-center text-sm text-muted-foreground">
              Carregando assinaturas…
            </div>
          ) : filteredRows.length === 0 ? (
            <EmptyState
              icon="repeat"
              title={search ? 'Nenhuma assinatura encontrada' : 'Nenhuma assinatura ativa'}
              description={
                search
                  ? 'Tente outro termo de busca.'
                  : 'Configure venda recorrente ao criar uma venda nova.'
              }
            />
          ) : (
            <table className="w-full text-sm">
              <thead className="text-xs text-muted-foreground uppercase tracking-wide">
                <tr className="border-b border-border">
                  <th className="text-left px-5 py-2 font-medium">Data início</th>
                  <th className="text-left px-3 py-2 font-medium">Nº cobrança</th>
                  <th className="text-left px-3 py-2 font-medium">Cliente</th>
                  <th className="text-left px-3 py-2 font-medium">Intervalo</th>
                  <th className="text-left px-3 py-2 font-medium">Próxima fatura</th>
                  <th className="text-left px-3 py-2 font-medium">Status</th>
                  <th className="text-right px-5 py-2 font-medium">Ações</th>
                </tr>
              </thead>
              <tbody>
                {filteredRows.map((r, idx) => {
                  const isActive = !r.recur_stopped_on;
                  return (
                    <tr
                      key={r.id}
                      className={`border-b border-border last:border-0 ${idx % 2 === 1 ? 'bg-muted/20' : ''}`}
                    >
                      <td className="px-5 py-3 tabular-nums">{formatDateTime(r.transaction_date)}</td>
                      <td className="px-3 py-3 font-mono text-xs">
                        {r.subscription_no ?? r.invoice_no}
                      </td>
                      <td className="px-3 py-3">{r.customer_name ?? '—'}</td>
                      <td className="px-3 py-3">
                        <span className="vd-sub-freq" aria-label="Frequência de cobrança">
                          {intervalLabel(r.recur_interval, r.recur_interval_type)}
                        </span>
                      </td>
                      <td className="px-3 py-3">
                        <span
                          className={`vd-sub-next${r.upcoming_invoice ? '' : ' empty'}`}
                          aria-label="Próxima fatura"
                        >
                          {r.upcoming_invoice ?? '—'}
                        </span>
                      </td>
                      <td className="px-3 py-3">
                        {isActive ? (
                          <span className="vd-sub-status active">
                            <Play className="h-3 w-3" />
                            Ativa
                          </span>
                        ) : (
                          <span className="vd-sub-status paused">
                            <Pause className="h-3 w-3" />
                            Pausada
                          </span>
                        )}
                      </td>
                      <td className="px-5 py-3 text-right">
                        {permissions.update && (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => toggleRecurring(r.id)}
                            disabled={togglingId === r.id}
                          >
                            {togglingId === r.id ? (
                              '...'
                            ) : isActive ? (
                              <>
                                <Pause className="h-3 w-3 mr-1" />
                                Pausar
                              </>
                            ) : (
                              <>
                                <Play className="h-3 w-3 mr-1" />
                                Retomar
                              </>
                            )}
                          </Button>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          )}
        </section>

        <Deferred data="customers" fallback={null}>
          <></>
        </Deferred>
      </div>
    </>
  );
}

SellsSubscriptions.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
