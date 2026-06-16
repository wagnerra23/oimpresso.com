#!/usr/bin/env node
// scripts/tests/foundation-ratchet.mjs — catracas "só diminui" da fundação de testes (SDD Semana 0 · FV-Q1).
//
// POR QUE (memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md §4):
// a full-suite nunca rodou verde em DB real. Antes da nightly diagnóstica medir, congelamos
// 3 contadores REAIS (medidos no repo, não no plano — regra anti-stale) pra nenhum PR piorar:
//   n_quarantine        — marcadores `legacy-quarantine` (burn-down: subir = regressão)
//   n_refresh_database  — ARQUIVOS que APLICAM o trait RefreshDatabase — `uses(...RefreshDatabase::class)`
//                         ou `use [...\]RefreshDatabase;` (alvo: DatabaseTransactions). MENÇÃO da palavra
//                         em comentário/docstring/string de skip NÃO conta (conserto raiz FV-Q1, ADR 0275).
//   n_business_first    — ocorrências de Business::first() cru em teste (alvo: trait WithSeededTenant)
//
// CONVENÇÃO QUARENTENA (hard-fail, independe de baseline): todo marcador exige
// `quarantine-reason: <motivo>` a ≤3 linhas. Quarentena sem razão escrita é proibida.
//
// Determinístico, Node puro, sem MySQL, segundos. Espelha os ratchets do projeto (a11y/reuse/no-mock).
// SUBIR baseline = SÓ `--write --force` (diff visível no PR — ex.: quarentena em massa Q3 planejada).
//
// USO:
//   node scripts/tests/foundation-ratchet.mjs            # gate vs baseline (+ job summary se em CI)
//   node scripts/tests/foundation-ratchet.mjs --json     # contadores em JSON
//   node scripts/tests/foundation-ratchet.mjs --write    # (re)grava baseline — só pra DESCER
//   ... --root <dir> --baseline <file>                   # fixtures/selftest (mesmo code path)

import { appendFileSync, existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';

const args = process.argv.slice(2);
const opt = (n) => { const i = args.indexOf(n); return i >= 0 ? args[i + 1] : null; };
const ROOT = opt('--root') || process.cwd();
const BASELINE = opt('--baseline') || join(ROOT, 'scripts/tests/baselines/foundation-ratchet-baseline.json');

const MARKER = /@group\s+legacy-quarantine\b|#\[Group\(['"]legacy-quarantine['"]\)\]|->group\(['"]legacy-quarantine['"]/;
const REASON = /quarantine-reason:\s*\S/;

// USO REAL do trait RefreshDatabase (≠ MENÇÃO). Conta só quem APLICA o trait:
//   `uses(...RefreshDatabase::class...)` (Pest) ou `use [...\]RefreshDatabase;` (import/trait-use).
// Remove comentários antes (docblock /* */ + linha //) pra não casar a docstring que explica POR QUE o
// teste EVITA o trait (padrão era-sqlite). String literal sobrevive ao strip (ex.: `->skip('… RefreshDatabase …')`)
// mas não casa `uses(`/`use …;`, então não conta. Conserta a raiz do FV-Q1: o `\bRefreshDatabase\b` cru
// contava ~50 falsos positivos (menção em comentário), medindo FORMA e não USO real (ADR 0275 — métrica honesta).
function refreshDatabaseTraitUsed(src) {
  const code = src.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/[^\n]*/g, '');
  return /\buses\s*\([^)]*\bRefreshDatabase\b/.test(code)
    || /^\s*use\s+[\w\\]*\bRefreshDatabase\b/m.test(code);
}

// Roots canônicos: tests/ + Modules/<X>/Tests/. Comparação case-insensitive + readdir
// (cada dir REAL visitado 1×) — imune ao alias NTFS Tests/tests que duplicaria contagem.
function testRoots(root) {
  const roots = [];
  if (existsSync(join(root, 'tests'))) roots.push(join(root, 'tests'));
  const mods = join(root, 'Modules');
  if (!existsSync(mods)) return roots;
  for (const m of readdirSync(mods, { withFileTypes: true })) {
    if (!m.isDirectory()) continue;
    for (const e of readdirSync(join(mods, m.name), { withFileTypes: true }))
      if (e.isDirectory() && e.name.toLowerCase() === 'tests') roots.push(join(mods, m.name, e.name));
  }
  return roots;
}
function* phpFiles(dir) {
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    if (e.isDirectory()) yield* phpFiles(join(dir, e.name));
    else if (e.name.endsWith('.php')) yield join(dir, e.name);
  }
}

