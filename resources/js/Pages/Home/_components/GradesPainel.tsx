// Abas de grade + drawer de detalhe do painel "Visão geral" (US-DASH-005).
//
// Âncora de design: prototipo-ui/cowork/dash-legacy-page.jsx — seção `TabBar` +
// `DataTablePro` + `Drawer`. O protótipo desenha 9 abas; o Blade legado tem 8
// (medido em resources/views/home/index.blade.php, 2026-08-27), e quem decide
// quais aparecem é o backend: `abas` já chega filtrada por permissão e setting.
// A 9ª do protótipo ("Fluxo de caixa") não existe no Blade e por isso não existe
// aqui — desenhar uma aba sem fonte seria inventar capacidade.
//
// Fora desta onda, por motivo declarado:
//   · contagem por aba (o `count` do protótipo) — seriam 8 queries de COUNT por
//     render só pra pintar um pill, contra o alvo de first-paint <= 800ms do
//     charter. A aba aberta mostra o total real no rodapé da tabela.
//   · "Exportar CSV" do rodapé — não existe no Blade legado; entra como pedido.
//   · botão "Lançar pagamento" do rodapé do Drawer — VIOLA o Non-Goal "NÃO permite
//     mutação — sem botões criar venda inline". Preservado como NAVEGAÇÃO para a
//     tela dedicada (`/payments/add_payment/{id}`, rota GET), que é o que o
//     charter manda: "atalhos viram menu / navegação separada".

