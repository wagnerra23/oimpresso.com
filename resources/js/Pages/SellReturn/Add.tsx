// SellReturn/Add — registro de devolução de venda (/sell-return/add/{venda}), visita Inertia.
// Thread 03 de venda-menu, PR 2 de 2. A lista é SellReturn/Index (PR 1).
//
// REGRA MESTRE (memory/proibicoes.md — valor + estoque): esta tela NÃO tem regra de cálculo
// própria. Ela posta em POST /sell-return (SellReturnController@store, intacto) os mesmos
// campos, em TEXTO no formato da empresa, que o form de sell_return/add.blade.php posta. O
// total mostrado antes de salvar vem de _components/devolucaoCalculo.ts, espelho do servidor.
// Refs: Add.charter.md · Add.casos.md (UC-SRADD-*) ·
//       memory/requisitos/Sells/RUNBOOK-sell-return-add.md · ADR 0104 · ADR 0093

import { useMemo, useState, type FormEvent } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { PageHeader } from '@/Components/PageHeader';
import { FormSection } from '@/Components/ui/form-section';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Grid, Inline, Stack } from '@/Components/layout';
import { calcularTotais, numUf } from './_components/devolucaoCalculo';

// ──────────────────────────────────────────────────────────────
// TIPOS — paridade SellReturnController@inertiaAddVenda
// ──────────────────────────────────────────────────────────────
interface LinhaVenda {
  sell_line_id: number;
  produto: string;
  sku: string | null;
  unidade: string;
  permite_decimal: boolean;
  quantidade_vendida: number;
  quantidade_vendida_txt: string;
  quantidade_devolvida_txt: string;
  preco_unitario_txt: string;
}

interface VendaOrigem {
  id: number;
  numero: string;
  data: string;
  cliente: string | null;
  local: string | null;
  devolucao: { id: number; numero: string } | null;
  numero_devolucao_txt: string;
  data_devolucao_txt: string;
  tax_id: number | null;
  imposto: { nome: string; percentual: number } | null;
  desconto_tipo: string;
  desconto_valor_txt: string;
  linhas: LinhaVenda[];
}

interface SellReturnAddProps {
  venda?: VendaOrigem; // deferida (grupo "venda")
}

const fmtBRL = (n: number): string =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0);

const fmtData = (valor: string): string => {
  const partes = (String(valor || '').split(' ')[0] ?? '').split('-');
  return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : valor;
};

// "Nenhum" vai como texto vazio, igual ao <select> da Blade.
const SEM_DESCONTO = '__nenhum__';

