// Sells/Shipments/Index — Remessas (thread 02 do playbook venda-menu · PT-01 Lista).
// Fonte de design: prototipo-ui/cowork/Wagner/venda-blade.jsx · TelaRemessas (alvo medido em
// governance/design/targets/vendas--remessas--index.*). Dados: o MESMO DataTables do Blade
// (GET /sells?only_shipments=true); edição pelo drawer PT-02 que salva no updateShipping existente.
// Refs: ADR 0104 (MWART) · ADR 0093 (multi-tenant) · RUNBOOK memory/requisitos/Sells/RUNBOOK-shipments.md
import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { MoreHorizontal } from 'lucide-react';
import { toast } from 'sonner';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import StatusBadge from '@/Components/shared/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { printSaleReceipt } from '@/Lib/printSaleReceipt';
import EditarRemessaSheet from './_components/EditarRemessaSheet';

type Opcoes = Record<string, string>;

export interface ShipmentsPageProps {
  shippingStatuses: Opcoes;
  customLabels: Opcoes;
  filters: {
    businessLocations: Opcoes;
    customers?: Opcoes;
    salesRepresentative: Opcoes;
    deliveryPersons: Opcoes;
  };
  permissions: { print: boolean; view_sell: boolean };
  urls: { datatable: string; edit: string; update: string };
}

export interface Remessa {
  id: number;
  data: string;
  fatura: string;
  cliente: string;
  contato: string;
  local: string;
  entregador: string;
  envio: string | null;
  pagamento: string | null;
  detalhes: string;
  extras: Opcoes;
}

const POR_PAGINA = 25;
const TODOS = '__todos__';
const VAZIO = '—';

/** O JSON legado traz algumas colunas como HTML — o texto sai por DOMParser (não executa script). */
function texto(v: unknown): string {
  const s = v == null ? '' : String(v);
  if (!s.includes('<')) return s.trim();
  return (new DOMParser().parseFromString(s, 'text/html').body.textContent ?? '').replace(/\s+/g, ' ').trim();
}

function celula(v: string): string {
  return v === '' ? VAZIO : v;
}

function FiltroSelect({ rotulo, valor, opcoes, onChange }: { rotulo: string; valor: string; opcoes: Opcoes; onChange: (v: string) => void }) {
  return (
    <label className="flex flex-col gap-1 text-xs text-muted-foreground">
      {rotulo}
      <Select value={valor || TODOS} onValueChange={(v) => onChange(v === TODOS ? '' : v)}>
        <SelectTrigger size="sm" className="w-full text-foreground"><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value={TODOS}>Todos</SelectItem>
          {Object.entries(opcoes).map(([id, nome]) => (
            <SafeSelectItem key={id} value={id}>{nome}</SafeSelectItem>
          ))}
        </SelectContent>
      </Select>
    </label>
  );
}

const PAGAMENTOS: Opcoes = { paid: 'Pago', due: 'Devido', partial: 'Parcial', overdue: 'Vencido' };