import { Icon } from '@/Components/Icon';
import { Inline, Stack } from '@/Components/layout';
import DataTable, { type EstadoDaLinha, type PaginatorShape } from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';
import StatusBadge from '@/Components/shared/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Skeleton } from '@/Components/ui/skeleton';
import { Deferred } from '@inertiajs/react';
import { hrefDaAba, type Filtros } from './abaHref';
import type { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';

/** Uma linha de grade. As chaves variam por aba — o backend manda só as da aba aberta. */
export interface LinhaDaGrade {
  id: number | string;
  documento?: string;
  contato?: string;
  vencimento?: string | null;
  data?: string | null;
  situacao?: string;
  devido?: number;
  total?: number;
  produto?: string;
  loja?: string;
  lote?: string;
  saldo?: number;
  atual?: string;
  minimo?: number;
  state?: EstadoDaLinha | null;
}

export interface Aba {
  key: string;
  label: string;
}

interface Props {
  abas: Aba[];
  aba: string | null;
  grade: PaginatorShape<LinhaDaGrade> | null;
  /** Params que a troca de aba precisa preservar (período + loja). */
  filtros: Filtros;
}

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

/** ISO -> dd/mm/aaaa. O backend manda ISO de propósito: formato é decisão da UI. */
const dia = (v?: string | null) => {
  if (!v) return '—';
  const [ano, mes, d] = v.split('-');
  return `${d}/${mes}/${ano}`;
};

const monoDireita = { mono: true, align: 'right' as const };

/** Coluna de texto simples, o caso mais comum. */
const texto = (
  id: keyof LinhaDaGrade,
  header: string,
  width?: number,
  ordenavel = true,
): ColumnDef<LinhaDaGrade, unknown> => ({
  id,
  header,
  // `accessorFn` NÃO é decoração: o `getCanSort()` do TanStack exige um accessor, e sem
  // ele o `enableSorting` é inerte — o cabeçalho renderiza como texto morto. Medido em
  // produção depois do #6392: os 5 `th` saíram com `botao:0` apesar do enableSorting.
  accessorFn: (row) => row[id] ?? '',
  enableSorting: ordenavel,
  cell: ({ row }) => <>{(row.original[id] as string) || '—'}</>,
  meta: width ? { width } : undefined,
});

const colunaData = (id: 'vencimento' | 'data', header: string): ColumnDef<LinhaDaGrade, unknown> => ({
  id,
  header,
  accessorFn: (row) => row[id] ?? '',
  enableSorting: true,
  cell: ({ row }) => <>{dia(row.original[id])}</>,
  meta: { width: 120, mono: true },
});

const colunaSituacao = (kind: string): ColumnDef<LinhaDaGrade, unknown> => ({
  id: 'situacao',
  header: 'Situação',
  enableSorting: false, // a âncora não marca esta coluna como sortable
  cell: ({ row }) => (row.original.situacao ? <StatusBadge kind={kind} value={row.original.situacao} /> : <>—</>),
  meta: { width: 130 },
});

const colunaValor = (id: 'devido' | 'total', header: string): ColumnDef<LinhaDaGrade, unknown> => ({
  id,
  header,
  accessorFn: (row) => row[id] ?? 0,
  enableSorting: true,
  cell: ({ row }) => {
    const v = row.original[id];
    return <>{typeof v === 'number' ? brl(v) : '—'}</>;
  },
  meta: { width: 130, ...monoDireita },
});

/**
 * Colunas por aba. Os rótulos são os do protótipo — "Nota" na venda e "Referência"
 * na compra não são sinônimos trocáveis: é o vocabulário de cada documento.
 */
const COLUNAS: Record<string, ColumnDef<LinhaDaGrade, unknown>[]> = {
  'venc-venda': [
    texto('documento', 'Nota', 110),
    texto('contato', 'Cliente'),
    colunaData('vencimento', 'Vencimento'),
    colunaSituacao('payment'),
    colunaValor('devido', 'Devido'),
  ],
  'venc-compra': [
    texto('documento', 'Referência', 120),
    texto('contato', 'Fornecedor'),
    colunaData('vencimento', 'Vencimento'),
    colunaSituacao('payment'),
    colunaValor('devido', 'Devido'),
  ],
  estoque: [
    texto('produto', 'Produto'),
    texto('loja', 'Loja', 140),
    { id: 'atual', header: 'Estoque', cell: ({ row }) => <>{row.original.atual}</>, meta: { width: 110, ...monoDireita } },
    {
      id: 'minimo',
      header: 'Mínimo',
      cell: ({ row }) => <>{row.original.minimo ?? '—'}</>,
      meta: { width: 100, ...monoDireita },
    },
  ],
  validade: [
    texto('produto', 'Produto'),
    texto('lote', 'Lote', 110),
    { id: 'saldo', header: 'Saldo', cell: ({ row }) => <>{row.original.saldo ?? '—'}</>, meta: { width: 100, ...monoDireita } },
    colunaData('vencimento', 'Vence em'),
  ],
  pedidos: [
    texto('documento', 'Pedido', 120),
    texto('contato', 'Cliente'),
    colunaData('data', 'Data'),
    colunaSituacao('documento'),
    colunaValor('total', 'Total'),
  ],
  'compras-abertas': [
    texto('documento', 'Ordem', 120),
    texto('contato', 'Fornecedor'),
    colunaData('data', 'Data'),
    colunaSituacao('documento'),
    colunaValor('total', 'Total'),
  ],
  requisicoes: [
    texto('documento', 'Requisição', 120),
    texto('contato', 'Setor solicitante'),
    colunaData('data', 'Data'),
    colunaSituacao('documento'),
    colunaValor('total', 'Estimado'),
  ],
  expedicao: [
    texto('documento', 'Nota', 110),
    texto('contato', 'Cliente'),
    colunaData('data', 'Entrega'),
    colunaSituacao('os'),
  ],
};

/** Rótulo legível de cada chave no drawer — a chave crua não é vocabulário de usuário. */
const ROTULOS: Partial<Record<keyof LinhaDaGrade, string>> = {
  documento: 'Documento',
  contato: 'Contato',
  vencimento: 'Vencimento',
  data: 'Data',
  situacao: 'Situação',
  devido: 'Devido',
  total: 'Total',
  produto: 'Produto',
  loja: 'Loja',
  lote: 'Lote',
  saldo: 'Saldo',
  atual: 'Estoque atual',
  minimo: 'Estoque mínimo',
};

/** As duas abas em que "lançar pagamento" faz sentido — as de título em aberto. */
const ABAS_COM_PAGAMENTO = new Set(['venc-venda', 'venc-compra']);

function valorLegivel(chave: keyof LinhaDaGrade, valor: unknown): string {
  if (valor === null || valor === undefined || valor === '') return '—';
  if (chave === 'vencimento' || chave === 'data') return dia(valor as string);
  if (chave === 'devido' || chave === 'total') return brl(valor as number);
  return String(valor);
}

export default function GradesPainel({ abas, aba, grade, filtros }: Props) {
  const [detalhe, setDetalhe] = useState<LinhaDaGrade | null>(null);

  if (abas.length === 0 || aba === null) {
    return null;
  }

  const rotuloAtivo = abas.find((a) => a.key === aba)?.label ?? '';
  const colunas = COLUNAS[aba] ?? [];

  const href = (key: string) => hrefDaAba(filtros, key);

  return (
    <Stack gap={3} asChild>
      <section aria-label="Grades do painel" data-contract="grades">
        {/*
          `maxVisible` default do componente e 5 (PageHeaderTabs.tsx:119) — com as 8 abas do
          catalogo, 3 sumiam atras de um gatilho que renderiza SO o icone `...`, sem rotulo:
          Ordens de compra, Requisicoes e Expedicoes pendentes. A ancora mostra TODAS
          (TabBar com overflowX:auto).

          O `className` chega ao wrapper externo, e `md:flex-wrap` vence o `md:flex-nowrap`
          do componente por ordem no `cn()` — sem isso, 8 rotulos longos (~1100px de texto)
          nao caberiam nos ~972px disponiveis a 1280 e estourariam, ja que acima de 768 o
          tablist tambem nao rola.
        */}
        <PageHeaderTabs
          ghosts={abas.map((a) => ({ key: a.key, label: a.label, href: href(a.key) }))}
          activeGhostKey={aba}
          maxVisible={abas.length}
          className="md:flex-wrap"
        />

        <Deferred data="grade" fallback={<Skeleton className="h-64 w-full rounded-lg" />}>
          {grade && grade.data.length > 0 ? (
            <Stack gap={0}>
              <DataTable<LinhaDaGrade>
                columns={colunas}
                data={grade.data}
                pagination={grade}
                endpoint={window.location.pathname}
                // O nome SEGUE a aba: a tabela troca de conteúdo sem trocar de DOM, e um
                // nome fixo mentiria depois do 1º clique. `rotuloAtivo` é o label da aba
                // ativa (L222) — a mesma copy que o `emptyMessage` abaixo ja usa.
                caption={rotuloAtivo ? `Grade — ${rotuloAtivo}` : 'Grade'}
                filters={{ ...filtros, aba }}
                showSearch={false}
                rowKey={(row) => row.id}
                rowState={(row) => row.state ?? undefined}
                onRowClick={setDetalhe}
                emptyMessage={`Nada em ${rotuloAtivo.toLowerCase()} neste período.`}
              />
              {/*
                Rodapé da âncora. Não é enfeite: é a ÚNICA coisa na tela que diz ao
                usuário que a linha abre um detalhe. Sem ele, o `onRowClick` existe e
                ninguém descobre — foi assim que a tela foi pra produção.
              */}
              <Inline gap={3} justify="between" align="center" wrap className="px-1 pt-2">
                <span className="font-mono text-[10.5px] text-muted-foreground">
                  {grade.data.length} de {grade.total} linhas · clique para abrir o detalhe
                </span>
              </Inline>
            </Stack>
          ) : (
            <EmptyState
              icon="check"
              title={`Nada em ${rotuloAtivo.toLowerCase()}`}
              description="Quando houver registros nesta lista, eles aparecem aqui."
            />
          )}
        </Deferred>

        <Sheet open={detalhe !== null} onOpenChange={(aberto) => !aberto && setDetalhe(null)}>
          <SheetContent className="w-full sm:max-w-md">
            <SheetHeader>
              <SheetTitle>{detalhe?.documento || detalhe?.produto || 'Detalhe'}</SheetTitle>
              <SheetDescription>{rotuloAtivo}</SheetDescription>
            </SheetHeader>

            {detalhe && (
              <Stack gap={4} className="px-4">
                <Stack gap={2} asChild>
                  <dl className="text-[12.5px]">
                    {(Object.keys(ROTULOS) as Array<keyof LinhaDaGrade>)
                      .filter((chave) => detalhe[chave] !== undefined)
                      .map((chave) => (
                        <Inline key={chave} gap={4} align="baseline" justify="between" wrap>
                          <dt className="font-mono text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                            {ROTULOS[chave]}
                          </dt>
                          <dd className="min-w-0 text-right text-foreground">
                            {valorLegivel(chave, detalhe[chave])}
                          </dd>
                        </Inline>
                      ))}
                  </dl>
                </Stack>

                {ABAS_COM_PAGAMENTO.has(aba) && typeof detalhe.id === 'number' && (
                  <Inline gap={2}>
                    <Button asChild size="sm">
                      <a href={`/payments/add_payment/${detalhe.id}`}>
                        <Icon name="banknote" size={14} />
                        Lançar pagamento
                      </a>
                    </Button>
                  </Inline>
                )}
              </Stack>
            )}
          </SheetContent>
        </Sheet>
      </section>
    </Stack>
  );
}

export { GradesPainel };
