/**
 * ComissaoDrawer — onda 5 do preview `/sells/create-v3`.
 *
 * Porte de `prototipo-ui/cowork/Felipe/venda-v3/sells-comissao.jsx`. O cálculo mora em
 * `comissao-dominio.ts`, provado em `tests/js/comissao-dominio.test.ts` (16/16).
 *
 * POR QUE ISTO NÃO É UM CAMPO "COMISSIONISTA"
 * Quem VENDEU, quem TROUXE o cliente e quem EXECUTOU raramente são a mesma pessoa —
 * e cada um tem regra própria. Um select único não expressa isso, e o resultado é
 * comissão calculada fora do sistema, em planilha.
 *
 * Três decisões que a tela deixa explícitas porque são de NEGÓCIO, não de UI:
 *   - BASE por beneficiário — comissão sobre **bruto** paga o vendedor para dar desconto
 *     (o desconto sai do bolso da empresa, não do dele); sobre **margem**, alinha;
 *   - GATILHO — por **recebimento** é o que impede pagar comissão de venda que o cliente
 *     nunca pagou; por emissão/faturamento o direito nasce inteiro no evento;
 *   - FAIXA — é por valor TOTAL, não progressiva por fatia.
 *
 * ⚠️ TIER 0 — VALOR: comissão é dinheiro devido a alguém. A tela segue sem gravar.
 */

import { useMemo, type ReactNode } from 'react';

import { Grid, Inline, Stack } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';

import {
  BASES,
  GATILHOS,
  TIPOS_BENEFICIARIO,
  baseDoBeneficiario,
  comeMaisQueMetadeDaMargem,
  comissaoDoBeneficiario,
  comissaoLiberada,
  comissaoTotal,
  percentualDaFaixa,
  percentualSobreALiquida,
  type BaseComissao,
  type Beneficiario,
  type Gatilho,
  type TipoBeneficiario,
  type TotaisDaVenda,
} from './comissao-dominio';
import { brl, num } from './numeros';
import { Lbl, Pill } from './primitivos';
import { useCockpit } from './cockpit-portal';

function Campo({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <Lbl>{label}</Lbl>
      {children}
    </div>
  );
}

function Escolha<T extends string>({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value: T;
  onChange: (v: T) => void;
  options: readonly { value: string; label: string }[];
}) {
  return (
    <Campo label={label}>
      <Select value={value} onValueChange={(v) => onChange(v as T)}>
        <SelectTrigger className="w-full">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {options.map((o) => (
            <SafeSelectItem key={o.value} value={o.value} className="text-[12.5px]">
              {o.label}
            </SafeSelectItem>
          ))}
        </SelectContent>
      </Select>
    </Campo>
  );
}

const REGRAS = [
  { value: 'percentual', label: 'Percentual (%)' },
  { value: 'fixo', label: 'Valor fixo (R$)' },
  { value: 'faixa', label: 'Faixa por volume' },
] as const;

