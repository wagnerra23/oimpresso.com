// Relatório de lucro e prejuízo · charter/casos ao lado.
// Não calcula valor: cada número chega pronto do controller (dadosDeLucro e estoquePorPrecoDeVenda, os mesmos do
// partial da Blade), inclusive o CMV, o lucro bruto e o lucro líquido.
import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Grid, Inline, Stack } from '@/Components/layout';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';

type Item = { rotulo: string; valor: number };
type Opcao = { id: number; nome: string };
type Filtros = { location_id: string; start_date: string; end_date: string };
type Props = {
  esquerda: Item[];
  esquerda_modulos: Item[];
  direita: Item[];
  direita_modulos: Item[];
  vendas_por_subtipo: { subtipo: string; valor: number }[];
  resultado: { cmv: number; lucro_bruto: number; lucro_bruto_extras: string[]; lucro_liquido: number };
  filtros: Filtros;
  locais: Opcao[];
  moeda: { simbolo: string; casas: number };
};

const TODOS = 'todos';

function LucroPrejuizoIndex({ esquerda, esquerda_modulos, direita, direita_modulos, vendas_por_subtipo, resultado, filtros, locais, moeda }: Props) {
  const dinheiro = (v: number) => `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;

  const navegar = (mudanca: Partial<Filtros>) =>
    router.get('/reports/profit-loss', { tela: 'nova', ...filtros, ...mudanca }, { preserveState: true, preserveScroll: true, replace: true });

  const tabela = (titulo: string, itens: Item[], extras?: ReactNode) => (
    <div className="rounded-lg border bg-card">
      <table className="w-full text-sm">
        <caption className="sr-only">{titulo}</caption>
        <tbody>
          {itens.map(i => (
            <tr key={i.rotulo} className="border-t first:border-t-0">
              <th scope="row" className="px-4 py-2 text-left font-medium">{i.rotulo}</th>
              <td className="px-4 py-2 text-right font-mono tabular-nums whitespace-nowrap">{dinheiro(i.valor)}</td>
            </tr>
          ))}
          {extras}
        </tbody>
      </table>
    </div>
  );

  return (
    <>
      <div data-contract="header">
        <PageHeader title="Lucro e prejuízo" subtitle="O que entrou, o que saiu e o resultado do período" />
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

        <div data-contract="tabela">
          <Grid fit="md" gap={4}>
            {tabela('Saídas e estoque inicial', [...esquerda, ...esquerda_modulos])}
            {tabela('Entradas e estoque final', [...direita, ...direita_modulos], vendas_por_subtipo.length > 0 ? (
              <tr className="border-t">
                <td colSpan={2} className="px-4 py-2 text-xs text-muted-foreground">
                  Vendas por tipo: {vendas_por_subtipo.map(v => `${dinheiro(v.valor)}${v.subtipo ? ` (${v.subtipo})` : ''}`).join(' · ')}
                </td>
              </tr>
            ) : null)}
          </Grid>
        </div>

        <div data-contract="resultado" className="rounded-lg border bg-card px-4 py-3">
          <Stack gap={2}>
            <p className="text-sm">CMV (custo das mercadorias vendidas): <span className="font-mono tabular-nums font-semibold">{dinheiro(resultado.cmv)}</span></p>
            <p className="text-sm">
              Lucro bruto: <span className="font-mono tabular-nums font-semibold" data-testid="rlp-lucro-bruto">{dinheiro(resultado.lucro_bruto)}</span>
              {resultado.lucro_bruto_extras.length > 0 ? <span className="text-xs text-muted-foreground"> (inclui {resultado.lucro_bruto_extras.join(', ')})</span> : null}
            </p>
            <p className="text-base">Lucro líquido: <span className="font-mono tabular-nums font-semibold" data-testid="rlp-lucro-liquido">{dinheiro(resultado.lucro_liquido)}</span></p>
          </Stack>
        </div>
      </Stack>
    </>
  );
}

LucroPrejuizoIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default LucroPrejuizoIndex;
