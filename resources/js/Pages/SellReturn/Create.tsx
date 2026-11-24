// SellReturn/Create — "+ Nova devolução" (/sell-return/create), visita Inertia.
// Etapa 1 do protótipo Devolução V3 (prototipo-ui/cowork/Wagner/devolucao-v3.html):
// "Origem da devolução" no modo venda — escolher a venda finalizada que está voltando.
// Só LÊ: "Usar esta venda" abre o registro que já existe (/sell-return/add/{venda} →
// SellReturn/Add). Não grava, não calcula valor, não mexe em estoque.
//
// Carimbada do PT-01 Lista por criar-tela.mjs (UI-0013). Sem consignação e sem "Não é
// devolução?" — os dois não existem no repo (Create.charter.md Non-Goals).
// Refs: Create.charter.md · Create.casos.md (UC-SRCRE-*) ·
//       memory/requisitos/Sells/RUNBOOK-sell-return-create.md · ADR 0093

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { PageHeader } from '@/Components/PageHeader';
import SubNav from '@/Components/shared/SubNav';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Segmented } from '@/Components/ui/segmented';

// ──────────────────────────────────────────────────────────────
// TIPOS — paridade SellReturnController@origemVendas
// ──────────────────────────────────────────────────────────────
interface VendaOrigem {
  id: number;
  numero: string;
  data: string;
  cliente: string | null;
  documento: string | null;
  uf: string | null;
  nota: { numero: string; tipo: 'NF-e' | 'NFC-e' } | null;
  devolucao_numero: string | null;
  total: number;
}

type FiltroNota = 'todas' | 'nfe' | 'sem';

interface SellReturnCreateProps {
  vendas?: PaginatorShape<VendaOrigem>; // deferida (grupo "origem")
  filtros: { nota: FiltroNota; q: string };
}

// ──────────────────────────────────────────────────────────────
// HELPERS
// ──────────────────────────────────────────────────────────────
const fmtBRL = (n: number): string =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0);

const fmtData = (valor: string): string => {
  const dia = String(valor || '').split(' ')[0] ?? '';
  const partes = dia.split('-');
  return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : valor;
};

const NAV_VENDAS = [
  { label: 'Vendas', href: '/sells', inertia: true },
  { label: 'Caixa do dia', href: '/vendas/caixa', inertia: true },
  { label: 'Devoluções', href: '/sell-return', inertia: true },
];

const FILTROS_NOTA = [
  { value: 'todas', label: 'Todas' },
  { value: 'nfe', label: 'Com NF-e' },
  { value: 'sem', label: 'Sem nota' },
];

const colunas: ColumnDef<VendaOrigem>[] = [
  { accessorKey: 'numero', header: 'Venda', meta: { mono: true }, cell: ({ row }) => <strong>{row.original.numero}</strong> },
  {
    accessorKey: 'cliente',
    header: 'Cliente',
    cell: ({ row }) => {
      const v = row.original;
      const sub = [v.documento, v.uf].filter(Boolean).join(' · ');
      return (
        <div className="flex min-w-0 flex-col">
          <strong className="break-words">{v.cliente ?? '—'}</strong>
          {sub && <span className="text-xs text-muted-foreground">{sub}</span>}
        </div>
      );
    },
  },
  {
    id: 'nota',
    header: 'Nota',
    cell: ({ row }) =>
      row.original.nota ? (
        <span className="font-mono">
          {row.original.nota.tipo} {row.original.nota.numero}
        </span>
      ) : (
        <Badge variant="warning">Sem nota</Badge>
      ),
  },
  { accessorKey: 'data', header: 'Data', meta: { mono: true }, cell: ({ row }) => fmtData(row.original.data) },
  { accessorKey: 'total', header: 'Valor', meta: { align: 'right', mono: true }, cell: ({ row }) => fmtBRL(row.original.total) },
  {
    id: 'acoes',
    header: () => <span className="sr-only">Ações</span>,
    cell: ({ row }) => {
      const v = row.original;
      // Visita Inertia → SellReturn/Add. A venda que já tem devolução abre a mesma tela, na edição.
      return (
        <Button asChild variant="outline" size="sm">
          <Link
            href={`/sell-return/add/${v.id}`}
            aria-label={v.devolucao_numero ? `Abrir a devolução da venda ${v.numero}` : `Usar a venda ${v.numero}`}
          >
            {v.devolucao_numero ? 'Abrir devolução' : 'Usar esta venda'}
          </Link>
        </Button>
      );
    },
  },
];

