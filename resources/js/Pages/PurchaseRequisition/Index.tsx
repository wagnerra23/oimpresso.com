// @memcofre
//   tela: /purchase-requisition
//   module: Compras
//   stories: thread compras/playbook/07 (Requisições de compra)
//   permissao: purchase_requisition.view_all | purchase_requisition.view_own
//
// Requisições de compra do negócio. Atrás da flag `useV2ComprasRequisicoes` (PurchaseRequisitionController::FLAG_V2);
// desligada, a rota segue na Blade `purchase_requisition/index`. `?legacy=1` abre a Blade com a flag ligada.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Compras/_telas/RUNBOOK-purchase-requisition-index.md
// Âncora de design: prototipo-ui/cowork/Wagner/compras-extras.jsx → telaReqs() (rota cmp-requisicoes)
//
// A lista vem do mesmo SQL da Blade (PurchaseRequisitionController::consultaRequisicoes). As datas chegam formatadas
// pelo controller com a mesma expressão do @format_datetime (shift da ADR 0066): a tela não formata data.
// Ver e excluir seguem no modal da Blade (?legacy=1).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { ExternalLink, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';
import { Inline, Stack } from '@/Components/layout';
import NecessidadeDeCompra, { type LinhaNecessidade } from './_components/NecessidadeDeCompra';

export interface Requisicao {
  id: number;
  data: string | null;
  ref: string;
  local: string;
  status: string;
  status_rotulo: string;
  necessario_ate: string | null;
  adicionado_por: string;
  /** UC-REQMIN-07: o da última compra, na requisição pelo estoque mínimo; nulo = sem fornecedor. */
  fornecedor: string | null;
}
interface Lista {
  linhas: Requisicao[];
  truncado: boolean;
  kpis: { sem_pedido: number; parciais: number };
}
interface Opcoes {
  locais: Record<string, string>;
  status: Record<string, string>;
}
type Filtros = { location_id: string | null; status: string | null };
interface Props {
  filtros: Filtros;
  permissoes: { criar: boolean };
  requisicoes?: Lista;
  opcoes?: Opcoes;
  /** US-COM-030 (MRP). Nulo quando o módulo Compras não está no código. */
  necessidade?: LinhaNecessidade[] | null;
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

function Kpi({ rotulo, valor, nota }: { rotulo: string; valor: number; nota: string }) {
  return (
    <Stack gap={0} className="min-w-48 rounded-md border p-4">
      <span className="text-sm text-muted-foreground">{rotulo}</span>
      <strong className="text-2xl">{valor}</strong>
      <span className="text-xs text-muted-foreground">{nota}</span>
    </Stack>
  );
}

function RequisicoesIndex({ filtros, permissoes, requisicoes, opcoes, necessidade }: Props) {
  const linhas = useMemo(() => requisicoes?.linhas ?? [], [requisicoes]);
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
    router.get('/purchase-requisition', data, { only: ['requisicoes', 'filtros', 'necessidade'], preserveState: true, preserveScroll: true, replace: true });
  };

  const termo = q.trim().toLowerCase();
  const visiveis = linhas.filter((r) => !termo || `${r.ref} ${r.adicionado_por}`.toLowerCase().includes(termo));

  const colunas: ColumnDef<Requisicao, unknown>[] = [
    { id: 'data', header: 'Data', meta: { mono: true }, cell: ({ row: { original: r } }) => r.data ?? '—' },
    { id: 'ref', header: 'Nº de referência', meta: { mono: true }, cell: ({ row: { original: r } }) => <span className="font-medium">{r.ref}</span> },
    { id: 'local', header: 'Local', cell: ({ row: { original: r } }) => r.local },
    { id: 'fornecedor', header: 'Fornecedor', cell: ({ row: { original: r } }) => r.fornecedor ?? '—' },
    { id: 'status', header: 'Status', cell: ({ row: { original: r } }) => <Badge variant={TOM_STATUS[r.status] ?? 'outline'}>{r.status_rotulo}</Badge> },
    { id: 'prazo', header: 'Necessário até', meta: { mono: true }, cell: ({ row: { original: r } }) => r.necessario_ate ?? '—' },
    { id: 'quem', header: 'Adicionado por', cell: ({ row: { original: r } }) => r.adicionado_por || '—' },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Requisições de compra"
          subtitle={requisicoes ? <><strong>{requisicoes.kpis.sem_pedido}</strong> {requisicoes.kpis.sem_pedido === 1 ? 'requisição sem pedido' : 'requisições sem pedido'}</> : 'pedidos internos de reposição'}
          actions={
            <Inline gap={2}>
              <Button asChild variant="outline">
                <a href="/purchase-requisition?legacy=1"><ExternalLink className="size-4" /> Ver e excluir na tela clássica</a>
              </Button>
              {permissoes.criar && (
                <Button asChild>
                  <a href="/purchase-requisition/create"><Plus className="size-4" /> Adicionar</a>
                </Button>
              )}
            </Inline>
          } />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Deferred data="requisicoes" fallback={null}>
          {requisicoes ? (
            <Inline data-contract="requisicoes-kpis" gap={4} wrap>
              <Kpi rotulo="Sem pedido ainda" valor={requisicoes.kpis.sem_pedido} nota="requisição ordenada esperando o comprador" />
              <Kpi rotulo="Parcialmente pedidas" valor={requisicoes.kpis.parciais} nota="parte das linhas já virou pedido" />
            </Inline>
          ) : null}
        </Deferred>

        <p className="text-sm text-muted-foreground">
          A requisição é o pedido interno de reposição: o sistema sugere os itens cujo estoque caiu ao nível de alerta no local escolhido.
        </p>

        <Inline data-contract="requisicoes-filtros" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar requisição ou quem adicionou…" aria-label="Buscar requisição ou quem adicionou" />
          </div>
          <Deferred data="opcoes" fallback={null}>
            <Inline gap={2} wrap>
              <FiltroSelect rotulo="Local" valor={filtros.location_id} lista={opcoes?.locais} onChange={(v) => filtrar({ location_id: v })} />
              <FiltroSelect rotulo="Status" valor={filtros.status} lista={opcoes?.status} onChange={(v) => filtrar({ status: v })} />
            </Inline>
          </Deferred>
        </Inline>

        <Deferred data="necessidade" fallback={null}>
          <NecessidadeDeCompra linhas={necessidade ?? []} />
        </Deferred>

        <Deferred data="requisicoes" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando requisições…</p>}>
          {linhas.length > 0 ? (
            <Stack gap={2}>
              <div data-contract="requisicoes-table">
                <DataTable columns={colunas} data={visiveis} caption="Requisições de compra do negócio" rowKey={(r) => r.id}
                  emptyMessage="Nenhuma requisição com esse termo." />
              </div>
              <p className="text-sm text-muted-foreground">
                Quem escolhe fornecedor é o comprador: a requisição vira pedido pelo lado do pedido, e o status dela é reapurado a partir das linhas atendidas.
              </p>
              {requisicoes?.truncado && (
                <p className="text-sm">
                  A lista mostra as requisições mais recentes. Para ver todas, <a className="font-medium underline" href="/purchase-requisition?legacy=1">abra a tela clássica</a>.
                </p>
              )}
            </Stack>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhuma requisição de compra" description="Requisições com esses filtros aparecem aqui."
                action={permissoes.criar ? <Button asChild><a href="/purchase-requisition/create">Adicionar requisição</a></Button> : undefined} />
            </div>
          )}
        </Deferred>
      </Stack>
    </div>
  );
}

RequisicoesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default RequisicoesIndex;
