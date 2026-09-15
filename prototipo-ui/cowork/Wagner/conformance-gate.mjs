#!/usr/bin/env node
/* conformance-gate.mjs — GATE de cor-crua (anti-regressão de contrato DS).
 * Determinístico, sem browser, sem dependência. Roda em CI (exit≠0 = bloqueia merge) E local.
 * Regra: ratchet — cor crua em REGRAS DE TELA só pode CAIR. Adicionou cor crua = 🔴.
 * Cobre a classe UC-V10 (cor fora de token). NÃO cobre accent/computed-style (= teste de browser, Pest/Playwright).
 *
 * Provado (vendas.css, 2026-06-03) — controle-negativo nos DOIS lados (L-31):
 *   sensibilidade: +1 cor crua em regra de tela → conta sobe (pega o bug);
 *   especificidade: editar doc de impressão (.vd-trans) NÃO sobe · oklch(...var()) NÃO conta (não acusa inocente).
 *
 * Uso:  node conformance-gate.mjs <arquivo.css> [--update]
 * Baseline por arquivo em .conformance-baseline.json (versionado → ratchet auditável).
 *
 * AVISO HONESTO: regex ≠ parser CSS. Escopa regras flat (.sel{...}); @media/aninhado profundo pode escapar.
 * Versão de produção: portar esta regra pro Stylelint #2054 (parser real). Esta é a rede mínima rodável já.
 */
import { readFileSync, writeFileSync, existsSync } from "node:fs";

const BASELINE_FILE = ".conformance-baseline.json";

// Seletores onde cor crua é PERMITIDA (defs de token + exceções declaradas).
const TOKEN_DEF  = /:root|\[data-theme/;
const EXCEPTION  = /\.vd-trans|\.vd-pres/;          // transcript = papel A4 · apresentação = dark próprio
const SCREEN     = /\.vendas-aplus|\.vd-|\.vendas-page/;  // ajustar por tela / generalizar via charter regua/ds

// Conta valores de cor CRUA só nas regras de tela. oklch com var() dentro = token-driven, NÃO conta.
export function rawColorHits(css) {
  const hits = [];
  const re = /([.#:\[][^{}]*?)\{([^{}]*)\}/g;
  let m;
  while ((m = re.exec(css))) {
    const sel = m[1].trim(), body = m[2];
    if (TOKEN_DEF.test(sel) || EXCEPTION.test(sel) || !SCREEN.test(sel)) continue;
    for (const h of body.match(/#[0-9a-fA-F]{3,8}\b/g) || []) hits.push(`${sel.slice(0,40)} ${h}`);
    let i = 0;
    while ((i = body.indexOf("oklch(", i)) !== -1) {
      let depth = 0, j = i + 5, end = -1;
      for (; j < body.length; j++) { if (body[j] === "(") depth++; else if (body[j] === ")") { depth--; if (depth === 0) { end = j; break; } } }
      if (end === -1) break;
      const chunk = body.slice(i, end + 1);
      if (!chunk.includes("var(")) hits.push(`${sel.slice(0,40)} ${chunk}`);  // raw só se NÃO usa var
      i = end + 1;
    }
  }
  return hits;
}

function loadBaseline() { try { return existsSync(BASELINE_FILE) ? JSON.parse(readFileSync(BASELINE_FILE, "utf8")) : {}; } catch { return {}; } }

function main() {
  const file = process.argv[2];
  const update = process.argv.includes("--update");
  if (!file) { console.error("uso: node conformance-gate.mjs <arquivo.css> [--update]"); process.exit(2); }

  const count = rawColorHits(readFileSync(file, "utf8")).length;
  const baseline = loadBaseline();

  if (update) {
    baseline[file] = count;
    writeFileSync(BASELINE_FILE, JSON.stringify(baseline, null, 2) + "\n");
    console.log(`baseline gravado: ${file} = ${count}`);
    process.exit(0);
  }

  const limit = baseline[file] ?? count;     // 1ª vez adota o atual como teto
  const pass = count <= limit;
  console.log(`[conformance-gate] ${file}: cor-crua(regras de tela)=${count} · teto=${limit} · ${pass ? "✅ PASS" : "🔴 FAIL — cor crua nova; use token DS (--pos/--warn/--neg/--accent/--origin-*) ou baixe o teto com --update se removeu"}`);
  process.exit(pass ? 0 : 1);
}

main();
