// Relatório Validade de estoque (stock_expiry_report) · charter/casos ao lado.
// Só leitura e sem conta na tela: saldo e rodapé chegam prontos do controller (consultaValidade, a mesma consulta
// do DataTable da Blade), 25 por página no servidor.
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
type Faixa = { valor: string; nome: string };
type Filtros = { location_id: string; category_id: string; brand_id: string; unit_id: string; exp_date_filter: string };
type CampoSelect = 'location_id' | 'category_id' | 'brand_id' | 'unit_id';
type Linha = {
  produto: string; sku: string; local: string; saldo: number; unidade: string;
  lote: string; validade: string; fabricacao: string;
};
type Props = {
  linhas: Linha[];
  rodape: { por_unidade: { unidade: string; saldo: number }[] };
  paginacao: PaginacaoInfo;
  filtros: Filtros;
  locais: Opcao[];
  categorias: Opcao[];
  marcas: Opcao[];
  unidades: Opcao[];
  faixas: Faixa[];
};

const TODOS = 'todos';

function ValidadeIndex({ linhas, rodape, paginacao, filtros, locais, categorias, marcas, unidades, faixas }: Props) {
  const qtd = (v: number, unidade: string) => `${formatDecimalPtBR(Number(v), 2)} ${unidade}`.trim();

  const navegar = (mudanca: Partial<Filtros> & { page?: number }) =>
    router.get('/reports/stock-expiry', { tela: 'nova', ...filtros, page: 1, ...mudanca }, {
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

  const th = 'px-3 py-2 text-left font-semibold';

  return (
    <>
      <div data-contract="header">
        <PageHeader title="Validade de estoque" subtitle="Quanto ainda há de cada lote e quando vence" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          {filtro('Local', 'Todos os locais', locais, 'location_id')}
          {filtro('Categoria', 'Todas as categorias', categorias, 'category_id')}
          {filtro('Marca', 'Todas as marcas', marcas, 'brand_id')}
          {filtro('Unidade', 'Todas as unidades', unidades, 'unit_id')}
          <div className="w-52">
            <Select value={filtros.exp_date_filter || TODOS} onValueChange={v => navegar({ exp_date_filter: v === TODOS ? '' : v })}>
              <SelectTrigger aria-label="Ver estoque"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SafeSelectItem value={TODOS}>Todas as validades</SafeSelectItem>
                {faixas.filter(f => f.valor).map(f => <SafeSelectItem key={f.valor} value={f.valor}>{f.nome}</SafeSelectItem>)}
              </SelectContent>
            </Select>
          </div>
        </Inline>

        <div data-contract="tabela" className="overflow-x-auto rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">Validade de estoque, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className={th}>Produto</th>
                <th scope="col" className={th}>SKU</th>
                <th scope="col" className={th}>Local</th>
                <th scope="col" className="px-3 py-2 text-right font-semibold">Saldo</th>
                <th scope="col" className={th}>Lote</th>
                <th scope="col" className={th}>Validade</th>
                <th scope="col" className={th}>Fabricação</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={7} className="px-3 py-6 text-center text-muted-foreground">Nenhum lote com saldo.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.sku}-${l.lote}-${l.validade}-${i}`} className="border-t">
                  <td className="px-3 py-2">{l.produto}</td>
                  <td className="px-3 py-2 font-mono text-xs">{l.sku}</td>
                  <td className="px-3 py-2">{l.local}</td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{qtd(l.saldo, l.unidade)}</td>
                  <td className="px-3 py-2 font-mono text-xs">{l.lote}</td>
                  <td className="px-3 py-2 whitespace-nowrap">{l.validade}</td>
                  <td className="px-3 py-2 whitespace-nowrap">{l.fabricacao}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t bg-muted font-semibold">
              <tr>
                <th scope="row" colSpan={3} className="px-3 py-2 text-left">Total desta página</th>
                <td className="px-3 py-2 text-right font-mono tabular-nums" data-testid="rvl-rodape-saldo">
                  {rodape.por_unidade.map(r => qtd(r.saldo, r.unidade)).join(', ') || '0'}
                </td>
                <td colSpan={3} />
              </tr>
            </tfoot>
          </table>
        </div>

        <Paginacao info={paginacao} irPara={p => navegar({ page: p })} />
      </Stack>
    </>
  );
}

ValidadeIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ValidadeIndex;
