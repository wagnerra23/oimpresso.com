// Relatório por mesa (table_report) · charter/casos ao lado.
// Não calcula valor: o total de cada mesa chega pronto do controller (consultaMesas, a mesma consulta do DataTable
// da Blade), 25 por página no servidor. A Blade não tem rodapé.
import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Inline, Stack } from '@/Components/layout';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';
import { Paginacao, type PaginacaoInfo } from '../_shared/Paginacao';

type Opcao = { id: number; nome: string };
type Filtros = { location_id: string; start_date: string; end_date: string };
type Props = {
  linhas: { mesa: string; total: number }[];
  paginacao: PaginacaoInfo;
  filtros: Filtros;
  locais: Opcao[];
  moeda: { simbolo: string; casas: number };
};

const TODOS = 'todos';

function MesasIndex({ linhas, paginacao, filtros, locais, moeda }: Props) {
  const dinheiro = (v: number) => `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;

  const navegar = (mudanca: Partial<Filtros> & { page?: number }) =>
    router.get('/reports/table-report', { tela: 'nova', ...filtros, page: 1, ...mudanca }, {
      preserveState: true, preserveScroll: true, replace: true, only: ['linhas', 'paginacao', 'filtros'],
    });

  return (
    <>
      <div data-contract="header">
        <PageHeader title="Relatório por mesa" subtitle="Quanto cada mesa vendeu no período" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          <div className="w-52">
            <Select value={filtros.location_id || TODOS} onValueChange={v => navegar({ location_id: v === TODOS ? '' : v })}>
              <SelectTrigger aria-label="Local"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SafeSelectItem value={TODOS}>Todos os locais</SafeSelectItem>
                {locais.filter(o => o.id).map(o => <SafeSelectItem key={o.id} value={String(o.id)}>{o.nome}</SafeSelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="w-40"><Input type="date" aria-label="Início" value={filtros.start_date} onChange={e => navegar({ start_date: e.target.value })} /></div>
          <div className="w-40"><Input type="date" aria-label="Fim" value={filtros.end_date} onChange={e => navegar({ end_date: e.target.value })} /></div>
        </Inline>

        <div data-contract="tabela" className="overflow-x-auto rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">Total vendido por mesa, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className="px-3 py-2 text-left font-semibold">Mesa</th>
                <th scope="col" className="px-3 py-2 text-right font-semibold">Total vendido</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={2} className="px-3 py-6 text-center text-muted-foreground">Nenhuma venda em mesa no período.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.mesa}-${i}`} className="border-t">
                  <td className="px-3 py-2">{l.mesa}</td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{dinheiro(l.total)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <Paginacao info={paginacao} irPara={p => navegar({ page: p })} />
      </Stack>
    </>
  );
}

MesasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default MesasIndex;
