// SellReturn/Create — "+ Nova devolução" (/sell-return/create), visita Inertia.
// Etapa 1 do protótipo Devolução V3 (prototipo-ui/cowork/Wagner/devolucao-v3.html):
// "Origem da devolução" no modo venda — escolher a venda finalizada que está voltando.
// Só LÊ: escolhida a venda, o registro que já existe (/sell-return/add/{venda} →
// SellReturn/Add) faz a devolução. Não grava, não calcula valor, não mexe em estoque.
//
// Pedido [W] 2026-11-24: um clique na linha escolhe a venda e soma no totalizador, e dá
// para escolher várias vendas do MESMO cliente. Cada venda segue sendo uma devolução (o
// núcleo grava uma por venda, pelo mesmo store()); o registro percorre as escolhidas uma a
// uma pelo parâmetro `fila`. O total aqui é a soma das vendas — o teto da devolução; o
// valor final sai dos itens no registro.
//
// Carimbada do PT-01 Lista por criar-tela.mjs (UI-0013). Sem consignação e sem "Não é
// devolução?" — os dois não existem no repo (Create.charter.md Non-Goals).
// Refs: Create.charter.md · Create.casos.md (UC-SRCRE-*) ·
//       memory/requisitos/Sells/RUNBOOK-sell-return-create.md · ADR 0093

import AppShellV2 from '@/Layouts/AppShellV2';
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { X } from 'lucide-react';
import type { ColumnDef } from '@tanstack/react-table';
import { PageHeader } from '@/Components/PageHeader';
import SubNav from '@/Components/shared/SubNav';
import DataTable, { type PaginatorShape, type SelecaoDaTabela } from '@/Components/shared/DataTable';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Segmented } from '@/Components/ui/segmented';
import { Grid, Inline, Stack } from '@/Components/layout';
import { urlDoRegistro } from './_components/devolucaoFila';

// ──────────────────────────────────────────────────────────────
// TIPOS — paridade SellReturnController@origemVendas
// ──────────────────────────────────────────────────────────────
interface VendaOrigem {
  id: number;
  numero: string;
  data: string;
  contato_id: number | null;
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
        <Stack gap={0} className="min-w-0">
          <strong className="break-words">{v.cliente ?? '—'}</strong>
          {sub && <span className="text-xs text-muted-foreground">{sub}</span>}
        </Stack>
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
        // O clique no botão não pode também marcar a linha.
        <span onClick={(e) => e.stopPropagation()} onKeyDown={(e) => e.stopPropagation()}>
        <Button asChild variant="outline" size="sm">
          <Link
            href={`/sell-return/add/${v.id}`}
            aria-label={v.devolucao_numero ? `Abrir a devolução da venda ${v.numero}` : `Usar a venda ${v.numero}`}
          >
            {v.devolucao_numero ? 'Abrir devolução' : 'Usar esta venda'}
          </Link>
        </Button>
        </span>
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

  // Escolhidas guardadas pelo id: atravessam troca de página, filtro e busca.
  const [escolhidas, setEscolhidas] = useState<VendaOrigem[]>([]);
  const escolhida = (v: VendaOrigem) => escolhidas.some((e) => e.id === v.id);
  const total = escolhidas.reduce((s, v) => s + (v.total || 0), 0);

  const alternar = (v: VendaOrigem) => {
    // Venda que já tem devolução abre o registro dela; não entra na soma.
    if (v.devolucao_numero) {
      router.visit(`/sell-return/add/${v.id}`);
      return;
    }
    if (escolhida(v)) {
      setEscolhidas((atual) => atual.filter((e) => e.id !== v.id));
      return;
    }
    const outroCliente = escolhidas.length > 0 && escolhidas[0]!.contato_id !== v.contato_id;
    if (outroCliente) {
      toast.info(`Só entram na mesma devolução vendas de ${escolhidas[0]!.cliente ?? 'um mesmo cliente'}. A escolha recomeçou com a venda ${v.numero}.`);
      setEscolhidas([v]);
      return;
    }
    setEscolhidas((atual) => [...atual, v]);
  };

  const linhasDaPagina = (vendas?.data ?? []).filter((v) => !v.devolucao_numero);
  const doCliente = (v: VendaOrigem) =>
    escolhidas.length === 0 ? true : v.contato_id === escolhidas[0]!.contato_id;
  const marcaveis = linhasDaPagina.filter(doCliente);
  const marcadasNaPagina = marcaveis.filter(escolhida).length;

