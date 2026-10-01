// Lista de POS (/pos) — playbook venda-menu, thread 01. MWART: RUNBOOK-pos.md (ADR 0104).
// Fonte de design: prototipo-ui/cowork/Wagner/venda-blade.jsx `TelaPos`; alvo medido em
// governance/design/targets/vendas--pos--index.secoes.json (header · tabs · filtros · lista ·
// toolbar · rodape). Charter e casos ao lado (UC-POS-01..08).
//
// Linhas e totais vêm de GET /sells-list-json?is_direct_sale=0 (SellController@inertiaList).
// O rodapé usa `totals` do servidor (filtro inteiro) — nada de valor é somado aqui.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { Ban, Eye, MoreVertical, Pencil, Plus, Printer, Search, Trash2, Undo2, Wallet } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';
import StatusBadge from '@/Components/shared/StatusBadge';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { fmtDataHoraBr } from '@/Lib/datetime-br';
import SaleSheet from '../_components/SaleSheet';

interface PosRow {
  id: number;
  transaction_date: string;
  invoice_no: string;
  customer_name: string | null;
  location_name: string | null;
  payment_status: 'paid' | 'due' | 'partial' | string;
  is_overdue: boolean;
  payment_method_label: string | null;
  final_total: number;
  total_paid: number;
  shipping_status: string | null;
  items_count: number;
  seller_name: string | null;
}

interface Totals { count: number; sum_final_total: number; sum_total_paid: number; sum_due: number }
interface Meta { current_page: number; last_page: number; total: number }

export interface SellsPosIndexProps {
  permissions: { create: boolean; update: boolean; delete: boolean; payments: boolean; print: boolean };
  urls: { list: string; create: string };
}

const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const TODOS = 'todos';
const STATUS_PGTO = [
  { v: 'paid', l: 'Pago' },
  { v: 'due', l: 'Devido' },
  { v: 'partial', l: 'Parcial' },
  { v: 'overdue', l: 'Vencido' },
];
const PERIODOS = [
  { v: 'hoje', l: 'Hoje', dias: 0 },
  { v: '7', l: 'Últimos 7 dias', dias: 7 },
  { v: '30', l: 'Últimos 30 dias', dias: 30 },
];
const ENVIO: Record<string, string> = {
  ordered: 'Pedido', packed: 'Embalado', shipped: 'Enviado', delivered: 'Entregue', cancelled: 'Cancelado',
};
// Colunas do `sale_pos/partials/sales_table` (rótulo, alinhada à direita).
const COLUNAS: Array<[string, boolean]> = [
  ['Data', false], ['Nº da fatura', false], ['Cliente', false], ['Local', false],
  ['Status do pagamento', false], ['Forma de pagamento', false], ['Valor total', true],
  ['Total pago', true], ['Saldo devedor', true], ['Status de envio', false], ['Itens', true],
  ['Adicionado por', false],
];
// Telas do menu Vendas (abas do protótipo) — rotas reais de produção.
const ABAS = [
  { key: 'pos', label: 'Lista de POS', href: '/pos' },
  { key: 'rascunhos', label: 'Rascunhos', href: '/sells/drafts' },
  { key: 'cotacoes', label: 'Cotações', href: '/sells/quotations' },
  { key: 'remessas', label: 'Remessas', href: '/shipments' },
  { key: 'assinaturas', label: 'Assinaturas', href: '/subscriptions' },
  { key: 'caixa', label: 'Caixa do dia', href: '/vendas/caixa' },
];

const ymd = (d: Date) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

/** Período → date_from/date_to. O `date_to` leva 23:59:59 pra incluir o dia inteiro. */
function rangeDoPeriodo(periodo: string): { from: string; to: string } | null {
  const p = PERIODOS.find((x) => x.v === periodo);
  if (!p) return null;
  const hoje = new Date();
  const inicio = new Date(hoje);
  inicio.setDate(hoje.getDate() - p.dias);
  return { from: ymd(inicio), to: `${ymd(hoje)} 23:59:59` };
}

function rotuloPgto(r: PosRow): { value: string; label: string } {
  if (r.is_overdue) return { value: 'overdue', label: 'Vencido' };
  return { value: r.payment_status, label: STATUS_PGTO.find((s) => s.v === r.payment_status)?.l ?? r.payment_status };
}

