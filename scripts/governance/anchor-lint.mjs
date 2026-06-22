#!/usr/bin/env node
// anchor-lint.mjs — parser da gramática anchor spec↔código (ADR 0273 · passo SA-A2
// do plano memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md).
//
// POR QUE EXISTE: "a spec mente" (auditoria SDD 2026-06-12). O campo
// `**Implementado em:**` não tinha formato máquina-parseável — sem lint, anchor
// falso/morto/placeholder era indistinguível de anchor verdadeiro. Este script
// implementa EXATAMENTE a gramática do ADR 0273 §1 (sentinelas `_pendente_` e
// `_parcial_` como estados de 1ª classe) e classifica cada US dos SPECs:
//
//   sem_campo      US sem linha `**Implementado em:**`
//   placeholder    legado: _[TODO…]_ · _[path]_ · (a criar…) · pseudo-path _xx_
//   pendente       `_pendente_` — tela não construída é estado LEGÍTIMO (coberta)
//   parcial        `_parcial_` + ≥1 path, todos existentes (coberta, pendência rastreável)
//   anchored_ok    preenchido com ≥1 segmento-path e TODOS os paths existem no disco
//   anchored_dead  preenchido mas path inexistente OU sem nenhum path verificável
//                  (anchor quebrado = mentira detectável — ADR 0273 §2)
//   anchored_zombie ⟵ SA-A2-bis (2026-06-22): path EXISTE no disco mas a Page está
//                  DESLIGADA — renderizada só por controller não-referenciado nas
//                  rotas (dormente / atrás de Route::redirect 301). Existir ≠ estar
//                  vivo. Fecha o ponto-cego que deixou US-FIN-013 (Dashboard/Index,
//                  deprecado 2026-06-06) passar como 🟢. Mentira mais sutil que dead.
//
// anchor_coverage = (anchored_ok + pendente + parcial) / US_total  — por módulo e global.
// zombie NÃO conta como coberta (é mentira, igual dead).
//
// TAMBÉM lint de `**Testado em:**` (SA-A2-bis): superfície antes 100% sem governança
// — os ~13 testes-fantasma do Financeiro (`AutoCriacaoTituloVendaTest` etc) passaram
// anos sem ninguém checar. dead_tests = ref de teste (path .php OU ClassName...Test)
// que não existe no repo.
//
// Uso (na raiz do repo):
//   node scripts/governance/anchor-lint.mjs                 # full-tree, tabela humana
//   node scripts/governance/anchor-lint.mjs --json          # JSON determinístico (sem timestamp/sha)
//   node scripts/governance/anchor-lint.mjs <SPEC.md ...>   # diff-aware: só os SPECs passados
//   node scripts/governance/anchor-lint.mjs --check         # exit 1 se dead>0, zombie>0,
//                                                           # dead_tests>0 ou violação v1 —
//                                                           # RESERVADO pra fase F2 (ADR 0273 §4);
//                                                           # F1 ADVISORY usa modos acima (exit 0 sempre)
// Node puro (fs). Sem deps, sem DB, sem PHP. Idioma: clone de knowledge-drift.mjs.

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';

const ROOT = process.cwd();
const REQ = join(ROOT, 'memory', 'requisitos');
const JSON_OUT = process.argv.includes('--json');
const CHECK = process.argv.includes('--check');

