// Relatório Compras por produto (product_purchase_report) · charter/casos ao lado.
// Não calcula valor: quantidade, ajustado, preço, subtotal e rodapé chegam prontos do controller
// (consultaComprasPorProduto, a mesma consulta do DataTable da Blade), 25 por página no servidor.
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
type Filtros = { supplier_id: string; location_id: string; brand_id: string; start_date: string; end_date: string };
type CampoSelect = 'supplier_id' | 'location_id' | 'brand_id';
type Linha = {
  produto: string; sku: string; fornecedor: string; compra: string; data: string;
  quantidade: number; ajustado: number; preco_unitario: number; subtotal: number; unidade: string;
};
type Rodape = { subtotal: number; por_unidade: { unidade: string; quantidade: number; ajustado: number }[] };
type Props = {
  linhas: Linha[];
  rodape: Rodape;
  paginacao: PaginacaoInfo;
  filtros: Filtros;
  fornecedores: Opcao[];
  locais: Opcao[];
  marcas: Opcao[];
  moeda: { simbolo: string; casas: number };
};

const TODOS = 'todos';

function ComprasProdutoIndex({ linhas, rodape, paginacao, filtros, fornecedores, locais, marcas, moeda }: Props) {
  const dinheiro = (v: number) => `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;
  const qtd = (v: number, unidade: string) => `${formatDecimalPtBR(Number(v), 2)} ${unidade}`.trim();
  // Como o __sum_stock da Blade: "10,00 UN, 5,00 KG".
  const porUnidade = (campo: 'quantidade' | 'ajustado') => rodape.por_unidade.map(r => qtd(r[campo], r.unidade)).join(', ') || '0';

  const navegar = (mudanca: Partial<Filtros> & { page?: number }) =>
    router.get('/reports/product-purchase-report', { tela: 'nova', ...filtros, page: 1, ...mudanca }, {
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

  return (
    <>
      <div data-contract="header">
        <PageHeader title="Compras por produto" subtitle="Quanto de cada produto entrou, de qual fornecedor e a que preço" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          {filtro('Fornecedor', 'Todos os fornecedores', fornecedores, 'supplier_id')}
          {filtro('Local', 'Todos os locais', locais, 'location_id')}
          {filtro('Marca', 'Todas as marcas', marcas, 'brand_id')}
          <div className="w-40"><Input type="date" aria-label="Início" value={filtros.start_date} onChange={e => navegar({ start_date: e.target.value })} /></div>
          <div className="w-40"><Input type="date" aria-label="Fim" value={filtros.end_date} onChange={e => navegar({ end_date: e.target.value })} /></div>
        </Inline>

        <div data-contract="tabela" className="overflow-x-auto rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">Compras por produto, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className="px-3 py-2 text-left font-semibold">Produto</th>
                <th scope="col" className="px-3 py-2 text-left font-semibold">SKU</th>
                <th scope="col" className="px-3 py-2 text-left font-semibold">Fornecedor</th>
                <th scope="col" className="px-3 py-2 text-left font-semibold">Ref.</th>
                <th scope="col" className="px-3 py-2 text-left font-semibold">Data</th>
                <th scope="col" className="px-3 py-2 text-right font-semibold">Qtd.</th>
                <th scope="col" className="px-3 py-2 text-right font-semibold">Ajustado</th>
                <th scope="col" className="px-3 py-2 text-right font-semibold whitespace-nowrap">Preço unit.</th>
                <th scope="col" className="px-3 py-2 text-right font-semibold">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={9} className="px-3 py-6 text-center text-muted-foreground">Nenhuma compra no período.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.compra}-${l.sku}-${i}`} className="border-t">
                  <td className="px-3 py-2">{l.produto}</td>
                  <td className="px-3 py-2 font-mono text-xs">{l.sku}</td>
                  <td className="px-3 py-2">{l.fornecedor}</td>
                  <td className="px-3 py-2 font-mono text-xs">{l.compra}</td>
                  <td className="px-3 py-2 whitespace-nowrap">{l.data}</td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{qtd(l.quantidade, l.unidade)}</td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{qtd(l.ajustado, l.unidade)}</td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{dinheiro(l.preco_unitario)}</td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{dinheiro(l.subtotal)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t bg-muted font-semibold">
              <tr>
                <th scope="row" colSpan={5} className="px-3 py-2 text-left">Total desta página</th>
                <td className="px-3 py-2 text-right font-mono tabular-nums">{porUnidade('quantidade')}</td>
                <td className="px-3 py-2 text-right font-mono tabular-nums">{porUnidade('ajustado')}</td>
                <td />
                <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap" data-testid="cpr-rodape-subtotal">{dinheiro(rodape.subtotal)}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <Paginacao info={paginacao} irPara={p => navegar({ page: p })} />
      </Stack>
    </>
  );
}

ComprasProdutoIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ComprasProdutoIndex;
