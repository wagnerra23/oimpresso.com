// Relatório Vendas por produto, aba "Detalhado com compra" · charter/casos ao lado.
// Não calcula nada: as linhas chegam prontas do controller (consultaVendasComCompra, a mesma consulta do
// DataTable da Blade), 25 por página no servidor. A Blade não tem rodapé nesta aba.
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
type Filtros = {
  customer_id: string; customer_group_id: string; location_id: string; category_id: string; brand_id: string;
  start_date: string; end_date: string;
};
type CampoSelect = 'customer_id' | 'customer_group_id' | 'location_id' | 'category_id' | 'brand_id';
type Linha = {
  produto: string; sku: string; cliente: string; venda: string; data: string; compra: string;
  estoque_inicial: boolean; lote: string; fornecedor: string; quantidade: number; unidade: string;
};
type Props = {
  linhas: Linha[];
  paginacao: PaginacaoInfo;
  filtros: Filtros;
  mostra_lote: boolean;
  clientes: Opcao[];
  grupos: Opcao[];
  locais: Opcao[];
  categorias: Opcao[];
  marcas: Opcao[];
};

const TODOS = 'todos';

function VendasComCompraIndex({ linhas, paginacao, filtros, mostra_lote, clientes, grupos, locais, categorias, marcas }: Props) {
  const navegar = (mudanca: Partial<Filtros> & { page?: number }) =>
    router.get('/reports/product-sell-report-with-purchase', { tela: 'nova', ...filtros, page: 1, ...mudanca }, {
      preserveState: true, preserveScroll: true, replace: true, only: ['linhas', 'paginacao', 'filtros'],
    });

  /** '' = todos, como o select da Blade; o sentinela só existe porque o Radix não aceita value vazio. */
  const filtro = (rotulo: string, todos: string, opcoes: Opcao[], campo: CampoSelect) => (
    <div className="w-52">
      <Select value={filtros[campo] || TODOS} onValueChange={v => navegar({ [campo]: v === TODOS ? '' : v })}>
        <SelectTrigger aria-label={rotulo}><SelectValue /></SelectTrigger>
        <SelectContent>
          <SafeSelectItem value={TODOS}>{todos}</SafeSelectItem>
          {opcoes.filter(o => o.id).map(o => <SafeSelectItem key={o.id} value={String(o.id)}>{o.nome}</SafeSelectItem>)}
        </SelectContent>
      </Select>
    </div>
  );

  const th = 'px-3 py-2 text-left font-semibold';
  const colunas = mostra_lote ? 9 : 8;

  return (
    <>
      <div data-contract="header">
        <PageHeader title="Vendas por produto — com compra" subtitle="De qual compra saiu cada item vendido" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          {filtro('Cliente', 'Todos os clientes', clientes, 'customer_id')}
          {filtro('Grupo de clientes', 'Todos os grupos', grupos, 'customer_group_id')}
          {filtro('Local', 'Todos os locais', locais, 'location_id')}
          {filtro('Categoria', 'Todas as categorias', categorias, 'category_id')}
          {filtro('Marca', 'Todas as marcas', marcas, 'brand_id')}
          <div className="w-40"><Input type="date" aria-label="Início" value={filtros.start_date} onChange={e => navegar({ start_date: e.target.value })} /></div>
          <div className="w-40"><Input type="date" aria-label="Fim" value={filtros.end_date} onChange={e => navegar({ end_date: e.target.value })} /></div>
        </Inline>

        <div data-contract="tabela" className="overflow-x-auto rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">Vendas por produto com compra, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className={th}>Produto</th>
                <th scope="col" className={th}>SKU</th>
                <th scope="col" className={th}>Cliente</th>
                <th scope="col" className={th}>Venda</th>
                <th scope="col" className={th}>Data</th>
                <th scope="col" className={th}>Compra</th>
                {mostra_lote ? <th scope="col" className={th}>Lote</th> : null}
                <th scope="col" className={th}>Fornecedor</th>
                <th scope="col" className="px-3 py-2 text-right font-semibold">Qtd.</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={colunas} className="px-3 py-6 text-center text-muted-foreground">Nenhuma venda no período.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.venda}-${l.sku}-${i}`} className="border-t">
                  <td className="px-3 py-2">{l.produto}</td>
                  <td className="px-3 py-2 font-mono text-xs">{l.sku}</td>
                  <td className="px-3 py-2">{l.cliente}</td>
                  <td className="px-3 py-2 font-mono text-xs">{l.venda}</td>
                  <td className="px-3 py-2 whitespace-nowrap">{l.data}</td>
                  <td className={l.estoque_inicial ? 'px-3 py-2 text-xs italic text-muted-foreground' : 'px-3 py-2 font-mono text-xs'}>{l.compra}</td>
                  {mostra_lote ? <td className="px-3 py-2 font-mono text-xs">{l.lote}</td> : null}
                  <td className="px-3 py-2">{l.fornecedor}</td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{`${formatDecimalPtBR(Number(l.quantidade), 2)} ${l.unidade}`.trim()}</td>
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

VendasComCompraIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default VendasComCompraIndex;
