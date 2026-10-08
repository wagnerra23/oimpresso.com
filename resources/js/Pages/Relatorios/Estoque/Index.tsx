// Relatório de estoque (stock_report) · charter/casos ao lado.
// Não calcula nada: estoque, valores, lucro potencial e rodapé chegam prontos do controller (getProductStockDetails e
// as contas da coluna da Blade), 25 por página no servidor. Preço e valores só vêm com a permissão, como na Blade.
import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Inline, Stack } from '@/Components/layout';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';
import { Paginacao, type PaginacaoInfo } from '../_shared/Paginacao';

type Opcao = { id: number; nome: string };
type Filtros = { location_id: string; category_id: string; brand_id: string; unit_id: string };
type CampoSelect = keyof Filtros;
type Linha = {
  sku: string; produto: string; variacao: string; categoria: string; local: string; preco: number | null;
  estoque: number | null; unidade: string; valor_compra: number | null; valor_venda: number | null;
  lucro_potencial: number | null; vendido: number; transferido: number; ajustado: number; alerta: boolean;
};
type Rodape = {
  estoque: number; vendido: number; transferido: number; ajustado: number;
  valor_compra: number | null; valor_venda: number | null; lucro_potencial: number | null;
};
type Props = {
  linhas: Linha[];
  rodape: Rodape;
  mostra_preco: boolean;
  mostra_valor: boolean;
  paginacao: PaginacaoInfo;
  filtros: Filtros;
  locais: Opcao[];
  categorias: Opcao[];
  marcas: Opcao[];
  unidades: Opcao[];
  moeda: { simbolo: string; casas: number };
};

const TODOS = 'todos';

function EstoqueIndex({ linhas, rodape, mostra_preco, mostra_valor, paginacao, filtros, locais, categorias, marcas, unidades, moeda }: Props) {
  const dinheiro = (v: number | null) => (v === null ? '' : `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`);
  const qtd = (v: number, unidade = '') => `${formatDecimalPtBR(Number(v), 2)} ${unidade}`.trim();

  const navegar = (mudanca: Partial<Filtros> & { page?: number }) =>
    router.get('/reports/stock-report', { tela: 'nova', ...filtros, page: 1, ...mudanca }, {
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
  const colunas = 5 + (mostra_preco ? 1 : 0) + 1 + (mostra_valor ? 3 : 0) + 3;

  return (
    <>
      <div data-contract="header">
        <PageHeader title="Relatório de estoque" subtitle="Quanto há de cada produto em cada local e quanto vale" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          {filtro('Local', 'Todos os locais', locais, 'location_id')}
          {filtro('Categoria', 'Todas as categorias', categorias, 'category_id')}
          {filtro('Marca', 'Todas as marcas', marcas, 'brand_id')}
          {filtro('Unidade', 'Todas as unidades', unidades, 'unit_id')}
        </Inline>

        <div data-contract="tabela" className="overflow-x-auto rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">Estoque por produto e local, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className={`${th} text-left`}>SKU</th>
                <th scope="col" className={`${th} text-left`}>Produto</th>
                <th scope="col" className={`${th} text-left`}>Variação</th>
                <th scope="col" className={`${th} text-left`}>Categoria</th>
                <th scope="col" className={`${th} text-left`}>Local</th>
                {mostra_preco ? <th scope="col" className={`${th} text-right whitespace-nowrap`}>Preço de venda</th> : null}
                <th scope="col" className={`${th} text-right`}>Estoque</th>
                {mostra_valor ? (
                  <>
                    <th scope="col" className={`${th} text-right whitespace-nowrap`}>Valor (compra)</th>
                    <th scope="col" className={`${th} text-right whitespace-nowrap`}>Valor (venda)</th>
                    <th scope="col" className={`${th} text-right whitespace-nowrap`}>Lucro potencial</th>
                  </>
                ) : null}
                <th scope="col" className={`${th} text-right`}>Vendido</th>
                <th scope="col" className={`${th} text-right`}>Transferido</th>
                <th scope="col" className={`${th} text-right`}>Ajustado</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={colunas} className="px-3 py-6 text-center text-muted-foreground">Nenhum produto com os filtros escolhidos.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.sku}-${l.local}-${i}`} className={l.alerta ? 'border-t bg-destructive/10' : 'border-t'}>
                  <td className="px-3 py-2 font-mono text-xs">{l.sku}</td>
                  <td className="px-3 py-2">{l.produto}</td>
                  <td className="px-3 py-2">{l.variacao}</td>
                  <td className="px-3 py-2">{l.categoria}</td>
                  <td className="px-3 py-2">{l.local}</td>
                  {mostra_preco ? <td className={num}>{dinheiro(l.preco)}</td> : null}
                  <td className={num}>{l.estoque === null ? '--' : qtd(l.estoque, l.unidade)}</td>
                  {mostra_valor ? (
                    <>
                      <td className={num}>{dinheiro(l.valor_compra)}</td>
                      <td className={num}>{dinheiro(l.valor_venda)}</td>
                      <td className={num}>{dinheiro(l.lucro_potencial)}</td>
                    </>
                  ) : null}
                  <td className={num}>{qtd(l.vendido, l.unidade)}</td>
                  <td className={num}>{qtd(l.transferido, l.unidade)}</td>
                  <td className={num}>{qtd(l.ajustado, l.unidade)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t bg-muted font-semibold">
              <tr>
                <th scope="row" colSpan={5 + (mostra_preco ? 1 : 0)} className="px-3 py-2 text-left">Total desta página</th>
                <td className={num}>{qtd(rodape.estoque)}</td>
                {mostra_valor ? (
                  <>
                    <td className={num}>{dinheiro(rodape.valor_compra)}</td>
                    <td className={num}>{dinheiro(rodape.valor_venda)}</td>
                    <td className={num} data-testid="res-rodape-lucro">{dinheiro(rodape.lucro_potencial)}</td>
                  </>
                ) : null}
                <td className={num}>{qtd(rodape.vendido)}</td>
                <td className={num}>{qtd(rodape.transferido)}</td>
                <td className={num}>{qtd(rodape.ajustado)}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <Paginacao info={paginacao} irPara={p => navegar({ page: p })} />
      </Stack>
    </>
  );
}

EstoqueIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default EstoqueIndex;