// ── regexes canônicas (ADR 0273 §1 — referência única; NÃO afrouxar sem novo ADR) ──
const GRAMMAR_RE = /^\*\*Implementado em:\*\* (?:_pendente_(?: — .+)?|(?:_parcial_ · )?(?:`[^`]+`)(?: · `[^`]+`)* · verificado@[0-9a-f]{7} \(\d{4}-\d{2}-\d{2}\)(?: — .+)?)$/;
// detecção LENIENTE de campo (legados usam `> ` blockquote — Vestuario — e espaçamento vário)
const FIELD_RE = /^(?:>\s*)?\*\*Implementado em:\*\*\s*(.*)$/;
const TESTADO_RE = /^(?:>\s*)?\*\*Testado em:\*\*\s*(.*)$/;
const US_HEAD_RE = /^(#{2,4})\s+.*\bUS-[A-Z][A-Za-z0-9]*-\d/;
const US_ID_RE = /US-[A-Z][A-Za-z0-9]*-\d+(?:\.\.\d+)?/;
const HEAD_RE = /^(#{1,6})\s/;
// taxonomia de placeholder legado (ADR 0273 "Contexto") — pendente/parcial têm precedência
const PLACEHOLDER_RE = /TODO|_\[path\]_|\ba criar\b|_xx_/i;
const MDLINK_RE = /\[`([^`]+)`\]\(([^)]+)\)/g; // [`seg`](alvo) — alvo relativo ao SPEC
const ANCHOR_FORMAT_V1_RE = /^anchor_format:\s*["']?v1["']?\s*$/m;

// ── SA-A2-bis (2026-06-22): "wired ≠ só existe no disco" + lint de Testado em ──
// POR QUE: existsSync sozinho deixou passar âncora ZUMBI (US-FIN-013 apontava
// Dashboard/Index.tsx, dormente + 301→/unificado desde 2026-06-06; o lint dava 🟢).
// A verdade do "está vivo" é o ROTEADOR: uma Page só é VIVA se um controller
// REFERENCIADO nas rotas (use/::class — comentário não conta) a renderiza via
// Inertia::render. Determinístico, fs-puro, sem PHP/DB.

const _graphCache = new Map();
function listPhp(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) listPhp(p, acc);
    else if (e.name.endsWith('.php')) acc.push(p);
  }
  return acc;
}
function readPhpConcat(dir) {
  return listPhp(dir).map((f) => readFileSync(f, 'utf8')).join('\n');
}
// grafo de render por módulo: { allRendered:Set, liveRendered:Set } ou null (indeterminável)
function renderGraph(mod) {
  if (_graphCache.has(mod)) return _graphCache.get(mod);
  const modDir = join(ROOT, 'Modules', mod);
  const ctrlDir = join(modDir, 'Http', 'Controllers');
  if (!existsSync(ctrlDir)) { _graphCache.set(mod, null); return null; }
  const routeTxt = readPhpConcat(join(modDir, 'Routes')) + '\n' + readPhpConcat(join(modDir, 'routes'));
  // controllers VIVOS = importados (use …\XController;) ou usados com ::class nas rotas.
  // Comentário (// DashboardController …) NÃO casa nenhum dos dois → não vira vivo.
  const live = new Set();
  for (const m of routeTxt.matchAll(/use\s+[\w\\]+\\([A-Za-z0-9_]+Controller)\s*;/g)) live.add(m[1]);
  for (const m of routeTxt.matchAll(/([A-Za-z0-9_]+)::class/g)) live.add(m[1]);
  const allRendered = new Set(), liveRendered = new Set();
  for (const f of listPhp(ctrlDir)) {
    const base = f.split(/[\\/]/).pop().replace(/\.php$/, '');
    const txt = readFileSync(f, 'utf8');
    for (const m of txt.matchAll(/Inertia::render\(\s*['"]([^'"]+)['"]/g)) {
      allRendered.add(m[1]);
      if (live.has(base)) liveRendered.add(m[1]);
    }
  }
  const g = { allRendered, liveRendered };
  _graphCache.set(mod, g);
  return g;
}
// uma Page-âncora é ZUMBI: existe no disco, é renderizada por ALGUM controller,
// mas por NENHUM controller vivo (rendered-but-only-via-dead/redirect path).
// Conservador: sub-componentes (_components/, components/) e renders por variável
// (não-literais → não estão em allRendered) NUNCA são marcados (evita falso-positivo).
function pageZombie(seg) {
  const m = seg.match(/^resources\/js\/Pages\/(.+)\.tsx$/);
  if (!m || /\/_?components\//.test(seg)) return false;
  const comp = m[1];
  const g = renderGraph(comp.split('/')[0]);
  if (!g) return false;
  return g.allRendered.has(comp) && !g.liveRendered.has(comp);
}
let _testBasenames = null;
function testBasenames() {
  if (_testBasenames) return _testBasenames;
  _testBasenames = new Set();
  const modsDir = join(ROOT, 'Modules');
  if (existsSync(modsDir)) {
    for (const e of readdirSync(modsDir, { withFileTypes: true })) {
      if (!e.isDirectory()) continue;
      for (const f of listPhp(join(modsDir, e.name, 'Tests'))) {
        _testBasenames.add(f.split(/[\\/]/).pop().replace(/\.php$/, ''));
      }
    }
  }
  return _testBasenames;
}
// refs de teste mortas numa linha `**Testado em:**` (path .php inexistente OU
// ClassName…Test sem arquivo correspondente). _lacuna_ em itálico (sem backtick) = ignorado.
function deadTestRefs(rest, specDir) {
  const out = [];
  let remaining = rest;
  for (const m of rest.matchAll(MDLINK_RE)) {
    const t = m[2].split('#')[0];
    if (!/^https?:/.test(t) && (m[1].includes('/') || t.includes('/')) && !existsSync(resolve(specDir, t))) out.push(m[1]);
    remaining = remaining.replace(m[0], ' ');
  }
  for (const m of remaining.matchAll(/`([^`]+)`/g)) {
    const seg = m[1].replace(/[.,;:]+$/, '');
    if (seg.includes('/')) { if (seg.endsWith('.php') && !existsSync(resolve(ROOT, seg))) out.push(seg); }
    else if (/Test$/.test(seg) && !testBasenames().has(seg)) out.push(seg);
  }
  return out;
}

