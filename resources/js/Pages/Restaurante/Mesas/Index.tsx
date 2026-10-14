// @memcofre
//   tela: /modules/tables
//   module: Restaurante
//   stories: thread restaurante/playbook/01 (Mesas)
//   permissao: access_tables
//
// Mesas do negócio. Atrás da flag `useV2RestauranteMesas` (TableController::FLAG_V2); desligada, a rota segue na
// Blade `restaurant.table.index`. `?classico=1` abre a Blade com a flag ligada — é lá que se cadastra, edita e exclui.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Restaurante/RUNBOOK-mesas.md
// Âncora de design: governance/design/targets/restaurante--mesas--index.alvo.json (A1) ← integra-extras.jsx
//   RestauranteExtrasPage view="cfg-mesas".
//
// A situação não é campo da mesa: o controller deriva "ocupada" do pedido aberto (RestaurantUtil::getAllOrders),
// a mesma regra da Cozinha e dos Pedidos. A tela só mostra o que vem pronto.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { ExternalLink, Info, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { PageHeader } from '@/Components/PageHeader';
import PageHeaderTabs, { type PageHeaderGhost } from '@/Components/shared/PageHeaderTabs';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import KpiCard from '@/Components/shared/KpiCard';
import type { ColumnDef } from '@tanstack/react-table';
import { Grid, Inline, Stack } from '@/Components/layout';

export interface Mesa {
  id: number;
  nome: string;
  local: string;
  descricao: string | null;
  ocupada: boolean;
  pedidos_abertos: number;
  atendente: string | null;
}
interface Props {
  mesas?: Mesa[];
  pedidos_hoje?: number;
  pode_gerenciar: boolean;
}
interface ItemDeMenu { label: string; href?: string; children?: ItemDeMenu[] }

/** As telas do Restaurante, na ordem do protótipo. Atendentes fica de fora: o índice não lhe deu dono. */
const TELAS = ['/modules/tables', '/bookings', '/modules/kitchen', '/modules/orders'];
const caminho = (href: string) => new URL(href, window.location.origin).pathname;

function achatar(itens: ItemDeMenu[] | undefined): ItemDeMenu[] {
  return (itens ?? []).flatMap((i) => [i, ...achatar(i.children)]);
}

/** Abas derivadas do menu (cada item já sob a permissão dele): sem segundo dono da lista. */
function RestauranteAbas() {
  const { shell } = usePage<{ shell?: { menu?: ItemDeMenu[] } }>().props;
  const porRota = new Map(achatar(shell?.menu).filter((i) => i.href && i.href !== '#').map((i) => [caminho(i.href!), i.label]));
  const ghosts: PageHeaderGhost[] = TELAS.filter((r) => porRota.has(r)).map((r) => ({ key: r, label: porRota.get(r)!, href: r }));
  if (ghosts.length < 2) return null;

  return (
    <PageHeaderTabs ghosts={ghosts} activeGhostKey="/modules/tables" group="sistema" maxVisible={5}
      onGhostChange={(key) => { if (key !== '/modules/tables') window.location.assign(key); }} />
  );
}

function MesasIndex({ mesas, pedidos_hoje, pode_gerenciar }: Props) {
  const todas = useMemo(() => mesas ?? [], [mesas]);
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

  const termo = q.trim().toLowerCase();
  const visiveis = todas.filter((m) => !termo || `${m.nome} ${m.local}`.toLowerCase().includes(termo));
  const ocupadas = todas.filter((m) => m.ocupada).length;

  const colunas: ColumnDef<Mesa, unknown>[] = [
    { id: 'nome', header: 'Mesa', cell: ({ row: { original: m } }) => <span className="font-medium">{m.nome}</span> },
    { id: 'local', header: 'Local do negócio', cell: ({ row: { original: m } }) => m.local },
    { id: 'descricao', header: 'Descrição', cell: ({ row: { original: m } }) => <span className="text-muted-foreground">{m.descricao || '—'}</span> },
    {
      id: 'situacao', header: 'Situação',
      cell: ({ row: { original: m } }) => m.ocupada
        ? <Badge variant="warning">Ocupada{m.pedidos_abertos > 1 ? ` · ${m.pedidos_abertos} pedidos` : ''}</Badge>
        : <Badge variant="outline">Livre</Badge>,
    },
    { id: 'atendente', header: 'Atendente', cell: ({ row: { original: m } }) => m.atendente ?? '—' },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Mesas"
          subtitle={mesas ? <><strong>{ocupadas}</strong> de {todas.length} {todas.length === 1 ? 'mesa ocupada' : 'mesas ocupadas'}</> : 'mesas do restaurante'}
          subnav={<RestauranteAbas />}
          actions={pode_gerenciar ? (
            <Button asChild variant="outline">
              <a href="/modules/tables?classico=1"><ExternalLink className="size-4" /> Cadastrar e editar na tela clássica</a>
            </Button>
          ) : undefined} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Alert data-contract="aviso">
          <Info className="size-4" />
          <AlertTitle>Situação vem dos pedidos</AlertTitle>
          <AlertDescription>
            A mesa fica ocupada enquanto tem pedido ainda não servido. A permissão é uma só
            (<span className="font-mono">access_tables</span>) e vale para a tela inteira.
          </AlertDescription>
        </Alert>

        <Deferred data={['mesas', 'pedidos_hoje']} fallback={<p className="py-6 text-sm text-muted-foreground">Carregando mesas…</p>}>
          <Grid data-contract="kpis" min="sm" gap={4}>
            <KpiCard label="Mesas ocupadas" value={`${ocupadas} de ${todas.length}`} description="com pedido ainda não servido" tone={ocupadas ? 'warning' : 'success'} />
            <KpiCard label="Livres" value={todas.length - ocupadas} description="prontas para receber cliente" tone="success" />
            <KpiCard label="Pedidos hoje" value={pedidos_hoje ?? 0} description="vendas finalizadas com mesa" />
          </Grid>

          {todas.length > 0 ? (
            <Stack gap={2} data-contract="mesas-table">
              <Inline data-contract="toolbar" wrap gap={2}>
                <div className="relative min-w-64 flex-1">
                  <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                  <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
                    placeholder="Buscar mesa ou local…" aria-label="Buscar mesa pelo nome ou local" />
                </div>
              </Inline>
              <DataTable columns={colunas} data={visiveis} caption="Todas as suas mesas" rowKey={(m) => m.id}
                emptyMessage="Nenhuma mesa com esse termo." />
            </Stack>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhuma mesa cadastrada" description="Cadastre as mesas de cada local para o PDV e a cozinha."
                action={pode_gerenciar ? <Button asChild><a href="/modules/tables?classico=1">Cadastrar na tela clássica</a></Button> : undefined} />
            </div>
          )}
        </Deferred>
      </Stack>
    </div>
  );
}

MesasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default MesasIndex;
