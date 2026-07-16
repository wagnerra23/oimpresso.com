#!/usr/bin/env node
// design-coverage.mjs — mapa de cobertura de DESIGN por tela + catraca (só sobe).
//
// Complementa screen-coverage-map.mjs (que cobre CHARTER/E2E/SCORECARD/A11Y) com a dimensão
// que faltava: a tela DECLARA sua FONTE DE DESIGN? (protótipo bespoke via related_prototype,
// OU "n/a — segue DS/padrão" explícito). Sem isso a tela é SILENCIOSA — ninguém sabe de onde
// veio o design nem qual Padrão de Tela ela herda (UI-0013). Isto é "a parte de Design" por tela.
//
// NÃO duplica ancora.mjs — CONSOME `ancora.mjs --list --json` (o resolvedor canônico de âncora).
// A catraca ratcheta `declared` (telas com fonte declarada) — só sobe, nunca regride.
//
// Uso:
//   node scripts/qa/design-coverage.mjs           # relatório (read-only)
//   node scripts/qa/design-coverage.mjs --json     # + grava baseline
//   node scripts/qa/design-coverage.mjs --check    # exit 1 se `declared` regrediu vs baseline
//
// Contrato: Constituição UI v2 (UI-0013 — camadas/herança de Padrão de Tela) + ADR 0299/ancora.

import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, relative } from 'node:path';

const ROOT = process.cwd();
const ANCORA = join(ROOT, 'prototipo-ui', 'ancora.mjs');
const PAGES = join(ROOT, 'resources', 'js', 'Pages');
const argi = process.argv.indexOf('--baseline');
const BASELINE = argi >= 0 && process.argv[argi + 1]
  ? process.argv[argi + 1]
  : join(ROOT, 'memory', 'governance', 'design-coverage-baseline.json');

// 1. Fonte de design declarada por charter (via ancora --json)
let rows;
try {
  rows = JSON.parse(execFileSync('node', [ANCORA, '--list', '--json'], { encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 }));
} catch (e) {
  console.error(`design-coverage: falha ao rodar ancora.mjs --list --json: ${e.message}`);
  process.exit(2);
}
const totalCharters = rows.length;
const declared = rows.filter((r) => r.hasSource).length;
const silent = totalCharters - declared;

// 2. Contexto: páginas SEM charter nenhum (gap mais fundo — nem contrato de design têm)
function walkTsx(dir) {
  const out = [];
  for (const e of readdirSync(dir)) {
    const p = join(dir, e);
    const st = statSync(p);
    if (st.isDirectory()) { if (e !== '_components' && e !== '_partials') out.push(...walkTsx(p)); }
    else if (e.endsWith('.tsx') && !e.endsWith('.charter.tsx') && !e.includes('.test.')) out.push(p);
  }
  return out;
}
const pages = walkTsx(PAGES);
const noCharter = pages.filter((p) => !existsSync(p.replace(/\.tsx$/, '.charter.md'))).length;

const pct = (n, d) => d ? Math.round((n / d) * 100) : 0;

// ── --json: grava baseline ──
if (process.argv.includes('--json')) {
  writeFileSync(BASELINE, JSON.stringify({ declared, totalCharters, note: 'Catraca de cobertura de design: `declared` (telas com fonte de design declarada) só sobe. Baixar exige decisão consciente.' }, null, 2) + '\n');
  console.log(`baseline gravado: declared=${declared}/${totalCharters}`);
  process.exit(0);
}

// ── --check: catraca ──
if (process.argv.includes('--check')) {
  if (!existsSync(BASELINE)) { console.error('design-coverage: baseline ausente — rode --json pra semear.'); process.exit(1); }
  const base = JSON.parse(readFileSync(BASELINE, 'utf8'));
  if (declared < base.declared) {
    console.error(`design-coverage: cobertura de design REGREDIU — declared ${declared} < baseline ${base.declared}. Uma tela perdeu a fonte de design declarada.`);
    process.exit(1);
  }
  console.log(`design-coverage: OK — declared ${declared} ≥ baseline ${base.declared} (catraca).`);
  process.exit(0);
}

// ── relatório ──
console.log('═══ COBERTURA DE DESIGN (fonte declarada por tela · UI-0013) ═══');
console.log(`charters de página : ${totalCharters}`);
console.log(`  ✅ fonte declarada (protótipo ou "segue DS") : ${declared}  (${pct(declared, totalCharters)}%)`);
console.log(`  ⚠️  silenciosa (sem fonte declarada)          : ${silent}  (${pct(silent, totalCharters)}%)`);
console.log(`\ncontexto — páginas .tsx SEM charter algum      : ${noCharter} (gap mais fundo)`);
console.log(`\ncatraca: 'declared' só sobe. Fechar = declarar o Padrão de Tela / protótipo no charter (a parte de Design).`);