export default function ComissaoDrawer({
  aberto,
  onFechar,
  totais,
  beneficiarios,
  onBeneficiariosChange,
  gatilho,
  onGatilhoChange,
  parcelas,
  totalDaVenda,
  pessoas,
  margemConhecida = true,
}: {
  aberto: boolean;
  onFechar: () => void;
  totais: TotaisDaVenda;
  beneficiarios: Beneficiario[];
  onBeneficiariosChange: (b: Beneficiario[]) => void;
  gatilho: Gatilho;
  onGatilhoChange: (g: Gatilho) => void;
  parcelas: { valor: string; lanc: string }[];
  totalDaVenda: number;
  pessoas: { id: string; nome: string; papel: string }[];
  /** Gravando (UC-V408): a tela não lê o custo dos produtos, então não há margem. Sem ela,
   *  some a base "Margem" e o alerta de margem, que na cena usava 30% fixos da base. */
  margemConhecida?: boolean;
}) {
  const cockpit = useCockpit();

  const total = useMemo(() => comissaoTotal(beneficiarios, totais), [beneficiarios, totais]);
  const pctVenda = percentualSobreALiquida(total, totais);
  const liberada = comissaoLiberada(total, gatilho, parcelas, totalDaVenda);
  const alerta = margemConhecida && comeMaisQueMetadeDaMargem(total, totais);
  const bases = margemConhecida ? BASES : BASES.filter((b) => b.value !== 'margem');

  /* Um botão por tipo, como a âncora (`ComissaoModal`, venda-v3.jsx). Os padrões do
     beneficiário novo (base líquida, 3%) ficam os de antes: o protótipo põe agência em margem
     a 5%, e mudar o padrão mudaria o valor da comissão (REGRA MESTRE) — fica de fora da forma. */
  const adicionar = (tipo: TipoBeneficiario) => {
    const nome = pessoas[0]?.nome ?? '';
    onBeneficiariosChange([
      ...beneficiarios,
      { k: Date.now(), tipo, nome, base: 'liquido', regra: 'percentual', pct: '3', valor: '0' },
    ]);
  };

  const alterar = (k: number, patch: Partial<Beneficiario>) =>
    onBeneficiariosChange(beneficiarios.map((b) => (b.k === k ? { ...b, ...patch } : b)));

  const remover = (k: number) => onBeneficiariosChange(beneficiarios.filter((b) => b.k !== k));

  return (
    <Dialog open={aberto} onOpenChange={(v) => !v && onFechar()}>
      <DialogContent container={cockpit} className="venda-v3 max-h-[88vh] sm:max-w-[1000px]">
        <DialogHeader>
          <DialogTitle>Comissão desta venda</DialogTitle>
        </DialogHeader>

        <Stack gap={4} className="min-h-0 overflow-auto">
          {/* ─── topo: bases da venda + gatilho (faixa da âncora) ─────────── */}
          <Inline gap={4} align="end" wrap data-testid="comissao-bases" className="rounded-lg border border-border bg-muted/40 p-3">
            {([
              ['Bruto', totais.bruto],
              ['Líquido de desconto', totais.liquido],
              ['Margem estimada', margemConhecida ? totais.margem : null],
            ] as const).map(([l, v]) => (
              <div key={l}>
                <Lbl className="mb-0">{l}</Lbl>
                <b className="text-[13.5px] tabular-nums">{v === null ? '—' : brl(v)}</b>
              </div>
            ))}
            <div className="ml-auto min-w-[240px]">
              <Escolha label="Quando a comissão passa a ser devida" value={gatilho} onChange={onGatilhoChange} options={GATILHOS} />
            </div>
          </Inline>
          <span className="-mt-2 block text-[11.5px] leading-snug text-muted-foreground">
            {gatilho === 'recebimento'
              ? 'Cada parcela recebida libera a fatia dela — venda inadimplente não gera comissão.'
              : 'O direito nasce inteiro no evento, mesmo antes de o cliente pagar.'}
          </span>

          {/* ─── beneficiários ───────────────────────────────────────────── */}
          <Inline gap={2} align="center" className="flex-wrap">
            <Lbl className="mb-0">Beneficiários</Lbl>
            <Inline gap={1} wrap aria-label="Adicionar beneficiário" className="ml-auto">
              {TIPOS_BENEFICIARIO.map((t) => (
                <Button key={t.k} type="button" variant="outline" size="sm" className="h-[26px] rounded-full px-[9px] text-[12px]" onClick={() => adicionar(t.k)}>
                  + {t.l.split(' (')[0]}
                </Button>
              ))}
            </Inline>
          </Inline>

          {beneficiarios.length === 0 ? (
            <div className="rounded-lg border border-dashed border-border p-6 text-center">
              <span className="block text-[12.5px] text-muted-foreground">
                Sem beneficiário — esta venda não gera comissão.
              </span>
            </div>
          ) : (
            <Stack gap={3}>
              {beneficiarios.map((b) => {
                const tipo = TIPOS_BENEFICIARIO.find((t) => t.k === b.tipo) ?? TIPOS_BENEFICIARIO[0];
                const valor = comissaoDoBeneficiario(b, totais);
                return (
                  <div key={b.k} className="rounded-lg border border-border p-3">
                    <Inline gap={2} align="center" className="mb-2 flex-wrap">
                      <Pill tom="info">{tipo.l}</Pill>
                      <span className="text-[11px] text-muted-foreground">
                        paga via {tipo.pgto} · {tipo.doc}
                      </span>
                      <b className="ml-auto text-[14px] tabular-nums">{brl(valor)}</b>
                      <Button type="button" variant="outline" size="icon" className="size-6" onClick={() => remover(b.k)} title="Remover beneficiário">
                        ×
                      </Button>
                    </Inline>

                    <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-4">
                      <Escolha
                        label="Pessoa"
                        value={b.nome}
                        onChange={(v) => alterar(b.k, { nome: v })}
                        options={pessoas.map((p) => ({ value: p.nome, label: `${p.nome} · ${p.papel}` }))}
                      />
                      <Escolha label="Base" value={b.base} onChange={(v) => alterar(b.k, { base: v as BaseComissao })} options={bases} />
                      <Escolha label="Regra" value={b.regra} onChange={(v) => alterar(b.k, { regra: v as Beneficiario['regra'] })} options={REGRAS} />
                      {b.regra === 'fixo' ? (
                        <Campo label="Valor (R$)">
                          <Input className="text-right" value={b.valor} onChange={(e) => alterar(b.k, { valor: e.target.value })} inputMode="decimal" />
                        </Campo>
                      ) : b.regra === 'faixa' ? (
                        <Campo label="Faixa aplicada">
                          {/* a faixa sai da BASE do beneficiário, não do valor já calculado —
                              reconstruir a base a partir do resultado seria dar a volta pra
                              chegar no número que já se tem. */}
                          {/* Este span é um dos 3 ramos do MESMO `<Campo>` — alterna com
                              dois `<Input>`, que agora medem 34,19px. `h-8` (32px) o
                              deixava 2,19px mais baixo que os irmãos. Replica a caixa
                              deles (13px/1.4 + 7px + borda transparente = 34,19px) em vez
                              de fixar altura, que descolaria de novo no próximo ajuste. */}
                          <span className="block border border-transparent py-[7px] text-[13px] leading-[1.4]">
                            {num(percentualDaFaixa(baseDoBeneficiario(b, totais)), 0)}% por volume
                          </span>
                        </Campo>
                      ) : (
                        <Campo label="Percentual (%)">
                          <Input className="text-right" value={b.pct} onChange={(e) => alterar(b.k, { pct: e.target.value })} inputMode="decimal" />
                        </Campo>
                      )}
                    </Grid>
                  </div>
                );
              })}
            </Stack>
          )}

          {/* ─── avisos (na âncora, `Alert` no fim do corpo) ─────────────── */}
          {alerta && (
            <div role="status" className="rounded-lg border border-warning/40 bg-warning-soft p-3 text-[12.5px] leading-snug text-warning-fg">
              A comissão come mais da metade da margem desta venda.
            </div>
          )}
          {!margemConhecida && (
            <span className="block text-[11.5px] leading-snug text-muted-foreground">
              A margem depende do custo dos produtos, que a nova venda ainda não lê.
            </span>
          )}
        </Stack>

        {/* Rodapé da âncora: o total e um só botão. O "Confirmar comissão" que havia ao lado
            fazia o mesmo que "Fechar" (`onFechar`): o que se configura vale na hora. */}
        <DialogFooter className="sm:items-center">
          <div data-testid="comissao-total" className="mr-auto">
            <Lbl className="mb-0">Total de comissão</Lbl>
            <b className="text-[17px] tabular-nums">{brl(total)}</b>
            {total > 0 && <span className="ml-1 text-[11.5px] text-muted-foreground">· {num(pctVenda, 2)}% da venda</span>}
            {gatilho === 'recebimento' && (
              <span className="ml-3 text-[11.5px] text-muted-foreground">
                Liberada até agora <b className="tabular-nums text-foreground">{brl(liberada)}</b>
              </span>
            )}
          </div>
          <Button type="button" onClick={onFechar}>
            Fechar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
