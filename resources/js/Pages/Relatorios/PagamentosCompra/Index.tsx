// Relatório Pagamentos de compra (purchase_payment_report) · charter/casos ao lado.
// Não calcula valor: linhas e total da página vêm do ReportController (consultaPagamentosDeCompra,
// a mesma consulta do DataTable da Blade), 25 por página no servidor.
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
type Filtros = { supplier_id: string; location_id: string; start_date: string; end_date: string };
type CampoSelect = 'supplier_id' | 'location_id';
type Linha = { id: number; ref: string; pago_em: string; valor: number; fornecedor: string; forma: string; compra: string | null };
type Props = {
  linhas: Linha[];
  total_pagina: number;
  paginacao: PaginacaoInfo;
  filtros: Filtros;
  fornecedores: Opcao[];
  locais: Opcao[];
  moeda: { simbolo: string; casas: number };
};

const TODOS = 'todos';

/** "2026-10-07 14:32:00" → "07/10/2026 14:32" (só formata o texto que o servidor mandou). */
function dataHora(v: string) {
  const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(v);
  return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : v;
}

function PagamentosCompraIndex({ linhas, total_pagina, paginacao, filtros, fornecedores, locais, moeda }: Props) {
  const dinheiro = (v: number) => `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;

  const navegar = (mudanca: Partial<Filtros> & { page?: number }) =>
    router.get('/reports/purchase-payment-report', { tela: 'nova', ...filtros, page: 1, ...mudanca }, {
      preserveState: true, preserveScroll: true, replace: true, only: ['linhas', 'total_pagina', 'paginacao', 'filtros'],
    });

  /** '' = todos, como o select da Blade; o sentinela só existe porque o Radix não aceita value vazio. */
  const filtro = (rotulo: string, todos: string, opcoes: Opcao[], campo: CampoSelect) => (
    <div className="w-60">
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
        <PageHeader title="Pagamentos de compra" subtitle="Cada pagamento de compra, com fornecedor, forma e valor" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        {/* Largura no wrapper: `.cw-input { width: 100% }` fica fora de @layer e vence o `w-*` no próprio controle. */}
        <Inline data-contract="filtros" wrap gap={2}>
          {filtro('Fornecedor', 'Todos os fornecedores', fornecedores, 'supplier_id')}
          {filtro('Local', 'Todos os locais', locais, 'location_id')}
          <div className="w-40"><Input type="date" aria-label="Início" value={filtros.start_date} onChange={e => navegar({ start_date: e.target.value })} /></div>
          <div className="w-40"><Input type="date" aria-label="Fim" value={filtros.end_date} onChange={e => navegar({ end_date: e.target.value })} /></div>
        </Inline>

        <div data-contract="tabela" className="overflow-x-auto rounded-lg border bg-card">
          <table className="w-full text-sm">
            <caption className="sr-only">Pagamentos de compra, página {paginacao.atual}</caption>
            <thead className="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
              <tr>
                <th scope="col" className="px-4 py-2 text-left font-semibold">Ref.</th>
                <th scope="col" className="px-4 py-2 text-left font-semibold">Pago em</th>
                <th scope="col" className="px-4 py-2 text-right font-semibold">Valor</th>
                <th scope="col" className="px-4 py-2 text-left font-semibold">Fornecedor</th>
                <th scope="col" className="px-4 py-2 text-left font-semibold">Forma</th>
                <th scope="col" className="px-4 py-2 text-left font-semibold">Compra</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 ? (
                <tr><td colSpan={6} className="px-4 py-6 text-center text-muted-foreground">Nenhum pagamento no período.</td></tr>
              ) : linhas.map(l => (
                <tr key={l.id} className="border-t">
                  <td className="px-4 py-2 font-mono text-xs">{l.ref}</td>
                  <td className="px-4 py-2 whitespace-nowrap">{dataHora(l.pago_em)}</td>
                  <td className="px-4 py-2 text-right font-mono tabular-nums whitespace-nowrap">{dinheiro(l.valor)}</td>
                  <td className="px-4 py-2">{l.fornecedor}</td>
                  <td className="px-4 py-2">{l.forma}</td>
                  <td className="px-4 py-2 font-mono text-xs">{l.compra ?? ''}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t bg-muted font-semibold">
              <tr>
                <th scope="row" colSpan={2} className="px-4 py-2 text-left">Total desta página</th>
                <td className="px-4 py-2 text-right font-mono tabular-nums whitespace-nowrap" data-testid="pc-total-pagina">{dinheiro(total_pagina)}</td>
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

PagamentosCompraIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default PagamentosCompraIndex;
