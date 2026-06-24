#!/usr/bin/env node
// doneness-lint.mjs — catraca de fonte-única do "done-ness" de US (ADR 0302).
//
// POR QUE EXISTE: as SPECs têm DOIS campos que declaram se uma US está pronta, e eles
// drifam (dual-source-of-truth — a mesma doença dos 4 índices de ADR que viraram 1 fonte
// gerada, ADR 0256/0258):
//   1. `status:` no blockquote do US (ex: `> owner: w · status: todo · type: story`) —
//      digitado à mão, governado por NENHUM gate.
//   2. `**Implementado em:**` — âncora spec↔código verificável contra o disco (ADR 0273),
//      já parseada por anchor-lint.mjs.
// A ADR 0302 elege a ÂNCORA como fonte única de done-ness; o `status:` passa a ser
// derivado/aposentado. Este lint detecta o CONFLITO entre os dois — não a saúde do anchor
// (isso é anchor-lint.mjs, concern separado · SoC). Reusa a gramática/classify da ADR 0273.
//
// Estados de reconciliação por US que TEM `status:` (US sem status não entram — não há o
// que contradizer; elas só contam pra anchor_coverage do anchor-lint):
//   conflito_done_sem_ancora   status=done  + SEM âncora viva  → "diz pronto, zero prova"  (MORDE)
//   conflito_aberto_com_ancora status=aberto + âncora viva     → "diz a-fazer, código existe" (MORDE)
//   zona_cinza_aberto_sem_anc  status=aberto + sem âncora viva → ingovernável (advisory, NÃO morde)
//   consistente_done           status=done  + âncora viva      → ok
//   consistente_aberto         status=aberto + _pendente_      → ok (ambos concordam: não-feito)
//   terminal / outro           superseded / valor desconhecido → ignorado
// âncora viva = anchored_ok | parcial (ADR 0273 §2 — paths existem no disco).
//
// USO (na raiz do repo):
//   node scripts/governance/doneness-lint.mjs                 # full-tree, tabela humana
//   node scripts/governance/doneness-lint.mjs --json          # JSON determinístico (sem timestamp/sha)
//   node scripts/governance/doneness-lint.mjs <SPEC.md ...>   # diff-aware: só os SPECs passados
//   node scripts/governance/doneness-lint.mjs --check         # exit 1 se houver CONFLITO (não zona-cinza)
//                                                             # ADVISORY até promoção (calendário ADR 0275)
//   node scripts/governance/doneness-lint.mjs --baseline <path>      # grandfathera o conflito LEGADO
//   node scripts/governance/doneness-lint.mjs --emit-baseline        # imprime o baseline da dívida ATUAL
//
// SA-A2-ter ARMING (ADR 0275 calendário + ADR 0303), espelho do --baseline do anchor-lint:
// --baseline <path> grandfathera o conflito status×âncora LEGADO (chave `<kind>:<US-ID>`,
// ex `conflito_done_sem_ancora:US-NFE-049`) → o --check só MORDE conflito AUSENTE do baseline
// (no-new-lie · ratchet só-desce). Sem --baseline = comportamento IDÊNTICO ao anterior
// (raw · advisory zero-regressão). --emit-baseline imprime a dívida ATUAL (determinístico,
// regenerável). CRESCER o baseline = grandfatherar mentira nova → exige trailer `BASELINE-GROW`
// (baseline-tamper-guard). DIMINUIR (dívida reconciliada) é livre. fs-puro (1 JSON).
// Node puro (fs). Sem deps, sem DB, sem PHP. Idioma: clone de anchor-lint.mjs (ADR 0273).

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';

const ROOT = process.cwd();
const REQ = join(ROOT, 'memory', 'requisitos');
const JSON_OUT = process.argv.includes('--json');
const CHECK = process.argv.includes('--check');
const BASELINE_PATH = (() => { const i = process.argv.indexOf('--baseline'); return i >= 0 ? process.argv[i + 1] : null; })();
const EMIT_BASELINE = process.argv.includes('--emit-baseline');

