// @memcofre
//   tela: /purchase-order
//   module: Compras
//   stories: thread compras/playbook/06 (Pedidos de compra)
//   permissao: purchase_order.view_all | purchase_order.view_own
//
// Pedidos de compra do negócio. Atrás da flag `useV2ComprasPedidos` (PurchaseOrderController::FLAG_V2);
// desligada, a rota segue na Blade `purchase_order/index`. `?legacy=1` abre a Blade com a flag ligada.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Compras/_telas/RUNBOOK-purchase-order-index.md
// Âncora de design: prototipo-ui/cowork/Wagner/compras-extras.jsx → telaPedidos() (rota cmp-pedidos)
//
// A lista vem do mesmo SQL da Blade (PurchaseOrderController::consultaPedidos). Data e quantidade restante chegam
// formatadas pelo controller com as mesmas expressões das diretivas da Blade: a tela não formata nem soma número.
// Status inline, envio, excluir e notificação seguem nos modais da Blade (?legacy=1).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Download, ExternalLink, Pencil, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';
import { Inline, Stack } from '@/Components/layout';

export interface Pedido {
  id: number;
  data: string | null;
  ref: string;
  local: string;
  fornecedor: string | null;
  fornecedor_fantasia: string | null;
  status: string;
  status_rotulo: string;
  restante: number;
  restante_texto: string;
  envio: string | null;
  envio_rotulo: string | null;
  adicionado_por: string;
}
interface Lista {
  linhas: Pedido[];
  truncado: boolean;
  kpis: { em_aberto: number; restante_total: number; restante_total_texto: string };
}
interface Opcoes {
  locais: Record<string, string>;
  fornecedores: Record<string, string>;
  status: Record<string, string>;
  envios: Record<string, string>;
}
type Filtros = { location_id: string | null; supplier_id: string | null; status: string | null; shipping_status: string | null };
interface Props {
  filtros: Filtros;
  permissoes: { criar: boolean; editar: boolean };
  pedidos?: Lista;
  opcoes?: Opcoes;
}

const TODOS = '__todos__';
const TOM_STATUS: Record<string, 'default' | 'secondary' | 'outline'> = { ordered: 'outline', partial: 'secondary', completed: 'default' };

function FiltroSelect({ rotulo, valor, lista, onChange }: { rotulo: string; valor: string | null; lista?: Record<string, string>; onChange: (v: string | null) => void }) {
  return (
    <Select value={valor ?? TODOS} onValueChange={(v) => onChange(v === TODOS ? null : v)}>
      <SelectTrigger className="w-48" aria-label={rotulo}><SelectValue placeholder={rotulo} /></SelectTrigger>
      <SelectContent>
        <SelectItem value={TODOS}>{rotulo}: todos</SelectItem>
        {Object.entries(lista ?? {}).filter(([v]) => v !== '').map(([v, l]) => <SelectItem key={v} value={v}>{l}</SelectItem>)}
      </SelectContent>
    </Select>
  );
}