function measure(root) {
  const counters = { n_quarantine: 0, n_refresh_database: 0, n_business_first: 0 };
  const semRazao = [];
  for (const tr of testRoots(root)) for (const f of phpFiles(tr)) {
    const src = readFileSync(f, 'utf8');
    if (refreshDatabaseTraitUsed(src)) counters.n_refresh_database++;
    counters.n_business_first += (src.match(/\bBusiness::first\s*\(/g) || []).length;
    if (!MARKER.test(src)) continue;
    const lines = src.split('\n');
    lines.forEach((line, i) => {
      if (!MARKER.test(line)) return;
      counters.n_quarantine++;
      if (!REASON.test(lines.slice(Math.max(0, i - 3), i + 4).join('\n')))
        semRazao.push(`${relative(root, f).replace(/\\/g, '/')}:${i + 1}`);
    });
  }
  return { counters, semRazao };
}

const { counters, semRazao } = measure(ROOT);

if (args.includes('--json')) {
  console.log(JSON.stringify({ counters, quarantine_sem_razao: semRazao }, null, 2));
  process.exit(0);
}

if (args.includes('--write')) {
  let prev = null;
  try { prev = JSON.parse(readFileSync(BASELINE, 'utf8')).counters; } catch { /* 1ª medição */ }
  const sobe = prev ? Object.keys(counters).filter((k) => counters[k] > prev[k]) : [];
  if (sobe.length && !args.includes('--force')) {
    console.error(`✗ --write recusado: ${sobe.join(', ')} SUBIRIA. Catraca só desce. Subida planejada (ex.: quarentena em massa Q3) = --write --force, visível no diff do PR.`);
    process.exit(1);
  }
  mkdirSync(dirname(BASELINE), { recursive: true });
  writeFileSync(BASELINE, JSON.stringify({ generated_by: 'scripts/tests/foundation-ratchet.mjs --write', counters }, null, 2) + '\n');
  console.log(`✓ baseline gravado: ${JSON.stringify(counters)}${sobe.length ? ' (FORÇADO pra cima — justifique no PR)' : ''}`);
  process.exit(0);
}

// gate (default)
let baseline;
try { baseline = JSON.parse(readFileSync(BASELINE, 'utf8')).counters; }
catch { console.error(`✗ baseline ausente/ilegível (${BASELINE}). Rode: node scripts/tests/foundation-ratchet.mjs --write`); process.exit(2); }

const rows = Object.keys(baseline).map((k) => {
  const cur = counters[k] ?? 0; const base = baseline[k];
  return { k, base, cur, status: cur > base ? 'SUBIU' : cur < base ? 'desceu' : 'ok' };
});
const pioras = rows.filter((r) => r.status === 'SUBIU');
const fail = pioras.length > 0 || semRazao.length > 0;

if (process.env.GITHUB_STEP_SUMMARY) {
  const md = ['## Foundation ratchet (advisory · FV-Q1)', '', '| contador | baseline | atual | Δ |', '|---|---:|---:|---|',
    ...rows.map((r) => `| ${r.k} | ${r.base} | ${r.cur} | ${r.cur > r.base ? `🔴 +${r.cur - r.base}` : r.cur < r.base ? `🟢 −${r.base - r.cur}` : '—'} |`),
    semRazao.length ? `\n🔴 **quarentena sem \`quarantine-reason:\`** (${semRazao.length}): ${semRazao.join(' · ')}` : ''].join('\n');
  appendFileSync(process.env.GITHUB_STEP_SUMMARY, md + '\n');
}

for (const r of rows) console.log(`  ${r.status === 'SUBIU' ? '✗' : '✓'} ${r.k}: ${r.cur} (baseline ${r.base})`);
if (semRazao.length) console.error(`✗ marcador legacy-quarantine SEM quarantine-reason: a ≤3 linhas:\n  ${semRazao.join('\n  ')}`);
if (fail) {
  if (pioras.length) console.error(`✗ catraca FALHOU — fundação piorou: ${pioras.map((r) => `${r.k} ${r.base}→${r.cur}`).join(', ')}. Não adicione RefreshDatabase/Business::first() cru em teste novo (use DatabaseTransactions / trait de tenant seedado).`);
  process.exit(1);
}
const ganho = rows.filter((r) => r.status === 'desceu');
console.log(`✓ foundation ratchet OK${ganho.length ? ` — ↓ ${ganho.map((r) => r.k).join(', ')}: rode --write pra travar o ganho` : ''}.`);
