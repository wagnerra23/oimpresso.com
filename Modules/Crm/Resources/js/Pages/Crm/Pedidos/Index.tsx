// @memcofre
//   tela: /crm/order-request
//   module: Crm
//   stories: thread Crm/17 (OrderRequestController@listOrderRequests · Blade → Inertia)
//   permissao: so.view_all | so.view_own (as da grade da Blade)
//
// Pedidos que os clientes fizeram pelo portal, na lista da equipe. Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: alvo crm--pedidos--index (A2) · prototipo-ui/cowork/Wagner/crm-blade-telas.jsx → Pedidos()
// Contrato: governance/design/contracts/crm-pedidos.contract.json
//
// Esta onda é LEITURA, atrás da flag useV2CrmPedidos. A mudança de status do pedido segue na Blade
// (`?classico=1`). Valores só são MOSTRADOS: o total de cada pedido e a soma vêm prontos do servidor.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';

const ROTA = '/crm/order-request';
const CLASSICO = `${ROTA}?classico=1`;
const TODOS = '__todos';

interface Opcao { value: string; label: string }
interface Opcoes { locais: Opcao[]; clientes: Opcao[]; status: Opcao[] }
interface Pedido {
  id: number; data: string | null; numero: string; contato: string; local: string | null;
  status: string; restante: number | null; total: string;
}
type Filtros = Record<string, string | undefined>;
interface Props {
  filtros: Filtros;
  opcoes?: Opcoes;
  pedidos?: { pode_ver: boolean; lista: PaginatorShape<Pedido> | null; total: string };
}

const TOM: Record<string, 'info' | 'warning' | 'success'> = { ordered: 'info', partial: 'warning', completed: 'success' };
const MOEDA = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const brl = (v: string) => MOEDA.format(Number(v));
const rotulo = (lista: Opcao[] | undefined, v: string) => lista?.find((o) => o.value === v)?.label ?? v;

function filtrar(filtros: Filtros, mudanca: Filtros) {
  router.get(ROTA, { ...filtros, ...mudanca }, { preserveState: true, preserveScroll: true, replace: true });
}

function colunas(opcoes: Opcoes | undefined): ColumnDef<Pedido>[] {
  const texto = (header: string, acc: (r: Pedido) => ReactNode, width = 150): ColumnDef<Pedido> => ({
    id: header, header, cell: ({ row }) => acc(row.original) ?? '—', meta: { width },
  });

  return [
    texto('Data', (r) => <span className="font-mono">{r.data ?? '—'}</span>),
    texto('Nº do pedido', (r) => <span className="font-mono">{r.numero}</span>),
    texto('Contato', (r) => r.contato || '—', 220),
    texto('Local', (r) => r.local, 140),
    texto('Status', (r) => <Badge variant={TOM[r.status] ?? 'neutral'}>{rotulo(opcoes?.status, r.status)}</Badge>, 160),
    texto('Quantidade restante', (r) => <span className="font-mono">{r.restante ?? '—'}</span>, 170),
    texto('Total', (r) => <span className="font-mono">{brl(r.total)}</span>, 140),
  ];
}

export default function PedidosIndex({ filtros, opcoes, pedidos }: Props) {
  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Pedido de ordem" subtitle="Pedidos que os clientes fizeram pelo portal do contato." />

      <Card data-contract="crm-filtros">
        <CardContent className="flex flex-col gap-3 p-4">
          <h3 className="text-sm font-medium">Filtros</h3>
          <Deferred data="opcoes" fallback={<Skeleton className="h-16 w-full" />}>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <Filtro rotulo="Local do negócio" campo="location_id" lista={opcoes?.locais} filtros={filtros} />
              <Filtro rotulo="Cliente" campo="customer_id" lista={opcoes?.clientes} filtros={filtros} />
              <Filtro rotulo="Status" campo="status" lista={opcoes?.status} filtros={filtros} />
              <div className="flex flex-col gap-1">
                <Label>Intervalo de datas</Label>
                <div className="flex gap-2">
                  <Input type="date" aria-label="Início do intervalo" value={filtros.start_date ?? ''} onChange={(e) => filtrar(filtros, { start_date: e.target.value || undefined })} />
                  <Input type="date" aria-label="Fim do intervalo" value={filtros.end_date ?? ''} onChange={(e) => filtrar(filtros, { end_date: e.target.value || undefined })} />
                </div>
              </div>
            </div>
          </Deferred>
        </CardContent>
      </Card>

      <Card data-contract="crm-pedidos">
        <CardContent className="flex flex-col gap-3 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-sm font-medium">Pedido de ordem</h3>
            <div className="ml-auto flex flex-wrap gap-2" data-contract="crm-toolbar">
              <Button asChild variant="outline" size="sm"><a href={CLASSICO}>Mudar status na tela clássica</a></Button>
            </div>
          </div>

          <Deferred data="pedidos" fallback={<Skeleton className="h-64 w-full" />}>
            {pedidos && !pedidos.pode_ver ? (
              <p className="text-sm text-muted-foreground">Sem permissão para ver pedidos de venda.</p>
            ) : pedidos?.lista ? (
              <>
                <DataTable<Pedido>
                  caption="Pedido de ordem"
                  columns={colunas(opcoes)}
                  data={pedidos.lista.data}
                  pagination={pedidos.lista}
                  endpoint={ROTA}
                  filters={filtros}
                  initialSearch={filtros.q ?? ''}
                  searchPlaceholder="Buscar por nº ou contato"
                  emptyMessage="Nada com esses filtros"
                  rowKey={(r) => r.id}
                />
                <div className="flex items-center text-xs text-muted-foreground" data-contract="crm-rodape">
                  <span>Total dos pedidos filtrados</span>
                  <b className="ml-auto font-mono text-foreground">{brl(pedidos.total)}</b>
                </div>
              </>
            ) : null}
          </Deferred>
        </CardContent>
      </Card>
    </div>
  );
}

function Filtro({ rotulo: nome, campo, lista, filtros }: { rotulo: string; campo: string; lista?: Opcao[]; filtros: Filtros }) {
  return (
    <div className="flex flex-col gap-1">
      <Label>{nome}</Label>
      <Select value={filtros[campo] || TODOS} onValueChange={(v) => filtrar(filtros, { [campo]: v === TODOS ? undefined : v })}>
        <SelectTrigger aria-label={nome}><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value={TODOS}>Todos</SelectItem>
          {(lista ?? []).map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}
        </SelectContent>
      </Select>
    </div>
  );
}

PedidosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
