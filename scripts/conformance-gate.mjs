#!/usr/bin/env node
/* conformance-gate.mjs — GATE de cor-crua (anti-regressão de contrato DS · Camada 1).
 * Determinístico, sem browser, sem dependência. Roda em CI (exit≠0 = bloqueia merge) E local.
 * DUAS checagens determinísticas (zero browser):
 *   (1) ratchet cor-crua — cor crua em REGRAS DE TELA só pode CAIR. Adicionou = 🔴. Cobre UC-V10.
 *   (2) invariante --accent — todo --accent* em resources/css/*.css é roxo (hue 250–330). Fora = 🔴.
 *       Fecha a metade do verde×roxo (UC-V09) no nível do TOKEN, que o ratchet (isenta :root) não pega.
 * NÃO cobre só o que exige runtime: surface dark / computed cascade (= Camada 2 browser Pest, tier local).
 *
 * Por que existe ALÉM do stylelint #2054 (ADR 0209):
 *   - stylelint `color-no-hex` já congela #hex GLOBAL e barra hex novo (ratchet config/stylelint-baseline.json).
 *   - este gate pega o que o stylelint NÃO pega: `oklch(<hue numérico>)` CRU em regras de TELA
 *     (cor fora de token sem hex), escopado por seletor de tela — precisão que o stylelint flat não dá.
 *   Os dois são complementares (defense-in-depth). Cor crua hex aqui é dupla-rede inofensiva (ratchet only-down).
 *
 * Provado (vendas.css Cowork, 2026-06-03) — controle-negativo nos DOIS lados:
 *   sensibilidade: +1 cor crua em regra de tela → conta sobe (pega o bug);
 *   especificidade: editar doc de impressão (.vd-trans) NÃO sobe · oklch(...var()) NÃO conta (não acusa inocente).
 *   Controle-negativo versionado em tests/js/conformance-gate.spec.mjs (Camada META — testa o teste).
 *
 * Uso:  node scripts/conformance-gate.mjs <arquivo.css> [--update]
 * Baseline por arquivo em .conformance-baseline.json (versionado → ratchet auditável).
 *
 * AVISO HONESTO: regex ≠ parser CSS. Escopa regras flat (.sel{...}); @media/aninhado profundo pode escapar.
 * Versão de produção: portar esta regra pro Stylelint #2054 (parser real). Esta é a rede mínima rodável já.
 *
 * Refs: PROMPT_PARA_CODE_CONFORMANCE-GATE.md · ADR 0209 (ratchet gêmeo) · ADR 0235/0190 (roxo 295) · ADR 0238 (soberania).
 */
import { readFileSync, writeFileSync, existsSync, readdirSync } from "node:fs";

const BASELINE_FILE = ".conformance-baseline.json";

// ── Invariante ABSOLUTO do token de marca (NÃO ratchet) ─────────────────────────────────────
// O hue do `--accent` (e variantes --accent-soft/-line/-fg) é SEMPRE roxo (ADR 0235/0190 ·
// oklch 0.55 0.15 295). Cor de marca não "drifta devagar" — ou é roxo, ou é bug. Fecha a metade
// do verde×roxo que o ratchet de cor-crua NÃO pega: redefinição do TOKEN em :root (isento na
// Camada 1) pra um oklch verde. Sem browser, determinístico. Cobre a classe UC-V09 no nível CSS.
const ACCENT_HUE_OK = [250, 330];
const CSS_DIR = "resources/css";

