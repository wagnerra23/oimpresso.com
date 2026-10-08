// Relatório de impostos, abas entrada / saída / despesa · charter/casos ao lado.
// Não calcula imposto: cada coluna de alíquota chega pronta do controller (impostoPorAliquota, a mesma conta da coluna
// do DataTable da Blade), 25 por página no servidor.
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

type Tipo = 'purchase' | 'sell' | 'expense';
type Opcao = { id: number; nome: string };
type Filtros = { location_id: string; contact_id: string; start_date: string; end_date: string };
type Linha = {
  data: string; referencia: string; contato: string; documento: string; total: number; pagamento: string;
  desconto: number; desconto_tipo: string; impostos: Record<string, number>;
};
type Props = {
  tipo: Tipo;
  resumo: { diferenca: number };
  aliquotas: { id: string; nome: string }[];
  linhas: Linha[];
  rodape: { total: number; impostos: Record<string, number> };
  paginacao: PaginacaoInfo;
  filtros: Filtros;
  locais: Opcao[];
  contatos: Opcao[];
  moeda: { simbolo: string; casas: number };
};

const TODOS = 'todos';
const ABAS: { valor: Tipo; nome: string; referencia: string; contato: string }[] = [
  { valor: 'purchase', nome: 'Imposto de entrada (compras)', referencia: 'Ref.', contato: 'Fornecedor' },
  { valor: 'sell', nome: 'Imposto de saída (vendas)', referencia: 'Nº da venda', contato: 'Cliente' },
  { valor: 'expense', nome: 'Imposto de despesas', referencia: 'Ref.', contato: '' },
];

function ImpostosIndex({ tipo, resumo, aliquotas, linhas, rodape, paginacao, filtros, locais, contatos, moeda }: Props) {
  const dinheiro = (v: number) => `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;
  const aba = ABAS.find(a => a.valor === tipo) ?? ABAS[0];
  const comContato = aba.contato !== '';
  // Como a coluna da Blade: percentual com "%", fixo em moeda.
  const desconto = (l: Linha) => (l.desconto_tipo === 'percentage' ? `${formatDecimalPtBR(Number(l.desconto), 2)}%` : dinheiro(l.desconto));

  const navegar = (mudanca: Partial<Filtros> & { page?: number; tipo?: Tipo }) =>
    router.get('/reports/tax-details', { tela: 'nova', tipo, ...filtros, page: 1, ...mudanca }, {
      preserveState: true, preserveScroll: true, replace: true,
    });

  const seletor = (rotulo: string, todos: string, opcoes: Opcao[], campo: 'location_id' | 'contact_id') => (
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
  const colunasFixas = comContato ? 7 : 5;

  return (
    <>
      <div data-contract="header">
        <PageHeader title="Relatório de impostos" subtitle="Imposto por alíquota em compras, vendas e despesas" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          <div className="w-64">
            <Select value={tipo} onValueChange={v => navegar({ tipo: v as Tipo })}>
              <SelectTrigger aria-label="Aba"><SelectValue /></SelectTrigger>
              <SelectContent>
                {ABAS.map(a => <SafeSelectItem key={a.valor} value={a.valor}>{a.nome}</SafeSelectItem>)}
              </SelectContent>
            </Select>
          </div>
          {seletor('Local', 'Todos os locais', locais, 'location_id')}
          {seletor('Contato', 'Todos os contatos', contatos, 'contact_id')}
          <div className="w-40"><Input type="date" aria-label="Início" value={filtros.start_date} onChange={e => navegar({ start_date: e.target.value })} /></div>
          <div className="w-40"><Input type="date" aria-label="Fim" value={filtros.end_date} onChange={e => navegar({ end_date: e.target.value })} /></div>
        </Inline>

        {/* O topo da Blade: imposto de saída menos imposto de entrada (e despesas), calculado no servidor. */}
        <div data-contract="resumo" className="rounded-lg border bg-card px-4 py-3">
          <p className="text-sm text-muted-foreground">Imposto de saída menos imposto de entrada</p>
          <p className="font-mono text-xl font-semibold tabular-nums" data-testid="rim-resumo-diferenca">{dinheiro(resumo.diferenca)}</p>
        </div>

        <div data-contract="tabela" className="overflow-x-auto rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">{aba.nome}, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className={`${th} text-left`}>Data</th>
                <th scope="col" className={`${th} text-left`}>{aba.referencia}</th>
                {comContato ? <th scope="col" className={`${th} text-left`}>{aba.contato}</th> : null}
                <th scope="col" className={`${th} text-left whitespace-nowrap`}>CPF/CNPJ</th>
                <th scope="col" className={`${th} text-right`}>Total</th>
                <th scope="col" className={`${th} text-left`}>Pagamento</th>
                {comContato ? <th scope="col" className={`${th} text-right`}>Desconto</th> : null}
                {aliquotas.map(a => <th key={a.id} scope="col" className={`${th} text-right whitespace-nowrap`}>{a.nome}</th>)}
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={colunasFixas + aliquotas.length} className="px-3 py-6 text-center text-muted-foreground">Nenhuma transação com imposto no período.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.referencia}-${i}`} className="border-t">
                  <td className="px-3 py-2 whitespace-nowrap">{l.data}</td>
                  <td className="px-3 py-2 font-mono text-xs">{l.referencia}</td>
                  {comContato ? <td className="px-3 py-2">{l.contato}</td> : null}
                  <td className="px-3 py-2 font-mono text-xs">{l.documento}</td>
                  <td className={num}>{dinheiro(l.total)}</td>
                  <td className="px-3 py-2">{l.pagamento}</td>
                  {comContato ? <td className={num}>{desconto(l)}</td> : null}
                  {/* Como a Blade: célula vazia quando a alíquota não tem imposto na transação. */}
                  {aliquotas.map(a => <td key={a.id} className={num}>{l.impostos[a.id] > 0 ? dinheiro(l.impostos[a.id]) : ''}</td>)}
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t bg-muted font-semibold">
              <tr>
                <th scope="row" colSpan={comContato ? 4 : 3} className="px-3 py-2 text-left">Total desta página</th>
                <td className={num} data-testid="rim-rodape-total">{dinheiro(rodape.total)}</td>
                <td colSpan={comContato ? 2 : 1} />
                {aliquotas.map(a => <td key={a.id} className={num}>{dinheiro(rodape.impostos[a.id] ?? 0)}</td>)}
              </tr>
            </tfoot>
          </table>
        </div>

        <Paginacao info={paginacao} irPara={p => navegar({ page: p })} />
      </Stack>
    </>
  );
}

ImpostosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ImpostosIndex;
