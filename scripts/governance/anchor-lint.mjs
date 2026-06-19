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
//   sem_campo     US sem linha `**Implementado em:**`
//   placeholder   legado: _[TODO…]_ · _[path]_ · (a criar…) · pseudo-path _xx_
//   pendente      `_pendente_` — tela não construída é estado LEGÍTIMO (coberta)
//   parcial       `_parcial_` + ≥1 path, todos existentes (coberta, pendência rastreável)
//   anchored_ok   preenchido com ≥1 segmento-path e TODOS os paths existem no disco
//   anchored_dead preenchido mas path inexistente OU sem nenhum path verificável
//                 (anchor quebrado = mentira detectável — ADR 0273 §2)
//
// anchor_coverage = (anchored_ok + pendente + parcial) / US_total  — por módulo e global.
//
// Uso (na raiz do repo):
//   node scripts/governance/anchor-lint.mjs                 # full-tree, tabela humana
//   node scripts/governance/anchor-lint.mjs --json          # JSON determinístico (sem timestamp/sha)
//   node scripts/governance/anchor-lint.mjs <SPEC.md ...>   # diff-aware: só os SPECs passados
//   node scripts/governance/anchor-lint.mjs --check         # exit 1 se dead>0 ou violação v1 —
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
const US_HEAD_RE = /^(#{2,4})\s+.*\bUS-[A-Z][A-Za-z0-9]*-\d/;
const US_ID_RE = /US-[A-Z][A-Za-z0-9]*-\d+(?:\.\.\d+)?/;
const HEAD_RE = /^(#{1,6})\s/;
// taxonomia de placeholder legado (ADR 0273 "Contexto") — pendente/parcial têm precedência
const PLACEHOLDER_RE = /TODO|_\[path\]_|\ba criar\b|_xx_/i;
const MDLINK_RE = /\[`([^`]+)`\]\(([^)]+)\)/g; // [`seg`](alvo) — alvo relativo ao SPEC
const ANCHOR_FORMAT_V1_RE = /^anchor_format:\s*["']?v1["']?\s*$/m;

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
  if (rest.startsWith('_pendente_')) return { state: 'pendente', dead: [] };
  const parcial = rest.startsWith('_parcial_');
  if (!parcial && PLACEHOLDER_RE.test(rest)) return { state: 'placeholder', dead: [] };
  const paths = extractPaths(rest, specDir);
  const dead = paths.filter((p) => !existsSync(p.abs)).map((p) => p.seg);
  if (!paths.length) return { state: 'anchored_dead', dead: ['(nenhum segmento-path — preenchido/parcial exige ≥1 path, ADR 0273 §1)'] };
  if (dead.length) return { state: 'anchored_dead', dead };
  return { state: parcial ? 'parcial' : 'anchored_ok', dead: [] };
}

function lintSpec(file) {
  const txt = readFileSync(file, 'utf8');
  const specDir = dirname(file);
  const isV1 = ANCHOR_FORMAT_V1_RE.test(frontmatter(txt));
  const lines = txt.split('\n');
  const usList = []; // {id, line, level, fields:[{line, raw, rest}]}
  const orphans = [];
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
    if (f) (cur ? cur.fields : orphans).push({ line: i + 1, raw: line, rest: f[1] });
  }
  const counts = { sem_campo: 0, placeholder: 0, pendente: 0, parcial: 0, anchored_ok: 0, anchored_dead: 0 };
  const deadList = [], v1Violations = [];
  let fieldsTotal = 0, fieldsPlaceholder = 0, grammarOk = 0;
  const everyField = [...usList.flatMap((u) => u.fields), ...orphans];
  for (const f of everyField) {
    fieldsTotal++;
    if (GRAMMAR_RE.test(f.raw)) grammarOk++;
    else if (isV1) v1Violations.push({ line: f.line, raw: f.raw.slice(0, 120) });
    const c = classify(f.rest, specDir);
    if (c.state === 'placeholder') fieldsPlaceholder++;
    f.state = c.state; f.dead = c.dead;
  }
  for (const u of usList) {
    if (!u.fields.length) { counts.sem_campo++; continue; }
    const c = u.fields[0]; // 1 linha por US (gramática); extras contam em fields_total
    counts[c.state]++;
    if (c.state === 'anchored_dead') deadList.push({ us: u.id, line: c.line, missing: c.dead });
  }
  const usTotal = usList.length;
  const covered = counts.anchored_ok + counts.pendente + counts.parcial;
  return {
    us_total: usTotal, counts, coverage_pct: usTotal ? Math.round((1000 * covered) / usTotal) / 10 : null,
    fields_total: fieldsTotal, fields_placeholder: fieldsPlaceholder, fields_grammar_ok: grammarOk,
    orphan_fields: orphans.length, anchor_format_v1: isV1, dead: deadList, v1_violations: v1Violations,
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
const states = ['sem_campo', 'placeholder', 'pendente', 'parcial', 'anchored_ok', 'anchored_dead'];
const byState = Object.fromEntries(states.map((s) => [s, modules.reduce((a, m) => a + m.counts[s], 0)]));
const usTotal = sum('us_total');
const covered = byState.anchored_ok + byState.pendente + byState.parcial;
const coverage = usTotal ? Math.round((1000 * covered) / usTotal) / 10 : null;

for (const m of modules) m.flag = m.us_total === 0 ? '🟡' : (m.counts.anchored_dead > 0 || m.v1_violations.length || m.coverage_pct === 0) ? '🔴' : m.coverage_pct === 100 ? '🟢' : '🟡';

const report = {
  _meta: {
    lint: 'anchor spec↔código — gramática ADR 0273 §1 (sentinelas _pendente_/_parcial_ de 1ª classe)',
    generator: 'scripts/governance/anchor-lint.mjs',
    coverage_regra: 'anchor_coverage = (anchored_ok + pendente + parcial) / us_total — _pendente_ é coberto (tela não construída ≠ dívida de anchor); anchored_ok exige TODOS os paths existentes (§2)',
    determinismo: 'sem timestamps/sha no output — re-run sem mudança no repo = diff vazio',
    fase: 'F1 ADVISORY (ADR 0273 §4) — exit 0 sempre nos modos default/--json; --check (exit 1) reservado pra F2',
    scope: args.length ? 'diff-aware (args)' : 'full-tree',
  },
  summary: {
    specs_total: modules.length, us_total: usTotal, anchor_coverage_pct: coverage, by_state: byState,
    fields_total: sum('fields_total'), fields_placeholder: sum('fields_placeholder'),
    fields_grammar_ok: sum('fields_grammar_ok'), orphan_fields: sum('orphan_fields'),
    v1_files: modules.filter((m) => m.anchor_format_v1).length, v1_violations: sum('v1_violations'),
  },
  modules,
};

if (JSON_OUT) { process.stdout.write(JSON.stringify(report, null, 2) + '\n'); process.exit(0); }

console.log(`\n  ANCHOR LINT — spec↔código (ADR 0273) · ${modules.length} SPECs · escopo: ${report._meta.scope}\n`);
console.log(`  ${'MÓDULO'.padEnd(20)} ${'US'.padStart(4)} ${'s/campo'.padStart(7)} ${'phold'.padStart(5)} ${'pend'.padStart(4)} ${'parc'.padStart(4)} ${'ok'.padStart(4)} ${'dead'.padStart(4)} ${'cov%'.padStart(6)}`);
console.log('  ' + '─'.repeat(70));
for (const m of modules) {
  const c = m.counts;
  console.log(`  ${m.flag} ${m.module.padEnd(18)} ${String(m.us_total).padStart(4)} ${String(c.sem_campo).padStart(7)} ${String(c.placeholder).padStart(5)} ${String(c.pendente).padStart(4)} ${String(c.parcial).padStart(4)} ${String(c.anchored_ok).padStart(4)} ${String(c.anchored_dead).padStart(4)} ${String(m.coverage_pct ?? '—').padStart(6)}`);
  for (const d of m.dead) console.log(`       💀 ${d.us} (L${d.line}): ${d.missing.join(' · ')}`);
  for (const v of m.v1_violations) console.log(`       ✗ v1 L${v.line}: não casa gramática ADR 0273 §1 → ${v.raw}`);
}
console.log('  ' + '─'.repeat(70));
console.log(`\n  ANCHOR COVERAGE GLOBAL: ${coverage}%  (= (${byState.anchored_ok} ok + ${byState.pendente} pend + ${byState.parcial} parc) / ${usTotal} US)`);
console.log(`  Campos: ${report.summary.fields_total} total · ${report.summary.fields_placeholder} placeholder · ${report.summary.fields_grammar_ok} já na gramática v1 · ${report.summary.orphan_fields} órfãos (fora de bloco US)`);
console.log(`  Estados por US: sem_campo ${byState.sem_campo} · placeholder ${byState.placeholder} · pendente ${byState.pendente} · parcial ${byState.parcial} · anchored_ok ${byState.anchored_ok} · anchored_dead ${byState.anchored_dead}`);
console.log(`\n  💀 anchored_dead = anchor quebrado (mentira detectável). Corrigir via backfill SA-A4/A5 — nunca inventar path.\n`);

if (CHECK && (byState.anchored_dead > 0 || report.summary.v1_violations > 0)) process.exit(1);
process.exit(0);