function frontmatter(txt) {
  if (!txt.startsWith('---')) return '';
  const end = txt.indexOf('\n---', 3);
  return end === -1 ? '' : txt.slice(0, end);
}

// extrai segmentos-path verificáveis do resto do campo; devolve {paths:[{seg,abs}],…}
function extractPaths(rest, specDir) {
  const paths = [];
  let remaining = rest;
  for (const m of rest.matchAll(MDLINK_RE)) {
    const target = m[2].split('#')[0];
    if (/^https?:/.test(target)) continue;
    if (m[1].includes('/') || target.includes('/')) {
      paths.push({ seg: m[1], abs: resolve(specDir, target) });
      remaining = remaining.replace(m[0], ' ');
    }
  }
  for (const m of remaining.matchAll(/`([^`]+)`/g)) {
    const seg = m[1].replace(/[.,;:]+$/, '');
    // segmento-path = contém '/' E é relativo à raiz do repo (ADR 0273 §1);
    // `/rota` (URL) e `~/...` (home) não são verificáveis → tratados como símbolo
    if (seg.includes('/') && !seg.startsWith('/') && !seg.startsWith('~')) paths.push({ seg, abs: resolve(ROOT, seg) });
  }
  return paths;
}

function classify(rest, specDir) {
  if (rest.startsWith('_pendente_')) return { state: 'pendente', dead: [], zombie: [] };
  const parcial = rest.startsWith('_parcial_');
  if (!parcial && PLACEHOLDER_RE.test(rest)) return { state: 'placeholder', dead: [], zombie: [] };
  const paths = extractPaths(rest, specDir);
  const dead = paths.filter((p) => !existsSync(p.abs)).map((p) => p.seg);
  if (!paths.length) return { state: 'anchored_dead', dead: ['(nenhum segmento-path — preenchido/parcial exige ≥1 path, ADR 0273 §1)'], zombie: [] };
  if (dead.length) return { state: 'anchored_dead', dead, zombie: [] };
  const zombie = paths.filter((p) => pageZombie(p.seg)).map((p) => p.seg);
  if (zombie.length) return { state: 'anchored_zombie', dead: [], zombie };
  return { state: parcial ? 'parcial' : 'anchored_ok', dead: [], zombie: [] };
}

function lintSpec(file) {
  const txt = readFileSync(file, 'utf8');
  const specDir = dirname(file);
  const isV1 = ANCHOR_FORMAT_V1_RE.test(frontmatter(txt));
  const lines = txt.split('\n');
  const usList = []; // {id, line, level, fields:[{line, raw, rest}]}
  const orphans = [];
  const testadoLines = []; // {line, rest}
  let cur = null;
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i].trimEnd();
    const head = line.match(HEAD_RE);
    if (head) {
      if (US_HEAD_RE.test(line)) {
        cur = { id: (line.match(US_ID_RE) || ['US-?'])[0], line: i + 1, level: head[1].length, fields: [] };
        usList.push(cur);
      } else if (cur && head[1].length <= cur.level) cur = null;
      continue;
    }
    const f = line.match(FIELD_RE);
    if (f) { (cur ? cur.fields : orphans).push({ line: i + 1, raw: line, rest: f[1] }); continue; }
    const t = line.match(TESTADO_RE);
    if (t) testadoLines.push({ line: i + 1, rest: t[1] });
  }
  const counts = { sem_campo: 0, placeholder: 0, pendente: 0, parcial: 0, anchored_ok: 0, anchored_dead: 0, anchored_zombie: 0 };
  const deadList = [], zombieList = [], v1Violations = [];
  let fieldsTotal = 0, fieldsPlaceholder = 0, grammarOk = 0;
  const everyField = [...usList.flatMap((u) => u.fields), ...orphans];
  for (const f of everyField) {
    fieldsTotal++;
    if (GRAMMAR_RE.test(f.raw)) grammarOk++;
    else if (isV1) v1Violations.push({ line: f.line, raw: f.raw.slice(0, 120) });
    const c = classify(f.rest, specDir);
    if (c.state === 'placeholder') fieldsPlaceholder++;
    f.state = c.state; f.dead = c.dead; f.zombie = c.zombie;
  }
  for (const u of usList) {
    if (!u.fields.length) { counts.sem_campo++; continue; }
    const c = u.fields[0]; // 1 linha por US (gramática); extras contam em fields_total
    counts[c.state]++;
    if (c.state === 'anchored_dead') deadList.push({ us: u.id, line: c.line, missing: c.dead });
    if (c.state === 'anchored_zombie') zombieList.push({ us: u.id, line: c.line, dead_screens: c.zombie });
  }
  // lint de `Testado em:` — superfície antes sem governança (testes-fantasma)
  const deadTests = [];
  for (const t of testadoLines) {
    const refs = deadTestRefs(t.rest, specDir);
    if (refs.length) deadTests.push({ line: t.line, missing: refs });
  }
  const usTotal = usList.length;
  const covered = counts.anchored_ok + counts.pendente + counts.parcial; // zombie/dead NÃO contam
  return {
    us_total: usTotal, counts, coverage_pct: usTotal ? Math.round((1000 * covered) / usTotal) / 10 : null,
    fields_total: fieldsTotal, fields_placeholder: fieldsPlaceholder, fields_grammar_ok: grammarOk,
    orphan_fields: orphans.length, anchor_format_v1: isV1, dead: deadList, zombie: zombieList,
    dead_tests: deadTests, testado_lines: testadoLines.length, v1_violations: v1Violations,
  };
}

// ── seleção de SPECs: full-tree ou diff-aware (args posicionais) ─────────────
const args = process.argv.slice(2).filter((a) => !a.startsWith('--'));
let specs;
if (args.length) {
  specs = args.map((a) => resolve(ROOT, a)).filter((p) => /memory[\\/]requisitos[\\/][^\\/]+[\\/]SPEC\.md$/.test(p) && existsSync(p)).sort();
} else {
  specs = readdirSync(REQ, { withFileTypes: true })
    .filter((e) => e.isDirectory() && existsSync(join(REQ, e.name, 'SPEC.md')))
    .map((e) => join(REQ, e.name, 'SPEC.md')).sort();
}

const modules = specs.map((f) => ({ module: dirname(f).split(/[\\/]/).pop(), ...lintSpec(f) }));
const sum = (k) => modules.reduce((a, m) => a + m[k], 0);
const states = ['sem_campo', 'placeholder', 'pendente', 'parcial', 'anchored_ok', 'anchored_dead', 'anchored_zombie'];
const byState = Object.fromEntries(states.map((s) => [s, modules.reduce((a, m) => a + m.counts[s], 0)]));
const usTotal = sum('us_total');
const covered = byState.anchored_ok + byState.pendente + byState.parcial;
const coverage = usTotal ? Math.round((1000 * covered) / usTotal) / 10 : null;
const deadTestsTotal = modules.reduce((a, m) => a + m.dead_tests.length, 0);

for (const m of modules) m.flag = m.us_total === 0 ? '🟡' : (m.counts.anchored_dead > 0 || m.counts.anchored_zombie > 0 || m.dead_tests.length || m.v1_violations.length || m.coverage_pct === 0) ? '🔴' : m.coverage_pct === 100 ? '🟢' : '🟡';

const report = {
  _meta: {
    lint: 'anchor spec↔código — gramática ADR 0273 §1 (sentinelas _pendente_/_parcial_ de 1ª classe) + wired-check + testado-check (SA-A2-bis)',
    generator: 'scripts/governance/anchor-lint.mjs',
    coverage_regra: 'anchor_coverage = (anchored_ok + pendente + parcial) / us_total — _pendente_ é coberto (tela não construída ≠ dívida de anchor); anchored_ok exige TODOS os paths existentes (§2) E vivos no roteador (zumbi não conta)',
    wired_regra: 'Page-âncora ZUMBI = existe no disco + renderizada por controller NÃO-referenciado nas rotas (dormente/atrás de 301). Existir ≠ estar vivo. Conservador: sub-componentes e renders por variável nunca marcados.',
    testado_regra: 'dead_tests = ref em `**Testado em:**` (path .php OU ClassName…Test) inexistente no repo.',
    determinismo: 'sem timestamps/sha no output — re-run sem mudança no repo = diff vazio',
    fase: 'F1 ADVISORY (ADR 0273 §4) — exit 0 sempre nos modos default/--json; --check (exit 1) reservado pra F2',
    scope: args.length ? 'diff-aware (args)' : 'full-tree',
  },
  summary: {
    specs_total: modules.length, us_total: usTotal, anchor_coverage_pct: coverage, by_state: byState,
    fields_total: sum('fields_total'), fields_placeholder: sum('fields_placeholder'),
    fields_grammar_ok: sum('fields_grammar_ok'), orphan_fields: sum('orphan_fields'),
    dead_tests_total: deadTestsTotal,
    v1_files: modules.filter((m) => m.anchor_format_v1).length, v1_violations: sum('v1_violations'),
  },
  modules,
};

if (JSON_OUT) { process.stdout.write(JSON.stringify(report, null, 2) + '\n'); process.exit(0); }

console.log(`\n  ANCHOR LINT — spec↔código (ADR 0273 + wired/testado SA-A2-bis) · ${modules.length} SPECs · escopo: ${report._meta.scope}\n`);
console.log(`  ${'MÓDULO'.padEnd(20)} ${'US'.padStart(4)} ${'s/campo'.padStart(7)} ${'phold'.padStart(5)} ${'pend'.padStart(4)} ${'parc'.padStart(4)} ${'ok'.padStart(4)} ${'dead'.padStart(4)} ${'zomb'.padStart(4)} ${'dtst'.padStart(4)} ${'cov%'.padStart(6)}`);
console.log('  ' + '─'.repeat(82));
for (const m of modules) {
  const c = m.counts;
  console.log(`  ${m.flag} ${m.module.padEnd(18)} ${String(m.us_total).padStart(4)} ${String(c.sem_campo).padStart(7)} ${String(c.placeholder).padStart(5)} ${String(c.pendente).padStart(4)} ${String(c.parcial).padStart(4)} ${String(c.anchored_ok).padStart(4)} ${String(c.anchored_dead).padStart(4)} ${String(c.anchored_zombie).padStart(4)} ${String(m.dead_tests.length).padStart(4)} ${String(m.coverage_pct ?? '—').padStart(6)}`);
  for (const d of m.dead) console.log(`       💀 ${d.us} (L${d.line}): ${d.missing.join(' · ')}`);
  for (const z of m.zombie) console.log(`       🧟 ${z.us} (L${z.line}): tela DESLIGADA (renderizada só por controller fora das rotas) → ${z.dead_screens.join(' · ')}`);
  for (const t of m.dead_tests) console.log(`       🧪 Testado em (L${t.line}): teste inexistente → ${t.missing.join(' · ')}`);
  for (const v of m.v1_violations) console.log(`       ✗ v1 L${v.line}: não casa gramática ADR 0273 §1 → ${v.raw}`);
}
console.log('  ' + '─'.repeat(82));
console.log(`\n  ANCHOR COVERAGE GLOBAL: ${coverage}%  (= (${byState.anchored_ok} ok + ${byState.pendente} pend + ${byState.parcial} parc) / ${usTotal} US)`);
console.log(`  Campos: ${report.summary.fields_total} total · ${report.summary.fields_placeholder} placeholder · ${report.summary.fields_grammar_ok} já na gramática v1 · ${report.summary.orphan_fields} órfãos (fora de bloco US)`);
console.log(`  Estados por US: sem_campo ${byState.sem_campo} · placeholder ${byState.placeholder} · pendente ${byState.pendente} · parcial ${byState.parcial} · anchored_ok ${byState.anchored_ok} · anchored_dead ${byState.anchored_dead} · anchored_zombie ${byState.anchored_zombie}`);
console.log(`  Testes-fantasma (dead_tests): ${deadTestsTotal}`);
console.log(`\n  💀 dead = path inexistente · 🧟 zombie = path existe mas tela desligada · 🧪 = teste citado inexistente. Corrigir via reconciliação — nunca inventar path.\n`);

if (CHECK && (byState.anchored_dead > 0 || byState.anchored_zombie > 0 || deadTestsTotal > 0 || report.summary.v1_violations > 0)) process.exit(1);
process.exit(0);
