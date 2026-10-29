// Wave 1 W1-A — MWART /sells/quotations (Cotações).
// US-SELL-QUOTATIONS-COWORK marker — Onda Cowork Sells/Quotations (visual reuse família Index).
// Refs: ADR 0104, ADR 0149 (pattern reuse Sells/Drafts), ADR 0110, ADR 0143 (FSM quote_*), ADR 0093.
//
// Lista cotações (status=draft + sub_status=quotation). Pattern idêntico Drafts;
// muda título, endpoint AJAX (?is_quotation=1), ações específicas (enviar/converter).
//
// Visual: wrapper outer reusa família .sells-cowork (tokens canon + filtros + paginação)
// e adiciona .sells-cowork-quotations (extensões mínimas: badge orçamento, CTA converter,
// chip expiração). Sem mudar Controller / props / funcionalidade.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { ArrowLeft, FileText, Plus, RefreshCw, Search, Send } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';

interface QuoteRow {
  id: number;
  transaction_date: string;
  invoice_no: string;
  customer_name: string | null;
  business_location: string;
  total_items: number;
  total_quantity: number;
  added_by: string | null;
  // UC-FLX-04: abertura do link público pelo cliente ('AAAA-MM-DD HH:MM:SS' do banco).
  aberto_em: string | null;
  aberto_vezes: number;
  // P-037: orçamento de onde esta cópia veio ("Copiar orçamento"); null quando não é cópia.
  copia_de: { id: number; numero: string } | null;
}

export interface SellsQuotationsPageProps {
  kpis: { total: number };
  filters: {
    businessLocations: Record<number, string>;
    customers?: Record<number, string>;
    salesRepresentative: Record<number, string>;
  };
  // convert = permissão de venda E a flag enable_convert_draft_to_invoice (mesma condição do Blade).
  permissions: { view_all: boolean; view_own: boolean; convert: boolean };
  // prazo = rota do Produção com `{id}` (UC-FLX-06); null sem o módulo no pacote.
  urls: { datatable: string; back: string; convert: string; prazo?: string | null };
}

/** "2026-10-26" → "26/10" sem passar por Date (fuso não desloca o dia). */
function formatDiaMes(iso: string | null | undefined): string | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso ?? '');
  return m ? `${m[3]}/${m[2]}` : null;
}

type Prazo = { estado: 'carregando' } | { estado: 'pronto'; data: string | null } | { estado: 'falhou' };