export default function ShipmentsIndex(props: ShipmentsPageProps) {
  const { shippingStatuses, customLabels, filters, permissions, urls } = props;
  const [f, setF] = useState<Record<string, string>>({});
  const [busca, setBusca] = useState('');
  const [buscaAplicada, setBuscaAplicada] = useState('');
  const [pagina, setPagina] = useState(0);
  const [linhas, setLinhas] = useState<Remessa[]>([]);
  const [total, setTotal] = useState(0);
  const [estado, setEstado] = useState<'carregando' | 'pronto' | 'erro'>('carregando');
  const [alvo, setAlvo] = useState<Remessa | null>(null);

  const chaveDoRotulo = useMemo(() => {
    const m: Opcoes = {};
    Object.entries(shippingStatuses).forEach(([k, l]) => { m[l] = k; });
    return m;
  }, [shippingStatuses]);

  useEffect(() => {
    const t = window.setTimeout(() => { setBuscaAplicada(busca.trim()); setPagina(0); }, 300);
    return () => window.clearTimeout(t);
  }, [busca]);

  const carregar = useCallback(async () => {
    setEstado('carregando');
    const p = new URLSearchParams({
      only_shipments: 'true', draw: '1', start: String(pagina * POR_PAGINA), length: String(POR_PAGINA),
      'columns[0][data]': 'transaction_date', 'columns[0][name]': 'transaction_date', 'columns[0][searchable]': 'false', 'columns[0][orderable]': 'true',
      'columns[1][data]': 'invoice_no', 'columns[1][name]': 'invoice_no', 'columns[1][searchable]': 'true', 'columns[1][orderable]': 'false',
      'columns[2][data]': 'conatct_name', 'columns[2][name]': 'conatct_name', 'columns[2][searchable]': 'true', 'columns[2][orderable]': 'false',
      'order[0][column]': '0', 'order[0][dir]': 'desc', 'search[value]': buscaAplicada, 'search[regex]': 'false',
    });
    Object.entries(f).forEach(([k, v]) => { if (v) p.set(k, v); });
    try {
      const res = await fetch(`${urls.datatable}?${p.toString()}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error(String(res.status));
      const json = await res.json();
      setLinhas((json.data ?? []).map((r: Record<string, unknown>) => {
        const extras: Opcoes = {};
        Object.keys(customLabels).forEach((k) => { extras[k] = texto(r[k]); });
        return {
          id: Number(r.id),
          data: texto(r.transaction_date),
          fatura: texto(r.invoice_no_text ?? r.invoice_no),
          cliente: texto(r.conatct_name ?? r.name),
          contato: texto(r.mobile),
          local: texto(r.business_location),
          entregador: texto(r.delivery_person),
          envio: chaveDoRotulo[texto(r.shipping_status)] ?? null,
          pagamento: /data-orig-value="([a-z_]+)"/.exec(String(r.payment_status ?? ''))?.[1] ?? null,
          detalhes: texto(r.shipping_details),
          extras,
        };
      }));
      setTotal(Number(json.recordsFiltered ?? 0));
      setEstado('pronto');
    } catch {
      setLinhas([]);
      setEstado('erro');
    }
  }, [buscaAplicada, chaveDoRotulo, customLabels, f, pagina, urls.datatable]);

  useEffect(() => { carregar(); }, [carregar]);

  const filtrar = (k: string, v: string) => { setF((x) => ({ ...x, [k]: v })); setPagina(0); };

  const romaneio = async (r: Remessa) => {
    try {
      await printSaleReceipt({ printUrl: `/sells/${r.id}/print`, invoiceNo: r.fatura, mode: 'packing_slip' });
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Não foi possível gerar o romaneio.');
    }
  };

  const paginas = Math.max(1, Math.ceil(total / POR_PAGINA));

  return (
    <AppShellV2 title="Remessas" breadcrumbItems={[{ label: 'Vendas' }, { label: 'Remessas' }]}>
      <Head title="Remessas" />
      <div className="flex flex-col gap-4 p-6">
        <div data-contract="cabecalho">
          <PageHeader title="Remessas" subtitle="Fila de entrega: quem leva, em que status e com qual documento." />
        </div>

        <section data-contract="filtros" className="grid gap-3 rounded-lg border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4">
          <FiltroSelect rotulo="Local do negócio" valor={f.location_id ?? ''} opcoes={filters.businessLocations} onChange={(v) => filtrar('location_id', v)} />
          <FiltroSelect rotulo="Cliente" valor={f.customer_id ?? ''} opcoes={filters.customers ?? {}} onChange={(v) => filtrar('customer_id', v)} />
          <div className="flex flex-col gap-1 text-xs text-muted-foreground">
            <label htmlFor="rem-de">Período — de</label>
            <Input id="rem-de" type="date" value={f.start_date ?? ''} onChange={(e) => filtrar('start_date', e.target.value)} />
          </div>
          <div className="flex flex-col gap-1 text-xs text-muted-foreground">
            <label htmlFor="rem-ate">Período — até</label>
            <Input id="rem-ate" type="date" value={f.end_date ?? ''} onChange={(e) => filtrar('end_date', e.target.value)} />
          </div>
          <FiltroSelect rotulo="Usuário" valor={f.created_by ?? ''} opcoes={filters.salesRepresentative} onChange={(v) => filtrar('created_by', v)} />
          <FiltroSelect rotulo="Status do pagamento" valor={f.payment_status ?? ''} opcoes={PAGAMENTOS} onChange={(v) => filtrar('payment_status', v)} />
          <FiltroSelect rotulo="Status de envio" valor={f.shipping_status ?? ''} opcoes={shippingStatuses} onChange={(v) => filtrar('shipping_status', v)} />
          <FiltroSelect rotulo="Entregador" valor={f.delivery_person ?? ''} opcoes={filters.deliveryPersons} onChange={(v) => filtrar('delivery_person', v)} />
        </section>

        <section data-contract="lista" className="overflow-hidden rounded-lg border border-border bg-card">
          <div data-contract="toolbar" className="flex items-center gap-3 border-b border-border p-3">
            <Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar fatura ou cliente…" className="max-w-sm" aria-label="Buscar fatura ou cliente" />
            <span className="ml-auto text-xs text-muted-foreground">{total} remessa(s)</span>
          </div>
          {estado === 'carregando' && <p className="p-8 text-center text-sm text-muted-foreground">Carregando remessas…</p>}
          {estado === 'erro' && (
            <EmptyState variant="error" icon="alert-triangle" title="Não foi possível carregar as remessas"
              action={<Button variant="outline" size="sm" onClick={carregar}>Tentar de novo</Button>} />
          )}
          {estado === 'pronto' && linhas.length === 0 && (
            <EmptyState variant="search" icon="truck" title="Nenhuma remessa com esses filtros" description="Limpe um filtro ou amplie o período." />
          )}
          {estado === 'pronto' && linhas.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-xs uppercase tracking-wide text-muted-foreground">
                  <tr className="border-b border-border text-left">
                    <th className="px-3 py-2 font-medium">Ação</th>
                    <th className="px-3 py-2 font-medium">Data</th>
                    <th className="px-3 py-2 font-medium">Nº da fatura</th>
                    <th className="px-3 py-2 font-medium">Cliente</th>
                    <th className="px-3 py-2 font-medium">Contato</th>
                    <th className="px-3 py-2 font-medium">Local</th>
                    <th className="px-3 py-2 font-medium">Entregador</th>
                    <th className="px-3 py-2 font-medium">Status de envio</th>
                    <th className="px-3 py-2 font-medium">Detalhes de envio</th>
                    {Object.entries(customLabels).map(([k, l]) => <th key={k} className="px-3 py-2 font-medium">{l}</th>)}
                    <th className="px-3 py-2 font-medium">Status do pagamento</th>
                  </tr>
                </thead>
                <tbody>
                  {linhas.map((r) => (
                    <tr key={r.id} className="border-b border-border last:border-0">
                      <td className="px-3 py-2">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" aria-label={`Ações da remessa ${r.fatura}`}><MoreHorizontal className="h-4 w-4" /></Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="start">
                            <DropdownMenuItem onSelect={() => setAlvo(r)}>Editar remessa</DropdownMenuItem>
                            {permissions.print && <DropdownMenuItem onSelect={() => romaneio(r)}>Imprimir romaneio</DropdownMenuItem>}
                            {permissions.view_sell && <DropdownMenuItem asChild><Link href={`/sells/${r.id}`}>Ver venda</Link></DropdownMenuItem>}
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </td>
                      <td className="px-3 py-2 tabular-nums">{celula(r.data)}</td>
                      <td className="px-3 py-2 font-mono text-xs">{celula(r.fatura)}</td>
                      <td className="px-3 py-2">{celula(r.cliente)}</td>
                      <td className="px-3 py-2 font-mono text-xs">{celula(r.contato)}</td>
                      <td className="px-3 py-2 text-muted-foreground">{celula(r.local)}</td>
                      <td className="px-3 py-2">{celula(r.entregador)}</td>
                      <td className="px-3 py-2">{r.envio ? <StatusBadge kind="os" value={r.envio} label={shippingStatuses[r.envio]} /> : VAZIO}</td>
                      <td className="px-3 py-2">{celula(r.detalhes)}</td>
                      {Object.keys(customLabels).map((k) => <td key={k} className="px-3 py-2">{celula(r.extras[k] ?? '')}</td>)}
                      <td className="px-3 py-2">{r.pagamento ? <StatusBadge kind="payment" value={r.pagamento} label={PAGAMENTOS[r.pagamento]} /> : VAZIO}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          <div data-contract="rodape" className="flex flex-wrap items-center gap-3 border-t border-border p-3 text-xs text-muted-foreground">
            <span>Status de envio e entregador vêm da própria venda — editar aqui atualiza a transação, não cria documento novo.</span>
            <span className="ml-auto tabular-nums">Página {pagina + 1} de {paginas}</span>
            <Button variant="outline" size="sm" disabled={pagina === 0} onClick={() => setPagina((x) => x - 1)}>Anterior</Button>
            <Button variant="outline" size="sm" disabled={pagina + 1 >= paginas} onClick={() => setPagina((x) => x + 1)}>Próxima</Button>
          </div>
        </section>
      </div>

      <EditarRemessaSheet
        remessa={alvo}
        urls={urls}
        customLabels={customLabels}
        onClose={() => setAlvo(null)}
        onSalvo={() => { setAlvo(null); carregar(); }}
      />
    </AppShellV2>
  );
}
