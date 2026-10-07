// Relatório de comissão por vendedor (resumo) · charter/casos ao lado.
// Não calcula valor: mostra o JSON de /reports/sales-representative-total-{sell,expense,commission},
// com os parâmetros do report.js (vendedor, location_id, start_date, end_date).
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
  vendedores: Opcao[];
  locais: Opcao[];
  periodo: { inicio: string; fim: string };
  base_comissao: 'invoice_value' | 'payment_received';
  moeda: { simbolo: string; casas: number };
};
type Venda = { total_sell_exc_tax: number; total_sell_return_exc_tax: number; total_sell: number };
type Comissao = {
  total_commission: number;
  commission_percentage: number;
  total_sales_with_commission?: number;
  total_payment_with_commission?: number;
};

const TODOS = 'todos';

async function buscar<T>(url: string, params: Record<string, string>): Promise<T> {
  const r = await fetch(`${url}?${new URLSearchParams(params)}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  });
  if (!r.ok) throw new Error(`HTTP ${r.status}`);
  return r.json() as Promise<T>;
}

/** '' = todos, como o select da Blade; o sentinela só existe porque o Radix não aceita value vazio. */
function Filtro({ rotulo, todos, opcoes, valor, mudar }: { rotulo: string; todos: string; opcoes: Opcao[]; valor: string; mudar: (v: string) => void }) {
  return (
    <Select value={valor || TODOS} onValueChange={v => mudar(v === TODOS ? '' : v)}>
      <SelectTrigger className="w-60" aria-label={rotulo}><SelectValue /></SelectTrigger>
      <SelectContent>
        <SafeSelectItem value={TODOS}>{todos}</SafeSelectItem>
        {opcoes.filter(o => o.id).map(o => <SafeSelectItem key={o.id} value={String(o.id)}>{o.nome}</SafeSelectItem>)}
      </SelectContent>
    </Select>
  );
}

function SalesRepresentativeIndex({ vendedores, locais, periodo, base_comissao, moeda }: Props) {
  const [vendedor, setVendedor] = useState('');
  const [local, setLocal] = useState('');
  const [inicio, setInicio] = useState(periodo.inicio);
  const [fim, setFim] = useState(periodo.fim);
  const [venda, setVenda] = useState<Venda | null>(null);
  const [despesa, setDespesa] = useState<number | null>(null);
  const [comissao, setComissao] = useState<Comissao | null>(null);
  const [erro, setErro] = useState<string | null>(null);

  const dinheiro = (v: number | null | undefined) =>
    v == null ? '…' : `${moeda.simbolo} ${formatDecimalPtBR(Number(v), moeda.casas)}`;

  useEffect(() => {
    let vivo = true;
    const base = { location_id: local, start_date: inicio, end_date: fim };
    setVenda(null); setDespesa(null); setComissao(null); setErro(null);
    const falhou = () => vivo && setErro('Não foi possível carregar o resumo.');
    buscar<Venda>('/reports/sales-representative-total-sell', { ...base, created_by: vendedor })
      .then(d => vivo && setVenda(d)).catch(falhou);
    buscar<{ total_expense: number }>('/reports/sales-representative-total-expense', { ...base, expense_for: vendedor })
      .then(d => vivo && setDespesa(Number(d.total_expense ?? 0))).catch(falhou);
    if (vendedor) {
      buscar<Comissao>('/reports/sales-representative-total-commission', { ...base, commission_agent: vendedor })
        .then(d => vivo && setComissao(d)).catch(falhou);
    }
    return () => { vivo = false; };
  }, [vendedor, local, inicio, fim]);

  const baseValor = comissao?.total_payment_with_commission ?? comissao?.total_sales_with_commission;
  const baseRotulo = base_comissao === 'payment_received' ? 'dos pagamentos recebidos' : 'das vendas faturadas';

  return (
    <>
      <div data-contract="header">
        <PageHeader title="Comissão por vendedor" subtitle="Vendas, devoluções, comissão e despesas do período" />
      </div>
      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="filtros" wrap gap={2}>
          <Filtro rotulo="Vendedor" todos="Todos os usuários" opcoes={vendedores} valor={vendedor} mudar={setVendedor} />
          <Filtro rotulo="Local" todos="Todos os locais" opcoes={locais} valor={local} mudar={setLocal} />
          <Input type="date" className="w-40" aria-label="Início" value={inicio} onChange={e => setInicio(e.target.value)} />
          <Input type="date" className="w-40" aria-label="Fim" value={fim} onChange={e => setFim(e.target.value)} />
        </Inline>

        {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}

        <Stack data-contract="resumo" gap={2} className="rounded-lg border p-4 text-sm">
          <p>
            Vendas − devoluções: <strong>{dinheiro(venda?.total_sell_exc_tax)}</strong> − <strong>{dinheiro(venda?.total_sell_return_exc_tax)}</strong>
            {' = '}<strong data-testid="sr-total-venda">{dinheiro(venda?.total_sell)}</strong>
          </p>
          {vendedor ? (
            <p>
              Comissão: <strong data-testid="sr-total-comissao">{dinheiro(comissao?.total_commission)}</strong>
              {comissao && Number(comissao.commission_percentage) !== 0 && baseValor != null && (
                <span className="text-muted-foreground"> ({comissao.commission_percentage}% {baseRotulo}: {dinheiro(baseValor)})</span>
              )}
            </p>
          ) : (
            <p className="text-muted-foreground">Escolha um vendedor para ver a comissão.</p>
          )}
          <p>Despesas: <strong data-testid="sr-total-despesa">{dinheiro(despesa)}</strong></p>
        </Stack>
      </Stack>
    </>
  );
}

SalesRepresentativeIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default SalesRepresentativeIndex;
