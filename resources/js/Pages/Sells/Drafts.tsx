// Wave 1 W1-A — MWART /sells/drafts (Rascunhos).
// Refs: ADR 0104, ADR 0149 (pattern reuse Sells/Index), ADR 0110 (Cockpit V2), ADR 0093.
//
// Lista compacta de rascunhos (status=draft, sub_status NULL).
// Reusa SaleSheet drawer do Index pra ver detalhe/finalizar.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { ArrowLeft, FileText, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';

interface DraftRow {
  id: number;
  transaction_date: string;
  invoice_no: string;
  customer_name: string | null;
  business_location: string;
  total_items: number;
  total_quantity: number;
  added_by: string | null;
}

export interface SellsDraftsPageProps {
  kpis: { total: number };
  filters: {
    businessLocations: Record<number, string>;
    customers?: Record<number, string>;  // deferred
    salesRepresentative: Record<number, string>;
  };
  permissions: { view_all: boolean; view_own: boolean };
  urls: { datatable: string; back: string };
}

function formatDateTime(input: string): string {
  if (!input) return '';
  const d = new Date(input);
  if (Number.isNaN(d.getTime())) return input;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
  }).format(d);
}

export default function SellsDrafts(props: SellsDraftsPageProps) {
  const { kpis, urls } = props;
  const [rows, setRows] = useState<DraftRow[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [search, setSearch] = useState<string>('');

  // Fetch DataTables endpoint AJAX legacy (preservado back-compat).
  const fetchDrafts = useCallback(async () => {
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
      // DataTables retorna {data: [...], recordsTotal, ...}
      const items: DraftRow[] = (json.data ?? []).map((r: Record<string, unknown>) => ({
        id: Number(r.id ?? r.DT_RowId ?? 0),
        transaction_date: String(r.transaction_date ?? ''),
        invoice_no: String(r.invoice_no ?? ''),
        customer_name: r.name ? String(r.name) : null,
        business_location: String(r.business_location ?? ''),
        total_items: Number(r.total_items ?? 0),
        total_quantity: Number(r.total_quantity ?? 0),
        added_by: r.added_by ? String(r.added_by) : null,
      }));
      setRows(items);
    } catch {
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [urls.datatable]);

  useEffect(() => {
    fetchDrafts();
  }, [fetchDrafts]);

  // Atalho N pra criar venda nova
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

  const filteredRows = search.trim()
    ? rows.filter(
        (r) =>
          r.invoice_no.toLowerCase().includes(search.toLowerCase()) ||
          (r.customer_name ?? '').toLowerCase().includes(search.toLowerCase()),
      )
    : rows;

  return (
    <>
      <Head title="Rascunhos de venda" />

      <div className="container mx-auto px-6 py-6 space-y-6">
        {/* Header */}
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div className="flex items-start gap-3">
            <Button variant="ghost" size="icon" asChild aria-label="Voltar">
              <Link href={urls.back}>
                <ArrowLeft className="h-4 w-4" />
              </Link>
            </Button>
            <div>
              <h1 className="text-2xl font-semibold tracking-tight">Rascunhos</h1>
              <p className="text-sm text-muted-foreground mt-1">
                Vendas salvas como rascunho — finalizar depois.
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

        {/* KPI */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <KpiCard
            label="Total rascunhos"
            value={kpis.total}
            icon="file-text"
            tone="default"
          />
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
              Carregando rascunhos…
            </div>
          ) : filteredRows.length === 0 ? (
            <EmptyState
              icon="file-text"
              title={search ? 'Nenhum rascunho encontrado' : 'Nenhum rascunho'}
              description={
                search
                  ? 'Tente outro termo de busca.'
                  : 'Comece uma venda nova — pode salvar como rascunho a qualquer momento.'
              }
              action={
                !search ? (
                  <Button asChild>
                    <Link href="/sells/create">
                      <Plus className="h-4 w-4 mr-2" />
                      Nova venda
                    </Link>
                  </Button>
                ) : undefined
              }
            />
          ) : (
            <table className="w-full text-sm">
              <thead className="text-xs text-muted-foreground uppercase tracking-wide">
                <tr className="border-b border-border">
                  <th className="text-left px-5 py-2 font-medium">Data</th>
                  <th className="text-left px-3 py-2 font-medium">Nº rascunho</th>
                  <th className="text-left px-3 py-2 font-medium">Cliente</th>
                  <th className="text-left px-3 py-2 font-medium">Local</th>
                  <th className="text-right px-3 py-2 font-medium">Itens</th>
                  <th className="text-right px-5 py-2 font-medium">Ações</th>
                </tr>
              </thead>
              <tbody>
                {filteredRows.map((r, idx) => (
                  <tr
                    key={r.id}
                    className={`border-b border-border last:border-0 ${idx % 2 === 1 ? 'bg-muted/20' : ''}`}
                  >
                    <td className="px-5 py-3 tabular-nums">{formatDateTime(r.transaction_date)}</td>
                    <td className="px-3 py-3 font-mono text-xs">{r.invoice_no}</td>
                    <td className="px-3 py-3">{r.customer_name ?? '—'}</td>
                    <td className="px-3 py-3 text-muted-foreground">{r.business_location}</td>
                    <td className="px-3 py-3 text-right tabular-nums">{r.total_items}</td>
                    <td className="px-5 py-3 text-right">
                      <Button variant="outline" size="sm" asChild>
                        <Link href={`/sells/${r.id}/edit`}>
                          <FileText className="h-3 w-3 mr-1" />
                          Continuar
                        </Link>
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>

        {/* Deferred customers — pre-load dropdown grande pra filtros futuros */}
        <Deferred data="customers" fallback={null}>
          {/* customers props.filters.customers carregado pra autocomplete futuro */}
          <></>
        </Deferred>
      </div>
    </>
  );
}

SellsDrafts.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
