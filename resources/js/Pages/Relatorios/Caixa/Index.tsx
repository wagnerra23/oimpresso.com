// Relatório de Caixa (register_report) · charter/casos ao lado.
// Não calcula valor: valores por forma, total do caixa (ReportController::totalDoCaixa, a mesma conta da
// coluna da Blade) e rodapé da página chegam prontos; datas já formatadas pelo format_date da Blade.
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
type Forma = { chave: string; rotulo: string };
type Filtros = { user_id: string; status: string; start_date: string; end_date: string };
type Linha = {
  id: number; aberto_em: string; fechado_em: string; local: string; usuario: string; aberto: boolean;
  comprovantes_cartao: number; cheques: number; valores: Record<string, number>; total: number;
};
type Props = {
  linhas: Linha[];
  rodape: Record<string, number>;
  formas: Forma[];
  paginacao: PaginacaoInfo;
  filtros: Filtros;
  usuarios: Opcao[];
  moeda: { simbolo: string; casas: number };
};

const TODOS = 'todos';
const STATUS: Opcao[] = [{ id: 'open', nome: 'Aberto' }, { id: 'close', nome: 'Fechado' }];

function CaixaIndex({ linhas, rodape, formas, paginacao, filtros, usuarios, moeda }: Props) {
  const dinheiro = (v: number) => `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;

  const navegar = (mudanca: Partial<Filtros> & { page?: number }) =>
    router.get('/reports/register-report', { tela: 'nova', ...filtros, page: 1, ...mudanca }, {
      preserveState: true, preserveScroll: true, replace: true, only: ['linhas', 'rodape', 'paginacao', 'filtros'],
    });

  /** '' = todos, como o select da Blade; o sentinela só existe porque o Radix não aceita value vazio. */
  const filtro = (rotulo: string, todos: string, opcoes: Opcao[], campo: 'user_id' | 'status') => (
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

  // Cartão e cheque mostram também a quantidade, como a Blade: "valor (n)".
  const celula = (l: Linha, chave: string) => {
    const extra = chave === 'card' ? l.comprovantes_cartao : chave === 'cheque' ? l.cheques : null;
    return <>{dinheiro(l.valores[chave] ?? 0)}{extra !== null && <span className="text-muted-foreground"> ({extra})</span>}</>;
  };

  return (
    <>
      <div data-contract="header">
        <PageHeader title="Caixa (registro)" subtitle="Abertura e fechamento de caixa por operador, com o total por forma de recebimento" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          {filtro('Usuário', 'Todos os usuários', usuarios, 'user_id')}
          {filtro('Status', 'Todos', STATUS, 'status')}
          <div className="w-40"><Input type="date" aria-label="Início" value={filtros.start_date} onChange={e => navegar({ start_date: e.target.value })} /></div>
          <div className="w-40"><Input type="date" aria-label="Fim" value={filtros.end_date} onChange={e => navegar({ end_date: e.target.value })} /></div>
        </Inline>

        <div data-contract="tabela" className="overflow-x-auto rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">Caixas, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className="px-3 py-2 text-left font-semibold">Abertura</th>
                <th scope="col" className="px-3 py-2 text-left font-semibold">Fechamento</th>
                <th scope="col" className="px-3 py-2 text-left font-semibold">Local</th>
                <th scope="col" className="px-3 py-2 text-left font-semibold">Usuário</th>
                {formas.map(f => <th key={f.chave} scope="col" className="px-3 py-2 text-right font-semibold whitespace-nowrap">{f.rotulo}</th>)}
                <th scope="col" className="px-3 py-2 text-right font-semibold">Total</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={5 + formas.length} className="px-3 py-6 text-center text-muted-foreground">Nenhum caixa no período.</td></tr>
              ) : linhas.map(l => (
                <tr key={l.id} className="border-t">
                  <td className="px-3 py-2 whitespace-nowrap">{l.aberto_em}</td>
                  <td className="px-3 py-2 whitespace-nowrap">{l.aberto ? <span className="text-muted-foreground">aberto</span> : l.fechado_em}</td>
                  <td className="px-3 py-2">{l.local}</td>
                  <td className="px-3 py-2">{l.usuario}</td>
                  {formas.map(f => <td key={f.chave} className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{celula(l, f.chave)}</td>)}
                  <td className="px-3 py-2 text-right font-mono font-semibold tabular-nums whitespace-nowrap">{dinheiro(l.total)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t bg-muted font-semibold">
              <tr>
                <th scope="row" colSpan={4} className="px-3 py-2 text-left">Total desta página</th>
                {formas.map(f => <td key={f.chave} className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{dinheiro(rodape[f.chave] ?? 0)}</td>)}
                <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap" data-testid="cx-total-pagina">{dinheiro(rodape.total ?? 0)}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <Paginacao info={paginacao} irPara={p => navegar({ page: p })} />
      </Stack>
    </>
  );
}

CaixaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default CaixaIndex;
