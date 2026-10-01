// SalesOrder/Index — Pedido de venda (/sales-order). Thread 06 do playbook venda-menu.
// PT-01 Lista (UI-0013) + drawer PT-02 pra status. Golden: Sells/Drafts (lista dual que lê o
// endpoint AJAX legado). Protótipo: VendaPedidos em prototipo-ui/cowork/Wagner/venda-blade-telas.jsx.
// Refs: ADR 0104 (MWART), ADR 0093 (multi-tenant — o endpoint já escopa business_id).
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Pencil, Search } from 'lucide-react';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import EmptyState from '@/Components/shared/EmptyState';
import { Inline, Stack } from '@/Components/layout';

type StatusKey = 'ordered' | 'partial' | 'completed';

interface Pedido {
  id: number;
  data: string;
  numero: string;
  cliente: string;
  contato: string;
  local: string;
  status: StatusKey | null;
  statusLabel: string;
  envio: string;
  restante: string;
  adicionadoPor: string;
  editavel: boolean;
}

export interface SalesOrderIndexProps {
  salesOrderEnabled: boolean;
  filters: {
    businessLocations: Record<string, string>;
    customers?: Record<string, string>; // deferred
    statuses: Record<StatusKey, string>;
    shippingStatuses: Record<string, string>;
  };
  permissions: { view_all: boolean; view_own: boolean; create: boolean; edit_status: boolean };
  urls: { datatable: string; updateStatus: string; create: string };
}

const TODOS = 'ALL';
const TOM: Record<StatusKey, 'info' | 'warning' | 'success'> = { ordered: 'info', partial: 'warning', completed: 'success' };

/** O DataTable legado devolve células em HTML; a tela só quer o texto. */
function texto(html: unknown): string {
  if (html === null || html === undefined) return '';
  const doc = new DOMParser().parseFromString(String(html), 'text/html');
  return (doc.body.textContent ?? '').replace(/\s+/g, ' ').trim();
}

function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

/** Opções data-driven sem chave/rótulo vazio — Radix Select quebra com value="" (§5 2026-06-29). */
function opcoes(mapa: Record<string, string> | undefined) {
  return Object.entries(mapa ?? {}).filter(([k, v]) => k !== '' && v);
}

