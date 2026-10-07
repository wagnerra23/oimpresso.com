// Relatório de Despesas (expense_report) · charter/casos ao lado.
// Não calcula valor: linhas e total vêm do ReportController::getExpenseReport, da mesma consulta
// que a Blade recebe. Desenho: relatorios-data.jsx `expense_report` (tabela categoria × total).
import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Inline, Stack } from '@/Components/layout';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';

type Opcao = { id: number; nome: string };
type Filtros = { location_id: string; category: string; start_date: string; end_date: string };
type Props = {
  linhas: { categoria: string | null; total: number }[];
  total: number;
  filtros: Filtros;
  locais: Opcao[];
  categorias: Opcao[];
  moeda: { simbolo: string; casas: number };
};

const TODOS = 'todos';

function DespesasIndex({ linhas, total, filtros, locais, categorias, moeda }: Props) {
  const dinheiro = (v: number) => `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;

  const filtrar = (mudanca: Partial<Filtros>) =>
    router.get('/reports/expense-report', { tela: 'nova', ...filtros, ...mudanca }, {
      preserveState: true, preserveScroll: true, replace: true, only: ['linhas', 'total', 'filtros'],
    });

  /** '' = todos, como o select da Blade; o sentinela só existe porque o Radix não aceita value vazio. */
  const filtro = (rotulo: string, todos: string, opcoes: Opcao[], valor: string, campo: 'location_id' | 'category') => (
    <div className="w-60">
      <Select value={valor || TODOS} onValueChange={v => filtrar({ [campo]: v === TODOS ? '' : v })}>
        <SelectTrigger aria-label={rotulo}><SelectValue /></SelectTrigger>
        <SelectContent>
          <SafeSelectItem value={TODOS}>{todos}</SafeSelectItem>
          {opcoes.filter(o => o.id).map(o => <SafeSelectItem key={o.id} value={String(o.id)}>{o.nome}</SafeSelectItem>)}
        </SelectContent>
      </Select>
    </div>
  );

  return (
    <>
      <div data-contract="header">
        <PageHeader title="Despesas" subtitle="Despesa somada por categoria no período" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          {filtro('Local', 'Todos os locais', locais, filtros.location_id, 'location_id')}
          {filtro('Categoria de despesa', 'Todas as categorias', categorias, filtros.category, 'category')}
          <div className="w-40"><Input type="date" aria-label="Início" value={filtros.start_date} onChange={e => e.target.value && filtrar({ start_date: e.target.value })} /></div>
          <div className="w-40"><Input type="date" aria-label="Fim" value={filtros.end_date} onChange={e => e.target.value && filtrar({ end_date: e.target.value })} /></div>
        </Inline>

        <div data-contract="tabela" className="overflow-hidden rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">Despesas por categoria no período</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className="px-4 py-2 text-left font-semibold">Categoria de despesa</th>
                <th scope="col" className="px-4 py-2 text-right font-semibold">Total da despesa</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={2} className="px-4 py-6 text-center text-muted-foreground">Nenhuma despesa no período.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.categoria ?? 'outros'}-${i}`} className="border-t">
                  <td className="px-4 py-2">{l.categoria || 'Outros'}</td>
                  <td className="px-4 py-2 text-right font-mono tabular-nums">{dinheiro(l.total)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t bg-muted font-semibold">
              <tr>
                <th scope="row" className="px-4 py-2 text-left">Total</th>
                <td className="px-4 py-2 text-right font-mono tabular-nums" data-testid="desp-total">{dinheiro(total)}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </Stack>
    </>
  );
}

DespesasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default DespesasIndex;
