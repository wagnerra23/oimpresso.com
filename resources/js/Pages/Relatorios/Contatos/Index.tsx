// Relatório Clientes e fornecedores (contact) · charter/casos ao lado.
// Não calcula valor: colunas, devido (ReportController::devidoDoContato, a mesma conta da Blade) e rodapé
// da página chegam prontos do controller (consultaContatos, a mesma consulta do DataTable), 25 por página.
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

type Opcao = { id: number | string; nome: string };
type Filtros = { customer_group_id: string; contact_type: string; location_id: string; contact_id: string; start_date: string; end_date: string };
type CampoSelect = 'customer_group_id' | 'contact_type' | 'location_id' | 'contact_id';
type Coluna = 'compras' | 'devolucoes_compra' | 'vendas' | 'devolucoes_venda' | 'saldo_inicial_devido' | 'devido';
type Linha = { id: number; nome: string } & Record<Coluna, number>;
type Props = {
  linhas: Linha[];
  rodape: Record<Coluna, number>;
  paginacao: PaginacaoInfo;
  filtros: Filtros;
  grupos: Opcao[];
  locais: Opcao[];
  contatos: Opcao[];
  moeda: { simbolo: string; casas: number };
};

const TODOS = 'todos';
const TIPOS: Opcao[] = [{ id: 'customer', nome: 'Clientes' }, { id: 'supplier', nome: 'Fornecedores' }];
const COLUNAS: [Coluna, string][] = [
  ['compras', 'Total de compras'], ['devolucoes_compra', 'Devoluções de compra'], ['vendas', 'Total de vendas'],
  ['devolucoes_venda', 'Devoluções de venda'], ['saldo_inicial_devido', 'Saldo inicial devido'], ['devido', 'Total devido'],
];

function ContatosIndex({ linhas, rodape, paginacao, filtros, grupos, locais, contatos, moeda }: Props) {
  const dinheiro = (v: number) => `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;

  const navegar = (mudanca: Partial<Filtros> & { page?: number }) =>
    router.get('/reports/customer-supplier', { tela: 'nova', ...filtros, page: 1, ...mudanca }, {
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
        <PageHeader title="Clientes e fornecedores" subtitle="Por contato: quanto comprou, devolveu, quanto vendemos e o que ainda está devido" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          {filtro('Grupo de clientes', 'Todos os grupos', grupos, 'customer_group_id')}
          {filtro('Tipo de contato', 'Todos', TIPOS, 'contact_type')}
          {filtro('Local', 'Todos os locais', locais, 'location_id')}
          {filtro('Contato', 'Todos os contatos', contatos, 'contact_id')}
          <div className="w-40"><Input type="date" aria-label="Início" value={filtros.start_date} onChange={e => e.target.value && navegar({ start_date: e.target.value })} /></div>
          <div className="w-40"><Input type="date" aria-label="Fim" value={filtros.end_date} onChange={e => e.target.value && navegar({ end_date: e.target.value })} /></div>
        </Inline>

        <div data-contract="tabela" className="overflow-x-auto rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">Clientes e fornecedores, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className="px-3 py-2 text-left font-semibold">Contato</th>
                {COLUNAS.map(([c, r]) => <th key={c} scope="col" className="px-3 py-2 text-right font-semibold whitespace-nowrap">{r}</th>)}
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={1 + COLUNAS.length} className="px-3 py-6 text-center text-muted-foreground">Nenhum contato com movimento no período.</td></tr>
              ) : linhas.map(l => (
                <tr key={l.id} className="border-t">
                  <td className="px-3 py-2">{l.nome}</td>
                  {COLUNAS.map(([c]) => <td key={c} className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{dinheiro(l[c])}</td>)}
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t bg-muted font-semibold">
              <tr>
                <th scope="row" className="px-3 py-2 text-left">Total desta página</th>
                {COLUNAS.map(([c]) => <td key={c} className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap" data-testid={`ct-rodape-${c}`}>{dinheiro(rodape[c] ?? 0)}</td>)}
              </tr>
            </tfoot>
          </table>
        </div>

        <Paginacao info={paginacao} irPara={p => navegar({ page: p })} />
      </Stack>
    </>
  );
}

ContatosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ContatosIndex;