export default function SalesOrderIndex({ salesOrderEnabled, filters, permissions, urls }: SalesOrderIndexProps) {
  const [linhas, setLinhas] = useState<Pedido[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [busca, setBusca] = useState('');
  const [f, setF] = useState({ location_id: TODOS, customer_id: TODOS, status: TODOS, shipping_status: TODOS });
  const [alvo, setAlvo] = useState<Pedido | null>(null);
  const [novoStatus, setNovoStatus] = useState<StatusKey>('ordered');
  const [salvando, setSalvando] = useState(false);
  const [erro, setErro] = useState('');

  const chavePorRotulo = useMemo(() => {
    const m: Record<string, StatusKey> = {};
    (Object.keys(filters.statuses) as StatusKey[]).forEach((k) => { m[filters.statuses[k]] = k; });
    return m;
  }, [filters.statuses]);

  // Mesmo endpoint AJAX do Blade (SellController@index, ramo ajax sem X-Inertia).
  const carregar = useCallback(async () => {
    setCarregando(true);
    const q = new URLSearchParams({ draw: '1', start: '0', length: '100' });
    Object.entries(f).forEach(([k, v]) => { if (v !== TODOS) q.set(k, v); });
    try {
      const res = await fetch(`${urls.datatable}&${q.toString()}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('Falha ao carregar');
      const json = await res.json();
      setLinhas((json.data ?? []).map((r: Record<string, unknown>): Pedido => {
        const statusLabel = texto(r.status);
        return {
          id: Number(r.id ?? r.DT_RowId ?? 0),
          data: texto(r.transaction_date),
          numero: texto(r.invoice_no),
          cliente: texto(r.conatct_name),
          contato: texto(r.mobile),
          local: texto(r.business_location),
          status: chavePorRotulo[statusLabel] ?? null,
          statusLabel,
          envio: texto(r.shipping_status),
          restante: texto(r.so_qty_remaining),
          adicionadoPor: texto(r.added_by),
          // O endpoint marca a célula editável (admin e status != concluído) com edit-so-status.
          editavel: String(r.status ?? '').includes('edit-so-status'),
        };
      }));
    } catch {
      setLinhas([]);
    } finally {
      setCarregando(false);
    }
  }, [urls.datatable, f, chavePorRotulo]);

  useEffect(() => { carregar(); }, [carregar]);

  const termo = busca.trim().toLowerCase();
  const visiveis = termo ? linhas.filter((l) => `${l.numero} ${l.cliente}`.toLowerCase().includes(termo)) : linhas;

  function abrirStatus(p: Pedido) {
    setAlvo(p);
    setNovoStatus(p.status ?? 'ordered');
    setErro('');
  }

  async function salvarStatus() {
    if (!alvo) return;
    setSalvando(true);
    setErro('');
    try {
      const res = await fetch(urls.updateStatus.replace('{id}', String(alvo.id)), {
        method: 'PUT',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ status: novoStatus }),
      });
      const json = res.ok ? await res.json() : null;
      if (!json || Number(json.success) !== 1) throw new Error('falhou');
      // UC-SORD-02: a linha muda no lugar, sem recarregar a lista.
      setLinhas((ls) => ls.map((l) => (l.id === alvo.id
        ? { ...l, status: novoStatus, statusLabel: filters.statuses[novoStatus], editavel: novoStatus !== 'completed' }
        : l)));
      setAlvo(null);
    } catch {
      setErro('Não foi possível atualizar o status. Tente de novo.');
    } finally {
      setSalvando(false);
    }
  }

  const filtro = (chave: keyof typeof f, rotulo: string, mapa: Record<string, string> | undefined) => (
    <Stack gap={1} align="stretch" className="text-xs text-muted-foreground min-w-[180px]">
      <span aria-hidden="true">{rotulo}</span>
      <Select value={f[chave]} onValueChange={(v) => setF((s) => ({ ...s, [chave]: v }))}>
        <SelectTrigger aria-label={rotulo}><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value={TODOS}>Todos</SelectItem>
          {opcoes(mapa).map(([k, v]) => <SelectItem key={k} value={k}>{v}</SelectItem>)}
        </SelectContent>
      </Select>
    </Stack>
  );

  return (
    <AppShellV2>
      <Head title="Pedido de venda" />
      <div className="container mx-auto px-6 py-6 space-y-4">
        <div data-contract="cabecalho">
          <PageHeader
            title="Pedido de venda"
            subtitle="Pedidos que ainda não viraram venda — status e quantidade restante a atender."
            actions={permissions.create ? <PageHeaderPrimary label="Adicionar pedido" href={urls.create} /> : undefined}
          />
        </div>

        {!salesOrderEnabled && (
          <Alert>
            <AlertDescription>
              Pedido de venda está desligado nas configurações do POS deste negócio — o item não aparece no menu.
            </AlertDescription>
          </Alert>
        )}

        <Inline asChild wrap gap={3} align="end" className="rounded-lg border border-border bg-card p-4">
          <section data-contract="filtros">
            {filtro('location_id', 'Local do negócio', filters.businessLocations)}
            {/* customers é Inertia::defer — fallback mostra só "Todos" até a prop chegar. */}
            <Deferred data="customers" fallback={filtro('customer_id', 'Cliente', undefined)}>
              {filtro('customer_id', 'Cliente', filters.customers)}
            </Deferred>
            {filtro('status', 'Status', filters.statuses)}
            {filtro('shipping_status', 'Status de envio', filters.shippingStatuses)}
          </section>
        </Inline>

        <section data-contract="lista" className="rounded-lg border border-border bg-card overflow-hidden">
          <Inline gap={3} justify="between" className="p-3 border-b border-border">
            <div className="relative max-w-sm flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input variant="shadcn" value={busca} onChange={(e) => setBusca(e.target.value)}
                placeholder="Buscar pedido ou cliente…" className="pl-9" />
            </div>
            <span className="text-xs text-muted-foreground tabular-nums">{visiveis.length} de {linhas.length}</span>
          </Inline>
          {carregando ? (
            <div className="p-8 text-center text-sm text-muted-foreground">Carregando pedidos…</div>
          ) : visiveis.length === 0 ? (
            <EmptyState icon="file-text" title={termo ? 'Nenhum pedido encontrado' : 'Nenhum pedido de venda'}
              description={termo ? 'Tente outro termo de busca.' : 'Os pedidos aparecem aqui até virarem venda.'} />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-xs text-muted-foreground">
                  <tr className="border-b border-border">
                    <th className="text-left px-4 py-2 font-medium">Data</th>
                    <th className="text-left px-3 py-2 font-medium">Nº do pedido</th>
                    <th className="text-left px-3 py-2 font-medium">Cliente</th>
                    <th className="text-left px-3 py-2 font-medium">Contato</th>
                    <th className="text-left px-3 py-2 font-medium">Local</th>
                    <th className="text-left px-3 py-2 font-medium">Status</th>
                    <th className="text-left px-3 py-2 font-medium">Status de envio</th>
                    <th className="text-right px-3 py-2 font-medium">Quantidade restante</th>
                    <th className="text-left px-3 py-2 font-medium">Adicionado por</th>
                    <th className="text-right px-4 py-2 font-medium">Ação</th>
                  </tr>
                </thead>
                <tbody>
                  {visiveis.map((p) => (
                    <tr key={p.id} className="border-b border-border last:border-0">
                      <td className="px-4 py-2 tabular-nums whitespace-nowrap">{p.data}</td>
                      <td className="px-3 py-2 font-mono text-xs">
                        <a href={`/sells/${p.id}`} className="hover:underline">{p.numero}</a>
                      </td>
                      <td className="px-3 py-2">{p.cliente || '—'}</td>
                      <td className="px-3 py-2 font-mono text-xs">{p.contato || '—'}</td>
                      <td className="px-3 py-2 text-muted-foreground">{p.local}</td>
                      <td className="px-3 py-2">
                        {p.status ? <Badge variant={TOM[p.status]} dot>{p.statusLabel}</Badge> : p.statusLabel}
                      </td>
                      <td className="px-3 py-2">{p.envio || '—'}</td>
                      <td className="px-3 py-2 text-right tabular-nums">{p.restante}</td>
                      <td className="px-3 py-2">{p.adicionadoPor}</td>
                      <td className="px-4 py-2 text-right">
                        {permissions.edit_status && p.editavel && (
                          <Button variant="ghost" size="sm" onClick={() => abrirStatus(p)} aria-label={`Editar status de ${p.numero}`}>
                            <Pencil className="h-3.5 w-3.5 mr-1" />Status
                          </Button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          <p className="px-4 py-2 text-xs text-muted-foreground border-t border-border">
            Item condicional do menu: só aparece com <span className="font-mono">enable_sales_order</span> ligado nas configurações do POS.
          </p>
        </section>
      </div>

      <Sheet open={alvo !== null} onOpenChange={(aberto) => { if (!aberto) setAlvo(null); }}>
        <SheetContent side="right" className="w-[420px] sm:max-w-[420px]">
          <SheetHeader>
            <SheetTitle>Editar status · {alvo?.numero}</SheetTitle>
            <SheetDescription>Pedido → parcial → concluído. Concluído não volta a ser editável aqui.</SheetDescription>
          </SheetHeader>
          <div className="px-4 space-y-2">
            <Stack gap={1} align="stretch" className="text-sm">
              <span aria-hidden="true">Status do pedido</span>
              <Select value={novoStatus} onValueChange={(v) => setNovoStatus(v as StatusKey)}>
                <SelectTrigger aria-label="Status do pedido"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {(Object.keys(filters.statuses) as StatusKey[]).map((k) => (
                    <SelectItem key={k} value={k}>{filters.statuses[k]}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Stack>
            {erro && <p className="text-sm text-destructive" role="alert">{erro}</p>}
          </div>
          <SheetFooter>
            <Button variant="outline" onClick={() => setAlvo(null)}>Fechar</Button>
            <Button onClick={salvarStatus} disabled={salvando}>{salvando ? 'Atualizando…' : 'Atualizar'}</Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>
    </AppShellV2>
  );
}
