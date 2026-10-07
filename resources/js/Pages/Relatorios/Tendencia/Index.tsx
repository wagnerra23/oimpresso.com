// Relatório Produtos em tendência (trending_products) · charter/casos ao lado.
// Não calcula nada: o ranking vem de ProductUtil::getTrendingProducts, a mesma lista que vira o
// gráfico da Blade. Desenho: relatorios-data.jsx `trending_products`.
import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Inline, Stack } from '@/Components/layout';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';

type Opcao = { id: number | string; nome: string };
type Filtros = {
  location_id: string; category: string; brand: string; unit: string; product_type: string;
  limit: string; start_date: string; end_date: string;
};
type CampoSelect = 'location_id' | 'category' | 'brand' | 'unit' | 'product_type';
type Props = {
  linhas: { produto: string; sku: string; unidade: string; vendido: number }[];
  filtros: Filtros;
  locais: Opcao[];
  categorias: Opcao[];
  marcas: Opcao[];
  unidades: Opcao[];
};

const TODOS = 'todos';
// Os mesmos três tipos do select da Blade.
const TIPOS: Opcao[] = [{ id: 'single', nome: 'Simples' }, { id: 'variable', nome: 'Variável' }, { id: 'combo', nome: 'Combo' }];

function TendenciaIndex({ linhas, filtros, locais, categorias, marcas, unidades }: Props) {
  const filtrar = (mudanca: Partial<Filtros>) =>
    router.get('/reports/trending-products', { tela: 'nova', ...filtros, ...mudanca }, {
      preserveState: true, preserveScroll: true, replace: true, only: ['linhas', 'filtros'],
    });

  /** '' = todos, como o select da Blade; o sentinela só existe porque o Radix não aceita value vazio. */
  const filtro = (rotulo: string, todos: string, opcoes: Opcao[], campo: CampoSelect) => (
    <div className="w-52">
      <Select value={filtros[campo] || TODOS} onValueChange={v => filtrar({ [campo]: v === TODOS ? '' : v })}>
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
        <PageHeader title="Produtos em tendência" subtitle="Os produtos que mais saíram no período" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          {filtro('Local', 'Todos os locais', locais, 'location_id')}
          {filtro('Categoria', 'Todas as categorias', categorias, 'category')}
          {filtro('Marca', 'Todas as marcas', marcas, 'brand')}
          {filtro('Unidade', 'Todas as unidades', unidades, 'unit')}
          {filtro('Tipo de produto', 'Todos os tipos', TIPOS, 'product_type')}
          <div className="w-40"><Input type="date" aria-label="Início" value={filtros.start_date} onChange={e => filtrar({ start_date: e.target.value })} /></div>
          <div className="w-40"><Input type="date" aria-label="Fim" value={filtros.end_date} onChange={e => filtrar({ end_date: e.target.value })} /></div>
          <div className="w-28"><Input type="number" min={1} aria-label="Nº de produtos" value={filtros.limit} onChange={e => Number(e.target.value) >= 1 && filtrar({ limit: e.target.value })} /></div>
        </Inline>

        <div data-contract="tabela" className="overflow-hidden rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">Produtos mais vendidos no período</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className="px-4 py-2 text-left font-semibold">Produto</th>
                <th scope="col" className="px-4 py-2 text-left font-semibold">SKU</th>
                <th scope="col" className="px-4 py-2 text-right font-semibold">Total vendido</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={3} className="px-4 py-6 text-center text-muted-foreground">Nenhuma venda no período.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.sku}-${i}`} className="border-t">
                  <td className="px-4 py-2">{l.produto}</td>
                  <td className="px-4 py-2 font-mono text-xs text-muted-foreground">{l.sku}</td>
                  <td className="px-4 py-2 text-right font-mono tabular-nums">{formatDecimalPtBR(l.vendido, 2)} {l.unidade}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Stack>
    </>
  );
}

TendenciaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default TendenciaIndex;
