// @memcofre
//   tela: /expenses
//   module: Financeiro
//   stories: US-MWART-016 (thread financeiro/13)
//   permissao: all_expense.access ou view_own_expense (o escopo vem do ExpenseController::consultaDespesas)
//
// Lista de despesas atrás da flag `useV2FinanceiroDespesas` (ExpenseController::FLAG_V2); desligada,
// `/expenses` segue na Blade `expense.index`. `?classico=1` força a Blade com a flag ligada.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Financeiro/RUNBOOK-despesas.md
// Âncora de design: prototipo-ui/cowork/Wagner/financeiro-legado.jsx → telaDespesas()
//
// O rodapé soma SÓ a página visível, como a Blade (fnDrawCallback + sum_table_col): mudar isso
// é a decisão D-DESP-TOTAL, do [W]. Drawer e ações da linha chegam na US-MWART-017.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useMemo, type ReactNode } from 'react';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';
import { Inline, Stack } from '@/Components/layout';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';

export interface Despesa {
  id: number; data: string; ref: string; estorno: boolean; recorrente: boolean; gerada_de: string | null;
  categoria: string | null; subcategoria: string | null; local: string | null; status: 'paid' | 'due' | 'partial' | string;
  imposto: string | null; total: number; devido: number; para: string; contato: string | null; nota: string | null;
  criado_por: string; documento: string | null;
}
type Lista = Record<string, string>;
interface Opcoes { locais: Lista; usuarios: Lista; contatos: Lista; categorias: Lista; subcategorias: Lista }
type Filtros = Record<'location_id' | 'expense_for' | 'contact_id' | 'expense_category_id' | 'expense_sub_category_id' | 'payment_status' | 'start_date' | 'end_date', string>;
interface Props { filtros: Filtros; opcoes?: Opcoes; despesas?: PaginatorShape<Despesa>; pode: { add: boolean; edit: boolean; delete: boolean } }

/** Sentinela do Radix Select para "todos" (valor vazio não é permitido em SelectItem). */
const TODOS = '__todos';
const STATUS: Lista = { paid: 'Pago', due: 'Devido', partial: 'Parcial' };
const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);

function filtrar(filtros: Filtros, k: keyof Filtros, v: string) {
  router.get('/expenses', { ...filtros, [k]: v === TODOS ? '' : v }, {
    only: ['filtros', 'despesas'], preserveState: true, preserveScroll: true, replace: true,
  });
}

function Filtro({ rotulo, k, filtros, opcoes }: { rotulo: string; k: keyof Filtros; filtros: Filtros; opcoes: Lista }) {
  return (
    <Stack gap={1} className="min-w-44">
      <label htmlFor={`desp-${k}`} className="text-xs text-muted-foreground">{rotulo}</label>
      <Select value={filtros[k] || TODOS} onValueChange={(v) => filtrar(filtros, k, v)}>
        <SelectTrigger id={`desp-${k}`}><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value={TODOS}>Todos</SelectItem>
          {Object.entries(opcoes).filter(([id]) => id).map(([id, nome]) => <SelectItem key={id} value={id}>{nome}</SelectItem>)}
        </SelectContent>
      </Select>
    </Stack>
  );
}

const colunas: ColumnDef<Despesa>[] = [
  { accessorKey: 'data', header: 'Data' },
  {
    accessorKey: 'ref', header: 'Referência',
    cell: ({ row: { original: d } }) => (
      <Inline gap={1}>
        <span className="font-mono">{d.ref}</span>
        {d.recorrente && <Badge variant="outline">Recorrente</Badge>}
        {d.gerada_de && <Badge variant="outline" title={`Gerada de ${d.gerada_de}`}>Gerada</Badge>}
        {d.estorno && <Badge variant="secondary">Estorno</Badge>}
      </Inline>
    ),
  },
  { accessorKey: 'categoria', header: 'Categoria' },
  { accessorKey: 'subcategoria', header: 'Subcategoria' },
  { accessorKey: 'local', header: 'Local' },
  { accessorKey: 'status', header: 'Pagamento', cell: ({ getValue }) => <Badge variant="outline">{STATUS[getValue<string>()] ?? getValue<string>()}</Badge> },
  { accessorKey: 'imposto', header: 'Imposto' },
  { accessorKey: 'total', header: 'Total', meta: { align: 'right', mono: true }, cell: ({ getValue }) => brl(getValue<number>()) },
  { accessorKey: 'devido', header: 'Devido', meta: { align: 'right', mono: true }, cell: ({ getValue }) => brl(getValue<number>()) },
  { accessorKey: 'para', header: 'Despesa para' },
  { accessorKey: 'contato', header: 'Contato' },
  { accessorKey: 'nota', header: 'Nota' },
  { accessorKey: 'criado_por', header: 'Criado por' },
];

