// Formatação da tela de Fabricação. Espelha `MFG.fmt` / `MFG.num` do protótipo
// (prototipo-ui/cowork/manufacturing-data.jsx), mas construída SOBRE o formatador
// canônico do repo — `formatDecimalPtBR` (resources/js/Lib/numberPtBR.ts:96), achado
// por `npm run reuse:check "formatar moeda BRL pt-BR"`. Não há `brl` canônico em Lib/
// (só cópias por módulo em Financeiro/Fiscal/Produto), então aqui só o prefixo é local.
//
// §7.6 do handoff: NADA é arredondado no meio da conta — arredondar é apresentação.
// §7.3: divisão por zero é 0, nunca NaN/Infinity; `formatDecimalPtBR` já devolve "0,00"
// para não-finito, então a defesa vale para os dois lados.

import { formatDecimalPtBR } from '@/Lib/numberPtBR';

/** Dinheiro. `fmt(148.72)` → `"R$ 148,72"`. */
export function fmt(n: number | null | undefined): string {
  return `R$ ${formatDecimalPtBR(n, 2)}`;
}

/** Quantidade com N casas. `num(14.4, 2)` → `"14,40"`. */
export function num(n: number | null | undefined, casas = 2): string {
  return formatDecimalPtBR(n, casas);
}

/**
 * Rótulo do custo extra no drawer (§4.3) — o texto muda com `production_cost_type`,
 * e é ele que explica ao usuário de onde saiu o número.
 */
export function rotuloCustoExtra(tipo: string, extra: number, unidade: string): string {
  if (tipo === 'percentage') return `${num(extra, 0)}% sobre ingredientes`;
  if (tipo === 'per_unit') return `${fmt(extra)} por ${unidade} produzido`;
  return 'valor fixo';
}

/** Faixa de cor da margem — §7.5 `[FECHADA]`: ≥55 positivo · 45–54,9 alerta · <45 negativo. */
export function faixaMargem(margem: number): 'ok' | 'warn' | 'bad' {
  if (margem >= 55) return 'ok';
  if (margem >= 45) return 'warn';
  return 'bad';
}
