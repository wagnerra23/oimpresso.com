// ──────────────────────────────────────────────────────────────
// money — dinheiro SEMPRE em centavos inteiros (bigint no banco).
// Corrige v2 §6 (float em valor). Arredondamento único na borda.
// ──────────────────────────────────────────────────────────────

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
  return (cents / 100).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}