function DespesasIndex({ filtros, opcoes, despesas }: Props) {
  const linhas = useMemo(() => despesas?.data ?? [], [despesas]);
  // Rodapé = soma das linhas desta página, com o sinal que o servidor já aplicou (estorno negativo).
  const rodape = useMemo(() => ({
    total: linhas.reduce((a, d) => a + d.total, 0),
    devido: linhas.reduce((a, d) => a + d.devido, 0),
    status: Object.entries(linhas.reduce<Record<string, number>>((a, d) => ({ ...a, [d.status]: (a[d.status] ?? 0) + 1 }), {}))
      .map(([s, n]) => `${STATUS[s] ?? s}: ${n}`).join(' · '),
  }), [linhas]);

  return (
    <div className="fin-curadoria">
      <div data-contract="cabecalho">
        <PageHeader title="Despesas" subtitle={<>Lançamentos de despesa e estorno, com o que falta pagar</>}>
          <Inline className="ml-auto flex-shrink-0"><FinanceiroSubNav active="despesas" hidePrimary /></Inline>
        </PageHeader>
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Deferred data="opcoes" fallback={<p className="text-sm text-muted-foreground">Carregando filtros…</p>}>
          <Inline data-contract="filtros" wrap gap={3}>
            {opcoes && <>
              <Filtro rotulo="Local" k="location_id" filtros={filtros} opcoes={opcoes.locais} />
              <Filtro rotulo="Despesa para" k="expense_for" filtros={filtros} opcoes={opcoes.usuarios} />
              <Filtro rotulo="Contato" k="contact_id" filtros={filtros} opcoes={opcoes.contatos} />
              <Filtro rotulo="Categoria" k="expense_category_id" filtros={filtros} opcoes={opcoes.categorias} />
              <Filtro rotulo="Subcategoria" k="expense_sub_category_id" filtros={filtros} opcoes={opcoes.subcategorias} />
              <Filtro rotulo="Pagamento" k="payment_status" filtros={filtros} opcoes={STATUS} />
            </>}
            {(['start_date', 'end_date'] as const).map((k) => (
              <Stack key={k} gap={1}>
                <label htmlFor={`desp-${k}`} className="text-xs text-muted-foreground">{k === 'start_date' ? 'De' : 'Até'}</label>
                <Input id={`desp-${k}`} type="date" value={filtros[k]} onChange={(e) => e.target.value && filtrar(filtros, k, e.target.value)} />
              </Stack>
            ))}
          </Inline>
        </Deferred>

        <Deferred data="despesas" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando despesas…</p>}>
          <Stack gap={2} data-contract="lista">
            <DataTable columns={colunas} data={linhas} pagination={despesas} caption="Despesas do período"
              rowKey={(d) => d.id} emptyMessage="Nenhuma despesa no período com esses filtros." />
            <Inline data-contract="rodape" gap={4} justify="end" className="text-sm">
              <span>{rodape.status || '—'}</span>
              <span>Total desta página: <b className="font-mono">{brl(rodape.total)}</b></span>
              <span>Devido desta página: <b className="font-mono">{brl(rodape.devido)}</b></span>
            </Inline>
          </Stack>
        </Deferred>
      </Stack>
    </div>
  );
}

DespesasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default DespesasIndex;