export default function SellsPosIndex({ permissions, urls }: SellsPosIndexProps) {
  const [rows, setRows] = useState<PosRow[]>([]);
  const [totals, setTotals] = useState<Totals | null>(null);
  const [meta, setMeta] = useState<Meta>({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [erro, setErro] = useState<string | null>(null);
  const [status, setStatus] = useState(TODOS);
  const [periodo, setPeriodo] = useState(TODOS);
  const [busca, setBusca] = useState('');
  const [buscaAplicada, setBuscaAplicada] = useState('');
  const [page, setPage] = useState(1);
  const [vendaAberta, setVendaAberta] = useState<number | null>(null);

  useEffect(() => {
    const t = setTimeout(() => { setBuscaAplicada(busca.trim()); setPage(1); }, 300);
    return () => clearTimeout(t);
  }, [busca]);

  const carregar = useCallback(async () => {
    setLoading(true);
    setErro(null);
    const params = new URLSearchParams({ is_direct_sale: '0', page: String(page), per_page: '25' });
    if (status !== TODOS) params.set('payment_status', status);
    const range = rangeDoPeriodo(periodo);
    if (range) { params.set('date_from', range.from); params.set('date_to', range.to); }
    if (buscaAplicada) params.set('q', buscaAplicada);
    try {
      const res = await fetch(`${urls.list}?${params.toString()}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error(res.status === 403 ? 'Seu papel não pode ver a lista de POS.' : 'Falha ao carregar a lista.');
      const json = await res.json();
      setRows(json.data ?? []);
      setTotals(json.totals ?? null);
      setMeta(json.meta ?? { current_page: 1, last_page: 1, total: 0 });
    } catch (e) {
      setRows([]);
      setTotals(null);
      setErro((e as Error).message);
    } finally {
      setLoading(false);
    }
  }, [urls.list, page, status, periodo, buscaAplicada]);

  useEffect(() => { carregar(); }, [carregar]);

  const excluir = async (r: PosRow) => {
    if (!confirm(`Excluir a venda ${r.invoice_no}? Essa ação não pode ser desfeita.`)) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const res = await fetch(`/pos/${r.id}`, {
      method: 'DELETE',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
      credentials: 'same-origin',
    });
    const json = await res.json().catch(() => null);
    if (!res.ok || json?.success === false) { alert(json?.msg ?? 'Falha ao excluir a venda.'); return; }
    carregar();
  };

  const filtrosAtivos = status !== TODOS || periodo !== TODOS || buscaAplicada !== '';

  return (
    <>
      <Head title="Lista de POS" />
      <div className="flex-1 pb-8">
        <div data-contract="header">
          <PageHeader
            title="Lista de POS"
            subtitle="Vendas de balcão — o que se confere no fim do turno."
            actions={
              <>
                <Button variant="outline" size="sm" asChild><Link href="/sells">Todas as vendas</Link></Button>
                {permissions.create && (
                  <Button size="sm" asChild>
                    <a href={urls.create}><Plus className="h-4 w-4 mr-1" />Abrir POS</a>
                  </Button>
                )}
              </>
            }
          />
        </div>

        <div className="w-full px-6 pt-5 space-y-4">
          <div data-contract="tabs">
            <PageHeaderTabs ghosts={ABAS} activeGhostKey="pos" maxVisible={6} />
          </div>

          <section data-contract="filtros" aria-label="Filtros" className="flex flex-wrap items-end gap-3">
            <label className="flex flex-col gap-1 text-xs text-muted-foreground">
              Status do pagamento
              <Select value={status} onValueChange={(v) => { setStatus(v); setPage(1); }}>
                <SelectTrigger className="w-44" aria-label="Status do pagamento"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value={TODOS}>Todos</SelectItem>
                  {STATUS_PGTO.map((s) => <SelectItem key={s.v} value={s.v}>{s.l}</SelectItem>)}
                </SelectContent>
              </Select>
            </label>
            <label className="flex flex-col gap-1 text-xs text-muted-foreground">
              Período
              <Select value={periodo} onValueChange={(v) => { setPeriodo(v); setPage(1); }}>
                <SelectTrigger className="w-44" aria-label="Período"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value={TODOS}>Tudo</SelectItem>
                  {PERIODOS.map((p) => <SelectItem key={p.v} value={p.v}>{p.l}</SelectItem>)}
                </SelectContent>
              </Select>
            </label>
          </section>

          <section data-contract="lista" className="rounded-lg border border-border bg-card overflow-hidden">
            <div data-contract="toolbar" className="flex items-center gap-3 border-b border-border px-4 py-3">
              <div className="relative w-full max-w-sm">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input variant="shadcn" value={busca} onChange={(e) => setBusca(e.target.value)}
                  placeholder="Buscar fatura ou cliente…" className="pl-9" aria-label="Buscar fatura ou cliente" />
              </div>
              <span className="ml-auto text-xs text-muted-foreground tabular-nums">{meta.total} vendas</span>
            </div>

            {loading ? (
              <div className="p-8 text-center text-sm text-muted-foreground">Carregando vendas de POS…</div>
            ) : erro ? (
              <EmptyState variant="error" icon="alert-triangle" title="Não foi possível carregar" description={erro} />
            ) : rows.length === 0 ? (
              <EmptyState variant="search" icon="search" title={filtrosAtivos ? 'Nada com esses filtros' : 'Nenhuma venda de POS'}
                description={filtrosAtivos ? 'Limpe um filtro ou amplie o período.' : 'As vendas feitas no POS aparecem aqui.'} />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="text-xs text-muted-foreground">
                    <tr className="border-b border-border text-left">
                      <th className="px-3 py-2 font-medium w-12"><span className="sr-only">Ação</span></th>
                      {COLUNAS.map(([rotulo, direita]) => (
                        <th key={rotulo} className={`px-3 py-2 font-medium${direita ? ' text-right' : ''}`}>{rotulo}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((r) => {
                      const saldo = Math.max(0, r.final_total - r.total_paid);
                      const pg = rotuloPgto(r);
                      return (
                        <tr key={r.id} data-state={r.is_overdue ? 'urgent' : undefined}
                          className={`border-b border-border last:border-0 ${r.is_overdue ? 'border-l-[3px] border-l-destructive' : ''}`}>
                          <td className="px-3 py-2">
                            <AcoesVenda r={r} saldo={saldo} permissions={permissions}
                              onVer={() => setVendaAberta(r.id)} onExcluir={() => excluir(r)} />
                          </td>
                          <td className="px-3 py-2 tabular-nums whitespace-nowrap">{fmtDataHoraBr(r.transaction_date)}</td>
                          <td className="px-3 py-2 font-mono text-xs">{r.invoice_no}</td>
                          <td className="px-3 py-2">{r.customer_name ?? '—'}</td>
                          <td className="px-3 py-2 text-muted-foreground">{r.location_name ?? '—'}</td>
                          <td className="px-3 py-2"><StatusBadge kind="payment" value={pg.value} label={pg.label} /></td>
                          <td className="px-3 py-2">{r.payment_method_label ?? '—'}</td>
                          <td className="px-3 py-2 text-right tabular-nums">{brl.format(r.final_total)}</td>
                          <td className="px-3 py-2 text-right tabular-nums">{brl.format(r.total_paid)}</td>
                          <td className="px-3 py-2 text-right tabular-nums">{brl.format(saldo)}</td>
                          <td className="px-3 py-2">{r.shipping_status ? ENVIO[r.shipping_status] ?? r.shipping_status : '—'}</td>
                          <td className="px-3 py-2 text-right tabular-nums">{r.items_count}</td>
                          <td className="px-3 py-2">{r.seller_name ?? '—'}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}

            <div data-contract="rodape" className="flex flex-wrap items-center gap-3 border-t border-border px-4 py-3 text-sm">
              <span>
                Total: <b className="tabular-nums">{brl.format(totals?.sum_final_total ?? 0)}</b>
                {' · '}pago <b className="tabular-nums">{brl.format(totals?.sum_total_paid ?? 0)}</b>
                {' · '}em aberto <b className="tabular-nums">{brl.format(totals?.sum_due ?? 0)}</b>
              </span>
              <span className="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                Página {meta.current_page} de {meta.last_page}
                <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Anterior</Button>
                <Button variant="outline" size="sm" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>Próxima</Button>
              </span>
            </div>
          </section>
        </div>
      </div>

      <SaleSheet saleId={vendaAberta} open={vendaAberta != null}
        onOpenChange={(open) => { if (!open) setVendaAberta(null); }} onSaleChanged={carregar} />
    </>
  );
}

function AcoesVenda({ r, saldo, permissions, onVer, onExcluir }: {
  r: PosRow; saldo: number; permissions: SellsPosIndexProps['permissions']; onVer: () => void; onExcluir: () => void;
}) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" aria-label={`Ações da venda ${r.invoice_no}`}><MoreVertical className="h-4 w-4" /></Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-60">
        <DropdownMenuItem onClick={onVer}><Eye className="mr-2 h-4 w-4" />Ver detalhe</DropdownMenuItem>
        {permissions.update && (
          <DropdownMenuItem asChild><a href={`/pos/${r.id}/edit`}><Pencil className="mr-2 h-4 w-4" />Editar venda</a></DropdownMenuItem>
        )}
        {permissions.print && (
          <DropdownMenuItem asChild>
            <a href={`/sells/${r.id}/print`} target="_blank" rel="noopener noreferrer"><Printer className="mr-2 h-4 w-4" />Imprimir recibo</a>
          </DropdownMenuItem>
        )}
        <DropdownMenuSeparator />
        {saldo > 0 && permissions.payments && (
          <DropdownMenuItem onClick={onVer}><Wallet className="mr-2 h-4 w-4" />Adicionar pagamento</DropdownMenuItem>
        )}
        <DropdownMenuItem asChild><a href={`/sell-return/add/${r.id}`}><Undo2 className="mr-2 h-4 w-4" />Devolver venda</a></DropdownMenuItem>
        <DropdownMenuSeparator />
        {permissions.delete ? (
          <DropdownMenuItem variant="destructive" onClick={onExcluir}><Trash2 className="mr-2 h-4 w-4" />Excluir</DropdownMenuItem>
        ) : (
          <DropdownMenuItem disabled title="Seu papel não tem sell.delete — peça ao administrador.">
            <Ban className="mr-2 h-4 w-4" />Excluir (sem permissão sell.delete)
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

SellsPosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
