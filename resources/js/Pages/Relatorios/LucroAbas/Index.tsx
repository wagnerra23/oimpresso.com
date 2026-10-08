// Abas de lucro (por produto, categoria, marca, local, venda, data, cliente e dia) · charter/casos ao lado.
// Não calcula valor: o lucro de cada linha e o rodapé chegam prontos do controller (consultaLucro e lucroDaLinha, os
// mesmos do DataTable da Blade), 25 por página no servidor.
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

type Aba = 'product' | 'category' | 'brand' | 'location' | 'invoice' | 'date' | 'customer' | 'day';
type Opcao = { id: number; nome: string };
type Filtros = { location_id: string; start_date: string; end_date: string };
type Props = {
  aba: Aba;
  linhas: { rotulo: string; lucro: number }[];
  rodape: { lucro: number };
  paginacao: PaginacaoInfo;
  filtros: Filtros;
  locais: Opcao[];
  moeda: { simbolo: string; casas: number };
};

const TODOS = 'todos';
type AbaLucro = { valor: Aba; nome: string; coluna: string };
// Tupla não-vazia: ABAS[0] é sempre uma aba (o fallback do find não fica 'possivelmente undefined').
const ABAS: [AbaLucro, ...AbaLucro[]] = [
  { valor: 'product', nome: 'Por produto', coluna: 'Produto' },
  { valor: 'category', nome: 'Por categoria', coluna: 'Categoria' },
  { valor: 'brand', nome: 'Por marca', coluna: 'Marca' },
  { valor: 'location', nome: 'Por local', coluna: 'Local' },
  { valor: 'invoice', nome: 'Por venda', coluna: 'Venda' },
  { valor: 'date', nome: 'Por data', coluna: 'Data' },
  { valor: 'customer', nome: 'Por cliente', coluna: 'Cliente' },
  { valor: 'day', nome: 'Por dia da semana', coluna: 'Dia' },
];

function LucroAbasIndex({ aba, linhas, rodape, paginacao, filtros, locais, moeda }: Props) {
  const dinheiro = (v: number) => `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;
  const atual = ABAS.find(a => a.valor === aba) ?? ABAS[0];

  const navegar = (mudanca: Partial<Filtros> & { page?: number; aba?: Aba }) => {
    const { aba: novaAba, ...resto } = mudanca;
    router.get(`/reports/get-profit/${novaAba ?? aba}`, { tela: 'nova', ...filtros, page: 1, ...resto }, {
      preserveState: true, preserveScroll: true, replace: true,
    });
  };

  return (
    <>
      <div data-contract="header">
        <PageHeader title="Lucro bruto por…" subtitle="De onde vem o lucro: produto, categoria, cliente e mais" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          <div className="w-52">
            <Select value={aba} onValueChange={v => navegar({ aba: v as Aba })}>
              <SelectTrigger aria-label="Aba"><SelectValue /></SelectTrigger>
              <SelectContent>
                {ABAS.map(a => <SafeSelectItem key={a.valor} value={a.valor}>{a.nome}</SafeSelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="w-52">
            <Select value={filtros.location_id || TODOS} onValueChange={v => navegar({ location_id: v === TODOS ? '' : v })}>
              <SelectTrigger aria-label="Local"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SafeSelectItem value={TODOS}>Todos os locais</SafeSelectItem>
                {locais.filter(o => o.id).map(o => <SafeSelectItem key={o.id} value={String(o.id)}>{o.nome}</SafeSelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="w-40"><Input type="date" aria-label="Início" value={filtros.start_date} onChange={e => navegar({ start_date: e.target.value })} /></div>
          <div className="w-40"><Input type="date" aria-label="Fim" value={filtros.end_date} onChange={e => navegar({ end_date: e.target.value })} /></div>
        </Inline>

        <div data-contract="tabela" className="overflow-x-auto rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">Lucro bruto {atual.nome.toLowerCase()}, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className="px-3 py-2 text-left font-semibold">{atual.coluna}</th>
                <th scope="col" className="px-3 py-2 text-right font-semibold">Lucro bruto</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={2} className="px-3 py-6 text-center text-muted-foreground">Nenhuma venda no período.</td></tr>
              ) : linhas.map((l, i) => (
                <tr key={`${l.rotulo}-${i}`} className="border-t">
                  <td className="px-3 py-2">{l.rotulo}</td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{dinheiro(l.lucro)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t bg-muted font-semibold">
              <tr>
                <th scope="row" className="px-3 py-2 text-left">Total desta página</th>
                <td className="px-3 py-2 text-right font-mono tabular-nums" data-testid="rla-rodape">{dinheiro(rodape.lucro)}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        {aba !== 'day' ? <Paginacao info={paginacao} irPara={p => navegar({ page: p })} /> : null}
      </Stack>
    </>
  );
}

LucroAbasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default LucroAbasIndex;
