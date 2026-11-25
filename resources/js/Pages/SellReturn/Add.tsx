// SellReturn/Add — registro de devolução de venda (/sell-return/add/{venda}), visita Inertia.
// Thread 03 de venda-menu, PR 2 de 2. A lista é SellReturn/Index (PR 1).
//
// REGRA MESTRE (memory/proibicoes.md — valor + estoque): esta tela NÃO tem regra de cálculo
// própria. Ela posta em POST /sell-return (SellReturnController@store, intacto) os mesmos
// campos, em TEXTO no formato da empresa, que o form de sell_return/add.blade.php posta. O
// total mostrado antes de salvar vem de _components/devolucaoCalculo.ts, espelho do servidor.
// Refs: Add.charter.md · Add.casos.md (UC-SRADD-*) ·
//       memory/requisitos/Sells/RUNBOOK-sell-return-add.md · ADR 0104 · ADR 0093

import { useEffect, useMemo, useState, type FormEvent } from 'react';
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
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Grid, Inline, Stack } from '@/Components/layout';
import { acumuladoTxt, calcularTotais, numUf, podeVoltar } from './_components/devolucaoCalculo';
import { lerFila, urlDoRegistro } from './_components/devolucaoFila';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { CircleCheck, FileText, Info, Landmark, Package, PackageOpen, TriangleAlert, Wallet } from 'lucide-react';
import { Aviso, SecaoDevolucao, Selo } from './_components/secao';

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
  cliente_aceita_vale: boolean; // US-VEST-021 — falso no "Cliente padrão"
  local: string | null;
  devolucao: { id: number; numero: string } | null;
  numero_devolucao_txt: string;
  data_devolucao_txt: string;
  tax_id: number | null;
  imposto: { nome: string; percentual: number } | null;
  desconto_tipo: string;
  desconto_valor_txt: string;
  linhas: LinhaVenda[];
  // Nota fiscal autorizada da venda; null = venda sem nota (retorno só ao estoque, sem documento fiscal).
  nota: { numero: string; tipo: string } | null;
  // Onda 1 Devolução V3: null esconde a seção (flag desligada, sem permissão fiscal ou sem devolução salva).
  nfe_devolucao: NfeDevolucao | null;
}

/** O servidor responde com a mesma checagem da rota de emissão: a tela não promete o que ela recusa. */
interface NfeDevolucao {
  devolucao_id: number;
  disponivel: boolean;
  motivo: string | null;
  itens: number;
  nota_origem: string | null;
}

/** POST /sell-return/previa-financeira/{venda} — conta do servidor (UC-SRADD-09). */
interface PreviaFinanceira {
  total: number;
  ja_pago_ao_cliente: number;
  devido_ao_cliente: number;
  financeiro: {
    tem_titulo: boolean;
    em_aberto_na_venda: number;
    ja_abatido: number;
    abate: number;
    credito: number;
  } | null;
}

interface SellReturnAddProps {
  venda?: VendaOrigem; // deferida (grupo "venda")
}

const fmtBRL = (n: number): string =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0);

const fmtQtd = (n: number): string =>
  new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 4 }).format(n || 0);

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
const rotulo = 'text-xs font-medium uppercase tracking-wide text-muted-foreground';

