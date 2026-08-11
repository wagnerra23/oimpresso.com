/**
 * Domínio de parcelas — onda 3 do preview `/sells/create-v3` (CU-SELL-09).
 *
 * ⚠️ TERRITÓRIO TIER 0 — VALOR. Diferente da onda 2 (que só derivava peso), aqui o
 * cálculo É de dinheiro: dividir o total da venda em N parcelas. A REGRA MESTRE de
 * `memory/proibicoes.md` manda provar por DOIS caminhos independentes antes de aplicar,
 * e é o que o `parcelas-dominio.test.ts` faz — função real × aritmética à mão.
 *
 * O que protege enquanto isso: a tela **não grava** (sem `store()`, sem POST), então
 * nenhum número daqui chega ao banco. Ligar persistência é decisão separada, e aí a
 * REGRA MESTRE volta a valer inteira (dry-run + antes→depois + aprovação).
 *
 * Arquivo separado do componente por causa de `react-refresh/only-export-components` —
 * a catraca `lint:baseline` mordeu exatamente isso na onda 2.
 */

import { parseBR, submitSafe } from './numeros';

export const CONDICOES = [
  { id: '18', nome: 'PIX', parcelas: 1, intervalo: 0, tipo: 'PIX' },
  { id: '02', nome: 'Boleto 30/60', parcelas: 2, intervalo: 30, tipo: 'Boleto' },
  { id: '05', nome: 'Cartão 3x sem juros', parcelas: 3, intervalo: 30, tipo: 'Cartão de crédito' },
  { id: '09', nome: 'Entrada + 2x', parcelas: 3, intervalo: 28, tipo: 'Boleto' },
  { id: '12', nome: 'À vista — dinheiro', parcelas: 1, intervalo: 0, tipo: 'Dinheiro' },
];

export const PLANOS = ['1.1.5 — Recebido em depósito', '1.1.1 — Caixa', '1.2.1 — Duplicatas a receber'];
export const CONTAS = ['1 — Caixa financeiro', '2 — Banco Itaú c/c', '3 — Banco Sicredi'];
export const LANCAMENTOS = ['A RECEBER', 'RECEBIDA'];

/** Teto de parcelas. 48 é o do protótipo; existe pra `n` digitado não virar laço infinito. */
export const MAX_PARCELAS = 48;

export type Parcela = {
  k: number;
  num: number;
  de: number;
  valor: string;
  venc: string;
  pgto: string | null;
  tipo: string;
  lanc: string;
  plano: string;
  conta: string;
  doc: string;
  resp: string;
  hist: string;
};

/**
 * Divide `total` em `n` parcelas **sem perder nem inventar centavo**.
 *
 * A conta roda em **centavos inteiros** e o resto da divisão vai para as PRIMEIRAS
 * parcelas, uma unidade em cada. Isso garante o invariante que dá nome à função:
 *
 *     soma(ratear(total, n)) === total, para todo n ≥ 1
 *
 * ⚠️ O jeito ERRADO — e é o que esta função existe para não fazer — é dividir em float
 * e arredondar cada parcela: `R$ 100,00 / 3` daria `33,33 × 3 = 99,99`, e some **R$ 0,01**.
 * Num ERP isso vira diferença de conciliação que ninguém acha depois.
 *
 * Provado por dois caminhos em `parcelas-dominio.test.ts` (REGRA MESTRE de valor).
 */
export function ratear(total: number, n: number): number[] {
  const partes = Math.max(1, Math.floor(n));
  const centavos = Math.round(submitSafe(total) * 100);
  const base = Math.floor(centavos / partes);
  const resto = centavos - base * partes;

  return Array.from({ length: partes }, (_, i) => (base + (i < resto ? 1 : 0)) / 100);
}

/** Quantidade de parcelas saneada: inteiro, ≥1, ≤48. Texto lixo vira 1, nunca NaN. */
export function quantidadeSaneada(entrada: string): number {
  const n = Math.round(parseBR(entrada));
  if (!Number.isFinite(n) || n < 1) return 1;
  return Math.min(MAX_PARCELAS, n);
}

/** Soma das parcelas, com o mesmo guard de 2 casas do resto da tela. */
export function somaDasParcelas(parcelas: Pick<Parcela, 'valor'>[]): number {
  return submitSafe(parcelas.reduce((s, p) => s + parseBR(p.valor), 0));
}

/**
 * Diferença entre o total da venda e a soma das parcelas.
 *
 * Positiva = falta distribuir; negativa = passou do total. O componente só oferece o
 * "jogar a diferença na última" quando isto é diferente de zero — e a tolerância de
 * meio centavo existe porque `0.1 + 0.2 !== 0.3` em float binário.
 */
export const TOLERANCIA_CENTAVO = 0.005;

export function diferencaParaOTotal(total: number, parcelas: Pick<Parcela, 'valor'>[]): number {
  return submitSafe(total - somaDasParcelas(parcelas));
}

export function fechaNoTotal(total: number, parcelas: Pick<Parcela, 'valor'>[]): boolean {
  return Math.abs(diferencaParaOTotal(total, parcelas)) < TOLERANCIA_CENTAVO;
}

/* ─── datas ──────────────────────────────────────────────────────────────── */

/** Meia-noite local — comparar vencimento com "hoje" exige zerar a hora dos dois lados. */
export function diaZero(v: Date | string | number): Date {
  const d = new Date(v);
  d.setHours(0, 0, 0, 0);
  return d;
}

export function somarDias(base: Date, dias: number): Date {
  const d = new Date(base.getTime());
  d.setDate(d.getDate() + dias);
  return d;
}

/**
 * "Vence no mesmo dia de cada mês" — e é diferente de somar 30 dias.
 *
 * Dia 31 em mês de 30 (ou fevereiro) **não pode virar o mês seguinte**: a data é
 * grampeada no último dia do mês de destino. Somar 30 dias daria 01/07 para um
 * vencimento de 31/05, o que muda a competência e o mês fechado do financeiro.
 */
export function mesmoDiaNoMes(base: Date, mesesAdiante: number): Date {
  const d = diaZero(base);
  const diaDesejado = d.getDate();
  const alvo = new Date(d.getFullYear(), d.getMonth() + mesesAdiante, 1);
  const ultimoDiaDoAlvo = new Date(alvo.getFullYear(), alvo.getMonth() + 1, 0).getDate();
  alvo.setDate(Math.min(diaDesejado, ultimoDiaDoAlvo));
  return diaZero(alvo);
}

export function venceuAntesDeHoje(v: Date | string): boolean {
  return diaZero(v) < diaZero(new Date());
}

export function dataBR(d: Date | string): string {
  return diaZero(d).toLocaleDateString('pt-BR');
}

/** ISO curto (yyyy-mm-dd) — é o que `<input type="date">` consome. */
export function dataISO(d: Date | string): string {
  const x = diaZero(d);
  const mm = String(x.getMonth() + 1).padStart(2, '0');
  const dd = String(x.getDate()).padStart(2, '0');
  return `${x.getFullYear()}-${mm}-${dd}`;
}
