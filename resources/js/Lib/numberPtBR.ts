// Number parse/format pt-BR — paridade com `accounting.unformat` + `accounting.formatNumber`
// usados em `public/js/functions.js` (`__read_number` + `__write_number`) do Blade legacy
// UltimatePOS. Convenção: vírgula = decimal, ponto = milhar.
//
// Bug origem (Larissa @ Rota Livre biz=4 — 2026-05-27): input native `type="number"` +
// `Number(e.target.value)` interpretava "25,00" como NaN ou "25.000" como vinte-e-cinco-mil.
// Resultado: venda registrada R$ [redacted Tier 0] quando a real era R$ [redacted Tier 0]
//
// Estratégia (igual Blade legacy):
//   - "25,00"           → 25      (vírgula = decimal)
//   - "1.234,56"        → 1234.56 (ponto = milhar, vírgula = decimal)
//   - "25.000,00"       → 25000   (idem)
//   - "25.000"          → 25000   (sem vírgula, ponto = milhar)
//   - "25"              → 25
//   - ""                → 0
//   - "abc"             → NaN
//
// NÃO trata locale en-US ("1,234.56" = 1234.56). Sistema é pt-BR canônico.

const DEFAULT_PRECISION = 2;

/**
 * Parse string pt-BR em number. Tolera espaços e símbolo de moeda.
 *
 *   parseDecimalPtBR("25,00")     // 25
 *   parseDecimalPtBR("1.234,56")  // 1234.56
 *   parseDecimalPtBR("R$ [redacted Tier 0]")  // 25
 *   parseDecimalPtBR("")          // 0
 *   parseDecimalPtBR("abc")       // NaN
 */
export function parseDecimalPtBR(input: string | number | null | undefined): number {
  if (typeof input === 'number') return input;
  if (input == null) return 0;

  const trimmed = String(input).trim();
  if (trimmed === '') return 0;

  // Remove símbolo moeda + espaços + qualquer caractere não-numérico exceto . , -
  const cleaned = trimmed.replace(/[^0-9.,\-]/g, '');
  if (cleaned === '' || cleaned === '-') return NaN;

  // Detecta sinal
  const negative = cleaned.startsWith('-');
  const abs = negative ? cleaned.slice(1) : cleaned;

  // Convenção pt-BR: vírgula = decimal, ponto = milhar.
  // Se tem vírgula: tudo antes é parte inteira (com pontos como milhar), depois é decimal.
  // Se NÃO tem vírgula: ponto é separador de milhar.
  let normalized: string;
  const commaIdx = abs.lastIndexOf(',');
  if (commaIdx >= 0) {
    const intPart = abs.slice(0, commaIdx).replace(/\./g, '');
    const decPart = abs.slice(commaIdx + 1).replace(/\./g, ''); // garante sanity
    normalized = `${intPart}.${decPart}`;
  } else {
    // Sem vírgula → ponto é milhar. Remove todos os pontos.
    normalized = abs.replace(/\./g, '');
  }

  const n = Number(normalized);
  if (Number.isNaN(n)) return NaN;
  return negative ? -n : n;
}

/**
 * Formata number como string pt-BR. Equivalente a `accounting.formatNumber(n, precision, '.', ',')`
 * do Blade legacy.
 *
 *   formatDecimalPtBR(25)         // "25,00"
 *   formatDecimalPtBR(1234.56)    // "1.234,56"
 *   formatDecimalPtBR(25000)      // "25.000,00"
 *   formatDecimalPtBR(25, 0)      // "25"
 *   formatDecimalPtBR(NaN)        // "0,00"
 */
export function formatDecimalPtBR(value: number | null | undefined, precision: number = DEFAULT_PRECISION): string {
  const n = typeof value === 'number' && Number.isFinite(value) ? value : 0;
  return n.toLocaleString('pt-BR', {
    minimumFractionDigits: precision,
    maximumFractionDigits: precision,
  });
}
