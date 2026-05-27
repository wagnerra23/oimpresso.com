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
//   - "25.000"          → 25000   (sem vírgula + 3 dígitos depois → milhar pt-BR)
//   - "147.77"          → 147.77  (sem vírgula + ≤2 dígitos depois → decimal en-US tolerado)
//   - "25"              → 25
//   - ""                → 0
//   - "abc"             → NaN
//
// Heurística "ponto decimal en-US tolerado" (Bug Wagner @ test 2026-05-27):
//   user pode digitar "147.77" pensando em decimal en-US. Sem vírgula no input,
//   o sistema antes interpretava ponto como milhar → "147.77" virava 14777.
//   Agora aceitamos ponto como decimal SE o resultado for inequívoco:
//   1 único ponto + 1-2 dígitos depois = decimal. Múltiplos pontos OU 3+ dígitos
//   após = milhar pt-BR (preserva paridade Blade pra "25.000" = vinte e cinco mil).

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
  // Se NÃO tem vírgula: heurística — 1 ponto + ≤2 dígitos depois = decimal en-US tolerado,
  //   senão = milhar pt-BR (paridade Blade pra "25.000" = vinte e cinco mil).
  let normalized: string;
  const commaIdx = abs.lastIndexOf(',');
  if (commaIdx >= 0) {
    const intPart = abs.slice(0, commaIdx).replace(/\./g, '');
    const decPart = abs.slice(commaIdx + 1).replace(/\./g, ''); // garante sanity
    normalized = `${intPart}.${decPart}`;
  } else {
    // Sem vírgula → ponto pode ser milhar OU decimal en-US (heurística inequívoca).
    const dotCount = (abs.match(/\./g) || []).length;
    if (dotCount === 1) {
      const dotIdx = abs.indexOf('.');
      const decPart = abs.slice(dotIdx + 1);
      if (decPart.length <= 2) {
        // "147.77" / "1.5" / "25.0" → decimal en-US tolerado
        normalized = abs;
      } else {
        // "25.000" / "1.234" → milhar pt-BR (3+ dígitos depois)
        normalized = abs.replace(/\./g, '');
      }
    } else {
      // 0 pontos → puro inteiro. Múltiplos pontos → milhar pt-BR ("1.234.567").
      normalized = abs.replace(/\./g, '');
    }
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
