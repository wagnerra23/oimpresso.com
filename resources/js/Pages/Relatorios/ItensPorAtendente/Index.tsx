// Relatório Equipe de serviço, aba "itens por atendente" · charter/casos ao lado.
// Não calcula valor: desconto, total e rodapé chegam prontos do controller (consultaItensPorAtendente, a mesma
// consulta do DataTable da Blade), 25 por página no servidor.
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
type Filtros = { service_staff_id: string; location_id: string; start_date: string; end_date: string };
type Linha = {
  data: string; venda: string; atendente: string; produto: string; quantidade: number; unidade: string;
  preco: number; desconto: number; imposto: number; preco_com_imposto: number; total: number;
};
type Rodape = {
  por_unidade: { unidade: string; quantidade: number }[];
  preco: number; desconto: number; imposto: number; preco_com_imposto: number; total: number;
};
type Props = {
  linhas: Linha[];
  rodape: Rodape;
  paginacao: PaginacaoInfo;
  filtros: Filtros;
  atendentes: Opcao[];
  locais: Opcao[];
  moeda: { simbolo: string; casas: number };
};

const TODOS = 'todos';

function ItensPorAtendenteIndex({ linhas, rodape, paginacao, filtros, atendentes, locais, moeda }: Props) {
  const dinheiro = (v: number) => `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;
  const qtd = (v: number, unidade: string) => `${formatDecimalPtBR(Number(v), 2)} ${unidade}`.trim();

  const navegar = (mudanca: Partial<Filtros> & { page?: number }) =>
    router.get('/reports/service-staff-line-orders', { tela: 'nova', ...filtros, page: 1, ...mudanca }, {
      preserveState: true, preserveScroll: true, replace: true, only: ['linhas', 'rodape', 'paginacao', 'filtros'],
    });

  /** '' = todos, como o select da Blade; o sentinela só existe porque o Radix não aceita value vazio. */
  const filtro = (rotulo: string, todos: string, opcoes: Opcao[], campo: 'service_staff_id' | 'location_id') => (
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
        <PageHeader title="Equipe de serviço — itens por atendente" subtitle="O que cada atendente vendeu, item a item" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          {filtro('Atendente', 'Todos os atendentes', atendentes, 'service_staff_id')}
          {filtro('Local', 'Todos os locais', locais, 'location_id')}
          <div className="w-40"><Input type="date" aria-label="Início" value={filtros.start_date} onChange={e => navegar({ start_date: e.target.value })} /></div>
          <div className="w-40"><Input type="date" aria-label="Fim" value={filtros.end_date} onChange={e => navegar({ end_date: e.target.value })} /></div>
        </Inline>

        <div data-contract="tabela" className="overflow-x-auto rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">Itens vendidos por atendente, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className={`${th} text-left`}>Data</th>
                <th scope="col" className={`${th} text-left`}>Venda</th>
                <th scope="col" className={`${th} text-left`}>Atendente</th>
                <th scope="col" className={`${th} text-left`}>Produto</th>
                <th scope="col" className={`${th} text-right`}>Qtd.</th>
                <th scope="col" className={`${th} text-right`}>Preço</th>
                <th scope="col" className={`${th} text-right`}>Desconto</th>
                <th scope="col" className={`${th} text-right`}>Imposto</th>
                <th scope="col" className={`${th} text-right whitespace-nowrap`}>Preço c/ imposto</th>
                <th scope="col" className={`${th} text-right`}>Total</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={10} className="px-3 py-6 text-center text-muted-foreground">Nenhum item vendido por atendente no período.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.venda}-${i}`} className="border-t">
                  <td className="px-3 py-2 whitespace-nowrap">{l.data}</td>
                  <td className="px-3 py-2 font-mono text-xs">{l.venda}</td>
                  <td className="px-3 py-2">{l.atendente}</td>
                  <td className="px-3 py-2">{l.produto}</td>
                  <td className={num}>{qtd(l.quantidade, l.unidade)}</td>
                  <td className={num}>{dinheiro(l.preco)}</td>
                  <td className={num}>{dinheiro(l.desconto)}</td>
                  <td className={num}>{dinheiro(l.imposto)}</td>
                  <td className={num}>{dinheiro(l.preco_com_imposto)}</td>
                  <td className={num}>{dinheiro(l.total)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t bg-muted font-semibold">
              <tr>
                <th scope="row" colSpan={4} className="px-3 py-2 text-left">Total desta página</th>
                <td className={num}>{rodape.por_unidade.map(r => qtd(r.quantidade, r.unidade)).join(', ') || '0'}</td>
                <td className={num}>{dinheiro(rodape.preco)}</td>
                <td className={num}>{dinheiro(rodape.desconto)}</td>
                <td className={num}>{dinheiro(rodape.imposto)}</td>
                <td className={num}>{dinheiro(rodape.preco_com_imposto)}</td>
                <td className={num} data-testid="ria-rodape-total">{dinheiro(rodape.total)}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <Paginacao info={paginacao} irPara={p => navegar({ page: p })} />
      </Stack>
    </>
  );
}

ItensPorAtendenteIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ItensPorAtendenteIndex;
