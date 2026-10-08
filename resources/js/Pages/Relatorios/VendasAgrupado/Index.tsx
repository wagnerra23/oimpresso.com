// Relatório Vendas por produto, aba "Agrupado" (variação × dia) · charter/casos ao lado.
// Não calcula valor: quantidade, estoque, subtotal e rodapé chegam prontos do controller (consultaVendasAgrupado,
// a mesma consulta do DataTable da Blade), 25 por página no servidor.
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
  produto: string; sku: string; data: string; estoque_atual: number | null;
  quantidade: number; unidade: string; subtotal: number; no_rodape: boolean;
};
type Rodape = { subtotal: number; por_unidade: { unidade: string; quantidade: number }[] };
type Props = {
  linhas: Linha[];
  rodape: Rodape;
  paginacao: PaginacaoInfo;
  filtros: Filtros;
  clientes: Opcao[];
  grupos: Opcao[];
  locais: Opcao[];
  categorias: Opcao[];
  marcas: Opcao[];
  moeda: { simbolo: string; casas: number };
};

const TODOS = 'todos';

function VendasAgrupadoIndex({ linhas, rodape, paginacao, filtros, clientes, grupos, locais, categorias, marcas, moeda }: Props) {
  const dinheiro = (v: number) => `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;
  const qtd = (v: number, unidade: string) => `${formatDecimalPtBR(Number(v), 2)} ${unidade}`.trim();

  const navegar = (mudanca: Partial<Filtros> & { page?: number }) =>
    router.get('/reports/product-sell-grouped-report', { tela: 'nova', ...filtros, page: 1, ...mudanca }, {
      preserveState: true, preserveScroll: true, replace: true, only: ['linhas', 'rodape', 'paginacao', 'filtros'],
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

  const th = 'px-3 py-2 font-semibold';
  const num = 'px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap';

  return (
    <>
      <div data-contract="header">
        <PageHeader title="Vendas por produto — agrupado" subtitle="Quanto de cada produto saiu por dia" />
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
            <caption className="sr-only">Vendas por produto agrupadas por dia, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className={`${th} text-left`}>Produto</th>
                <th scope="col" className={`${th} text-left`}>SKU</th>
                <th scope="col" className={`${th} text-left`}>Data</th>
                <th scope="col" className={`${th} text-right whitespace-nowrap`}>Estoque atual</th>
                <th scope="col" className={`${th} text-right whitespace-nowrap`}>Qtd. vendida</th>
                <th scope="col" className={`${th} text-right`}>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={6} className="px-3 py-6 text-center text-muted-foreground">Nenhuma venda no período.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.sku}-${l.data}-${i}`} className="border-t">
                  <td className="px-3 py-2">{l.produto}</td>
                  <td className="px-3 py-2 font-mono text-xs">{l.sku}</td>
                  <td className="px-3 py-2 whitespace-nowrap">{l.data}</td>
                  <td className={num}>{l.estoque_atual === null ? '' : qtd(l.estoque_atual, l.unidade)}</td>
                  <td className={num}>{qtd(l.quantidade, l.unidade)}</td>
                  <td className={num}>{dinheiro(l.subtotal)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t bg-muted font-semibold">
              <tr>
                <th scope="row" colSpan={4} className="px-3 py-2 text-left">Total desta página</th>
                <td className={num}>{rodape.por_unidade.map(r => qtd(r.quantidade, r.unidade)).join(', ') || '0'}</td>
                <td className={num} data-testid="vpa-rodape-subtotal">{dinheiro(rodape.subtotal)}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <Paginacao info={paginacao} irPara={p => navegar({ page: p })} />
      </Stack>
    </>
  );
}

VendasAgrupadoIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default VendasAgrupadoIndex;