/** 'AAAA-MM-DD HH:MM:SS' → 'dd/mm hh:mm', pelo texto (sem `new Date()`, que trocaria o fuso). */
function diaHora(v: string): string {
  const [d = '', h = ''] = v.split(' ');
  const [, m, dia] = d.split('-');
  return `${dia}/${m} ${h.slice(0, 5)}`.trim();
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

export default function SellsQuotations(props: SellsQuotationsPageProps) {
  const { kpis, urls, permissions } = props;
  // Q3: cotação escolhida para converter (abre a confirmação) e trava contra clique duplo.
  const [converter, setConverter] = useState<QuoteRow | null>(null);
  const [convertendo, setConvertendo] = useState<boolean>(false);
  // UC-FLX-06: entrega prevista pela fila dos postos, consultada ao abrir a confirmação.
  const [prazo, setPrazo] = useState<Prazo | null>(null);

  useEffect(() => {
    if (!converter || !urls.prazo) {
      setPrazo(null);
      return;
    }
    let vivo = true;
    setPrazo({ estado: 'carregando' });
    fetch(urls.prazo.replace('{id}', String(converter.id)), {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((res) => (res.ok ? res.json() : Promise.reject(res.status)))
      .then((json: { data_prevista?: string | null }) => {
        if (vivo) setPrazo({ estado: 'pronto', data: json.data_prevista ?? null });
      })
      .catch(() => {
        if (vivo) setPrazo({ estado: 'falhou' });
      });
    return () => {
      vivo = false;
    };
  }, [converter, urls.prazo]);

  // Converter reusa SellPosController@convertToInvoice pela MESMA URL do Blade, em navegação
  // comum (como o app.js do Blade faz): baixa estoque e vira venda final no servidor.
  const confirmarConversao = () => {
    if (!converter || convertendo) return;
    setConvertendo(true);
    window.location.assign(urls.convert.replace('{id}', String(converter.id)));
  };
  const [rows, setRows] = useState<QuoteRow[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [search, setSearch] = useState<string>('');

  const fetchQuotations = useCallback(async () => {
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
      const items: QuoteRow[] = (json.data ?? []).map((r: Record<string, unknown>) => ({
        id: Number(r.id ?? r.DT_RowId ?? 0),
        transaction_date: String(r.transaction_date ?? ''),
        invoice_no: String(r.invoice_no ?? ''),
        customer_name: r.name ? String(r.name) : null,
        business_location: String(r.business_location ?? ''),
        total_items: Number(r.total_items ?? 0),
        total_quantity: Number(r.total_quantity ?? 0),
        added_by: r.added_by ? String(r.added_by) : null,
        aberto_em: r.quote_first_opened_at ? String(r.quote_first_opened_at) : null,
        aberto_vezes: Number(r.quote_open_count ?? 0),
        copia_de: r.duplicated_from_tx_id
          ? { id: Number(r.duplicated_from_tx_id), numero: String(r.duplicated_from_invoice_no ?? '') }
          : null,
      }));
      setRows(items);
    } catch {
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [urls.datatable]);

  useEffect(() => {
    fetchQuotations();
  }, [fetchQuotations]);

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
        router.visit('/sells/create?sub_status=quotation');
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
      <Head title="Cotações" />

      <div className="sells-cowork sells-cowork-quotations container mx-auto px-6 py-6 space-y-6">
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
                <h1 className="text-2xl font-semibold tracking-tight">Cotações</h1>
                <span className="vd-quote-badge" aria-label="Tipo: Orçamento">Orçamento</span>
              </div>
              <p className="text-sm text-muted-foreground mt-1">
                Propostas formais — enviar pro cliente e converter em venda quando aprovado.
              </p>
            </div>
          </div>
          <Button asChild>
            <Link href="/sells/create?sub_status=quotation">
              <Plus className="h-4 w-4 mr-2" />
              Nova cotação
            </Link>
          </Button>
        </div>

        {/* KPI */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <KpiCard label="Total cotações" value={kpis.total} icon="file-text" tone="default" />
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
              Carregando cotações…
            </div>
          ) : filteredRows.length === 0 ? (
            <EmptyState
              icon="file-text"
              title={search ? 'Nenhuma cotação encontrada' : 'Nenhuma cotação'}
              description={
                search
                  ? 'Tente outro termo de busca.'
                  : 'Crie uma cotação pra enviar formalmente pro cliente.'
              }
              action={
                !search ? (
                  <Button asChild>
                    <Link href="/sells/create?sub_status=quotation">
                      <Plus className="h-4 w-4 mr-2" />
                      Nova cotação
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
                  <th className="text-left px-3 py-2 font-medium">Nº cotação</th>
                  <th className="text-left px-3 py-2 font-medium">Cliente</th>
                  <th className="text-left px-3 py-2 font-medium">Local</th>
                  <th className="text-right px-3 py-2 font-medium">Itens</th>
                  <th className="text-left px-3 py-2 font-medium">Aberto</th>
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
                    <td className="px-3 py-3 font-mono text-xs">
                      {r.invoice_no}
                      {r.copia_de && (
                        <div className="font-sans text-muted-foreground" data-testid="quote-copia-de">
                          Cópia de{' '}
                          <Link href={`/sells/${r.copia_de.id}`} className="underline">
                            {r.copia_de.numero}
                          </Link>
                        </div>
                      )}
                    </td>
                    <td className="px-3 py-3">{r.customer_name ?? '—'}</td>
                    <td className="px-3 py-3 text-muted-foreground">{r.business_location}</td>
                    <td className="px-3 py-3 text-right tabular-nums">{r.total_items}</td>
                    <td className="px-3 py-3 tabular-nums" data-testid="quote-aberto">
                      {r.aberto_em && r.aberto_vezes > 0 ? (
                        <span title={`Aberto ${r.aberto_vezes} vez(es) pelo cliente`}>
                          {diaHora(r.aberto_em)}
                          {r.aberto_vezes > 1 && <span className="text-muted-foreground"> · {r.aberto_vezes}×</span>}
                        </span>
                      ) : (
                        <span className="text-muted-foreground">Não aberto</span>
                      )}
                    </td>
                    <td className="px-5 py-3 text-right">
                      <div className="inline-flex gap-2">
                        <Button variant="outline" size="sm" asChild>
                          <Link href={`/sells/${r.id}/edit`}>
                            <FileText className="h-3 w-3 mr-1" />
                            Editar
                          </Link>
                        </Button>
                        <Button variant="default" size="sm" asChild>
                          <a href={`/sells/${r.id}/print?quotation=1`} target="_blank" rel="noopener noreferrer">
                            <Send className="h-3 w-3 mr-1" />
                            Enviar
                          </a>
                        </Button>
                        {permissions.convert && (
                          <Button variant="outline" size="sm" onClick={() => setConverter(r)}>
                            <RefreshCw className="h-3 w-3 mr-1" />
                            Converter em venda
                          </Button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>

        <AlertDialog open={converter !== null} onOpenChange={(open) => { if (!open && !convertendo) setConverter(null); }}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Converter a cotação {converter?.invoice_no} em venda?</AlertDialogTitle>
              <AlertDialogDescription>
                A venda é criada com os mesmos itens e preços e o estoque é baixado agora.
                A cotação sai desta lista.
              </AlertDialogDescription>
            </AlertDialogHeader>
            {prazo && prazo.estado !== 'falhou' && (
              <p className="text-sm" data-testid="entrega-prevista" aria-live="polite">
                {prazo.estado === 'carregando' && <span className="text-muted-foreground">Calculando a entrega…</span>}
                {prazo.estado === 'pronto' && formatDiaMes(prazo.data) && (
                  <>Entrega prevista: <strong className="tabular-nums">{formatDiaMes(prazo.data)}</strong></>
                )}
                {prazo.estado === 'pronto' && !formatDiaMes(prazo.data) && (
                  <span className="text-muted-foreground">Sem roteiro de produção para prever a entrega.</span>
                )}
              </p>
            )}
            <AlertDialogFooter>
              <AlertDialogCancel disabled={convertendo}>Cancelar</AlertDialogCancel>
              <AlertDialogAction onClick={confirmarConversao} disabled={convertendo}>
                {convertendo ? 'Convertendo…' : 'Converter'}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>

        <Deferred data="customers" fallback={null}>
          <></>
        </Deferred>
      </div>
    </>
  );
}

SellsQuotations.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
