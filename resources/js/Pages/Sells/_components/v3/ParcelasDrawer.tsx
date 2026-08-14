/**
 * ParcelasDrawer — onda 3 do preview `/sells/create-v3` (CU-SELL-09).
 *
 * Porte de `prototipo-ui/cowork/venda-v3/sells-parcelas.jsx`. O domínio — e todo o
 * cálculo — mora em `parcelas-dominio.ts`, provado em `tests/js/parcelas-dominio.test.ts`.
 *
 * ⚠️ TIER 0 — VALOR. Esta onda divide DINHEIRO, diferente da onda 2 (que só derivava
 * peso). A REGRA MESTRE exige prova por dois caminhos independentes: `ratear()` foi
 * provado contra aritmética à mão em centavos inteiros, para 11 totais × 48 quantidades,
 * mais o caso `100 / 3` que demonstra o centavo perdido do jeito ingênuo.
 *
 * O que protege enquanto a persistência não existe: a tela **não grava**. O drawer
 * devolve as parcelas para o state da Page e nada além disso — sem POST, sem `store()`.
 *
 * O `Confirmar` só libera quando a soma FECHA no total (tolerância de meio centavo, que
 * existe porque `0.1 + 0.2 !== 0.3` em float). Deixar confirmar com diferença seria
 * exportar para o financeiro um plano de pagamento que não paga a venda.
 */

import { useMemo, useState, type ReactNode } from 'react';

import { Grid, Inline, Stack } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import EmptyState from '@/Components/shared/EmptyState';
/* O Dialog volta — não como o drawer (a C1 tirou ele daí, e com razão), mas
   como o MODAL da parcela: decisão pontual sobre uma linha, com o drawer atrás
   ainda visível. É a mesma composição da âncora (Drawer + Modal aninhado). */
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Textarea } from '@/Components/ui/textarea';
import { Input } from '@/Components/ui/input';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';

import { brl, fmtBR, parseBR, submitSafe } from './numeros';
import {
  CONDICOES,
  CONTAS,
  LANCAMENTOS,
  PLANOS,
  dataISO,
  diaZero,
  METODOS_PAGAMENTO,
  dataBR,
  diferencaParaOTotal,
  fechaNoTotal,
  mesmoDiaNoMes,
  quantidadeSaneada,
  ratear,
  somaDasParcelas,
  somarDias,
  venceuAntesDeHoje,
  type Parcela,
} from './parcelas-dominio';
import { Lbl, Pill } from './primitivos';

function Campo({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <Lbl>{label}</Lbl>
      {children}
    </div>
  );
}

function Escolha({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  options: string[];
}) {
  return (
    <Campo label={label}>
      <Select value={value} onValueChange={onChange}>
        <SelectTrigger className="w-full">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {options.map((o) => (
            <SafeSelectItem key={o} value={o} className="text-[12.5px]">
              {o}
            </SafeSelectItem>
          ))}
        </SelectContent>
      </Select>
    </Campo>
  );
}