// ──────────────────────────────────────────────────────────────
// COMPONENTE
// ──────────────────────────────────────────────────────────────
export default function SellReturnCreate({ vendas, filtros }: SellReturnCreateProps) {
  const trocarFiltro = (nota: string) =>
    router.get(
      '/sell-return/create',
      { nota: nota === 'todas' ? undefined : nota, q: filtros.q || undefined },
      { preserveScroll: true, preserveState: true, replace: true },
    );

  // `vendas` é deferida. A tabela fica montada mesmo enquanto ela carrega: dentro de um
  // <Deferred>, cada troca de filtro desmontaria a tabela e a busca perderia o foco.
  const carregando = vendas === undefined;

  return (
    <AppShellV2 title="Nova devolução" breadcrumbItems={[{ label: 'Vendas' }, { label: 'Devoluções' }, { label: 'Nova' }]}>
      <Head title="Nova devolução" />

      <div data-contract="cabecalho">
        <PageHeader
          title="Nova devolução"
          subtitle="Escolha a venda finalizada que está voltando"
          subnav={<SubNav items={NAV_VENDAS} ariaLabel="Vendas e telas vinculadas" />}
          actions={
            <Button asChild variant="outline">
              <Link href="/sell-return">Voltar às devoluções</Link>
            </Button>
          }
        />
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
        <Card>
          <CardHeader>
            <CardTitle>1 · Origem da devolução</CardTitle>
            <CardDescription>Escolha a venda finalizada que está voltando.</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-3">
            <div data-contract="filtros" className="flex flex-wrap items-center gap-3">
              <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Vendas finalizadas</span>
              <Segmented
                value={filtros.nota}
                onValueChange={trocarFiltro}
                options={FILTROS_NOTA}
                aria-label="Filtrar por nota"
              />
            </div>

            <div data-contract="lista">
              <DataTable
                columns={colunas}
                data={vendas?.data ?? []}
                pagination={vendas}
                endpoint="/sell-return/create"
                filters={{ nota: filtros.nota === 'todas' ? undefined : filtros.nota }}
                initialSearch={filtros.q}
                searchPlaceholder="Cliente ou nº da venda ou da NF-e"
                emptyMessage={carregando ? 'Carregando vendas…' : 'Nenhuma venda encontrada para esse filtro.'}
                caption="Vendas finalizadas"
                rowKey={(v) => v.id}
                totalLabel="vendas"
              />
            </div>

            <p className="text-xs text-muted-foreground">
              Com NF-e, a devolução pode gerar a nota de devolução com os tributos da nota original. Sem nota, como
              nas vendas da Rota Livre, o retorno só ajusta a venda, o estoque e o financeiro.
            </p>
          </CardContent>
        </Card>

        <Card className="h-fit">
          <CardHeader>
            <CardTitle>Fechamento fiscal</CardTitle>
            <CardDescription>Aguardando a origem</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-3">
            <div className="rounded-md border border-border bg-muted/40 p-3">
              <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Total da devolução
              </span>
              <b className="block font-mono text-2xl">{fmtBRL(0)}</b>
              <small className="text-muted-foreground">Escolha a venda finalizada</small>
            </div>
            <p className="text-xs text-muted-foreground">
              Os itens e os totais aparecem quando a venda for escolhida.
            </p>
          </CardContent>
        </Card>
      </div>
    </AppShellV2>
  );
}
