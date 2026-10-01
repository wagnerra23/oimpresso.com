// SellReturn/Index — lista de devoluções de venda (/sell-return), visita Inertia.
// Thread 03 de venda-menu, PR 1 de 2: só a LISTA. O registro (/sell-return/add/{venda})
// segue na Blade até o PR 2, que espera a REGRA MESTRE de valor/estoque.
//
// Estrutura do desenho VendasDevolucoesPage (vendas-extras.jsx; alvo
// governance/design/targets/vendas--devolucao--index.secoes.json): cabeçalho · navegação de
// Vendas · 3 KPIs · tabela. Montada com os componentes canônicos (PageHeader · SubNav ·
// KpiCard · DataTable) e tokens do DS — a tela é de outro módulo, então NÃO veste o bundle
// `.sells-cowork` (ui:lint R7 / PT-04 L80).
// Refs: Index.charter.md · Index.casos.md (UC-SRIDX-*) ·
//       memory/requisitos/Sells/RUNBOOK-sell-return-index.md · ADR 0104 · ADR 0093

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { PageHeader } from '@/Components/PageHeader';
import SubNav from '@/Components/shared/SubNav';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { Grid } from '@/Components/layout';

// ──────────────────────────────────────────────────────────────
// TIPOS — paridade SellReturnController@inertiaIndex
// ──────────────────────────────────────────────────────────────
interface LinhaDevolucao {
  id: number;
  data: string;
  numero: string;
  venda_id: number;
  venda_numero: string;
  cliente: string | null;
  local: string;
  situacao_pagamento: string; // paid | partial | due
  total: number;
  pago: number;
}

interface Kpis {
  com_saldo: number;
  no_mes: number;
  valor_mes: number;
}

interface SellReturnIndexProps {
  kpis?: Kpis; // deferida (grupo "lista")
  devolucoes?: PaginatorShape<LinhaDevolucao>; // deferida (grupo "lista")
  permissions: { ver_todas: boolean; ver_proprias: boolean };
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

// Situação do PAGAMENTO da devolução ao cliente (payment_status gravado).
const SITUACAO: Record<string, { label: string; variant: 'success' | 'warning' | 'danger' | 'neutral' }> = {
  paid: { label: 'Pago', variant: 'success' },
  partial: { label: 'Parcial', variant: 'warning' },
  due: { label: 'A pagar', variant: 'danger' },
};

const NAV_VENDAS = [
  { label: 'Vendas', href: '/sells', inertia: true },
  { label: 'Caixa do dia', href: '/vendas/caixa', inertia: true },
  { label: 'Devoluções', href: '/sell-return', inertia: true },
];

const colunas: ColumnDef<LinhaDevolucao>[] = [
  { accessorKey: 'numero', header: 'Devolução', meta: { mono: true }, cell: ({ row }) => `#${row.original.numero}` },
  { accessorKey: 'data', header: 'Data', meta: { mono: true }, cell: ({ row }) => fmtData(row.original.data) },
  {
    accessorKey: 'venda_numero',
    header: 'Venda orig.',
    meta: { mono: true },
    cell: ({ row }) => (
      <Link href={`/sells/${row.original.venda_id}`} aria-label={`Abrir a venda ${row.original.venda_numero}`}>
        #{row.original.venda_numero}
      </Link>
    ),
  },
  { accessorKey: 'cliente', header: 'Cliente', cell: ({ row }) => <strong>{row.original.cliente ?? '—'}</strong> },
  { accessorKey: 'local', header: 'Local' },
  { accessorKey: 'total', header: 'Total', meta: { align: 'right', mono: true }, cell: ({ row }) => fmtBRL(row.original.total) },
  { accessorKey: 'pago', header: 'Pago', meta: { align: 'right', mono: true }, cell: ({ row }) => fmtBRL(row.original.pago) },
  {
    accessorKey: 'situacao_pagamento',
    header: 'Situação',
    cell: ({ row }) => {
      const sit = SITUACAO[row.original.situacao_pagamento] ?? {
        label: row.original.situacao_pagamento,
        variant: 'neutral' as const,
      };
      return <Badge variant={sit.variant}>{sit.label}</Badge>;
    },
  },
  {
    id: 'acoes',
    header: () => <span className="sr-only">Ações</span>,
    cell: ({ row }) => (
      // Registro/edição segue na Blade até o PR 2 (SellReturn/Add).
      <a href={`/sell-return/add/${row.original.venda_id}`} aria-label={`Editar a devolução ${row.original.numero}`}>
        Editar
      </a>
    ),
  },
];

// ──────────────────────────────────────────────────────────────
// COMPONENTE
// ──────────────────────────────────────────────────────────────
export default function SellReturnIndex({ kpis, devolucoes, permissions }: SellReturnIndexProps) {
  const soProprias = !permissions.ver_todas && permissions.ver_proprias;
  const comSaldo = kpis?.com_saldo ?? 0;

  return (
    <AppShellV2 title="Devoluções de venda" breadcrumbItems={[{ label: 'Vendas' }, { label: 'Devoluções' }]}>
      <Head title="Devoluções de venda" />

      <div data-contract="cabecalho">
        <PageHeader
          title="Devoluções de venda"
          subtitle={`Devoluções registradas, venda de origem e o que falta pagar ao cliente${soProprias ? ' · só as suas' : ''}`}
          subnav={<SubNav items={NAV_VENDAS} ariaLabel="Vendas e telas vinculadas" />}
          actions={
            // A devolução começa pela venda: abre-se a venda e usa-se Devolver.
            <Button asChild>
              <Link href="/sells" title="A devolução começa pela venda — abra a venda e use Devolver">
                + Nova devolução
              </Link>
            </Button>
          }
        />
      </div>

      <Deferred data={['kpis', 'devolucoes']} fallback={<Skeleton className="mt-4 h-64 w-full" />}>
        <>
          <Grid data-contract="kpis" min="sm" gap={4} className="mt-4">
            <KpiCard
              label="Com saldo a pagar"
              value={comSaldo}
              description="devolução ainda não paga ao cliente"
              tone={comSaldo > 0 ? 'warning' : 'default'}
            />
            <KpiCard label="Devoluções no mês" value={kpis?.no_mes ?? 0} description="total registrado" />
            <KpiCard
              label="Valor devolvido no mês"
              value={fmtBRL(kpis?.valor_mes ?? 0)}
              description="soma das devoluções do mês"
            />
          </Grid>

          <div data-contract="lista" className="mt-6">
            {!devolucoes || devolucoes.data.length === 0 ? (
              <EmptyState
                icon="rotate-ccw"
                title="Nenhuma devolução registrada"
                description="A devolução começa pela venda: abra a venda e use Devolver."
              />
            ) : (
              <DataTable
                columns={colunas}
                data={devolucoes.data}
                pagination={devolucoes}
                endpoint="/sell-return"
                caption="Devoluções de venda"
                rowKey={(d) => d.id}
              />
            )}
          </div>
        </>
      </Deferred>
    </AppShellV2>
  );
}
