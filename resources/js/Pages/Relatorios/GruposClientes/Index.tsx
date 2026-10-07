// Relatório Grupos de clientes (customer_group) · charter/casos ao lado.
// Não calcula valor: as linhas vêm de ReportController::consultaGrupoDeClientes, a mesma consulta
// do DataTable da Blade. Desenho: relatorios-data.jsx `customer_group` (grupo × total vendido).
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
type Filtros = { customer_group_id: string; location_id: string; start_date: string; end_date: string };
type Props = {
  linhas: { grupo: string | null; total: number }[];
  filtros: Filtros;
  grupos: Opcao[];
  locais: Opcao[];
  moeda: { simbolo: string; casas: number };
};

const TODOS = 'todos';

function GruposClientesIndex({ linhas, filtros, grupos, locais, moeda }: Props) {
  const dinheiro = (v: number) => `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;

  const filtrar = (mudanca: Partial<Filtros>) =>
    router.get('/reports/customer-group', { tela: 'nova', ...filtros, ...mudanca }, {
      preserveState: true, preserveScroll: true, replace: true, only: ['linhas', 'filtros'],
    });

  /** '' = todos, como o select da Blade; o sentinela só existe porque o Radix não aceita value vazio. */
  const filtro = (rotulo: string, todos: string, opcoes: Opcao[], valor: string, campo: 'customer_group_id' | 'location_id') => (
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
        <PageHeader title="Grupos de clientes" subtitle="Quanto cada grupo de clientes comprou no período" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          {filtro('Grupo de clientes', 'Todos os grupos', grupos, filtros.customer_group_id, 'customer_group_id')}
          {filtro('Local', 'Todos os locais', locais, filtros.location_id, 'location_id')}
          <div className="w-40"><Input type="date" aria-label="Início" value={filtros.start_date} onChange={e => e.target.value && filtrar({ start_date: e.target.value })} /></div>
          <div className="w-40"><Input type="date" aria-label="Fim" value={filtros.end_date} onChange={e => e.target.value && filtrar({ end_date: e.target.value })} /></div>
        </Inline>

        <div data-contract="tabela" className="overflow-hidden rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">Total vendido por grupo de clientes no período</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className="px-4 py-2 text-left font-semibold">Grupo de clientes</th>
                <th scope="col" className="px-4 py-2 text-right font-semibold">Total vendido</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={2} className="px-4 py-6 text-center text-muted-foreground">Nenhuma venda no período.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.grupo ?? 'sem-grupo'}-${i}`} className="border-t">
                  <td className="px-4 py-2">{l.grupo || 'Sem grupo'}</td>
                  <td className="px-4 py-2 text-right font-mono tabular-nums">{dinheiro(l.total)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Stack>
    </>
  );
}

GruposClientesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default GruposClientesIndex;