// ── regexes (espelham anchor-lint.mjs — fonte única da gramática anchor é ADR 0273 §1) ──
const FIELD_RE = /^(?:>\s*)?\*\*Implementado em:\*\*\s*(.*)$/;
const US_HEAD_RE = /^(#{2,4})\s+.*\bUS-[A-Z][A-Za-z0-9]*-\d/;
const US_ID_RE = /US-[A-Z][A-Za-z0-9]*-\d+(?:\.\.\d+)?/;
const HEAD_RE = /^(#{1,6})\s/;
const PLACEHOLDER_RE = /TODO|_\[path\]_|\ba criar\b|_xx_/i;
const MDLINK_RE = /\[`([^`]+)`\]\(([^)]+)\)/g;
// status: só na linha de metadados do US (blockquote `> ...`) — evita falso match de
// `{status: 'arquivada'}` em corpo de Rota e do frontmatter `status: ativo` (fora de US).
const STATUS_RE = /^>\s*.*\bstatus:\s*([A-Za-z][A-Za-z0-9_-]*)/;

// vocabulário de status (84 done · 365 todo · 17 review · 16 backlog · 6 blocked · 5 doing ·
// 4 in[_-]progress · 1 superseded — medido 2026-06-22). done = único "declara pronto".
const DONE = new Set(['done']);
const OPEN = new Set(['todo', 'backlog', 'doing', 'blocked', 'review', 'in_progress', 'in-progress', 'wip', 'review-pendente']);
const TERMINAL = new Set(['superseded', 'cancelled', 'canceled', 'wontfix', 'duplicate', 'recusado']);

// ── classify do anchor (idêntico a anchor-lint.mjs — não afrouxar sem ADR) ───────────────
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
    if (seg.includes('/') && !seg.startsWith('/') && !seg.startsWith('~')) paths.push({ seg, abs: resolve(ROOT, seg) });
  }
  return paths;
}

function classifyAnchor(rest, specDir) {
  if (rest.startsWith('_pendente_')) return 'pendente';
  const parcial = rest.startsWith('_parcial_');
  if (!parcial && PLACEHOLDER_RE.test(rest)) return 'placeholder';
  const paths = extractPaths(rest, specDir);
  if (!paths.length) return 'anchored_dead';
  if (paths.some((p) => !existsSync(p.abs))) return 'anchored_dead';
  return parcial ? 'parcial' : 'anchored_ok';
}

// estado do anchor de uma US: sem o campo = 'sem_campo'; senão classifica o 1º campo.
const anchorState = (anchorRest, specDir) => (anchorRest == null ? 'sem_campo' : classifyAnchor(anchorRest, specDir));
const isLive = (state) => state === 'anchored_ok' || state === 'parcial';

function reconcile(status, state) {
  const live = isLive(state);
  if (DONE.has(status)) return live ? 'consistente_done' : 'conflito_done_sem_ancora';
  if (OPEN.has(status)) {
    if (live) return 'conflito_aberto_com_ancora';
    if (state === 'pendente') return 'consistente_aberto';
    return 'zona_cinza_aberto_sem_anc';
  }
  return TERMINAL.has(status) ? 'terminal' : 'outro';
}

const RECON = ['conflito_done_sem_ancora', 'conflito_aberto_com_ancora', 'zona_cinza_aberto_sem_anc',
  'consistente_done', 'consistente_aberto', 'terminal', 'outro'];

// chave canônica do conflito pro baseline grandfather (espelho keyAceite/keyTeste do anchor-lint):
// `<kind>:<US-ID>` — estável (sem nº de linha, que driva). ex: conflito_done_sem_ancora:US-NFE-049.
const keyConflict = (c) => `${c.kind}:${c.us}`;

function lintSpec(file) {
  const txt = readFileSync(file, 'utf8');
  const specDir = dirname(file);
  const lines = txt.split('\n');
  const usList = []; // {id, line, level, status, anchorRest}
  let cur = null;
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i].trimEnd();
    const head = line.match(HEAD_RE);
    if (head) {
      if (US_HEAD_RE.test(line)) {
        cur = { id: (line.match(US_ID_RE) || ['US-?'])[0], line: i + 1, level: head[1].length, status: null, anchorRest: null };
        usList.push(cur);
      } else if (cur && head[1].length <= cur.level) cur = null;
      continue;
    }
    if (!cur) continue;
    const s = line.match(STATUS_RE);
    if (s && cur.status == null) cur.status = s[1].toLowerCase();
    const f = line.match(FIELD_RE);
    if (f && cur.anchorRest == null) cur.anchorRest = f[1];
  }
  const counts = Object.fromEntries(RECON.map((r) => [r, 0]));
  const conflicts = [];
  let withStatus = 0;
  for (const u of usList) {
    if (u.status == null) continue; // US sem status não entra na reconciliação
    withStatus++;
    const state = anchorState(u.anchorRest, specDir);
    const r = reconcile(u.status, state);
    counts[r]++;
    if (r.startsWith('conflito_')) conflicts.push({ us: u.id, line: u.line, status: u.status, anchor: state, kind: r });
  }
  return { us_total: usList.length, us_with_status: withStatus, counts, conflicts };
}

// ── seleção de SPECs: full-tree ou diff-aware (args posicionais) — igual anchor-lint ─────
// exclui o valor de `--baseline <path>` dos posicionais (é caminho de baseline, não SPEC).
const _rawArgv = process.argv.slice(2);
const _baselineValIdx = _rawArgv.includes('--baseline') ? _rawArgv.indexOf('--baseline') + 1 : -1;
const args = _rawArgv.filter((a, i) => !a.startsWith('--') && i !== _baselineValIdx);
let specs;
if (args.length) {
  specs = args.map((a) => resolve(ROOT, a)).filter((p) => /memory[\\/]requisitos[\\/][^\\/]+[\\/]SPEC\.md$/.test(p) && existsSync(p)).sort();
} else {
  specs = readdirSync(REQ, { withFileTypes: true })
    .filter((e) => e.isDirectory() && existsSync(join(REQ, e.name, 'SPEC.md')))
    .map((e) => join(REQ, e.name, 'SPEC.md')).sort();
}

const modules = specs.map((f) => ({ module: dirname(f).split(/[\\/]/).pop(), ...lintSpec(f) }));
const byRecon = Object.fromEntries(RECON.map((r) => [r, modules.reduce((a, m) => a + m.counts[r], 0)]));
const usTotal = modules.reduce((a, m) => a + m.us_total, 0);
const withStatus = modules.reduce((a, m) => a + m.us_with_status, 0);
const conflictsTotal = byRecon.conflito_done_sem_ancora + byRecon.conflito_aberto_com_ancora;

// ── baseline grandfather (ARMING SA-A2-ter · ADR 0275/0303) — espelho do anchor-lint ──────
// emit: imprime a dívida de conflito ATUAL (chave canônica, sorted+unique) — fonte regenerável;
// arming consome o que ele emite. Mesma engine que CHECA é a que EMITE → chaves nunca derivam.
const _allConflictKeys = modules.flatMap((m) => m.conflicts.map(keyConflict));
if (EMIT_BASELINE) {
  const grandfathered = [...new Set(_allConflictKeys)].sort();
  process.stdout.write(JSON.stringify({
    _meta: {
      baseline: 'doneness GRANDFATHER — conflitos status×âncora legados isentos (ratchet só-desce · ADR 0302/0275 advisory→required por calendário)',
      regra: 'gate --check com --baseline morde só chave AUSENTE daqui (no-new-lie). CRESCER esta lista = grandfatherar conflito novo → exige trailer `BASELINE-GROW` (baseline-tamper-guard). DIMINUIR (reconciliado) é livre.',
      gerado_por: 'node scripts/governance/doneness-lint.mjs --emit-baseline',
      consumido_por: ['scripts/governance/doneness-lint.mjs --baseline', '.github/workflows/anchor-drift.yml'],
      adr: ['0302-fonte-unica-doneness-anchor-aposenta-status-spec', '0273-anchor-spec-codigo-formato-canonico-fluxo-novo', '0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes'],
      escopo: args.length ? 'diff-aware (args) — NÃO use pra gerar o baseline canônico; rode full-tree' : 'full-tree',
      total: grandfathered.length,
    },
    grandfathered,
  }, null, 2) + '\n');
  process.exit(0);
}

// load + filtro: conflito grandfatherado NÃO conta no veredito de saída (report mantém visibilidade).
function loadBaseline(p) {
  if (!p) return null; // sem baseline = comportamento legado (raw)
  if (!existsSync(p)) return new Set(); // ausente = vazio (ordering-robusto entre PRs; advisory)
  try { return new Set((JSON.parse(readFileSync(p, 'utf8')).grandfathered) || []); }
  catch { console.error(`doneness-lint: --baseline ${p} não parseia como JSON.`); process.exit(2); }
}
const BASELINE = loadBaseline(BASELINE_PATH);
for (const m of modules) m._conflicts_active = BASELINE ? m.conflicts.filter((c) => !BASELINE.has(keyConflict(c))) : m.conflicts;
const grandfatheredConflicts = BASELINE ? _allConflictKeys.filter((k) => BASELINE.has(k)).length : 0;
// ATIVOS (= raw quando sem --baseline; raw-menos-grandfathered quando com baseline) — é o que MORDE.
const conflictsActiveTotal = modules.reduce((a, m) => a + m._conflicts_active.length, 0);

for (const m of modules) {
  const c = m.counts;
  m.flag = m._conflicts_active.length > 0 ? '🔴'
    : c.zona_cinza_aberto_sem_anc > 0 ? '🟡' : m.us_with_status > 0 ? '🟢' : '⚪';
}

const report = {
  _meta: {
    lint: 'doneness fonte-única — conflito status:×âncora (ADR 0302). Âncora = fonte única (ADR 0273); status: legado derivado/aposentado.',
    generator: 'scripts/governance/doneness-lint.mjs',
    regra: 'CONFLITO = (status=done sem âncora viva) + (status=aberto com âncora viva). zona-cinza (aberto sem âncora) é advisory, NÃO conta como conflito.',
    ancora_viva: 'anchored_ok | parcial (paths existem no disco · ADR 0273 §2). _pendente_ NÃO é viva (tela não construída).',
    determinismo: 'sem timestamps/sha no output — re-run sem mudança no repo = diff vazio',
    fase: 'ADVISORY (ADR 0271/0275) — exit 0 sempre nos modos default/--json; --check (exit 1) é o primitivo de enforcement, promovido por calendário (ADR 0275)',
    baseline: BASELINE
      ? `grandfather aplicado (${BASELINE_PATH}): conflitos legados grandfatherados NÃO mordem (no-new-lie · ADR 0275). conflitos_ativos = conflito NOVO/tocado.`
      : 'sem --baseline: conflitos_total = brutos (legado + novo). Arming passa --baseline pra grandfatherar o legado.',
    scope: args.length ? 'diff-aware (args)' : 'full-tree',
  },
  summary: {
    specs_total: modules.length, us_total: usTotal, us_with_status: withStatus,
    conflitos_total: conflictsTotal, conflitos_ativos: conflictsActiveTotal, grandfathered: grandfatheredConflicts,
    conflito_done_sem_ancora: byRecon.conflito_done_sem_ancora,
    conflito_aberto_com_ancora: byRecon.conflito_aberto_com_ancora,
    zona_cinza_aberto_sem_anc: byRecon.zona_cinza_aberto_sem_anc,
    consistente_done: byRecon.consistente_done, consistente_aberto: byRecon.consistente_aberto,
    terminal: byRecon.terminal, outro: byRecon.outro,
  },
  modules,
};

if (JSON_OUT) { process.stdout.write(JSON.stringify(report, null, 2) + '\n'); process.exit(0); }

console.log(`\n  DONENESS LINT — fonte-única status:×âncora (ADR 0302) · ${modules.length} SPECs · escopo: ${report._meta.scope}\n`);
console.log(`  ${'MÓDULO'.padEnd(20)} ${'US'.padStart(4)} ${'c/st'.padStart(5)} ${'done✗'.padStart(6)} ${'abrt✗'.padStart(6)} ${'cinza'.padStart(6)} ${'ok'.padStart(4)}`);
console.log('  ' + '─'.repeat(64));
for (const m of modules) {
  if (m.us_with_status === 0) continue;
  const c = m.counts;
  const ok = c.consistente_done + c.consistente_aberto;
  console.log(`  ${m.flag} ${m.module.padEnd(18)} ${String(m.us_total).padStart(4)} ${String(m.us_with_status).padStart(5)} ${String(c.conflito_done_sem_ancora).padStart(6)} ${String(c.conflito_aberto_com_ancora).padStart(6)} ${String(c.zona_cinza_aberto_sem_anc).padStart(6)} ${String(ok).padStart(4)}`);
  for (const x of m._conflicts_active) console.log(`       ⚠️  ${x.us} (L${x.line}): status=${x.status} × anchor=${x.anchor} → ${x.kind}`);
}
console.log('  ' + '─'.repeat(64));
console.log(`\n  US: ${usTotal} total · ${withStatus} com status: (superfície do dual-source) · ${usTotal - withStatus} sem status:`);
console.log(`  CONFLITOS (mordem em --check): ${conflictsActiveTotal}${BASELINE ? ` ativo(s) (bruto ${conflictsTotal} − ${grandfatheredConflicts} grandfathered)` : `  = ${byRecon.conflito_done_sem_ancora} done-sem-âncora + ${byRecon.conflito_aberto_com_ancora} aberto-com-âncora`}`);
if (BASELINE) console.log(`  Grandfather (${BASELINE_PATH}): ${grandfatheredConflicts} conflito(s) legado(s) isento(s) (no-new-lie · ratchet só-desce · ADR 0275)`);
console.log(`  Zona-cinza (advisory, NÃO morde): ${byRecon.zona_cinza_aberto_sem_anc} aberto-sem-âncora (ingovernável até backfill)`);
console.log(`  Consistentes: ${byRecon.consistente_done} done+âncora · ${byRecon.consistente_aberto} aberto+pendente · terminal ${byRecon.terminal} · outro ${byRecon.outro}`);
console.log(`\n  Fonte única de done-ness = **Implementado em:** (ADR 0273). status: é legado derivado/aposentado (ADR 0302).\n`);

if (CHECK && conflictsActiveTotal > 0) process.exit(1);
process.exit(0);