// Acha `--accent[...]: oklch(L C H ...)` com 3 números crus; ignora oklch(from var(...)) (sem hue numérico).
export function accentHueViolations(css, file = "") {
  const out = [];
  const re = /(--accent[\w-]*)\s*:\s*oklch\(\s*[\d.]+\s+[\d.]+\s+([\d.]+)/g;
  let m;
  while ((m = re.exec(css))) {
    const hue = parseFloat(m[2]);
    if (hue < ACCENT_HUE_OK[0] || hue > ACCENT_HUE_OK[1]) {
      out.push(`${file} ${m[1]} hue=${hue}° (fora de roxo ${ACCENT_HUE_OK[0]}–${ACCENT_HUE_OK[1]} — drift verde×roxo no TOKEN · ADR 0235/0190)`);
    }
  }
  return out;
}

// Varre todo resources/css/*.css pelo invariante do accent. Determinístico, sem baseline.
function accentSweep() {
  const hits = [];
  for (const f of readdirSync(CSS_DIR).filter((n) => n.endsWith(".css"))) {
    const path = `${CSS_DIR}/${f}`;
    hits.push(...accentHueViolations(readFileSync(path, "utf8"), path));
  }
  return hits;
}

// Seletores onde cor crua é PERMITIDA (defs de token + exceções declaradas).
const TOKEN_DEF  = /:root|\[data-theme/;
// transcript = papel A4 (cor fixa proposital) · apresentação = dark próprio (oklch intencional, régua L-122).
const EXCEPTION  = /\.vd-trans|\.vd-pres/;
// Regras de TELA da família Sells/Cowork. Generaliza o vocabulário vertical do repo
// (.sells-cowork escopa tudo; .vendas-aplus/.vd-/.os- são os blocos internos; .vendas-page o legacy).
// Ajustar/expandir por tela ao generalizar pra outros módulos (.fin-*, .repair-*, …).
const SCREEN     = /\.sells-cowork|\.vendas-aplus|\.vendas-page|\.vd-|\.os-/;

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

function checkOne(file, baseline) {
  const count = rawColorHits(readFileSync(file, "utf8")).length;
  const limit = baseline[file] ?? count;     // 1ª vez adota o atual como teto
  const pass = count <= limit;
  console.log(`[conformance-gate] ${file}: cor-crua(regras de tela)=${count} · teto=${limit} · ${pass ? "✅ PASS" : "🔴 FAIL — cor crua nova; use token DS (--pos/--warn/--neg/--accent/--origin-*) ou baixe o teto com --update se removeu"}`);
  return pass;
}

function main() {
  const all = process.argv.includes("--all");
  const update = process.argv.includes("--update");
  const file = process.argv[2] && !process.argv[2].startsWith("--") ? process.argv[2] : null;
  const baseline = loadBaseline();

  // --all: checa TODOS os arquivos do baseline (modo CI). Falha se QUALQUER um regredir.
  if (all) {
    const files = Object.keys(baseline);
    if (!files.length) { console.error("baseline vazio; rode --update primeiro"); process.exit(2); }
    // --all --update: re-crava o teto de TODOS os arquivos já no baseline (após sweep DS intencional).
    if (update) {
      for (const f of files) baseline[f] = rawColorHits(readFileSync(f, "utf8")).length;
      writeFileSync(BASELINE_FILE, JSON.stringify(baseline, null, 2) + "\n");
      console.log(`baseline re-gravado (${files.length} arquivos):\n` + files.map((f) => `  ${f} = ${baseline[f]}`).join("\n"));
      process.exit(0);
    }
    const failed = files.filter((f) => !checkOne(f, baseline));
    // Invariante absoluto do token de marca (verde×roxo no nível do TOKEN — o que o ratchet não pega).
    const accentBad = accentSweep();
    if (accentBad.length) {
      console.error(`\n🔴 token --accent fora do roxo canônico (${accentBad.length}):`);
      for (const v of accentBad) console.error(`   ${v}`);
    } else {
      console.log(`[conformance-gate] --accent: todos os ${CSS_DIR}/*.css em roxo ${ACCENT_HUE_OK[0]}–${ACCENT_HUE_OK[1]} ✅`);
    }
    if (failed.length) console.error(`\n🔴 ${failed.length}/${files.length} arquivo(s) com cor crua nova — merge bloqueado.`);
    if (!failed.length && !accentBad.length) console.log(`\n✅ ${files.length} arquivo(s) conformes (cor crua + token de marca).`);
    process.exit(failed.length || accentBad.length ? 1 : 0);
  }

  if (!file) { console.error("uso: node scripts/conformance-gate.mjs <arquivo.css> [--update]  |  --all (modo CI)"); process.exit(2); }

  if (update) {
    baseline[file] = rawColorHits(readFileSync(file, "utf8")).length;
    writeFileSync(BASELINE_FILE, JSON.stringify(baseline, null, 2) + "\n");
    console.log(`baseline gravado: ${file} = ${baseline[file]}`);
    process.exit(0);
  }

  process.exit(checkOne(file, baseline) ? 0 : 1);
}

// Só executa o CLI quando rodado direto (não quando importado pelo teste de controle-negativo).
if (import.meta.url === `file://${process.argv[1]}` || process.argv[1]?.endsWith("conformance-gate.mjs")) {
  main();
}