function PedidosIndex({ filtros, permissoes, pedidos, opcoes }: Props) {
  const linhas = useMemo(() => pedidos?.linhas ?? [], [pedidos]);
  const [q, setQ] = useState('');
  const busca = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, []);

  // Os filtros da Blade são do servidor: o mesmo SQL recebe os mesmos parâmetros.
  const filtrar = (mudanca: Partial<Filtros>) => {
    const novo = { ...filtros, ...mudanca };
    const data = Object.fromEntries(Object.entries(novo).filter(([, v]) => v !== null && v !== ''));
    router.get('/purchase-order', data, { only: ['pedidos', 'filtros'], preserveState: true, preserveScroll: true, replace: true });
  };

  const termo = q.trim().toLowerCase();
  const visiveis = linhas.filter((p) => !termo
    || `${p.ref} ${p.fornecedor ?? ''} ${p.fornecedor_fantasia ?? ''} ${p.adicionado_por}`.toLowerCase().includes(termo));

  const colunas: ColumnDef<Pedido, unknown>[] = [
    { id: 'data', header: 'Data', meta: { mono: true }, cell: ({ row: { original: p } }) => p.data ?? '—' },
    { id: 'ref', header: 'Nº de referência', meta: { mono: true }, cell: ({ row: { original: p } }) => <span className="font-medium">{p.ref}</span> },
    { id: 'local', header: 'Local', cell: ({ row: { original: p } }) => p.local },
    {
      id: 'fornecedor', header: 'Fornecedor',
      cell: ({ row: { original: p } }) => (
        <Stack gap={0}>{p.fornecedor_fantasia && <span>{p.fornecedor_fantasia}</span>}<span className={p.fornecedor_fantasia ? 'text-muted-foreground' : undefined}>{p.fornecedor ?? '—'}</span></Stack>
      ),
    },
    { id: 'status', header: 'Status', cell: ({ row: { original: p } }) => <Badge variant={TOM_STATUS[p.status] ?? 'outline'}>{p.status_rotulo}</Badge> },
    { id: 'restante', header: 'Quantidade restante', meta: { align: 'right', mono: true }, cell: ({ row: { original: p } }) => p.restante_texto },
    { id: 'envio', header: 'Status do envio', cell: ({ row: { original: p } }) => p.envio_rotulo ? <Badge variant="outline">{p.envio_rotulo}</Badge> : '—' },
    { id: 'quem', header: 'Adicionado por', cell: ({ row: { original: p } }) => p.adicionado_por || '—' },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: p } }) => (
        <Inline gap={1} justify="end">
          <Button asChild variant="ghost" size="sm">
            <a href={`/download-purchase-order/${p.id}/pdf`} target="_blank" rel="noreferrer" aria-label={`Baixar PDF do pedido ${p.ref}`}><Download className="size-4" /> PDF</a>
          </Button>
          {permissoes.editar && (
            <Button asChild variant="ghost" size="sm">
              <a href={`/purchase-order/${p.id}/edit`} aria-label={`Editar o pedido ${p.ref}`}><Pencil className="size-4" /> Editar</a>
            </Button>
          )}
        </Inline>
      ),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Pedidos de compra"
          subtitle={pedidos ? <><strong>{pedidos.kpis.em_aberto}</strong> {pedidos.kpis.em_aberto === 1 ? 'pedido em aberto' : 'pedidos em aberto'}</> : 'o que foi pedido aos fornecedores'}
          actions={
            <Inline gap={2}>
              <Button asChild variant="outline">
                <a href="/purchase-order?legacy=1"><ExternalLink className="size-4" /> Status, envio e exclusão na tela clássica</a>
              </Button>
              {permissoes.criar && (
                <Button asChild>
                  <a href="/purchase-order/create"><Plus className="size-4" /> Adicionar</a>
                </Button>
              )}
            </Inline>
          } />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Deferred data="pedidos" fallback={null}>
          {pedidos ? (
            <Inline data-contract="pedidos-kpis" gap={4} wrap>
              <Stack gap={0} className="min-w-48 rounded-md border p-4">
                <span className="text-sm text-muted-foreground">Pedidos em aberto</span>
                <strong className="text-2xl">{pedidos.kpis.em_aberto}</strong>
                <span className="text-xs text-muted-foreground">ordenados ou parciais — concluído sai da fila</span>
              </Stack>
              <Stack gap={0} className="min-w-48 rounded-md border p-4">
                <span className="text-sm text-muted-foreground">Quantidade restante</span>
                <strong className="text-2xl font-mono">{pedidos.kpis.restante_total_texto}</strong>
                <span className="text-xs text-muted-foreground">quantidade pedida que ainda não foi comprada</span>
              </Stack>
            </Inline>
          ) : null}
        </Deferred>

        <Inline data-contract="pedidos-filtros" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar pedido, fornecedor ou quem adicionou…" aria-label="Buscar pedido, fornecedor ou quem adicionou" />
          </div>
          <Deferred data="opcoes" fallback={null}>
            <Inline gap={2} wrap>
              <FiltroSelect rotulo="Local" valor={filtros.location_id} lista={opcoes?.locais} onChange={(v) => filtrar({ location_id: v })} />
              <FiltroSelect rotulo="Fornecedor" valor={filtros.supplier_id} lista={opcoes?.fornecedores} onChange={(v) => filtrar({ supplier_id: v })} />
              <FiltroSelect rotulo="Status" valor={filtros.status} lista={opcoes?.status} onChange={(v) => filtrar({ status: v })} />
              <FiltroSelect rotulo="Envio" valor={filtros.shipping_status} lista={opcoes?.envios} onChange={(v) => filtrar({ shipping_status: v })} />
            </Inline>
          </Deferred>
        </Inline>

        <Deferred data="pedidos" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando pedidos…</p>}>
          {linhas.length > 0 ? (
            <Stack gap={2}>
              <div data-contract="pedidos-table">
                <DataTable columns={colunas} data={visiveis} caption="Pedidos de compra do negócio" rowKey={(p) => p.id}
                  emptyMessage="Nenhum pedido com esse termo." />
              </div>
              <p className="text-sm text-muted-foreground">
                O pedido não movimenta estoque: quem movimenta é a compra criada a partir dele. Status do pedido e status do envio são eixos separados.
              </p>
              {pedidos?.truncado && (
                <p className="text-sm">
                  A lista mostra os pedidos mais recentes. Para ver todos, <a className="font-medium underline" href="/purchase-order?legacy=1">abra a tela clássica</a>.
                </p>
              )}
            </Stack>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhum pedido de compra" description="Pedidos com esses filtros aparecem aqui."
                action={permissoes.criar ? <Button asChild><a href="/purchase-order/create">Adicionar pedido</a></Button> : undefined} />
            </div>
          )}
        </Deferred>
      </Stack>
    </div>
  );
}

PedidosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default PedidosIndex;