function csrf(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

// ──────────────────────────────────────────────────────────────
// FORMULÁRIO
// ──────────────────────────────────────────────────────────────
function FormularioDevolucao({ venda }: { venda: VendaOrigem }) {
  const [quantidades, setQuantidades] = useState<string[]>(() =>
    venda.linhas.map((l) => (numUf(l.quantidade_devolvida_txt) > 0 ? l.quantidade_devolvida_txt : '')),
  );
  const [numeroDevolucao, setNumeroDevolucao] = useState(venda.numero_devolucao_txt);
  const [descontoTipo, setDescontoTipo] = useState(venda.desconto_tipo);
  const [descontoValor, setDescontoValor] = useState(venda.desconto_valor_txt);
  const [salvando, setSalvando] = useState(false);

  const linhasCalculo = venda.linhas.map((l, i) => ({
    quantidade_txt: quantidades[i] ?? '',
    preco_unitario_txt: l.preco_unitario_txt,
  }));
  const totais = useMemo(
    () => calcularTotais(linhasCalculo, descontoTipo, descontoValor, venda.imposto?.percentual ?? null),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [quantidades, descontoTipo, descontoValor, venda],
  );

  // R1: teto = vendida. Decimal só quando a unidade permite.
  const erros = venda.linhas.map((l, i) => {
    const q = numUf(quantidades[i] ?? '');
    if (q < 0) return 'Quantidade inválida.';
    if (!l.permite_decimal && !Number.isInteger(q)) return 'Esta unidade não aceita fração.';
    return null;
  });
  const algumaQuantidade = quantidades.some((q) => numUf(q) > 0);
  const podeSalvar = algumaQuantidade && erros.every((e) => e === null) && !salvando;

  const mudarQuantidade = (i: number, texto: string) => {
    const linha = venda.linhas[i];
    if (!linha) return;
    const valor = numUf(texto) > linha.quantidade_vendida ? linha.quantidade_vendida_txt : texto;
    setQuantidades((qs) => qs.map((q, j) => (j === i ? valor : q)));
  };

  const salvar = async (e: FormEvent) => {
    e.preventDefault();
    if (!podeSalvar) return;
    setSalvando(true);

    // Mesmas chaves e mesmos textos do form Blade (sell_return/add.blade.php).
    const corpo = new URLSearchParams();
    corpo.append('transaction_id', String(venda.id));
    corpo.append('invoice_no', numeroDevolucao);
    corpo.append('transaction_date', venda.data_devolucao_txt);
    venda.linhas.forEach((l, i) => {
      corpo.append(`products[${i}][quantity]`, quantidades[i] ?? '');
      corpo.append(`products[${i}][unit_price_inc_tax]`, l.preco_unitario_txt);
      corpo.append(`products[${i}][sell_line_id]`, String(l.sell_line_id));
    });
    corpo.append('discount_type', descontoTipo);
    corpo.append('discount_amount', descontoValor);
    corpo.append('tax_id', venda.tax_id == null ? '' : String(venda.tax_id));
    // Ignorado pelo servidor (ele recalcula o imposto); vai arredondado a 2 casas, como texto pt-BR.
    corpo.append('tax_amount', totais.imposto.toFixed(2).replace('.', ','));

    try {
      const res = await fetch('/sell-return', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf(),
        },
        body: corpo.toString(),
      });
      const json = await res.json().catch(() => null);
      if (!res.ok || !json || Number(json.success) !== 1) {
        toast.error(json?.msg ?? `Não foi possível salvar a devolução (HTTP ${res.status}).`);
        return;
      }
      toast.success(`Devolução da venda ${venda.numero} salva.`);
      router.visit('/sell-return');
    } catch {
      toast.error('Erro de rede ao salvar a devolução.');
    } finally {
      setSalvando(false);
    }
  };

  return (
    <form onSubmit={salvar} data-contract="formulario">
      <Stack gap={4} className="mt-4">
        <FormSection title="Venda de origem" data-contract="venda-origem">
          <Grid min="sm" gap={4} className="text-sm">
            <span><strong>Venda:</strong> <Link href={`/sells/${venda.id}`}>#{venda.numero}</Link></span>
            <span><strong>Data:</strong> {fmtData(venda.data)}</span>
            <span><strong>Cliente:</strong> {venda.cliente ?? '—'}</span>
            <span><strong>Local:</strong> {venda.local ?? '—'}</span>
          </Grid>
        </FormSection>

        <FormSection title="Itens a devolver" data-contract="itens">
          <Grid min="sm" gap={4} className="mb-4">
            <Stack gap={1}>
              <Label htmlFor="devolucao-numero" className="text-xs text-muted-foreground">Número da devolução</Label>
              <Input id="devolucao-numero" value={numeroDevolucao} placeholder="gerado ao salvar" onChange={(e) => setNumeroDevolucao(e.target.value)} />
            </Stack>
            <Stack gap={1}>
              <Label htmlFor="devolucao-data" className="text-xs text-muted-foreground">Data</Label>
              <Input id="devolucao-data" value={venda.data_devolucao_txt} readOnly />
            </Stack>
          </Grid>

          <div className="overflow-x-auto">
            <table className="w-full text-sm" aria-label="Itens da venda e quantidade a devolver">
              <thead>
                <tr className="border-b border-border text-left text-xs text-muted-foreground">
                  <th className="py-2 pr-2">Produto</th>
                  <th className="py-2 pr-2 text-right">Preço unitário</th>
                  <th className="py-2 pr-2 text-right">Vendida</th>
                  <th className="py-2 pr-2">Devolver</th>
                  <th className="py-2 text-right">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                {venda.linhas.map((l, i) => (
                  <tr key={l.sell_line_id} className="border-b border-border align-top">
                    <td className="py-2 pr-2">
                      <strong>{l.produto}</strong>
                      {l.sku && <div className="text-xs text-muted-foreground">{l.sku}</div>}
                    </td>
                    <td className="py-2 pr-2 text-right tabular-nums">{fmtBRL(numUf(l.preco_unitario_txt))}</td>
                    <td className="py-2 pr-2 text-right tabular-nums">{l.quantidade_vendida_txt} {l.unidade}</td>
                    <td className="w-40 py-2 pr-2">
                      <Input
                        inputMode="decimal"
                        value={quantidades[i] ?? ''}
                        placeholder="0"
                        aria-label={`Quantidade a devolver de ${l.produto} (máximo ${l.quantidade_vendida_txt})`}
                        aria-invalid={erros[i] !== null}
                        onChange={(e) => mudarQuantidade(i, e.target.value)}
                      />
                      {erros[i] && <p className="mt-1 text-xs text-destructive">{erros[i]}</p>}
                    </td>
                    <td className="py-2 text-right tabular-nums">
                      {fmtBRL(numUf(quantidades[i] ?? '') * numUf(l.preco_unitario_txt))}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </FormSection>

        <FormSection title="Desconto e total" data-contract="totais">
          <Grid min="sm" gap={4}>
            <Stack gap={1}>
              <Label htmlFor="devolucao-desconto-tipo" className="text-xs text-muted-foreground">Tipo de desconto</Label>
              <Select value={descontoTipo || SEM_DESCONTO} onValueChange={(v) => setDescontoTipo(v === SEM_DESCONTO ? '' : v)}>
                <SelectTrigger id="devolucao-desconto-tipo" className="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value={SEM_DESCONTO}>Nenhum</SelectItem>
                  <SelectItem value="fixed">Valor fixo</SelectItem>
                  <SelectItem value="percentage">Percentual</SelectItem>
                </SelectContent>
              </Select>
            </Stack>
            <Stack gap={1}>
              <Label htmlFor="devolucao-desconto" className="text-xs text-muted-foreground">Desconto</Label>
              <Input id="devolucao-desconto" inputMode="decimal" value={descontoValor} onChange={(e) => setDescontoValor(e.target.value)} />
            </Stack>
          </Grid>

          <Stack gap={1} className="mt-4 items-end text-sm tabular-nums">
            <span>Subtotal: {fmtBRL(totais.subtotal)}</span>
            <span>(−) Desconto: {fmtBRL(totais.desconto)}</span>
            <span>
              (+) Imposto{venda.imposto ? ` (${venda.imposto.nome} · ${venda.imposto.percentual}%)` : ''}: {fmtBRL(totais.imposto)}
            </span>
            <strong className="text-base">Total devolvido: {fmtBRL(totais.total)}</strong>
          </Stack>
        </FormSection>

        <Inline justify="end" gap={2}>
          <Button variant="outline" asChild>
            <Link href="/sell-return">Cancelar</Link>
          </Button>
          <Button type="submit" disabled={!podeSalvar}>
            {salvando ? 'Salvando…' : 'Salvar devolução'}
          </Button>
        </Inline>
      </Stack>
    </form>
  );
}

// ──────────────────────────────────────────────────────────────
// PÁGINA
// ──────────────────────────────────────────────────────────────
export default function SellReturnAdd({ venda }: SellReturnAddProps) {
  return (
    <AppShellV2
      title="Devolução de venda"
      breadcrumbItems={[{ label: 'Vendas' }, { label: 'Devoluções', href: '/sell-return' }, { label: 'Registrar' }]}
    >
      <Head title="Devolução de venda" />

      <div data-contract="cabecalho">
        <PageHeader
          title={venda?.devolucao ? `Devolução ${venda.devolucao.numero}` : 'Devolver itens da venda'}
          subtitle="O que volta ao estoque e quanto fica a pagar ao cliente"
        />
      </div>

      <Deferred data="venda" fallback={<Skeleton className="mt-4 h-96 w-full" />}>
        {venda ? <FormularioDevolucao venda={venda} /> : <></>}
      </Deferred>
    </AppShellV2>
  );
}
