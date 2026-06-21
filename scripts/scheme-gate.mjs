#!/usr/bin/env node
/* scheme-gate.mjs — ratchet anti-href/scheme-cru (ORACULO DE CONTEUDO, parcela 2).
 *
 * Por que existe: o red-team adversarial de 2026-06-17 mostrou que NENHUM gate mordia CONTEUDO
 *   em .tsx — so canal (lint/build/conformance). A parcela 1 (dsih-gate) fechou
 *   dangerouslySetInnerHTML. Esta fecha o OUTRO sink classico de XSS em React: URL com scheme
 *   perigoso. `<a href="javascript:...">`, `<iframe src="data:text/html,...">` e `vbscript:`
 *   executam script quando o usuario clica/renderiza — e o React NAO escapa o scheme de uma URL.
 *   (ADR 0283 §9: "Proximas: href/scheme cru + smoke autenticado cross-tenant".)
 *
 * O que faz (deterministico, zero-dep, zero-browser — espelha dsih-gate.mjs / conformance-gate.mjs):
 *   ratchet per-file sobre os .tsx de resources/js (recursivo) — a contagem de schemes perigosos
 *   por arquivo so pode CAIR. Adicionou (arquivo novo OU contagem subiu) = merge bloqueado.
 *   Removeu (trocou por https:/rota relativa) = baixe o teto com --update.
 *
 * Schemes vigiados: `javascript:`, `vbscript:` e `data:text/html` (data URI que renderiza HTML).
 *   `data:image/...` (icones SVG inline etc.) NAO conta — so o que executa/renderiza HTML.
 *
 * Allowlist = o proprio baseline: usos ja existentes entram no teto e sao tolerados; so o NOVO
 *   e barrado. Conforme sinks reais sao corrigidos, o teto desce (o oraculo aprende).
 *
 * Comentarios (linha // e bloco, incluindo JSX) sao strippados antes da contagem — mencao a
 *   "javascript:" num comentario de seguranca NAO conta.
 *
 * Self-test embutido (--selftest): prova sensibilidade (known-bad conta) E especificidade
 *   (comentario, https:, data:image/svg contam 0). O workflow roda --selftest ANTES do --all.
 *
 * Uso:
 *   node scripts/scheme-gate.mjs --all        modo CI: falha se algum arquivo regredir
 *   node scripts/scheme-gate.mjs --update     re-grava o teto (apos corrigir sinks)
 *   node scripts/scheme-gate.mjs --selftest   prova que o gate morde (fixtures boa/ruim)
 *
 * Baseline: .scheme-baseline.json (versionado). Refs: red-team 2026-06-17 ·
 *   proposals/handoff-loop-zero-paste.md (§9 oraculo de conteudo) · ADR 0283 · dsih-gate.mjs.
 */
import { readFileSync, writeFileSync, existsSync, readdirSync } from "node:fs";
import { join } from "node:path";

const BASELINE_FILE = ".scheme-baseline.json";
const ROOT = "resources/js";
// javascript:/vbscript: (com espaco opcional antes do `:`) ou data:text/html (data URI executavel).
const NEEDLE = /\b(?:javascript|vbscript)\s*:|\bdata:\s*text\/html/gi;

// Strip comentarios (bloco e linha //, cobre JSX) antes de contar.
function stripComments(src) {
  return src
    .replace(/\/\*[\s\S]*?\*\//g, "")
    .split("\n")
    .filter((l) => !l.trim().startsWith("//"))
    .join("\n");
}

export function schemeCount(src) {
  return (stripComments(src).match(NEEDLE) || []).length;
}

function walkTsx(dir, acc = []) {
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name).replace(/\\/g, "/");
    if (e.isDirectory()) walkTsx(p, acc);
    else if (e.name.endsWith(".tsx")) acc.push(p);
  }
  return acc;
}

function currentCounts() {
  const out = {};
  for (const f of walkTsx(ROOT).sort()) {
    const n = schemeCount(readFileSync(f, "utf8"));
    if (n > 0) out[f] = n;
  }
  return out;
}

function loadBaseline() {
  try { return existsSync(BASELINE_FILE) ? JSON.parse(readFileSync(BASELINE_FILE, "utf8")) : {}; }
  catch { return {}; }
}

// Prova que o gate MORDE: sensibilidade (scheme real conta) + especificidade (inocente nao conta).
function selftest() {
  const fails = [];
  const bites = {
    "href javascript:": `<a href="javascript:alert(1)">x</a>`,
    "src data:text/html": `<iframe src="data:text/html,<script>x</script>" />`,
    "vbscript:": `<a href="vbscript:msgbox(1)">x</a>`,
  };
  for (const [label, src] of Object.entries(bites)) {
    if (schemeCount(src) < 1) fails.push(`sensibilidade: "${label}" deveria contar >=1, contou ${schemeCount(src)}`);
  }
  const innocents = {
    "comentario //": `// nunca usar javascript: em href`,
    "comentario JSX": `<div>{/* evite data:text/html aqui */}{texto}</div>`,
    "https": `<a href="https://exemplo.com/path">ok</a>`,
    "rota relativa": `<a href={route('clientes.show', id)}>ok</a>`,
    "data:image/svg": `<img src="data:image/svg+xml;base64,PHN2Zy8+" />`,
  };
  for (const [label, src] of Object.entries(innocents)) {
    if (schemeCount(src) !== 0) fails.push(`especificidade: "${label}" deveria contar 0, contou ${schemeCount(src)}`);
  }
  if (fails.length) {
    console.error("scheme-gate SELFTEST falhou — o gate nao morde como deveria:");
    for (const f of fails) console.error(`   ${f}`);
    process.exit(1);
  }
  console.log("[scheme-gate] selftest OK (morde javascript:/vbscript:/data:text-html, ignora https/comentario/data:image).");
}

function main() {
  if (process.argv.includes("--selftest")) { selftest(); return; }

  const update = process.argv.includes("--update");
  const current = currentCounts();

  if (update) {
    writeFileSync(BASELINE_FILE, JSON.stringify(current, null, 2) + "\n");
    const total = Object.values(current).reduce((a, b) => a + b, 0);
    console.log(`[scheme-gate] baseline gravado: ${Object.keys(current).length} arquivo(s), ${total} ocorrencia(s).`);
    process.exit(0);
  }

  const baseline = loadBaseline();
  const fails = [];
  for (const [f, n] of Object.entries(current)) {
    const teto = baseline[f] ?? 0; // arquivo fora do baseline = teto 0 -> qualquer scheme novo barra
    if (n > teto) {
      fails.push(`${f}: scheme perigoso (javascript:/vbscript:/data:text/html) ${teto}->${n} — use https:/rota relativa ou sanitize; e XSS sink.`);
    }
  }

  if (fails.length) {
    console.error(`scheme-gate: ${fails.length} regressao(oes) — merge bloqueado:`);
    for (const f of fails) console.error(`   ${f}`);
    console.error(`\nSe foi remocao intencional de um sink, re-crave o teto: node scripts/scheme-gate.mjs --update (commit separado).`);
    process.exit(1);
  }

  const total = Object.values(current).reduce((a, b) => a + b, 0);
  console.log(`[scheme-gate] OK — nenhum scheme perigoso novo (${Object.keys(current).length} arquivo(s) sob teto, ${total} ocorrencia(s)).`);
  process.exit(0);
}

if (import.meta.url === `file://${process.argv[1]}` || process.argv[1]?.endsWith("scheme-gate.mjs")) {
  main();
}
