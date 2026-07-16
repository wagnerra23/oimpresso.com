#!/usr/bin/env node
// @ts-check
/**
 * screen-coverage-map.mjs — mapa de cobertura de QA por tela + baseline da catraca.
 *
 * Cruza as 4 camadas de garantia que uma tela Inertia pode ter:
 *   1. CHARTER   — contrato vivo (<Tela>.charter.md ao lado do .tsx)
 *   2. E2E       — referência da tela em tests/Browser/**.php (Pest 4 Browser/Playwright)
 *   3. SCORECARD — nota persistida (memory/governance/scorecards/screens/*.yaml)
 *   4. A11Y      — referência da tela em teste que injeta axe (heurística: "axe" no arquivo E2E)
 *
 * Saídas:
 *   - stdout: resumo por módulo + agregados (read-only, sem efeito colateral)
 *   - --json: escreve memory/governance/screen-coverage-baseline.json (o que a CATRACA lê)
 *
 * É o Passo 0 da sobrevivência (ADR proposto screen-qa-specialist-sustentavel):
 * a catraca de cobertura compara o estado de um PR contra este baseline e
 * BLOQUEIA se qualquer agregado regredir (telas cobertas só sobem).
 *
 * Uso:
 *   node scripts/qa/screen-coverage-map.mjs            # só relatório
 *   node scripts/qa/screen-coverage-map.mjs --json     # + grava baseline
 *   node scripts/qa/screen-coverage-map.mjs --check    # falha (exit 1) se regrediu vs baseline
 */

import { readFileSync, readdirSync, statSync, writeFileSync, existsSync } from 'node:fs';
import { join, relative, sep, basename } from 'node:path';
import assert from 'node:assert/strict';

const ROOT = process.cwd();
const PAGES_DIR = join(ROOT, 'resources', 'js', 'Pages');
const BROWSER_DIR = join(ROOT, 'tests', 'Browser');
const VISREG_MANIFEST = join(BROWSER_DIR, 'visreg-screens.json');
const SCORECARD_DIR = join(ROOT, 'memory', 'governance', 'scorecards', 'screens');
const BASELINE = join(ROOT, 'memory', 'governance', 'screen-coverage-baseline.json');

const flags = new Set(process.argv.slice(2));
const PAGE_AUX_DIR = /^(?:_.*|components?|partials?|hooks?|utils?|lib|types?|constants?|schemas?|stores?|contexts?)$/i;

/** Lista recursiva de arquivos sob `dir` cujo nome casa `match`. */
function walk(dir, match, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    const st = statSync(full);
    if (st.isDirectory()) walk(full, match, acc);
    else if (match(full)) acc.push(full);
  }
  return acc;
}

export function isAuxiliaryScreenPath(relTsx) {
  return relTsx.split(/[\\/]/).slice(0, -1).some((part) => PAGE_AUX_DIR.test(part));
}

// 1. Universo de telas: Pages/**/*.tsx, exceto diretórios auxiliares e testes.
const isScreen = (f) =>
  f.endsWith('.tsx') &&
  !isAuxiliaryScreenPath(relative(PAGES_DIR, f)) &&
  !f.endsWith('.charter.tsx') &&
  !f.includes('.test.');
const screens = walk(PAGES_DIR, isScreen);

// 2. Corpus de E2E (conteúdo concatenado pra busca de referência).
const browserFiles = walk(BROWSER_DIR, (f) => f.endsWith('.php'));
const browserCorpus = browserFiles
  .map((f) => ({ file: f, body: readFileSync(f, 'utf8') }))
  .map((x) => ({ ...x, hasAxe: /axe|accessibilit/i.test(x.body) }));
const visregSources = new Set(
  JSON.parse(readFileSync(VISREG_MANIFEST, 'utf8')).map((entry) => entry.source),
);

// 3. Scorecards existentes (slug modulo-tela).
const scorecards = new Set(
  walk(SCORECARD_DIR, (f) => f.endsWith('.yaml') || f.endsWith('.yml')).map((f) =>
    basename(f).replace(/\.(ya?ml)$/, '').toLowerCase(),
  ),
);

/**
 * Uma tela é "referenciada" por um E2E se o caminho relativo do Page (Mod/Tela)
 * aparece LITERALMENTE no body do teste — ex: `'Sells/Create'` no mapa do Pest Browser.
 * NÃO casamos por basename solto ('Index', 'Create', 'Cockpit'): um único teste citando
 * '/Index' creditaria todas as ~90 telas homônimas. Esse termo gerava 106 falsos-positivos
 * (e2e inflado de 4 → 110); a contagem honesta é só quem cita o caminho exato. Ver ADR 0249.
 */