  const selecao: SelecaoDaTabela<VendaOrigem> = {
    isSelected: escolhida,
    onToggle: alternar,
    allState: marcadasNaPagina === 0 ? 'none' : marcadasNaPagina === marcaveis.length ? 'all' : 'some',
    onToggleAll: (marcar) => {
      if (!marcar) {
        const fora = new Set(marcaveis.map((v) => v.id));
        setEscolhidas((atual) => atual.filter((e) => !fora.has(e.id)));
        return;
      }
      // Sem escolha ainda, "marcar todas" pega o cliente da primeira venda da página.
      const base = escolhidas[0] ?? linhasDaPagina[0];
      if (!base) return;
      const novas = linhasDaPagina.filter((v) => v.contato_id === base.contato_id && !escolhida(v));
      setEscolhidas((atual) => [...atual, ...novas]);
    },
    rowLabel: (v) => `Escolher a venda ${v.numero}`,
    allLabel: 'Escolher as vendas desta página do mesmo cliente',
  };

  const continuar = () => {
    if (!escolhidas.length) return;
    router.visit(urlDoRegistro(escolhidas.map((v) => v.id)));
  };

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

      <Grid gap={4} className="mt-4 lg:grid-cols-[minmax(0,1fr)_320px]">
        <Card>
          <CardHeader>
            <CardTitle>1 · Origem da devolução</CardTitle>
            <CardDescription>Escolha a venda finalizada que está voltando.</CardDescription>
          </CardHeader>
          <CardContent>
            <Stack gap={3}>
            <Inline data-contract="filtros" wrap align="center" gap={3}>
              <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Vendas finalizadas</span>
              <Segmented
                value={filtros.nota}
                onValueChange={trocarFiltro}
                options={FILTROS_NOTA}
                aria-label="Filtrar por nota"
              />
            </Inline>

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
                selection={selecao}
                onRowClick={alternar}
              />
            </div>

            <p className="text-xs text-muted-foreground">
              Com NF-e, a devolução pode gerar a nota de devolução com os tributos da nota original. Sem nota, como
              nas vendas da Rota Livre, o retorno só ajusta a venda, o estoque e o financeiro.
            </p>
            </Stack>
          </CardContent>
        </Card>

        <Card className="h-fit" data-contract="totalizador">
          <CardHeader>
            <CardTitle>Fechamento fiscal</CardTitle>
            <CardDescription>
              {escolhidas.length === 0
                ? 'Aguardando a origem'
                : `${escolhidas.length} ${escolhidas.length === 1 ? 'venda escolhida' : 'vendas escolhidas'} · ${escolhidas[0]!.cliente ?? 'cliente'}`}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Stack gap={3}>
            <div className="rounded-md border border-border bg-muted/40 p-3">
              <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Total da devolução
              </span>
              <b className="block font-mono text-2xl" data-contract="total">{fmtBRL(total)}</b>
              <small className="text-muted-foreground">
                {escolhidas.length === 0 ? 'Escolha a venda finalizada' : 'Até o total das vendas; os itens definem o valor final'}
              </small>
            </div>

            {escolhidas.length > 0 && (
              <Stack gap={1} data-contract="escolhidas">
                {escolhidas.map((v) => (
                  <Inline key={v.id} justify="between" align="center" gap={2} className="text-sm">
                    <span className="font-mono">{v.numero}</span>
                    <Inline align="center" gap={1}>
                      <span className="font-mono">{fmtBRL(v.total)}</span>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-11 w-11"
                        aria-label={`Tirar a venda ${v.numero}`}
                        onClick={() => setEscolhidas((atual) => atual.filter((e) => e.id !== v.id))}
                      >
                        <X className="h-4 w-4" />
                      </Button>
                    </Inline>
                  </Inline>
                ))}
              </Stack>
            )}

            <Button type="button" disabled={escolhidas.length === 0} onClick={continuar}>
              {escolhidas.length > 1 ? `Devolver ${escolhidas.length} vendas` : 'Continuar'}
            </Button>
            <p className="text-xs text-muted-foreground">
              {escolhidas.length > 1
                ? 'Cada venda vira uma devolução própria. O registro abre uma de cada vez, na ordem escolhida.'
                : 'Clique na venda para escolher. Vendas do mesmo cliente podem entrar juntas.'}
            </p>
            </Stack>
          </CardContent>
        </Card>
      </Grid>
    </AppShellV2>
  );
}
