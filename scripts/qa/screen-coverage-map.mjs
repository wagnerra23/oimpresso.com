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
 *   node scripts/qa/screen-coverage-map.mjs                    # mapa agregado (todas as telas)
 *   node scripts/qa/screen-coverage-map.mjs --json             # + grava baseline
 *   node scripts/qa/screen-coverage-map.mjs --check            # falha (exit 1) se regrediu vs baseline
 *   node scripts/qa/screen-coverage-map.mjs --screen Mod/Tela  # TODOS os arquivos de UMA tela + linkagem
 *                                                              #   (resolver: trio+scorecard+e2e+UC↔teste+
 *                                                              #    RUNBOOK/visual-comparison/proto-baseline com
 *                                                              #    FLAG de ambiguidade — reporta, não adivinha)
 */

import { readFileSync, readdirSync, statSync, writeFileSync, existsSync } from 'node:fs';
import { join, relative, sep, basename } from 'node:path';
import assert from 'node:assert/strict';

const ROOT = process.cwd();
const PAGES_DIR = join(ROOT, 'resources', 'js', 'Pages');
const BROWSER_DIR = join(ROOT, 'tests', 'Browser');
const VISREG_MANIFEST = join(BROWSER_DIR, 'visreg-screens.json');
const SCORECARD_DIR = join(ROOT, 'memory', 'governance', 'scorecards', 'screens');
const REQ_DIR = join(ROOT, 'memory', 'requisitos'); // onde vivem RUNBOOK/visual-comparison/proto-baseline (nome drifta)
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

