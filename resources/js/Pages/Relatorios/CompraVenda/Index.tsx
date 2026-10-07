// Relatório Compras e vendas (purchase_sell) · charter/casos ao lado.
// Não calcula valor: mostra o JSON de GET /reports/purchase-sell (ajax), com os parâmetros do
// report.js (start_date, end_date, location_id). Desenho: relatorios-page.jsx `Resumo`.
import AppShellV2 from '@/Layouts/AppShellV2';
import { useEffect, useState, type ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Inline, Stack } from '@/Components/layout';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';

type Opcao = { id: number; nome: string };
type Props = {
  locais: Opcao[];
  periodo: { inicio: string; fim: string };
  moeda: { simbolo: string; casas: number };
};
type Num = number | string | null;
type Totais = {
  purchase: { total_purchase_exc_tax: Num; total_purchase_inc_tax: Num; purchase_due: Num };
  sell: { total_sell_exc_tax: Num; total_sell_inc_tax: Num; invoice_due: Num };
  total_purchase_return: Num;
  total_sell_return: Num;
  difference: { total: Num; due: Num };
};

const TODOS = 'todos';

function CompraVendaIndex({ locais, periodo, moeda }: Props) {
  const [local, setLocal] = useState('');
  const [inicio, setInicio] = useState(periodo.inicio);
  const [fim, setFim] = useState(periodo.fim);
  const [dados, setDados] = useState<Totais | null>(null);
  const [erro, setErro] = useState<string | null>(null);

  // null do SUM (período sem movimento) vira 0, como o __currency_trans_from_en da Blade.
  const dinheiro = (v: Num | undefined) =>
    dados === null ? '…' : `${moeda.simbolo} ${formatDecimalPtBR(Number(v ?? 0), moeda.casas)}`;

  useEffect(() => {
    let vivo = true;
    setDados(null);
    setErro(null);
    const q = new URLSearchParams({ start_date: inicio, end_date: fim, location_id: local });
    fetch(`/reports/purchase-sell?${q}`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(r => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json() as Promise<Totais>;
      })
      .then(d => { if (vivo) setDados(d); })
      .catch(() => { if (vivo) setErro('Não foi possível carregar os totais.'); });
    return () => { vivo = false; };
  }, [local, inicio, fim]);

  const painel = (titulo: string, linhas: [string, Num | undefined, string][]) => (
    <section className="overflow-hidden rounded-lg border bg-card" aria-label={titulo}>
      <h2 className="border-b bg-muted px-4 py-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">{titulo}</h2>
      <dl>
        {linhas.map(([rotulo, valor, id]) => (
          <div key={id} className="flex items-baseline justify-between gap-3 border-b px-4 py-2 last:border-b-0">
            <dt className="text-sm text-muted-foreground">{rotulo}</dt>
            <dd className="font-mono tabular-nums" data-testid={id}>{dinheiro(valor)}</dd>
          </div>
        ))}
      </dl>
    </section>
  );

  // Cor como o __highlight da Blade: positivo verde, negativo vermelho.
  const fecho = (rotulo: string, valor: Num | undefined, id: string) => {
    const n = Number(valor ?? 0);
    const tom = dados === null || n === 0 ? 'text-foreground' : n > 0 ? 'text-success' : 'text-destructive';
    return (
      <div className="flex flex-col gap-0.5 rounded-lg border bg-muted px-4 py-3">
        <span className="text-xs uppercase tracking-wider text-muted-foreground">{rotulo}</span>
        <span className={`font-mono text-2xl font-semibold tabular-nums ${tom}`} data-testid={id}>{dinheiro(valor)}</span>
      </div>
    );
  };

  return (
    <>
      <div data-contract="header">
        <PageHeader title="Compras e vendas" subtitle="Compras e vendas do período, com devoluções e o que ficou a pagar e a receber" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="filtros" wrap gap={2}>
          <Select value={local || TODOS} onValueChange={v => setLocal(v === TODOS ? '' : v)}>
            <SelectTrigger className="w-60" aria-label="Local"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SafeSelectItem value={TODOS}>Todos os locais</SafeSelectItem>
              {locais.filter(o => o.id).map(o => <SafeSelectItem key={o.id} value={String(o.id)}>{o.nome}</SafeSelectItem>)}
            </SelectContent>
          </Select>
          <Input type="date" className="w-40" aria-label="Início" value={inicio} onChange={e => setInicio(e.target.value)} />
          <Input type="date" className="w-40" aria-label="Fim" value={fim} onChange={e => setFim(e.target.value)} />
        </Inline>

        <div aria-live="polite" className="sr-only">{dados === null && !erro ? 'Carregando totais' : ''}</div>
        {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}

        <div data-contract="resumo" className="grid grid-cols-1 gap-3 min-[1001px]:grid-cols-2">
          {painel('Compras', [
            ['Total de compras', dados?.purchase.total_purchase_exc_tax, 'cv-compra-total'],
            ['Compras com imposto', dados?.purchase.total_purchase_inc_tax, 'cv-compra-imposto'],
            ['Devoluções de compra com imposto', dados?.total_purchase_return, 'cv-compra-devolucao'],
            ['A pagar (compras)', dados?.purchase.purchase_due, 'cv-compra-apagar'],
          ])}
          {painel('Vendas', [
            ['Total de vendas', dados?.sell.total_sell_exc_tax, 'cv-venda-total'],
            ['Vendas com imposto', dados?.sell.total_sell_inc_tax, 'cv-venda-imposto'],
            ['Devoluções de venda com imposto', dados?.total_sell_return, 'cv-venda-devolucao'],
            ['A receber (vendas)', dados?.sell.invoice_due, 'cv-venda-areceber'],
          ])}
        </div>

        <div data-contract="fecho" className="grid grid-cols-1 gap-3 min-[1001px]:grid-cols-2">
          {fecho('Diferença (vendas − devoluções) − (compras − devoluções)', dados?.difference.total, 'cv-diferenca')}
          {fecho('Diferença a receber − a pagar', dados?.difference.due, 'cv-diferenca-devida')}
        </div>
      </Stack>
    </>
  );
}

CompraVendaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default CompraVendaIndex;
