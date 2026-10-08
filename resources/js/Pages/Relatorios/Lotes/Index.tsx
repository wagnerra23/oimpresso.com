// Relatório de Lotes (lot_report) · charter/casos ao lado.
// Não calcula nada: estoque, vendido, ajustado e o rodapé por unidade chegam prontos do controller
// (consultaLotes, a mesma consulta do DataTable da Blade), 25 por página no servidor.
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
type Linha = {
  sku: string; produto: string; lote: string; validade: string; vencido: boolean;
  estoque: number; vendido: number; ajustado: number; unidade: string;
};
type Rodape = { unidade: string; estoque: number; vendido: number; ajustado: number };
type Props = {
  linhas: Linha[];
  rodape: Rodape[];
  paginacao: PaginacaoInfo;
  filtros: Filtros;
  locais: Opcao[];
  categorias: Opcao[];
  marcas: Opcao[];
  unidades: Opcao[];
};

const TODOS = 'todos';

function LotesIndex({ linhas, rodape, paginacao, filtros, locais, categorias, marcas, unidades }: Props) {
  const qtd = (v: number, unidade: string) => `${formatDecimalPtBR(Number(v), 2)} ${unidade}`.trim();
  // Como o __sum_stock da Blade: "10,00 UN, 5,00 KG".
  const porUnidade = (campo: 'estoque' | 'vendido' | 'ajustado') => rodape.map(r => qtd(r[campo], r.unidade)).join(', ') || '0';

  const navegar = (mudanca: Partial<Filtros> & { page?: number }) =>
    router.get('/reports/lot-report', { tela: 'nova', ...filtros, page: 1, ...mudanca }, {
      preserveState: true, preserveScroll: true, replace: true, only: ['linhas', 'rodape', 'paginacao', 'filtros'],
    });

  /** '' = todos, como o select da Blade; o sentinela só existe porque o Radix não aceita value vazio. */
  const filtro = (rotulo: string, todos: string, opcoes: Opcao[], campo: keyof Filtros) => (
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
        <PageHeader title="Lotes" subtitle="Estoque, vendido e ajustado de cada lote, com a validade" />
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
            <caption className="sr-only">Lotes, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className="px-3 py-2 text-left font-semibold">SKU</th>
                <th scope="col" className="px-3 py-2 text-left font-semibold">Produto</th>
                <th scope="col" className="px-3 py-2 text-left font-semibold">Lote</th>
                <th scope="col" className="px-3 py-2 text-left font-semibold">Validade</th>
                <th scope="col" className="px-3 py-2 text-right font-semibold">Estoque</th>
                <th scope="col" className="px-3 py-2 text-right font-semibold">Vendido</th>
                <th scope="col" className="px-3 py-2 text-right font-semibold">Ajustado</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={7} className="px-3 py-6 text-center text-muted-foreground">Nenhum lote.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.sku}-${l.lote}-${i}`} className="border-t">
                  <td className="px-3 py-2 font-mono text-xs">{l.sku}</td>
                  <td className="px-3 py-2">{l.produto}</td>
                  <td className="px-3 py-2 font-mono text-xs">{l.lote}</td>
                  <td className="px-3 py-2 whitespace-nowrap">
                    {l.validade || '--'}{l.vencido && <span className="ml-2 rounded bg-destructive/10 px-1.5 py-0.5 text-xs text-destructive">vencido</span>}
                  </td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{qtd(l.estoque, l.unidade)}</td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{qtd(l.vendido, l.unidade)}</td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{qtd(l.ajustado, l.unidade)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t bg-muted font-semibold">
              <tr>
                <th scope="row" colSpan={4} className="px-3 py-2 text-left">Total desta página</th>
                <td className="px-3 py-2 text-right font-mono tabular-nums" data-testid="lt-rodape-estoque">{porUnidade('estoque')}</td>
                <td className="px-3 py-2 text-right font-mono tabular-nums">{porUnidade('vendido')}</td>
                <td className="px-3 py-2 text-right font-mono tabular-nums">{porUnidade('ajustado')}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <Paginacao info={paginacao} irPara={p => navegar({ page: p })} />
      </Stack>
    </>
  );
}

LotesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default LotesIndex;
