/**
 * money — dinheiro SEMPRE em centavos inteiros (Cents), nunca float.
 *
 * Porte fiel de `ref/handoff-3/backend/packages/shared/src/money.ts` (regra
 * canônica do Wagner). Arredondamento único na borda. Use em todo o cliente;
 * `OiMoney` aceita `cents` e formata via `formatBRL`.
 */

/** Centavos inteiros. Use em todo o sistema; nunca um Number "reais". */
export type Cents = number;

/** Converte reais (entrada do usuário) → centavos, arredondando uma única vez. */
export function reaisToCents(reais: number): Cents {
  return Math.round(reais * 100);
}

/** Converte string PT-BR ("1.234,56") → centavos. */
export function parseBRLToCents(input: string): Cents {
  const normalized = input.trim().replace(/\./g, "").replace(",", ".");
  const n = Number(normalized);
  if (Number.isNaN(n)) throw new Error(`Valor monetário inválido: "${input}"`);
  return reaisToCents(n);
}

/** Soma de centavos (inteiros — sem erro de ponto flutuante). */
export function sumCents(...values: Cents[]): Cents {
  return values.reduce((a, b) => a + b, 0);
}

/** Multiplica centavos por uma quantidade, arredondando o resultado. */
export function mulCents(cents: Cents, qty: number): Cents {
  return Math.round(cents * qty);
}

/** Aplica desconto percentual (0–100) sobre centavos. */
export function applyDiscountPct(cents: Cents, pct: number): Cents {
  return Math.round(cents * (1 - pct / 100));
}

/** Formata centavos como moeda PT-BR ("R$ 1.234,56"). */
export function formatBRL(cents: Cents): string {
  return (cents / 100).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
  });
}

/** Formata centavos compacto ("R$ 1,2k" / "R$ 1,3M"). */
export function formatBRLcompact(cents: Cents): string {
  const reais = cents / 100;
  const abs = Math.abs(reais);
  if (abs >= 1_000_000)
    return `R$ ${(reais / 1_000_000).toLocaleString("pt-BR", { maximumFractionDigits: 1 })}M`;
  if (abs >= 1_000)
    return `R$ ${(reais / 1_000).toLocaleString("pt-BR", { maximumFractionDigits: 1 })}k`;
  return formatBRL(cents);
}

/**
 * Gera N parcelas distribuindo o total SEM perder centavo (a última absorve a
 * sobra). Espelha `OIFlow.gerarParcelas` / `domain/finance/titulos.ts`.
 */
export function gerarParcelasCents(
  totalCents: Cents,
  n: number,
): Cents[] {
  const base = Math.floor(totalCents / n);
  const resto = totalCents - base * n;
  return Array.from({ length: n }, (_, i) =>
    i === n - 1 ? base + resto : base,
  );
}