function e2eFor(relTsx) {
  const key = relTsx.replace(/\.tsx$/, ''); // ex: Sells/Create
  const keyAlt = key.replace(/\//g, '\\');  // idem em refs com separador Windows
  return browserCorpus.filter((b) => b.body.includes(key) || b.body.includes(keyAlt));
}

export function inertiaSourcesFor(relTsx) {
  const pageSource = relTsx.replace(/\.tsx$/, '');
  return pageSource.endsWith('/Index')
    ? [pageSource, pageSource.slice(0, -'/Index'.length)]
    : [pageSource];
}

export function coverageRegressions(current, previous) {
  return ['charter', 'e2e', 'a11y', 'scorecard'].filter((key) => current[key] < previous[key]);
}

if (flags.has('--selftest')) {
  assert.deepEqual(inertiaSourcesFor('Financeiro/Unificado/Index.tsx'), [
    'Financeiro/Unificado/Index',
    'Financeiro/Unificado',
  ]);
  assert.deepEqual(inertiaSourcesFor('Sells/Create.tsx'), ['Sells/Create']);
  assert.equal(isAuxiliaryScreenPath('Compras/components/Drawer.tsx'), true);
  assert.equal(isAuxiliaryScreenPath('Cliente/_drawer/AuditoriaTab.tsx'), true);
  assert.equal(isAuxiliaryScreenPath('Compras/Index.tsx'), false);
  assert.deepEqual(
    coverageRegressions(
      { charter: 10, e2e: 1, a11y: 1, scorecard: 10 },
      { charter: 10, e2e: 2, a11y: 1, scorecard: 10 },
    ),
    ['e2e'],
  );
  console.log('screen-coverage selftest: aliases Inertia e universo de telas passaram');
  process.exit(0);
}

const rows = screens.map((abs) => {
  const relTsx = relative(PAGES_DIR, abs).split(sep).join('/');
  const mod = relTsx.split('/')[0];
  const charter = existsSync(abs.replace(/\.tsx$/, '.charter.md'));
  const e2e = e2eFor(relTsx);
  const hasVisregContract = inertiaSourcesFor(relTsx).some((source) => visregSources.has(source));
  const slug = relTsx.replace(/\.tsx$/, '').replace(/\//g, '-').toLowerCase();
  return {
    screen: relTsx,
    module: mod,
    charter,
    e2e: e2e.length > 0 || hasVisregContract,
    a11y: e2e.some((b) => b.hasAxe),
    scorecard: scorecards.has(slug),
  };
});

// Agregados.
const total = rows.length;
const pct = (n) => (total ? Math.round((n / total) * 1000) / 10 : 0);
const agg = {
  total,
  charter: rows.filter((r) => r.charter).length,
  e2e: rows.filter((r) => r.e2e).length,
  a11y: rows.filter((r) => r.a11y).length,
  scorecard: rows.filter((r) => r.scorecard).length,
};

// Por módulo.
const byModule = {};
for (const r of rows) {
  const m = (byModule[r.module] ??= { total: 0, charter: 0, e2e: 0, a11y: 0, scorecard: 0 });
  m.total++;
  if (r.charter) m.charter++;
  if (r.e2e) m.e2e++;
  if (r.a11y) m.a11y++;
  if (r.scorecard) m.scorecard++;
}

// --- Relatório stdout ---
console.log(`\n=== Mapa de cobertura QA-de-tela · ${total} telas ===\n`);
console.log(`  CHARTER (contrato)   : ${agg.charter}/${total}  (${pct(agg.charter)}%)`);
console.log(`  E2E (Pest Browser)   : ${agg.e2e}/${total}  (${pct(agg.e2e)}%)`);
console.log(`  A11Y (axe no E2E)    : ${agg.a11y}/${total}  (${pct(agg.a11y)}%)`);
console.log(`  SCORECARD (nota)     : ${agg.scorecard}/${total}  (${pct(agg.scorecard)}%)\n`);

const mods = Object.entries(byModule).sort((a, b) => b[1].total - a[1].total);
console.log('  Módulo'.padEnd(22) + 'Telas  Charter  E2E  Score');
for (const [m, s] of mods) {
  console.log(
    '  ' + m.padEnd(20) + String(s.total).padStart(5) + String(s.charter).padStart(9) + String(s.e2e).padStart(5) + String(s.scorecard).padStart(7),
  );
}

// --- Baseline / catraca ---
const snapshot = { generated_note: 'baseline da catraca de cobertura — NÃO editar à mão', aggregates: agg, by_module: byModule };

if (flags.has('--json')) {
  writeFileSync(BASELINE, JSON.stringify(snapshot, null, 2) + '\n');
  console.log(`\n✓ baseline gravado em ${relative(ROOT, BASELINE)}`);
}

if (flags.has('--check')) {
  if (!existsSync(BASELINE)) {
    console.error('\n✗ baseline ausente — rode com --json primeiro.');
    process.exit(2);
  }
  const prev = JSON.parse(readFileSync(BASELINE, 'utf8')).aggregates;
  const regress = coverageRegressions(agg, prev);
  if (regress.length) {
    console.error(`\n✗ CATRACA: cobertura regrediu em ${regress.join(', ')} (vs baseline). PR bloqueado.`);
    for (const k of regress) console.error(`   ${k}: ${prev[k]} → ${agg[k]}`);
    process.exit(1);
  }
  console.log('\n✓ CATRACA: nenhuma regressão de cobertura.');
}
