// Wave 1 W1-A — MWART /sells/drafts (Rascunhos).
// Refs: ADR 0104, ADR 0149 (pattern reuse Sells/Index), ADR 0110 (Cockpit V2), ADR 0143 FSM, ADR 0093.
//
// Lista compacta de rascunhos (status=draft, sub_status NULL).
// Reusa SaleSheet drawer do Index pra ver detalhe/finalizar.
//
// US-SELL-DRAFTS-COWORK — Onda Cowork Sells/Drafts (2026-05-18).
// Visual reusa família Sells/Index (.sells-cowork) + extensões mínimas
// específicas (.sells-cowork-drafts) — badge "Rascunho", chip idade,
// botão "Continuar venda". Espelha pattern PR #1049 Sells/Quotations.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { ArrowLeft, FileText, MoreVertical, Plus, Printer, Search, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { printSaleReceipt } from '@/Lib/printSaleReceipt';
import { Grid } from '@/Components/layout';
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
  /** Total gravado do rascunho (`transactions.final_total`, decimal cru do MySQL). */
  final_total: number;
}

export interface SellsDraftsPageProps {
  kpis: { total: number };
  filters: {
    businessLocations: Record<number, string>;
    customers?: Record<number, string>;  // deferred
    salesRepresentative: Record<number, string>;
  };
  /** `delete` = `draft.delete` (P-076 — Excluir no menu Ações da linha). */
  permissions: { view_all: boolean; view_own: boolean; delete?: boolean };
  urls: { datatable: string; back: string };
}

// O endpoint manda a quantidade já formatada em pt-BR (`format_quantity` → "1,0000"), e
// `Number("1,0000")` dava NaN na coluna Itens. Ponto é milhar, vírgula é decimal.
function quantidadePtBR(v: unknown): number {
  if (typeof v === 'number') return v;
  const n = Number(String(v ?? '').replace(/\./g, '').replace(',', '.'));
  return Number.isFinite(n) ? n : 0;
}

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