export default function ParcelasDrawer({
  aberto,
  onFechar,
  total,
  parcelas,
  onParcelasChange,
  documentoBase,
}: {
  aberto: boolean;
  onFechar: () => void;
  total: number;
  parcelas: Parcela[];
  onParcelasChange: (p: Parcela[]) => void;
  documentoBase: string;
}) {
  const [condicao, setCondicao] = useState('18');
  const [quantidade, setQuantidade] = useState('2');
  const [intervalo, setIntervalo] = useState('30');
  const [porMes, setPorMes] = useState(true);
  const [conta, setConta] = useState(CONTAS[0] ?? '');
  const [primeiroVenc, setPrimeiroVenc] = useState(() => dataISO(new Date()));

  const cond = useMemo(() => CONDICOES.find((c) => c.id === condicao) ?? CONDICOES[0]!, [condicao]);
  const soma = somaDasParcelas(parcelas);
  const diferenca = diferencaParaOTotal(total, parcelas);
  const fecha = fechaNoTotal(total, parcelas);

  const aplicarCondicao = (id: string) => {
    const c = CONDICOES.find((x) => x.id === id) ?? CONDICOES[0]!;
    setCondicao(id);
    setQuantidade(String(c.parcelas));
    setIntervalo(String(c.intervalo));
  };

  const gerar = () => {
    const qtd = quantidadeSaneada(quantidade);
    const passoDias = Math.max(0, Math.round(parseBR(intervalo)) || 0);
    const base = diaZero(primeiroVenc);
    const agora = Date.now();

    /* "mesmo dia de cada mês" NÃO é somar 30 dias — dia 31 seria empurrado pro mês
       seguinte e mudaria a competência. Ver `mesmoDiaNoMes` e a prova no teste. */
    const vencimentoDe = (i: number) => (porMes ? mesmoDiaNoMes(base, i) : somarDias(base, i * passoDias));

    onParcelasChange(
      ratear(total, qtd).map((valor, i) => ({
        k: agora + i,
        num: i + 1,
        de: qtd,
        valor: fmtBR(valor),
        venc: dataISO(vencimentoDe(i)),
        pgto: null,
        tipo: cond.tipo,
        lanc: LANCAMENTOS[0]!,
        plano: PLANOS[0]!,
        conta,
        doc: `${documentoBase} ${i + 1}/${qtd}`,
        resp: '',
        hist: '',
      })),
    );
  };

  /** Joga a diferença na ÚLTIMA parcela — o ajuste manual que fecha a conta. */
  const jogarDiferencaNaUltima = () => {
    if (parcelas.length === 0) return;
    const ultima = parcelas[parcelas.length - 1]!;
    const novo = submitSafe(parseBR(ultima.valor) + diferenca);
    onParcelasChange(parcelas.map((p, i) => (i === parcelas.length - 1 ? { ...p, valor: fmtBR(novo) } : p)));
  };

  /* Rascunho do editor de parcela. Ele NÃO escreve na lista enquanto o usuário
     digita: só no Confirmar. Editar valor a cada tecla faria a conferência
     "soma × total" piscar vermelho no meio da digitação e, pior, gravaria
     estados intermediários num campo Tier 0. */
  const [emEdicao, setEmEdicao] = useState<Parcela | null>(null);

  /* Grava a parcela inteira pelo MESMO caminho que os inputs da linha usam —
     `onParcelasChange` com a lista mapeada. Um só ponto de escrita: se um dia
     a persistência mudar, muda num lugar. */
  const salvarEdicao = () => {
    if (!emEdicao) return;
    onParcelasChange(parcelas.map((p) => (p.k === emEdicao.k ? emEdicao : p)));
    setEmEdicao(null);
  };

  const editarValor = (k: number, valor: string) =>
    onParcelasChange(parcelas.map((p) => (p.k === k ? { ...p, valor } : p)));

  const editarVenc = (k: number, venc: string) =>
    onParcelasChange(parcelas.map((p) => (p.k === k ? { ...p, venc } : p)));

  return (
    <>
    <Sheet open={aberto} onOpenChange={(v) => !v && onFechar()}>
      {/* `venda-v3` FICA aqui: o Radix portala o conteúdo pro <body>, FORA do
          wrapper da Page — sem a classe, nada de `venda-v3.css` alcança o que
          está dentro (§5 proibicoes, 2026-07-10). */}
      <SheetContent
        side="right"
        className="venda-v3 w-full overflow-hidden p-0 sm:max-w-[860px]"
      >
        {/* coluna é `Stack`, não `flex flex-col` na mão — `layout:check`/ADR 0253. */}
        <Stack gap={0} className="h-full">
          <SheetHeader className="shrink-0 border-b border-border px-5 py-4 text-left">
            <SheetTitle className="text-[15px] leading-tight">
              Financeiro da venda — parcelas
            </SheetTitle>
            <SheetDescription className="text-[12px] text-muted-foreground">
              Total a parcelar {brl(total)}
            </SheetDescription>
          </SheetHeader>

          <Stack gap={4} className="min-h-0 flex-1 overflow-auto px-5 py-4">
          {/* ─── gerador ─────────────────────────────────────────────────── */}
          <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-4">
            <Escolha
              label="Condição"
              value={condicao}
              onChange={aplicarCondicao}
              options={CONDICOES.map((c) => c.id)}
            />
            <Campo label="Parcelas">
              <Input
                value={quantidade}
                onChange={(e) => setQuantidade(e.target.value)}
                inputMode="numeric"
              />
            </Campo>
            <Campo label="Intervalo (dias)">
              <Input
                value={intervalo}
                onChange={(e) => setIntervalo(e.target.value)}
                inputMode="numeric"
                disabled={porMes}
              />
            </Campo>
            <Campo label="1º vencimento">
              <Input
                type="date"
                value={primeiroVenc}
                onChange={(e) => setPrimeiroVenc(e.target.value)}
              />
            </Campo>
          </Grid>

          <Inline gap={3} align="center" className="flex-wrap">
            <Inline gap={2} align="center">
              <Checkbox id="v3-por-mes" checked={porMes} onCheckedChange={(v) => setPorMes(v === true)} />
              <label htmlFor="v3-por-mes" className="cursor-pointer text-[12px] leading-tight">
                Vence no mesmo dia de cada mês
              </label>
            </Inline>
            <div className="min-w-[220px] flex-1">
              <Escolha label="Caixa / conta de destino" value={conta} onChange={setConta} options={CONTAS} />
            </div>
            <Button type="button" onClick={gerar} className="mt-4">
              Gerar parcelas
            </Button>
            {parcelas.length > 0 && (
              <Button type="button" variant="outline" className="mt-4" onClick={() => onParcelasChange([])}>
                Limpar
              </Button>
            )}
          </Inline>

          {/* ─── grade ───────────────────────────────────────────────────── */}
                    {/* Sem parcelas a tabela sumia inteira — a tela muda e não diz por quê.
              A âncora (`sells-parcelas.jsx:99`) põe um EmptyState que ENSINA o
              caminho; é o que o operador precisa na primeira venda do dia. */}
          {parcelas.length === 0 && (
            <EmptyState
              icon="inbox"
              title="Nenhuma parcela gerada"
              description="Escolha a condição, o número de parcelas e o 1º vencimento e clique em Gerar parcelas. Depois você pode editar valor, data e conta de cada uma."
            />
          )}

          {parcelas.length > 0 && (
            <div className="overflow-x-auto rounded-lg border border-border">
              <table className="w-full text-[12.5px]">
                <thead className="bg-muted/60 text-left text-[11px] tracking-wide text-muted-foreground uppercase">
                  <tr>
                    <th className="px-3 py-2 font-medium">#</th>
                    <th className="px-3 py-2 font-medium">Documento</th>
                    <th className="px-3 py-2 font-medium">Vencimento</th>
                    <th className="px-3 py-2 font-medium">Valor</th>
                    <th className="px-3 py-2 font-medium">Tipo</th>
                    <th className="px-3 py-2 font-medium">Conta</th>
                    <th className="w-10 px-3 py-2" />
                    <th className="px-3 py-2 font-medium">Lançamento</th>
                  </tr>
                </thead>
                <tbody>
                  {parcelas.map((p) => (
                    <tr key={p.k} className="border-t border-border">
                      <td className="px-3 py-1.5 font-mono">
                        {p.num}/{p.de}
                      </td>
                      <td className="px-3 py-1.5 font-mono">{p.doc}</td>
                      <td className="px-3 py-1.5">
                        <Inline gap={2} align="center">
                          <Input
                            type="date"
                            className="h-7 w-[140px] text-[12px]"
                            value={p.venc}
                            onChange={(e) => editarVenc(p.k, e.target.value)}
                          />
                          {venceuAntesDeHoje(p.venc) && <Pill tom="destructive">vencida</Pill>}
                        </Inline>
                      </td>
                      <td className="px-3 py-1.5">
                        <Input
                          className="h-7 w-[110px] text-right text-[12px]"
                          value={p.valor}
                          onChange={(e) => editarValor(p.k, e.target.value)}
                          inputMode="decimal"
                        />
                      </td>
                      <td className="px-3 py-1.5">{p.tipo}</td>
                      <td className="px-3 py-1.5">
                        {/* "recebida 12/08" > "RECEBIDA": a data é o que responde "isso já
                              entrou?" sem abrir outra tela (âncora `sells-parcelas.jsx:120`). */}
                          <Pill tom={p.lanc === 'RECEBIDA' ? 'success' : 'neutro'}>
                            {p.lanc === 'RECEBIDA' && p.pgto ? `recebida ${dataBR(p.pgto)}` : p.lanc}
                          </Pill>
                      </td>
                      <td className="px-3 py-1.5 text-center">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <button
                              type="button"
                              title="Ações da parcela"
                              className="inline-flex size-7 cursor-pointer items-center justify-center rounded-md border border-border bg-card text-muted-foreground hover:text-foreground"
                            >
                              ⋯
                            </button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem onSelect={() => setEmEdicao(p)}>
                              Editar detalhes…
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* ─── conferência ─────────────────────────────────────────────── */}
          {parcelas.length > 0 && (
            <Inline
              gap={3}
              align="center"
              className={
                'flex-wrap rounded-lg border p-3 ' +
                (fecha ? 'border-success/30 bg-success-soft' : 'border-warning/40 bg-warning-soft')
              }
            >
              <div>
                <Lbl className="mb-0">Soma das parcelas</Lbl>
                <b className="text-[15px] tabular-nums">{brl(soma)}</b>
              </div>
              <div>
                <Lbl className="mb-0">Total da venda</Lbl>
                <b className="text-[15px] tabular-nums">{brl(total)}</b>
              </div>
              {!fecha && (
                <>
                  <div>
                    <Lbl className="mb-0">{diferenca > 0 ? 'Falta distribuir' : 'Passou do total'}</Lbl>
                    <b className="text-[15px] tabular-nums text-warning-fg">{brl(Math.abs(diferenca))}</b>
                  </div>
                  <Button type="button" variant="outline" size="sm" className="ml-auto" onClick={jogarDiferencaNaUltima}>
                    Jogar {brl(Math.abs(diferenca))} na última
                  </Button>
                </>
              )}
              {fecha && (
                <span className="ml-auto">
                  <Pill tom="success">as parcelas fecham no total</Pill>
                </span>
              )}
            </Inline>
          )}
        </Stack>

          <SheetFooter className="shrink-0 flex-row items-center justify-between gap-2 border-t border-border px-5 py-3">
          <Button type="button" variant="outline" onClick={onFechar}>
            Fechar
          </Button>
          {/* Confirmar só libera fechando no total — plano de pagamento que não paga a
              venda não pode sair daqui como se estivesse pronto. */}
          <Button
            type="button"
            onClick={onFechar}
            disabled={parcelas.length === 0 || !fecha}
            title={!fecha && parcelas.length > 0 ? 'A soma das parcelas precisa fechar no total da venda' : undefined}
          >
            Confirmar parcelas
          </Button>
          </SheetFooter>
        </Stack>
      </SheetContent>
    </Sheet>

    {/* ─── editor da parcela ────────────────────────────────────────────────
        A âncora (`sells-parcelas.jsx:151`) abre um modal por parcela, e é o que
        torna as parcelas EDITÁVEIS de verdade: gerar 6x é o começo, ajustar a
        3ª pra outro plano de contas com histórico é o trabalho real.

        Modal (não outro drawer) porque é decisão pontual sobre UMA linha, com
        o drawer atrás ainda visível — a mesma escolha da âncora. */}
    <Dialog open={!!emEdicao} onOpenChange={(v) => !v && setEmEdicao(null)}>
      <DialogContent className="venda-v3 sm:max-w-[620px]">
        <DialogHeader>
          <DialogTitle>
            {emEdicao ? `Parcela ${emEdicao.num}/${emEdicao.de}` : 'Parcela'}
          </DialogTitle>
        </DialogHeader>

        {emEdicao && (
          <Stack gap={3}>
            <Grid gap={3} className="sm:grid-cols-2">
              <div>
                <Lbl>Responsável</Lbl>
                <Input
                  value={emEdicao.resp}
                  placeholder="Cliente da venda"
                  onChange={(e) => setEmEdicao({ ...emEdicao, resp: e.target.value })}
                />
              </div>
              <Escolha
                label="Lançamento"
                value={emEdicao.lanc}
                onChange={(v) => setEmEdicao({ ...emEdicao, lanc: v })}
                options={LANCAMENTOS}
              />
            </Grid>

            <Grid gap={3} className="sm:grid-cols-3">
              <Escolha
                label="Tipo de pagamento"
                value={emEdicao.tipo}
                onChange={(v) => setEmEdicao({ ...emEdicao, tipo: v })}
                options={METODOS_PAGAMENTO}
              />
              <div>
                <Lbl>Documento</Lbl>
                <Input
                  value={emEdicao.doc}
                  onChange={(e) => setEmEdicao({ ...emEdicao, doc: e.target.value })}
                />
              </div>
              <div>
                {/* MESMO `<Input>` da linha, de propósito: dar ao valor um
                    segundo componente de digitação criaria dois comportamentos
                    para o campo Tier 0 desta tela. */}
                <Lbl>Valor</Lbl>
                <Input
                  className="text-right"
                  inputMode="decimal"
                  value={emEdicao.valor}
                  onChange={(e) => setEmEdicao({ ...emEdicao, valor: e.target.value })}
                />
              </div>
            </Grid>

            <Grid gap={3} className="sm:grid-cols-2">
              <div>
                <Lbl>Vencimento</Lbl>
                <Input
                  type="date"
                  value={emEdicao.venc}
                  onChange={(e) => setEmEdicao({ ...emEdicao, venc: e.target.value })}
                />
              </div>
              <div>
                {/* Data do pagamento: é ela que faz a linha virar "recebida 12/08"
                    na lista (onda C2). */}
                <Lbl>Pagamento</Lbl>
                <Input
                  type="date"
                  value={emEdicao.pgto ?? ''}
                  onChange={(e) => setEmEdicao({ ...emEdicao, pgto: e.target.value || null })}
                />
              </div>
            </Grid>

            <Grid gap={3} className="sm:grid-cols-2">
              <Escolha
                label="Plano de contas"
                value={emEdicao.plano}
                onChange={(v) => setEmEdicao({ ...emEdicao, plano: v })}
                options={PLANOS}
              />
              <Escolha
                label="Conta"
                value={emEdicao.conta}
                onChange={(v) => setEmEdicao({ ...emEdicao, conta: v })}
                options={CONTAS}
              />
            </Grid>

            <div>
              <Lbl>Histórico</Lbl>
              <Textarea
                rows={2}
                value={emEdicao.hist}
                placeholder="Cliente pediu boleto por e-mail"
                onChange={(e) => setEmEdicao({ ...emEdicao, hist: e.target.value })}
              />
            </div>
          </Stack>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => setEmEdicao(null)}>
            Cancelar
          </Button>
          <Button type="button" onClick={salvarEdicao}>
            Confirmar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
    </>
  );
}
