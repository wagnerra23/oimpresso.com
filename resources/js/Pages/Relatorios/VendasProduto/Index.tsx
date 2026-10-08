// Relatório Vendas por produto (product_sell_report, aba "Detalhado") · charter/casos ao lado.
// Não calcula valor: quantidade, preços, desconto, imposto, subtotal e rodapé chegam prontos do controller
// (consultaVendasPorProduto, a mesma consulta do DataTable da Blade), 25 por página no servidor.
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
  produto: string; sku: string; cliente: string; contato: string; venda: string; data: string;
  quantidade: number; unidade: string; preco_unitario: number; desconto_tipo: string; desconto: number;
  imposto: number; imposto_nome: string; preco_com_imposto: number; subtotal: number; pagamento: string; combo_filho: boolean;
};
type Rodape = {
  subtotal: number;
  por_unidade: { unidade: string; quantidade: number }[];
  por_imposto: { imposto: string; valor: number }[];
};
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

function VendasProdutoIndex({ linhas, rodape, paginacao, filtros, clientes, grupos, locais, categorias, marcas, moeda }: Props) {
  const dinheiro = (v: number) => `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;
  const qtd = (v: number, unidade: string) => `${formatDecimalPtBR(Number(v), 2)} ${unidade}`.trim();
  // Como a coluna da Blade: percentual com "%", fixo só o número, sem desconto em branco.
  const desconto = (l: Linha) =>
    l.desconto_tipo === 'percentage' ? `${formatDecimalPtBR(Number(l.desconto), 2)} %`
      : l.desconto_tipo === 'fixed' ? formatDecimalPtBR(Number(l.desconto), 2) : '';

  const navegar = (mudanca: Partial<Filtros> & { page?: number }) =>
    router.get('/reports/product-sell-report', { tela: 'nova', ...filtros, page: 1, ...mudanca }, {
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
        <PageHeader title="Vendas por produto" subtitle="Quanto de cada produto saiu, para qual cliente e por quanto" />
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
            <caption className="sr-only">Vendas por produto, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className={`${th} text-left`}>Produto</th>
                <th scope="col" className={`${th} text-left`}>SKU</th>
                <th scope="col" className={`${th} text-left`}>Cliente</th>
                <th scope="col" className={`${th} text-left`}>Venda</th>
                <th scope="col" className={`${th} text-left`}>Data</th>
                <th scope="col" className={`${th} text-right`}>Qtd.</th>
                <th scope="col" className={`${th} text-right whitespace-nowrap`}>Preço unit.</th>
                <th scope="col" className={`${th} text-right`}>Desconto</th>
                <th scope="col" className={`${th} text-right`}>Imposto</th>
                <th scope="col" className={`${th} text-right whitespace-nowrap`}>Preço c/ imposto</th>
                <th scope="col" className={`${th} text-right`}>Subtotal</th>
                <th scope="col" className={`${th} text-left`}>Pagamento</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={12} className="px-3 py-6 text-center text-muted-foreground">Nenhuma venda no período.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.venda}-${l.sku}-${i}`} className={l.combo_filho ? 'border-t text-muted-foreground' : 'border-t'}>
                  <td className="px-3 py-2">{l.combo_filho ? `↳ ${l.produto}` : l.produto}</td>
                  <td className="px-3 py-2 font-mono text-xs">{l.sku}</td>
                  <td className="px-3 py-2">{l.cliente}</td>
                  <td className="px-3 py-2 font-mono text-xs">{l.venda}</td>
                  <td className="px-3 py-2 whitespace-nowrap">{l.data}</td>
                  <td className={num}>{qtd(l.quantidade, l.unidade)}</td>
                  <td className={num}>{dinheiro(l.preco_unitario)}</td>
                  <td className={num}>{desconto(l)}</td>
                  <td className={num}>{dinheiro(l.imposto)}{l.imposto_nome ? <small className="block">({l.imposto_nome})</small> : null}</td>
                  <td className={num}>{dinheiro(l.preco_com_imposto)}</td>
                  <td className={num}>{dinheiro(l.subtotal)}</td>
                  <td className="px-3 py-2">{l.pagamento}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t bg-muted font-semibold">
              <tr>
                <th scope="row" colSpan={5} className="px-3 py-2 text-left">Total desta página</th>
                <td className={num}>{rodape.por_unidade.map(r => qtd(r.quantidade, r.unidade)).join(', ') || '0'}</td>
                <td colSpan={2} />
                <td className={num}>{rodape.por_imposto.map(r => `${r.imposto}: ${dinheiro(r.valor)}`).join(', ')}</td>
                <td />
                <td className={num} data-testid="vpr-rodape-subtotal">{dinheiro(rodape.subtotal)}</td>
                <td />
              </tr>
            </tfoot>
          </table>
        </div>

        <Paginacao info={paginacao} irPara={p => navegar({ page: p })} />
      </Stack>
    </>
  );
}

VendasProdutoIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default VendasProdutoIndex;