// `final_total` chega como decimal do MySQL ("389.7000") — ponto é decimal aqui, não milhar.
function valorDoBanco(v: unknown): number {
  const n = typeof v === 'number' ? v : Number(String(v ?? ''));
  return Number.isFinite(n) ? n : 0;
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

// Idade do rascunho em dias (sinal de abandono — quanto mais velho, mais quente).
// fresh: 0-2d · aging: 3-7d · stale: 8d+
function draftAgeDays(input: string): number | null {
  if (!input) return null;
  const d = new Date(input);
  if (Number.isNaN(d.getTime())) return null;
  const diffMs = Date.now() - d.getTime();
  return Math.max(0, Math.floor(diffMs / (1000 * 60 * 60 * 24)));
}

function draftAgeTone(days: number | null): 'fresh' | 'aging' | 'stale' {
  if (days === null || days <= 2) return 'fresh';
  if (days <= 7) return 'aging';
  return 'stale';
}

function draftAgeLabel(days: number | null): string {
  if (days === null) return '—';
  if (days === 0) return 'hoje';
  if (days === 1) return '1 dia';
  return `${days} dias`;
}

export default function SellsDrafts(props: SellsDraftsPageProps) {
  const { kpis, urls, permissions } = props;
  const podeExcluir = Boolean(permissions?.delete);
  const [rows, setRows] = useState<DraftRow[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [search, setSearch] = useState<string>('');
  // P-076 — rascunho escolhido no menu Ações para excluir (abre a confirmação).
  const [aExcluir, setAExcluir] = useState<DraftRow | null>(null);
  const [excluindo, setExcluindo] = useState<boolean>(false);

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
        total_items: quantidadePtBR(r.total_items),
        total_quantity: quantidadePtBR(r.total_quantity),
        added_by: r.added_by ? String(r.added_by) : null,
        final_total: valorDoBanco(r.final_total),
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

  const imprimir = async (r: DraftRow) => {
    try {
      await printSaleReceipt({ printUrl: `/sells/${r.id}/print`, invoiceNo: r.invoice_no });
    } catch (e) {
      toast.error(`Não foi possível imprimir o rascunho ${r.invoice_no}: ${(e as Error).message}`);
    }
  };

  // Mesmo endpoint da lista Blade e da pílula Rascunhos de /sells: DELETE /sells/{id}
  // (SellPosController@destroy). Responde 200 com {success:false} quando recusa.
  const confirmarExclusao = async () => {
    if (!aExcluir) return;
    const r = aExcluir;
    setExcluindo(true);
    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
      const res = await fetch(`/sells/${r.id}`, {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
      });
      const json = await res.json().catch(() => null);
      if (!res.ok || !json?.success) {
        toast.error(json?.msg ?? `Não foi possível excluir o rascunho ${r.invoice_no}.`);
        return;
      }
      toast.success(`Rascunho ${r.invoice_no} excluído.`);
      setAExcluir(null);
      // Lista e total vêm do servidor de novo — o total do topo não é contado no navegador.
      await fetchDrafts();
      router.reload({ only: ['kpis'] });
    } catch (e) {
      toast.error(`Erro ao excluir: ${(e as Error).message}`);
    } finally {
      setExcluindo(false);
    }
  };

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

      {/* US-SELL-DRAFTS-COWORK — wrapper duplo: família .sells-cowork (tokens canon)
          + extensão .sells-cowork-drafts (badge/idade/CTA-continuar específicos). */}
      <div className="sells-cowork sells-cowork-drafts container mx-auto px-6 py-6 space-y-6">
        {/* Header */}
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div className="flex items-start gap-3">
            <Button variant="ghost" size="icon" asChild aria-label="Voltar">
              <Link href={urls.back}>
                <ArrowLeft className="h-4 w-4" />
              </Link>
            </Button>
            <div>
              <div className="flex items-center gap-2 flex-wrap">
                <h1 className="text-2xl font-semibold tracking-tight">Rascunhos</h1>
                <span className="vd-draft-badge" aria-label="Status rascunho">
                  Rascunho
                </span>
              </div>
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
          {/* variant=shadcn pra permitir pl-9 (ícone-prefix). Default cowork tem
              padding hardcoded `0 8px` que sobrepõe o pl-9 Tailwind — ícone fica
              em cima do texto. ADR UI-0015 default cowork 2026-05-27. */}
          <Input
            variant="shadcn"
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
                  <th className="text-left px-3 py-2 font-medium">Idade</th>
                  <th className="text-left px-3 py-2 font-medium">Nº rascunho</th>
                  <th className="text-left px-3 py-2 font-medium">Cliente</th>
                  <th className="text-left px-3 py-2 font-medium">Local</th>
                  <th className="text-right px-3 py-2 font-medium">Itens</th>
                  <th className="text-right px-3 py-2 font-medium">Valor</th>
                  <th className="text-right px-5 py-2 font-medium">Ações</th>
                </tr>
              </thead>
              <tbody>
                {filteredRows.map((r, idx) => {
                  const ageDays = draftAgeDays(r.transaction_date);
                  const ageTone = draftAgeTone(ageDays);
                  return (
                    <tr
                      key={r.id}
                      className={`border-b border-border last:border-0 ${idx % 2 === 1 ? 'bg-muted/20' : ''}`}
                    >
                      <td className="px-5 py-3 tabular-nums">{formatDateTime(r.transaction_date)}</td>
                      <td className="px-3 py-3">
                        <span
                          className={`vd-draft-age ${ageTone}`}
                          aria-label={`Idade do rascunho: ${draftAgeLabel(ageDays)}`}
                        >
                          {draftAgeLabel(ageDays)}
                        </span>
                      </td>
                      <td className="px-3 py-3 font-mono text-xs">{r.invoice_no}</td>
                      <td className="px-3 py-3">{r.customer_name ?? '—'}</td>
                      <td className="px-3 py-3 text-muted-foreground">{r.business_location}</td>
                      <td className="px-3 py-3 text-right tabular-nums">{r.total_items}</td>
                      <td className="px-3 py-3 text-right tabular-nums">{BRL.format(r.final_total)}</td>
                      <td className="px-5 py-3 text-right">
                        <div className="inline-flex items-center justify-end gap-1.5">
                          <Link
                            href={`/sells/${r.id}/edit`}
                            className="vd-draft-continue-btn"
                            aria-label={`Continuar venda ${r.invoice_no}`}
                          >
                            <FileText className="h-3 w-3" />
                            Continuar venda
                          </Link>
                          {/* P-076 — menu Ações (aprovado por [M] 2026-11-10): Imprimir · Excluir. */}
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button
                                variant="outline"
                                size="icon"
                                className="h-7 w-7"
                                aria-label={`Ações do rascunho ${r.invoice_no}`}
                              >
                                <MoreVertical className="h-3.5 w-3.5" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-64">
                              <DropdownMenuItem onSelect={() => imprimir(r)}>
                                <Printer className="h-4 w-4" />
                                Imprimir rascunho
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              {podeExcluir ? (
                                <DropdownMenuItem variant="destructive" onSelect={() => setAExcluir(r)}>
                                  <Trash2 className="h-4 w-4 mr-2" />
                                  Excluir rascunho
                                </DropdownMenuItem>
                              ) : (
                                <DropdownMenuItem disabled className="flex-col items-start gap-0.5">
                                  <span className="inline-flex items-center">
                                    <Trash2 className="h-4 w-4 mr-2" />
                                    Excluir rascunho
                                  </span>
                                  <span className="text-xs text-muted-foreground">
                                    Seu perfil não tem permissão para excluir rascunho. Peça ao administrador.
                                  </span>
                                </DropdownMenuItem>
                              )}
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          )}
        </section>

        {/* P-076 — confirmação de exclusão: cliente, itens e valor, sem volta. */}
        <AlertDialog
          open={aExcluir !== null}
          onOpenChange={(aberto) => {
            if (!aberto && !excluindo) setAExcluir(null);
          }}
        >
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Excluir rascunho {aExcluir?.invoice_no}</AlertDialogTitle>
              <AlertDialogDescription>
                O rascunho será apagado. Não dá para desfazer.
              </AlertDialogDescription>
            </AlertDialogHeader>
            {aExcluir && (
              <Grid asChild gap={2} className="grid-cols-[auto_1fr] gap-x-4 rounded-md bg-muted/40 px-3 py-2.5 text-sm">
                <dl>
                  <dt className="text-muted-foreground">Cliente</dt>
                  <dd>{aExcluir.customer_name ?? '—'}</dd>
                  <dt className="text-muted-foreground">Itens</dt>
                  <dd className="tabular-nums">
                    {aExcluir.total_items} {aExcluir.total_items === 1 ? 'item' : 'itens'}
                  </dd>
                  <dt className="text-muted-foreground">Valor</dt>
                  <dd className="tabular-nums">{BRL.format(aExcluir.final_total)}</dd>
                </dl>
              </Grid>
            )}
            <AlertDialogFooter>
              <AlertDialogCancel disabled={excluindo}>Cancelar</AlertDialogCancel>
              <Button variant="destructive" onClick={confirmarExclusao} disabled={excluindo}>
                {excluindo ? 'Excluindo…' : 'Excluir rascunho'}
              </Button>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>

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