function FormularioDevolucao({ venda }: { venda: VendaOrigem }) {
  // Protótipo Devolução V3 (venda sem nota): o campo é "Volta agora" — o que volta NESTA vez.
  // O store() segue recebendo o acumulado; acumuladoTxt() faz a ponte (UC-SRADD-07/08 intactos).
  const [voltaAgora, setVoltaAgora] = useState<string[]>(() => venda.linhas.map(() => ''));
  const [numeroDevolucao, setNumeroDevolucao] = useState(venda.numero_devolucao_txt);
  const [descontoTipo, setDescontoTipo] = useState(venda.desconto_tipo);
  const [descontoValor, setDescontoValor] = useState(venda.desconto_valor_txt);
  const [salvando, setSalvando] = useState(false);
  // US-VEST-021 — pagar ao cliente depois (fica "A pagar" na lista) ou já como vale-troca.
  const [pagamento, setPagamento] = useState<'depois' | 'vale'>('depois');

  const semNota = venda.nota === null;
  const saldos = venda.linhas.map((l) => podeVoltar(l.quantidade_vendida, l.quantidade_devolvida_txt));
  // O que vai ao store(): acumulado por linha (já devolvido + volta agora).
  const acumulados = venda.linhas.map((l, i) => acumuladoTxt(l.quantidade_devolvida_txt, voltaAgora[i] ?? ''));

  const totais = useMemo(
    () =>
      calcularTotais(
        venda.linhas.map((l, i) => ({ quantidade_txt: acumulados[i] ?? '', preco_unitario_txt: l.preco_unitario_txt })),
        descontoTipo,
        descontoValor,
        venda.imposto?.percentual ?? null,
      ),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [voltaAgora, descontoTipo, descontoValor, venda],
  );
  const totaisJaDevolvidos = useMemo(
    () =>
      calcularTotais(
        venda.linhas.map((l) => ({ quantidade_txt: l.quantidade_devolvida_txt, preco_unitario_txt: l.preco_unitario_txt })),
        descontoTipo,
        descontoValor,
        venda.imposto?.percentual ?? null,
      ),
    [venda, descontoTipo, descontoValor],
  );
  // Valor do que volta agora = total acumulado depois − total já devolvido (mesma conta, duas vezes).
  const valorAgora = totais.total - totaisJaDevolvidos.total;
  const jaDevolveu = venda.linhas.some((l) => numUf(l.quantidade_devolvida_txt) > 0);

  // R1: teto = o que ainda pode voltar. Decimal só quando a unidade permite.
  const erros = venda.linhas.map((l, i) => {
    const q = numUf(voltaAgora[i] ?? '');
    if (q < 0) return 'Quantidade inválida.';
    if (!l.permite_decimal && !Number.isInteger(q)) return 'Esta unidade não aceita fração.';
    return null;
  });
  const unidadesAgora = voltaAgora.reduce((s, q) => s + Math.max(0, numUf(q)), 0);
  const itensAgora = voltaAgora.filter((q) => numUf(q) > 0).length;
  const algoVolta = itensAgora > 0;
  // Item 4 das pendências (2026-11-24): sem nada voltando agora não há o que salvar.
  const podeSalvar = algoVolta && erros.every((e) => e === null) && !salvando;
  const saldoTotal = saldos.reduce((s, x) => s + x, 0);

  const devolverTodoSaldo = () => setVoltaAgora(venda.linhas.map((_l, i) => (saldos[i]! > 0 ? String(saldos[i]).replace('.', ',') : '')));
  const limpar = () => setVoltaAgora(venda.linhas.map(() => ''));
  const mudarQuantidade = (i: number, texto: string) => {
    const saldo = saldos[i] ?? 0;
    const valor = numUf(texto) > saldo ? String(saldo).replace('.', ',') : texto;
    setVoltaAgora((qs) => qs.map((q, j) => (j === i ? valor : q)));
  };

  // UC-SRADD-09 · prévia financeira. O servidor recebe o ACUMULADO (como o store()) e devolve o
  // abatimento total da devolução; a tela mostra o que muda AGORA (abate − já abatido).
  const [previa, setPrevia] = useState<PreviaFinanceira | null>(null);
  const [previaErro, setPreviaErro] = useState(false);
  const chavePrevia = JSON.stringify([voltaAgora, descontoTipo, descontoValor]);
  useEffect(() => {
    if (!algoVolta) {
      setPrevia(null);
      return;
    }
    const corpo = new URLSearchParams();
    venda.linhas.forEach((l, i) => {
      corpo.append(`products[${i}][quantity]`, acumulados[i] ?? '');
      corpo.append(`products[${i}][unit_price_inc_tax]`, l.preco_unitario_txt);
    });
    corpo.append('discount_type', descontoTipo);
    corpo.append('discount_amount', descontoValor);
    const controle = new AbortController();
    const espera = window.setTimeout(async () => {
      try {
        const res = await fetch(`/sell-return/previa-financeira/${venda.id}`, {
          method: 'POST',
          credentials: 'same-origin',
          signal: controle.signal,
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
          },
          body: corpo.toString(),
        });
        if (!res.ok) throw new Error(String(res.status));
        setPrevia((await res.json()) as PreviaFinanceira);
        setPreviaErro(false);
      } catch (err) {
        if ((err as Error)?.name !== 'AbortError') setPreviaErro(true);
      }
    }, 400);
    return () => {
      window.clearTimeout(espera);
      controle.abort();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chavePrevia, algoVolta, venda]);
  // Dupla prova à vista: o total da tela (espelho) e o do servidor têm de bater.
  const totalDivergente = previa !== null && Math.abs(previa.total - totais.total) > 0.005;
  const abateAgora = previa?.financeiro ? Math.max(0, previa.financeiro.abate - previa.financeiro.ja_abatido) : 0;

  const salvar = async (e: FormEvent) => {
    e.preventDefault();
    if (!podeSalvar) return;
    setSalvando(true);

    // Mesmas chaves e mesmos textos do form Blade (sell_return/add.blade.php); a quantidade é o acumulado.
    const corpo = new URLSearchParams();
    corpo.append('transaction_id', String(venda.id));
    corpo.append('invoice_no', numeroDevolucao);
    corpo.append('transaction_date', venda.data_devolucao_txt);
    venda.linhas.forEach((l, i) => {
      corpo.append(`products[${i}][quantity]`, acumulados[i] ?? '');
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
      if (pagamento === 'vale') {
        // O store() não devolve o id da devolução; o vale é pedido pela venda (uma devolução por venda).
        const vt = await fetch(`/sell-return/vale-troca-da-venda/${venda.id}`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
        });
        const vj = await vt.json().catch(() => null);
        if (vt.ok && vj) {
          toast.success(`Vale-troca de ${fmtBRL(Number(vj.valor))} no saldo do cliente.`);
        } else {
          // A devolução já foi salva: o vale pode ser refeito depois; o aviso diz o porquê.
          toast.error(vj?.mensagem ?? `A devolução foi salva, mas o vale-troca não (HTTP ${vt.status}).`);
        }
      }
      // Várias vendas do mesmo cliente (escolhidas em /sell-return/create): cada uma é uma
      // devolução própria; salva esta, abre a próxima da fila.
      const fila = lerFila(window.location.search);
      if (fila.length) {
        toast.info(`Agora a venda seguinte — faltam ${fila.length}.`);
        router.visit(urlDoRegistro(fila));
      } else {
        router.visit('/sell-return');
      }
    } catch {
      toast.error('Erro de rede ao salvar a devolução.');
    } finally {
      setSalvando(false);
    }
  };

  const documento = venda.nota ? `${venda.nota.tipo} ${venda.nota.numero}` : 'Sem nota';
  const acao = semNota ? 'Devolver ao estoque' : 'Salvar devolução';

  // Conferência (protótipo, venda sem nota): avisos antes de devolver ao estoque.
  const avisos: { tom: 'neg' | 'pos' | 'info'; titulo: string; texto: string }[] = [];
  if (!algoVolta) {
    avisos.push(
      saldoTotal > 0
        ? { tom: 'neg', titulo: 'Nenhuma quantidade para voltar.', texto: 'Informe quanto de cada item está voltando.' }
        : { tom: 'neg', titulo: 'Nada mais pode voltar.', texto: 'Tudo o que foi vendido nesta venda já voltou ao estoque.' },
    );
  } else {
    avisos.push({
      tom: 'pos',
      titulo: semNota ? 'Pronto para devolver ao estoque.' : 'Pronto para salvar.',
      texto: `${fmtQtd(unidadesAgora)} un voltam para ${venda.local ?? 'o estoque'} e a venda ${venda.numero} é reduzida em ${fmtBRL(valorAgora)}.`,
    });
  }
  if (semNota) {
    avisos.push({
      tom: 'info',
      titulo: 'Venda sem documento fiscal.',
      texto: 'Não há nota de devolução: o retorno ajusta a venda, o estoque e o financeiro.',
    });
  }
  const tomAviso = { neg: 'destructive', pos: 'success', info: 'info' } as const;
  const iconeAviso = { neg: <TriangleAlert />, pos: <CircleCheck />, info: <Info /> } as const;

  // Layout do protótipo Devolução V3 (prototipo-ui/cowork/Wagner/devolucao-v3.html, venda sem
  // nota — blocoSn/renderSnItens): origem, itens que voltam, conferência; à direita o retorno ao
  // estoque e a ação. Os estados, a conta (calcularTotais) e o post ao store() são os de antes.
  return (
    <form onSubmit={salvar} data-contract="formulario">
      <Grid gap={4} className="mt-4 lg:grid-cols-[minmax(0,1fr)_340px]">
        <Stack gap={4} className="min-w-0">
          <SecaoDevolucao
            data-contract="venda-origem"
            passo={1}
            icone={<FileText />}
            titulo="Origem da devolução"
            sub={semNota ? 'Venda sem nota que está voltando.' : 'A venda finalizada que está voltando.'}
          >
              <Stack gap={4}>
                <Grid min="sm" gap={4} className="text-sm">
                  <Stack gap={0}>
                    <span className={rotulo}>Venda</span>
                    <Link href={`/sells/${venda.id}`} className="font-mono font-semibold">{venda.numero}</Link>
                    <span className="text-xs text-muted-foreground">Finalizada em {fmtData(venda.data)}</span>
                  </Stack>
                  <Stack gap={0}>
                    <span className={rotulo}>Cliente</span>
                    <strong className="break-words">{venda.cliente ?? '—'}</strong>
                  </Stack>
                  <Stack gap={0} data-contract="documento-fiscal">
                    <span className={rotulo}>Documento fiscal</span>
                    <strong>{documento}</strong>
                    {semNota && <span className="text-xs text-muted-foreground">O retorno só movimenta o estoque</span>}
                  </Stack>
                </Grid>
                <Grid min="sm" gap={4} className="text-sm">
                  {/* O store() devolve ao local da venda; escolher outro depósito é a onda 2 (RUNBOOK §6). */}
                  <Stack gap={0} data-contract="deposito-entrada">
                    <span className={rotulo}>Depósito de entrada</span>
                    <strong className="break-words">{venda.local ?? '—'}</strong>
                    <span className="text-xs text-muted-foreground">O local da venda</span>
                  </Stack>
                  <Stack gap={1}>
                    <Label htmlFor="devolucao-numero" className={rotulo}>Número da devolução</Label>
                    <Input id="devolucao-numero" value={numeroDevolucao} placeholder="gerado ao salvar" onChange={(e) => setNumeroDevolucao(e.target.value)} />
                  </Stack>
                  <Stack gap={1}>
                    <Label htmlFor="devolucao-data" className={rotulo}>Data</Label>
                    <Input id="devolucao-data" value={venda.data_devolucao_txt} readOnly />
                  </Stack>
                </Grid>
                <Inline justify="end">
                  <Button asChild variant="ghost" size="sm">
                    <Link href="/sell-return/create">Trocar venda</Link>
                  </Button>
                </Inline>
              </Stack>
          </SecaoDevolucao>

          <SecaoDevolucao
            data-contract="itens"
            passo={2}
            icone={<Package />}
            tom="success"
            titulo="Itens que voltam ao estoque"
            sub="Limitado ao que foi vendido menos o que já voltou."
            semPadding
            direita={
              <>
                <Button type="button" variant="outline" size="sm" onClick={devolverTodoSaldo} disabled={saldoTotal <= 0} data-contract="devolver-tudo">
                  Devolver todo o saldo
                </Button>
                <Button type="button" variant="ghost" size="sm" onClick={limpar} disabled={!algoVolta}>
                  Limpar
                </Button>
              </>
            }
          >
              <div className="overflow-x-auto">
                <table className="w-full text-[12.5px]" aria-label="Itens da venda e quantidade que volta agora">
                  <thead>
                    <tr className="border-b border-border bg-muted text-left text-[10.5px] font-semibold uppercase tracking-[.04em] text-muted-foreground">
                      <th className="px-2.5 py-2">Produto</th>
                      <th className="px-2.5 py-2 text-right">Vendido</th>
                      <th className="px-2.5 py-2 text-right">Já voltou</th>
                      <th className="px-2.5 py-2 text-right">Pode voltar</th>
                      <th className="px-2.5 py-2 text-right">Volta agora</th>
                      <th className="px-2.5 py-2 text-right">Valor</th>
                    </tr>
                  </thead>
                  <tbody>
                    {venda.linhas.map((l, i) => {
                      const saldo = saldos[i] ?? 0;
                      const ja = numUf(l.quantidade_devolvida_txt);
                      const qAgora = numUf(voltaAgora[i] ?? '');
                      const preco = numUf(l.preco_unitario_txt);
                      return (
                        <tr key={l.sell_line_id} className="border-b border-border align-top hover:bg-[color-mix(in_oklch,var(--color-primary)_4%,var(--color-card))]">
                          <td className="px-2.5 py-2">
                            <strong>{l.produto}</strong>
                            {l.sku && <div className="font-mono text-xs text-muted-foreground">{l.sku}</div>}
                          </td>
                          <td className="px-2.5 py-2 text-right font-mono tabular-nums">{l.quantidade_vendida_txt}</td>
                          <td className="px-2.5 py-2 text-right font-mono tabular-nums">{ja > 0 ? l.quantidade_devolvida_txt : '—'}</td>
                          <td className="px-2.5 py-2 text-right font-mono font-semibold tabular-nums">{fmtQtd(saldo)}</td>
                          <td className="w-32 px-2.5 py-2 text-right">
                            <Input
                              className="text-right"
                              inputMode="decimal"
                              value={voltaAgora[i] ?? ''}
                              placeholder="0"
                              disabled={saldo <= 0}
                              aria-label={`Quantidade de ${l.produto} que volta agora (até ${fmtQtd(saldo)})`}
                              aria-invalid={erros[i] !== null}
                              onChange={(e) => mudarQuantidade(i, e.target.value)}
                            />
                            {erros[i] && <p className="mt-1 text-xs text-destructive">{erros[i]}</p>}
                          </td>
                          <td className="px-2.5 py-2 text-right font-mono tabular-nums">
                            {qAgora > 0 ? fmtBRL(qAgora * preco) : '—'}
                            <small className="block text-xs text-muted-foreground">{fmtBRL(preco)} /{l.unidade || 'un'}</small>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                  <tfoot>
                    <tr className="bg-muted font-semibold">
                      <td className="px-2.5 py-2">{itensAgora} {itensAgora === 1 ? 'item voltando' : 'itens voltando'}</td>
                      <td /><td /><td />
                      <td className="px-2.5 py-2 text-right font-mono tabular-nums">{fmtQtd(unidadesAgora)}</td>
                      <td className="px-2.5 py-2 text-right font-mono tabular-nums">{fmtBRL(algoVolta ? valorAgora : 0)}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <div className="border-t border-border p-4" data-contract="desconto">
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
              </div>
          </SecaoDevolucao>

          <SecaoDevolucao
            data-contract="conferencia"
            passo={3}
            icone={<TriangleAlert />}
            tom="warning"
            titulo="Conferência"
            sub={semNota ? 'Antes de devolver ao estoque.' : 'Antes de salvar a devolução.'}
          >
              <Stack gap={2} aria-live="polite">
                {avisos.map((a) => (
                  <Aviso key={a.titulo} tom={tomAviso[a.tom]} icone={iconeAviso[a.tom]} titulo={a.titulo}>
                    {a.texto}
                  </Aviso>
                ))}
              </Stack>
          </SecaoDevolucao>

          <SecaoDevolucao
            data-contract="pagamento-cliente"
            passo={4}
            icone={<Wallet />}
            tom="info"
            titulo="Pagamento ao cliente"
            sub="Como o cliente recebe o que a devolução deixa a favor dele."
          >
              <RadioGroup value={pagamento} onValueChange={(v) => setPagamento(v as 'depois' | 'vale')} className="gap-3">
                <Inline gap={2} className="items-start">
                  <RadioGroupItem id="pagar-depois" value="depois" />
                  <Label htmlFor="pagar-depois" className="font-normal">
                    Pagar depois — a devolução fica &quot;A pagar&quot; na lista de devoluções
                  </Label>
                </Inline>
                <Inline gap={2} className="items-start">
                  <RadioGroupItem id="pagar-vale" value="vale" disabled={!venda.cliente_aceita_vale} />
                  <Label htmlFor="pagar-vale" className="font-normal">
                    Vale-troca — o que falta pagar da devolução vira saldo do cliente, para usar no caixa como Adiantamento
                    {!venda.cliente_aceita_vale && (
                      <span className="block text-xs text-muted-foreground">
                        Indisponível: a venda é do Cliente padrão. Cadastre o cliente na venda para dar vale-troca.
                      </span>
                    )}
                  </Label>
                </Inline>
              </RadioGroup>
          </SecaoDevolucao>
        </Stack>

        <Stack gap={4} className="h-fit lg:sticky lg:top-4">
          <SecaoDevolucao
            data-contract="totais"
            icone={<PackageOpen />}
            tom="warning"
            titulo="Retorno ao estoque"
            sub={semNota ? 'Venda sem nota' : `Venda com ${documento}`}
            direita={semNota && !venda.imposto ? <Selo tom="warning">Sem tributos</Selo> : undefined}
          >
              <Stack gap={3}>
                {/* `.plate` do protótipo: no claro, placa invertida; no escuro, tinta do primary (14%/35%). */}
                <div
                  className="rounded-lg bg-foreground px-4 py-3.5 text-background dark:border dark:border-[color-mix(in_oklch,var(--color-primary)_35%,var(--color-border))] dark:bg-[color-mix(in_oklch,var(--color-primary)_14%,var(--color-card))] dark:text-foreground"
                  data-contract="volta-agora"
                >
                  <span className="mb-2 block text-[10.5px] font-semibold uppercase leading-none tracking-[.06em] opacity-70">Unidades que voltam</span>
                  <b className="block font-mono text-[28px] leading-none tabular-nums">{fmtQtd(unidadesAgora)} un</b>
                  <small className="mt-2 block text-[11.5px] opacity-70">
                    {fmtBRL(algoVolta ? valorAgora : 0)} · {venda.local ?? '—'}
                  </small>
                </div>
                <Stack gap={1} className="text-sm tabular-nums">
                  <Inline justify="between"><span className="text-muted-foreground">Venda</span><span className="font-mono">{venda.numero}</span></Inline>
                  <Inline justify="between"><span className="text-muted-foreground">Documento fiscal</span><span>{venda.nota ? documento : 'Nenhum'}</span></Inline>
                  {jaDevolveu && (
                    <Inline justify="between"><span className="text-muted-foreground">Já devolvido antes</span><span className="font-mono">{fmtBRL(totaisJaDevolvidos.total)}</span></Inline>
                  )}
                  {algoVolta && (
                    <>
                      <Inline justify="between"><span className="text-muted-foreground">(−) Desconto</span><span className="font-mono">{fmtBRL(totais.desconto - totaisJaDevolvidos.desconto)}</span></Inline>
                      {venda.imposto && (
                        <Inline justify="between">
                          <span className="text-muted-foreground">(+) Imposto ({venda.imposto.nome} · {venda.imposto.percentual}%)</span>
                          <span className="font-mono">{fmtBRL(totais.imposto - totaisJaDevolvidos.imposto)}</span>
                        </Inline>
                      )}
                      <Inline justify="between" className="font-semibold">
                        <span>Total da devolução depois de salvar</span><span className="font-mono">{fmtBRL(totais.total)}</span>
                      </Inline>
                    </>
                  )}
                </Stack>
              </Stack>
          </SecaoDevolucao>

          {algoVolta && (
            <SecaoDevolucao data-contract="previa-financeira" icone={<Landmark />} tom="info" titulo="Financeiro" sub="Conta a receber da venda">
                {previaErro && !previa && (
                  <p className="text-sm text-muted-foreground">Não foi possível calcular o efeito no Financeiro agora. A devolução pode ser salva normalmente.</p>
                )}
                {!previa && !previaErro && <Skeleton className="h-12 w-full" />}
                {previa && (
                  <Stack gap={1} className="text-sm" aria-live="polite">
                    {totalDivergente && (
                      <p className="text-destructive" data-contract="previa-divergente">
                        O servidor calculou {fmtBRL(previa.total)} para esta devolução, diferente do total acima. Confira antes de salvar.
                      </p>
                    )}
                    {previa.ja_pago_ao_cliente > 0 && (
                      <span>Já pago ao cliente nesta devolução: {fmtBRL(previa.ja_pago_ao_cliente)}</span>
                    )}
                    {pagamento === 'depois' ? (
                      previa.financeiro?.tem_titulo ? (
                        <>
                          <span>
                            Abate agora do que o cliente deve nesta venda: <strong>{fmtBRL(abateAgora)}</strong>
                            {' '}(em aberto hoje: {fmtBRL(Math.max(0, previa.financeiro.em_aberto_na_venda - previa.financeiro.ja_abatido))})
                          </span>
                          {previa.financeiro.ja_abatido > 0 && (
                            <span className="text-muted-foreground">Já abatido pela devolução antes: {fmtBRL(previa.financeiro.ja_abatido)}</span>
                          )}
                          <span>
                            Crédito do cliente nesta devolução depois de salvar: <strong>{fmtBRL(previa.financeiro.credito)}</strong>
                            {previa.financeiro.credito > 0 && ' — a devolução fica "A pagar" na lista de devoluções'}
                          </span>
                        </>
                      ) : (
                        <span>
                          A venda não tem conta a receber em aberto no Financeiro: os <strong>{fmtBRL(previa.devido_ao_cliente)}</strong> da devolução ficam de crédito para o cliente.
                        </span>
                      )
                    ) : (
                      <>
                        <span>Vale-troca no saldo do cliente: <strong>{fmtBRL(previa.devido_ao_cliente)}</strong></span>
                        {previa.financeiro?.tem_titulo && (
                          <span className="text-muted-foreground">
                            O vale não abate o que o cliente deve: a venda continua com {fmtBRL(previa.financeiro.em_aberto_na_venda)} em aberto no Financeiro.
                          </span>
                        )}
                      </>
                    )}
                  </Stack>
                )}
              </SecaoDevolucao>
          )}

          {/* `.fin` do protótipo: borda com 22% do primary e sombra de painel que flutua. */}
          <div
            data-contract="acao"
            className="rounded-lg border border-[color-mix(in_oklch,var(--color-primary)_22%,var(--color-border))] bg-card p-3 shadow-[0_-2px_10px_color-mix(in_oklch,var(--color-foreground)_7%,transparent),0_10px_28px_color-mix(in_oklch,var(--color-foreground)_12%,transparent)]"
          >
              <Stack gap={3}>
                <Inline justify="between" className="items-baseline text-sm">
                  <span className={rotulo}>Volta ao estoque</span>
                  <b className="font-mono text-xl tabular-nums">{fmtQtd(unidadesAgora)} un</b>
                  <span className="font-mono tabular-nums text-muted-foreground">{fmtBRL(algoVolta ? valorAgora : 0)}</span>
                </Inline>
                <Button type="submit" disabled={!podeSalvar}>
                  {salvando ? 'Salvando…' : acao}
                </Button>
                <span className={`text-center text-xs ${podeSalvar || salvando ? 'text-muted-foreground' : 'text-destructive'}`}>
                  {algoVolta ? 'O estoque é atualizado ao salvar.' : 'Informe ao menos uma quantidade para voltar.'}
                </span>
                <Button variant="outline" asChild>
                  <Link href="/sell-return">Cancelar</Link>
                </Button>
              </Stack>
            </div>
        </Stack>
      </Grid>
    </form>
  );
}

// ──────────────────────────────────────────────────────────────
// NF-E DE DEVOLUÇÃO (onda 1 do protótipo Devolução V3)
// ──────────────────────────────────────────────────────────────
// Fica fora do formulário: vale para a devolução JÁ SALVA, nunca para o que está sendo editado.
// O servidor diz se dá para emitir e por quê não; o botão só chama a rota do Fiscal.
function SecaoNfeDevolucao({ nfe }: { nfe: NfeDevolucao }) {
  const [enviando, setEnviando] = useState(false);

  function emitir() {
    setEnviando(true);
    router.post(`/fiscal/acoes/venda-devolucao/${nfe.devolucao_id}/nfe`, {}, {
      preserveScroll: true,
      onFinish: () => setEnviando(false),
    });
  }

  return (
    <div className="mt-4" data-contract="nfe-devolucao">
      <FormSection title="NF-e de devolução">
        {nfe.disponivel ? (
          <Inline justify="between" gap={3} className="flex-wrap items-center">
            <p className="text-sm">
              Declara {nfe.itens} {nfe.itens === 1 ? 'item' : 'itens'} da devolução salva
              {nfe.nota_origem ? `, referenciando a NF-e ${nfe.nota_origem}` : ''}. Base e alíquotas vêm da nota de
              origem.
            </p>
            <Button type="button" onClick={emitir} disabled={enviando}>
              {enviando ? 'Emitindo…' : 'Emitir NF-e de devolução'}
            </Button>
          </Inline>
        ) : (
          <p className="text-sm text-muted-foreground">{nfe.motivo}</p>
        )}
      </FormSection>
    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// PÁGINA
// ──────────────────────────────────────────────────────────────
/** Quando a devolução junta várias vendas, diz quantas ainda vêm depois desta. */
function AvisoFila() {
  const faltam = typeof window === 'undefined' ? 0 : lerFila(window.location.search).length;
  if (!faltam) return null;
  return (
    <Alert className="mt-4" data-contract="aviso-fila">
      <AlertTitle>Devolução de várias vendas</AlertTitle>
      <AlertDescription>
        {faltam === 1
          ? 'Depois de salvar esta venda, abre a próxima venda escolhida.'
          : `Depois de salvar esta venda, abrem as próximas ${faltam} vendas escolhidas, uma de cada vez.`}
      </AlertDescription>
    </Alert>
  );
}

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

      <AvisoFila />

      {/* Aviso do protótipo (venda-blade-caixa.jsx, VendaDevolucaoForm), texto literal. */}
      <Alert className="mt-4" data-contract="aviso-devolucao">
        <TriangleAlert aria-hidden="true" />
        <AlertTitle>Devolução de venda</AlertTitle>
        <AlertDescription>
          A devolução volta o item pro estoque e gera crédito pro cliente. O que já foi produzido sob medida costuma não
          voltar — confira antes de confirmar.
        </AlertDescription>
      </Alert>

      <Deferred data="venda" fallback={<Skeleton className="mt-4 h-96 w-full" />}>
        {venda ? (
          <>
            <FormularioDevolucao venda={venda} />
            {venda.nfe_devolucao && <SecaoNfeDevolucao nfe={venda.nfe_devolucao} />}
          </>
        ) : (
          <></>
        )}
      </Deferred>
    </AppShellV2>
  );
}