export function coverageRegressions(current, previous, currentCovered = {}, previousCovered = {}) {
  const decode = (value) => new Set((value ?? '').split('|').filter(Boolean));

  return ['charter', 'e2e', 'a11y', 'scorecard'].filter((key) => {
    if (current[key] < previous[key]) return true;
    const now = decode(currentCovered[key]);
    return [...decode(previousCovered[key])].some((screen) => !now.has(screen));
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// RESOLVER POR-TELA (--screen <Mod/Tela>) — "achar TODOS os arquivos da tela"
// ─────────────────────────────────────────────────────────────────────────────
// POR QUE: perguntar "quais arquivos a tela X TEM, e como linkam?" não tinha
// resposta única. O mapa agregado (acima) mede charter/e2e/scorecard EM MASSA, mas
// os artefatos de UMA tela vivem FRAGMENTADOS em 4+ ferramentas + convenções de nome
// que DRIFTAM (scorecard=full-path · RUNBOOK=tela · proto-baseline=rota · visual-
// comparison=livre). Refutado no caso real Produto/Create + Financeiro/Unificado
// (2026-07-18): 8 arquivos existem, o mapa acha 3 por 3 chaves diferentes; RUNBOOK
// tem 2 candidatos (index E unificado). Este modo UNIFICA + é HONESTO sobre o que não
// resolve por nome: reporta CANDIDATOS + FLAG de ambiguidade, NUNCA adivinha "o"
// arquivo (lápide §5 2026-06-30: proveniência vem da declaração do charter, não do
// filename). NÃO é gate — é resolver/report (reporta, humano decide).

/** screenSlug — slug do scorecard: caminho da tela em kebab. PURO. */
export function screenSlug(relTsx) {
  return relTsx.replace(/\.tsx$/, '').replace(/\//g, '-').toLowerCase();
}

/**
 * classifyArtifact — dado os candidatos (por nome) de UM tipo de artefato, diz se
 * resolve ÚNICO, está AUSENTE, ou é AMBÍGUO (>1 candidato = a máquina NÃO sabe qual é
 * "o" arquivo da tela → precisa DECLARAÇÃO, não adivinhação). PURO/testável — é o
 * núcleo que o selftest exercita (ambiguidade é o defeito que NADA mais pega hoje).
 * @param {string[]} candidates
 * @returns {{ status:'unique'|'missing'|'ambiguous', candidates:string[] }}
 */
export function classifyArtifact(candidates) {
  const status = candidates.length === 0 ? 'missing' : candidates.length === 1 ? 'unique' : 'ambiguous';
  return { status, candidates };
}

/** UCs declarados num casos.md (heading "## UC-XX ..."; ~~UC~~ tachado = retirado, não conta). PURO. */
export function ucsFromCasos(content) {
  const out = [];
  for (const block of (content || '').split(/^##\s+/m).slice(1)) {
    const m = block.match(/^(UC-[A-Z0-9]{0,8}-?\d{1,3})\b/i);
    if (m) out.push(m[1].toUpperCase());
  }
  return out;
}

const norm = (p) => relative(ROOT, p).replace(/\\/g, '/');

// Corpus de teste AMPLO (tests/ e2e/ Modules/**/Tests app/) — inclui o Pest de
// CONTRATO em tests/Feature (que o e2e-Browser do mapa não vê). Lazy: só no --screen.
function testCorpusText() {
  const dirs = ['tests', 'e2e', 'Modules', 'app'];
  let corpus = '';
  for (const d of dirs) {
    for (const f of walk(join(ROOT, d), (p) => /Test\.php$|\.spec\.[tj]sx?$|\.test\.[tj]sx?$/.test(p))) {
      try { corpus += '\n' + readFileSync(f, 'utf8'); } catch { /* ignore */ }
    }
  }
  return corpus;
}

/**
 * resolveScreenFiles — dado a tela, acha TODOS os arquivos + linkagem. Impuro (lê FS).
 * @param {string} relTsx caminho relativo a Pages/ com ou sem `.tsx` (ex: "Produto/Create")
 */
export function resolveScreenFiles(relTsx) {
  relTsx = relTsx.replace(/\.tsx$/, '') + '.tsx';
  const segs = relTsx.replace(/\.tsx$/, '').split('/');
  const mod = segs[0];
  const telaK = segs[segs.length - 1].toLowerCase();
  // Tela aninhada (Mod/Sub/Index): o nome REAL do artefato costuma ser o dir "Sub"
  // (rota/sub-view), não "index". Casar AMBAS as chaves surfa o drift — ex.
  // Financeiro/Unificado/Index: proto-baseline é `unificado.*`, não `index.*`.
  const nameKeys = segs.length >= 3 ? [telaK, segs[segs.length - 2].toLowerCase()] : [telaK];
  const abs = join(PAGES_DIR, relTsx);
  const charterPath = abs.replace(/\.tsx$/, '.charter.md');
  const casosPath = abs.replace(/\.tsx$/, '.casos.md');

  // Trio (siblings — RESOLUÇÃO CONFIÁVEL por caminho exato).
  const trio = { tsx: existsSync(abs), charter: existsSync(charterPath), casos: existsSync(casosPath) };

  // Scorecard (slug — confiável, mesma chave do mapa agregado).
  const slug = screenSlug(relTsx);
  const scorecard = classifyArtifact(
    walk(SCORECARD_DIR, (f) => /\.ya?ml$/.test(f) && basename(f).replace(/\.ya?ml$/, '').toLowerCase() === slug).map(norm),
  );

  // E2E Browser (path-literal — o que o mapa agregado credita; SÓ tests/Browser).
  const key = relTsx.replace(/\.tsx$/, '');
  const e2eBrowser = walk(BROWSER_DIR, (f) => f.endsWith('.php'))
    .filter((f) => { const b = readFileSync(f, 'utf8'); return b.includes(key) || b.includes(key.replace(/\//g, '\\')); })
    .map(norm);

  // UCs do casos.md + teste que cita o id (corpus AMPLO — inclui Pest de contrato).
  let ucLink = [];
  if (trio.casos) {
    const ucs = ucsFromCasos(readFileSync(casosPath, 'utf8'));
    const corpus = testCorpusText();
    ucLink = ucs.map((uc) => ({ uc, tested: corpus.includes(uc) }));
  }

  // Artefatos de nome-DRIFTADO — reporta CANDIDATOS por nome + FLAG ambiguidade.
  const modFiles = existsSync(join(REQ_DIR, mod)) ? walk(join(REQ_DIR, mod), () => true).map(norm) : [];
  const nameHas = (f) => nameKeys.some((k) => basename(f).toLowerCase().includes(k));
  const runbook = classifyArtifact(modFiles.filter((f) => /RUNBOOK/i.test(basename(f)) && nameHas(f)));
  const visualcomp = classifyArtifact(modFiles.filter((f) => /visual-comparison\.md$/.test(f) && nameHas(f)));
  const protobaseline = classifyArtifact(modFiles.filter((f) => /\.proto-baseline\.json$/.test(f) && nameHas(f)));

  // Linkagem: charter → related_prototype declarado?
  let relatedPrototype = null;
  if (trio.charter) {
    const m = readFileSync(charterPath, 'utf8').match(/related_prototype:\s*(.+)/);
    relatedPrototype = m ? m[1].trim() : '(ausente)';
  }

  return { screen: relTsx, mod, trio, scorecard, e2eBrowser, ucLink, runbook, visualcomp, protobaseline, relatedPrototype };
}

// --screen <Mod/Tela> — resolve UMA tela e imprime tudo + linkagem, depois sai.
if (flags.has('--screen')) {
  const argIdx = process.argv.indexOf('--screen');
  const target = process.argv[argIdx + 1];
  if (!target) { console.error('uso: --screen <Mod/Tela>  (ex: Produto/Create)'); process.exit(2); }
  const r = resolveScreenFiles(target);
  const mark = (b) => (b ? '✓' : '✗');
  const artLine = (name, a) =>
    `  ${name.padEnd(18)} ${a.status === 'unique' ? '✓ ' + a.candidates[0] : a.status === 'missing' ? '✗ ausente' : '⚠ AMBÍGUO (' + a.candidates.length + '): ' + a.candidates.join(' · ')}`;
  console.log(`\n=== Arquivos da tela · ${r.screen} ===\n`);
  console.log('  TRIO (siblings, resolução confiável):');
  console.log(`    ${mark(r.trio.tsx)} .tsx   ${mark(r.trio.charter)} .charter.md   ${mark(r.trio.casos)} .casos.md`);
  console.log('');
  console.log(artLine('scorecard', r.scorecard));
  console.log(`  ${'e2e (Browser)'.padEnd(18)} ${r.e2eBrowser.length ? r.e2eBrowser.join(' · ') : '✗ nenhum teste Browser cita o path'}`);
  console.log(artLine('RUNBOOK', r.runbook));
  console.log(artLine('visual-comparison', r.visualcomp));
  console.log(artLine('proto-baseline', r.protobaseline));
  console.log('');
  console.log('  UC ↔ teste (corpus amplo, inclui Pest de contrato):');
  if (!r.ucLink.length) console.log('    (sem casos.md ou sem UC declarado)');
  for (const u of r.ucLink) console.log(`    ${mark(u.tested)} ${u.uc}${u.tested ? '' : '  ← ÓRFÃO (nenhum teste cita o id)'}`);
  console.log('');
  console.log(`  LINKAGEM charter → related_prototype: ${r.relatedPrototype ?? '(sem charter)'}`);
  // Veredito honesto de COMPLETUDE + o que a máquina NÃO resolve sozinha.
  const ambiguos = [['RUNBOOK', r.runbook], ['visual-comparison', r.visualcomp], ['proto-baseline', r.protobaseline]]
    .filter(([, a]) => a.status === 'ambiguous').map(([n]) => n);
  const orfaos = r.ucLink.filter((u) => !u.tested).map((u) => u.uc);
  console.log('\n  VEREDITO:');
  console.log(`    trio completo: ${r.trio.tsx && r.trio.charter && r.trio.casos ? '✓' : '✗ INCOMPLETO'}`);
  if (ambiguos.length) console.log(`    ⚠ nome ambíguo (resolva por declaração no charter, não por nome): ${ambiguos.join(', ')}`);
  if (orfaos.length) console.log(`    ⚠ UC órfão (sem teste): ${orfaos.join(', ')}`);
  if (!ambiguos.length && !orfaos.length) console.log('    ✓ sem ambiguidade de nome nem UC órfão');
  process.exit(0);
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
  assert.deepEqual(
    coverageRegressions(
      { charter: 10, e2e: 2, a11y: 1, scorecard: 10 },
      { charter: 10, e2e: 2, a11y: 1, scorecard: 10 },
      { e2e: 'B.tsx|C.tsx' },
      { e2e: 'A.tsx|B.tsx' },
    ),
    ['e2e'],
  );
  // --- Resolver por-tela: classifyArtifact é o núcleo que MORDE (detecta ambiguidade) ---
  assert.equal(classifyArtifact([]).status, 'missing');
  assert.equal(classifyArtifact(['a.yaml']).status, 'unique');
  // CONTROLE-NEGATIVO (o defeito REAL Financeiro/RUNBOOK: 2 candidatos p/ 1 tela).
  // Sem esta asserção a detecção de ambiguidade poderia quebrar calada = teatro.
  assert.equal(classifyArtifact(['RUNBOOK-index.md', 'RUNBOOK-unificado.md']).status, 'ambiguous');
  assert.equal(screenSlug('Financeiro/Unificado/Index.tsx'), 'financeiro-unificado-index');
  assert.equal(screenSlug('Produto/Create.tsx'), 'produto-create');
  // UC: heading conta; tachado ~~UC~~ (retirado, padrão da Maiara em Create.casos.md) NÃO conta.
  assert.deepEqual(ucsFromCasos('## UC-PCAD-01 x\n## ~~UC-PCAD-02~~ retirado\n## UC-PCAD-04 y\n'), ['UC-PCAD-01', 'UC-PCAD-04']);
  console.log('screen-coverage selftest: aliases Inertia + resolver por-tela (classifyArtifact/screenSlug/ucsFromCasos) passaram');
  process.exit(0);
}

const rows = screens.map((abs) => {
  const relTsx = relative(PAGES_DIR, abs).split(sep).join('/');
  const mod = relTsx.split('/')[0];
  const charter = existsSync(abs.replace(/\.tsx$/, '.charter.md'));
  const e2e = e2eFor(relTsx);
  const hasVisregContract = inertiaSourcesFor(relTsx).some((source) => visregSources.has(source));
  const slug = screenSlug(relTsx);
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
const coveredScreens = Object.fromEntries(
  ['charter', 'e2e', 'a11y', 'scorecard'].map((key) => [
    key,
    rows.filter((row) => row[key]).map((row) => row.screen).sort().join('|'),
  ]),
);

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
const snapshot = {
  generated_note: 'baseline da catraca de cobertura — NÃO editar à mão',
  aggregates: agg,
  covered_screens: coveredScreens,
  by_module: byModule,
};

if (flags.has('--json')) {
  writeFileSync(BASELINE, JSON.stringify(snapshot, null, 2) + '\n');
  console.log(`\n✓ baseline gravado em ${relative(ROOT, BASELINE)}`);
}

if (flags.has('--check')) {
  if (!existsSync(BASELINE)) {
    console.error('\n✗ baseline ausente — rode com --json primeiro.');
    process.exit(2);
  }
  const previousSnapshot = JSON.parse(readFileSync(BASELINE, 'utf8'));
  const prev = previousSnapshot.aggregates;
  const regress = coverageRegressions(agg, prev, coveredScreens, previousSnapshot.covered_screens);
  if (regress.length) {
    console.error(`\n✗ CATRACA: cobertura regrediu em ${regress.join(', ')} (vs baseline). PR bloqueado.`);
    for (const k of regress) console.error(`   ${k}: ${prev[k]} → ${agg[k]}`);
    process.exit(1);
  }
  console.log('\n✓ CATRACA: nenhuma regressão de cobertura.');
}
