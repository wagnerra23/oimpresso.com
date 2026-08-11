/**
 * ComissaoDrawer — onda 5 do preview `/sells/create-v3`.
 *
 * Porte de `prototipo-ui/cowork/venda-v3/sells-comissao.jsx`. O cálculo mora em
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

import { useMemo, useState, type ReactNode } from 'react';

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
        <SelectTrigger className="h-8 w-full text-[12.5px]">
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
}) {
  const [novoTipo, setNovoTipo] = useState<TipoBeneficiario>('funcionario');

  const total = useMemo(() => comissaoTotal(beneficiarios, totais), [beneficiarios, totais]);
  const pctVenda = percentualSobreALiquida(total, totais);
  const liberada = comissaoLiberada(total, gatilho, parcelas, totalDaVenda);
  const alerta = comeMaisQueMetadeDaMargem(total, totais);

  const adicionar = () => {
    const nome = pessoas[0]?.nome ?? '';
    onBeneficiariosChange([
      ...beneficiarios,
      { k: Date.now(), tipo: novoTipo, nome, base: 'liquido', regra: 'percentual', pct: '3', valor: '0' },
    ]);
  };

  const alterar = (k: number, patch: Partial<Beneficiario>) =>
    onBeneficiariosChange(beneficiarios.map((b) => (b.k === k ? { ...b, ...patch } : b)));

  const remover = (k: number) => onBeneficiariosChange(beneficiarios.filter((b) => b.k !== k));

  return (
    <Dialog open={aberto} onOpenChange={(v) => !v && onFechar()}>
      <DialogContent className="max-h-[88vh] sm:max-w-[1000px]">
        <DialogHeader>
          <DialogTitle>Modelo de comissão da venda</DialogTitle>
        </DialogHeader>

        <Stack gap={4} className="min-h-0 overflow-auto">
          {/* ─── gatilho ─────────────────────────────────────────────────── */}
          <Grid gap={3} className="sm:grid-cols-2">
            <Escolha label="Quando a comissão passa a ser devida" value={gatilho} onChange={onGatilhoChange} options={GATILHOS} />
            <div className="self-end">
              <span className="block text-[11.5px] leading-snug text-muted-foreground">
                {gatilho === 'recebimento'
                  ? 'Cada parcela recebida libera a fatia dela — venda inadimplente não gera comissão.'
                  : 'O direito nasce inteiro no evento, mesmo antes de o cliente pagar.'}
              </span>
            </div>
          </Grid>

          {/* ─── beneficiários ───────────────────────────────────────────── */}
          <Inline gap={3} align="end" className="flex-wrap">
            <div className="min-w-[260px] flex-1">
              <Escolha
                label="Adicionar beneficiário"
                value={novoTipo}
                onChange={setNovoTipo}
                options={TIPOS_BENEFICIARIO.map((t) => ({ value: t.k, label: t.l }))}
              />
            </div>
            <Button type="button" onClick={adicionar}>
              Adicionar
            </Button>
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
                      <Escolha label="Base" value={b.base} onChange={(v) => alterar(b.k, { base: v as BaseComissao })} options={BASES} />
                      <Escolha label="Regra" value={b.regra} onChange={(v) => alterar(b.k, { regra: v as Beneficiario['regra'] })} options={REGRAS} />
                      {b.regra === 'fixo' ? (
                        <Campo label="Valor (R$)">
                          <Input className="h-8 text-right text-[12.5px]" value={b.valor} onChange={(e) => alterar(b.k, { valor: e.target.value })} inputMode="decimal" />
                        </Campo>
                      ) : b.regra === 'faixa' ? (
                        <Campo label="Faixa aplicada">
                          {/* a faixa sai da BASE do beneficiário, não do valor já calculado —
                              reconstruir a base a partir do resultado seria dar a volta pra
                              chegar no número que já se tem. */}
                          <span className="block h-8 text-[12.5px] leading-8">
                            {num(percentualDaFaixa(baseDoBeneficiario(b, totais)), 0)}% por volume
                          </span>
                        </Campo>
                      ) : (
                        <Campo label="Percentual (%)">
                          <Input className="h-8 text-right text-[12.5px]" value={b.pct} onChange={(e) => alterar(b.k, { pct: e.target.value })} inputMode="decimal" />
                        </Campo>
                      )}
                    </Grid>
                  </div>
                );
              })}
            </Stack>
          )}

          {/* ─── resumo ──────────────────────────────────────────────────── */}
          <Inline gap={4} align="center" className={'flex-wrap rounded-lg border p-3 ' + (alerta ? 'border-warning/40 bg-warning-soft' : 'border-border bg-muted/40')}>
            <div>
              <Lbl className="mb-0">Comissão da venda</Lbl>
              <b className="text-[15px] tabular-nums">{brl(total)}</b>
              {total > 0 && <span className="ml-1 text-[11.5px] text-muted-foreground">· {num(pctVenda, 2)}% da venda</span>}
            </div>
            {gatilho === 'recebimento' && (
              <div>
                <Lbl className="mb-0">Liberada até agora</Lbl>
                <b className="text-[15px] tabular-nums">{brl(liberada)}</b>
              </div>
            )}
            {alerta && (
              <span className="ml-auto text-[11.5px] leading-snug text-warning-fg">
                A comissão come mais da metade da margem desta venda.
              </span>
            )}
          </Inline>
        </Stack>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onFechar}>
            Fechar
          </Button>
          <Button type="button" onClick={onFechar}>
            Confirmar comissão
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
