#!/usr/bin/env node
/* dsih-gate.mjs — ratchet anti-dangerouslySetInnerHTML (ORACULO DE CONTEUDO, parcela 1).
 *
 * Por que existe: o red-team adversarial de 2026-06-17 achou stored-XSS VIVO em producao
 *   (Messages #2891, Todo.description + contact_address #2893) que passou pelos 17 required
 *   checks — porque NENHUM gate mordia CONTEUDO em .tsx (so canal: lint/build/conformance).
 *   Este e o primeiro gate a morder conteudo: dangerouslySetInnerHTML e o sink de XSS no1 do
 *   React e quase nunca e necessario. Render de dado de usuario via dSIH = XSS.
 *
 * O que faz (deterministico, zero-dep, zero-browser — espelha conformance-gate.mjs):
 *   ratchet per-file sobre os .tsx de resources/js (recursivo) — a contagem de
 *   dangerouslySetInnerHTML por arquivo so pode CAIR. Adicionou (arquivo novo OU contagem
 *   subiu) = merge bloqueado. Removeu (corrigiu sink) = baixe o teto com --update.
 *
 * Allowlist = o proprio baseline: usos legitimos ja existentes (label de paginacao Laravel,
 *   Knowledge admin charter-documentado, DataTable generico) entram no teto e sao tolerados;
 *   so o NOVO e barrado. Conforme sinks reais sao corrigidos, o teto desce (o oraculo aprende).
 *
 * Comentarios (linha e bloco, incluindo JSX) sao strippados antes da contagem — mencao a
 *   dangerouslySetInnerHTML em comentario de seguranca NAO conta.
 *
 * Self-test embutido (--selftest): prova que o detector MORDE (known-bad conta) e nao acusa
 *   inocente (comentario conta 0). O workflow roda --selftest ANTES do --all (quem vigia o vigia).
 *
 * Uso:
 *   node scripts/dsih-gate.mjs --all        modo CI: falha se algum arquivo regredir
 *   node scripts/dsih-gate.mjs --update     re-grava o teto (apos corrigir sinks)
 *   node scripts/dsih-gate.mjs --selftest   prova que o gate morde (fixtures boa/ruim)
 *
 * Baseline: .dsih-baseline.json (versionado). Refs: red-team 2026-06-17 ·
 *   proposals/handoff-loop-zero-paste.md (secao 9 oraculo de conteudo) · PRs #2891/#2893.
 */
import { readFileSync, writeFileSync, existsSync, readdirSync } from "node:fs";
import { join } from "node:path";

const BASELINE_FILE = ".dsih-baseline.json";
const ROOT = "resources/js";
const NEEDLE = /dangerouslySetInnerHTML/g;

// Strip comentarios (bloco e linha //, cobre JSX) antes de contar.
function stripComments(src) {
  return src
    .replace(/\/\*[\s\S]*?\*\//g, "")
    .split("\n")
    .filter((l) => !l.trim().startsWith("//"))
    .join("\n");
}

export function dsihCount(src) {
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
    const n = dsihCount(readFileSync(f, "utf8"));
    if (n > 0) out[f] = n;
  }
  return out;
}

function loadBaseline() {
  try { return existsSync(BASELINE_FILE) ? JSON.parse(readFileSync(BASELINE_FILE, "utf8")) : {}; }
  catch { return {}; }
}

// Prova que o gate MORDE: sensibilidade (dSIH real conta) + especificidade (comentario nao conta).
function selftest() {
  const bad = `<div dangerouslySetInnerHTML={{ __html: x }} />`;
  const goodLine = `// dangerouslySetInnerHTML aqui e so comentario de seguranca`;
  const goodJsx = `<div>{/* nunca usar dangerouslySetInnerHTML */}{texto}</div>`;
  const fails = [];
  if (dsihCount(bad) !== 1) fails.push(`sensibilidade: known-bad deveria contar 1, contou ${dsihCount(bad)}`);
  if (dsihCount(goodLine) !== 0) fails.push(`especificidade: comentario // deveria contar 0, contou ${dsihCount(goodLine)}`);
  if (dsihCount(goodJsx) !== 0) fails.push(`especificidade: comentario JSX deveria contar 0, contou ${dsihCount(goodJsx)}`);
  if (fails.length) {
    console.error("dsih-gate SELFTEST falhou — o gate nao morde como deveria:");
    for (const f of fails) console.error(`   ${f}`);
    process.exit(1);
  }
  console.log("[dsih-gate] selftest OK (detector morde known-bad, ignora comentario).");
}

function main() {
  if (process.argv.includes("--selftest")) { selftest(); return; }

  const update = process.argv.includes("--update");
  const current = currentCounts();

  if (update) {
    writeFileSync(BASELINE_FILE, JSON.stringify(current, null, 2) + "\n");
    const total = Object.values(current).reduce((a, b) => a + b, 0);
    console.log(`[dsih-gate] baseline gravado: ${Object.keys(current).length} arquivo(s), ${total} ocorrencia(s).`);
    process.exit(0);
  }

  const baseline = loadBaseline();
  const fails = [];
  for (const [f, n] of Object.entries(current)) {
    const teto = baseline[f] ?? 0; // arquivo fora do baseline = teto 0 -> qualquer dSIH novo barra
    if (n > teto) {
      fails.push(`${f}: dangerouslySetInnerHTML ${teto}->${n} — render como TEXTO (React escapa) ou sanitize; e XSS sink.`);
    }
  }

  if (fails.length) {
    console.error(`dsih-gate: ${fails.length} regressao(oes) — merge bloqueado:`);
    for (const f of fails) console.error(`   ${f}`);
    console.error(`\nSe foi remocao intencional de um sink, re-crave o teto: node scripts/dsih-gate.mjs --update (commit separado).`);
    process.exit(1);
  }

  const total = Object.values(current).reduce((a, b) => a + b, 0);
  console.log(`[dsih-gate] OK — nenhum dangerouslySetInnerHTML novo (${Object.keys(current).length} arquivo(s) sob teto, ${total} ocorrencia(s)).`);
  process.exit(0);
}

if (import.meta.url === `file://${process.argv[1]}` || process.argv[1]?.endsWith("dsih-gate.mjs")) {
  main();
}
