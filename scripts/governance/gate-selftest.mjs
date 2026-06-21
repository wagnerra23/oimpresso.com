#!/usr/bin/env node
// gate-selftest.mjs — QUEM VIGIA OS VIGIAS (frente GT-G6, plano-mãe SDD 2026-06-12 §2
// "TESTADA nível 2": fixtures boa+ruim por catraca provam que os gates MORDEM).
//
// Pra cada catraca de governança existente em main, roda o script REAL contra um par
// de fixtures versionadas (tests/governance-fixtures/ · README lá dentro):
//   good → TEM que sair 0 E imprimir a mensagem de OK esperada;
//   bad  → TEM que sair 1 E imprimir a ACUSAÇÃO esperada (exit 1 por crash ≠ morder).
// Se o caso ruim passar, a catraca parou de morder → este selftest avermelha.
//
// Catracas cobertas: knowledge-drift --check (anti-ghost) · foundation-ratchet
// (quarentena/RefreshDatabase/Business::first — fixtures reusadas de
// scripts/tests/fixtures/foundation-ratchet, já versionadas em main) · ledger-check
// --enforce (protocolo refutador GT-G5) · sdd-scorecard --ratchet ARMADO (GT-G2) ·
// memory-health (Check A colisão ADR não-registrada — único .mjs que MORDE no merge
// via governance-gate-umbrella, antes fora do selftest · ADR 0256 Knowledge Survival) ·
// anchor-lint --check (anchored_dead = anchor morto · ADR 0273 §2 · P08).
//
// USO (na raiz do repo):
//   node scripts/governance/gate-selftest.mjs              # 6 catracas × 2 fixtures
//   node scripts/governance/gate-selftest.mjs --json
//   node scripts/governance/gate-selftest.mjs --only ledger-check
//   node scripts/governance/gate-selftest.mjs --script knowledge-drift=<path>
//     ↑ sanity: aponte pra uma CÓPIA temp com o exit 1 neutralizado e prove que o
//       selftest pega (caso ruim passa → selftest exit 1). Nunca neutralize o real.
// Node puro (fs + spawnSync). Sem deps, sem DB, sem rede. Segundos.

import { spawnSync } from 'node:child_process';
import { appendFileSync, cpSync, mkdirSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const ROOT = process.cwd();
const FIX = join(ROOT, 'tests', 'governance-fixtures');
const args = process.argv.slice(2);
const JSON_OUT = args.includes('--json');
const ONLY = args.includes('--only') ? args[args.indexOf('--only') + 1] : null;
const SUMMARY = process.env.GITHUB_STEP_SUMMARY;

const overrides = {};
args.forEach((a, i) => {
  if (a !== '--script' || !args[i + 1]) return;
  const eq = args[i + 1].indexOf('=');
  overrides[args[i + 1].slice(0, eq)] = resolve(args[i + 1].slice(eq + 1));
});
const script = (id, def) => overrides[id] || join(ROOT, def);

// GITHUB_STEP_SUMMARY vazio nos filhos: fixture não polui o summary real do CI.
const runNode = (file, argv, cwd, env = {}) => spawnSync(process.execPath, [file, ...argv], {
  cwd, encoding: 'utf8', env: { ...process.env, GITHUB_STEP_SUMMARY: '', ...env },
});

// sdd-scorecard mede via cwd (e exec'a knowledge-drift relativo a ele) → sandbox temp
// com a fixture + os scripts REAIS copiados. Fixture em git fica só dados.
function runScorecard(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-${kind}-`));
  try {
    cpSync(join(FIX, 'sdd-scorecard', kind), sb, { recursive: true });
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    cpSync(script('sdd-scorecard', 'scripts/governance/sdd-scorecard.mjs'), join(sb, 'scripts', 'governance', 'sdd-scorecard.mjs'));
    cpSync(join(ROOT, 'scripts', 'governance', 'knowledge-drift.mjs'), join(sb, 'scripts', 'governance', 'knowledge-drift.mjs'));
    // sdd-scorecard agora delega anchor_coverage a anchor-lint.mjs (ledger §A · ADR 0273 §2) — copia essa dep tb.
    cpSync(join(ROOT, 'scripts', 'governance', 'anchor-lint.mjs'), join(sb, 'scripts', 'governance', 'anchor-lint.mjs'));
    return runNode(join(sb, 'scripts', 'governance', 'sdd-scorecard.mjs'), ['--ratchet'], sb, { SDD_RATCHET_ARM: '1' });
  } finally { rmSync(sb, { recursive: true, force: true }); }
}

// memory-health varre process.cwd() (memory/decisions/ + _INDEX-LIFECYCLE.md) e lê o
// baseline em scripts/governance/.memory-health-baseline.json → mesmo padrão sandbox:
// fixture (só memory/decisions/ + baseline neutro) + o script REAL copiado por cima.
// Fixture isola o Check A (colisão ADR) — sem memory/{requisitos,sessions,reference}
// nem .github/workflows, as outras checagens nem disparam.
function runMemoryHealth(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-memory-health-${kind}-`));
  try {
    cpSync(join(FIX, 'memory-health', kind), sb, { recursive: true });
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    cpSync(script('memory-health', 'scripts/governance/memory-health.mjs'), join(sb, 'scripts', 'governance', 'memory-health.mjs'));
    return runNode(join(sb, 'scripts', 'governance', 'memory-health.mjs'), [], sb);
  } finally { rmSync(sb, { recursive: true, force: true }); }
}

const CATRACAS = [
  {
    id: 'knowledge-drift',
    run: (kind) => runNode(script('knowledge-drift', 'scripts/governance/knowledge-drift.mjs'), ['--check'], join(FIX, 'knowledge-drift', kind)),
    expect: { good: /nenhum ghost novo/, bad: /GhostNovo/ },
  },
  {
    id: 'foundation-ratchet',
    run: (kind) => {
      const fx = join(ROOT, 'scripts', 'tests', 'fixtures', 'foundation-ratchet', kind);
      return runNode(script('foundation-ratchet', 'scripts/tests/foundation-ratchet.mjs'), ['--root', fx, '--baseline', join(fx, 'baseline.json')], ROOT);
    },
    expect: { good: /foundation ratchet OK/, bad: /catraca FALHOU/ },
  },
  {
    id: 'ledger-check',
    run: (kind) => {
      const fx = join(FIX, 'ledger-check');
      return runNode(script('ledger-check', 'scripts/governance/ledger-check.mjs'),
        ['--pr', '9999', '--files-from', join(fx, 'files.txt'), '--ledger', join(fx, kind, 'ledger.json'), '--enforce'], ROOT);
    },
    expect: { good: /entry valida no ledger/, bad: /FAIL ledger-check/ },
  },
  {
    id: 'sdd-scorecard',
    run: runScorecard,
    expect: { good: /nenhuma regressão/, bad: /RATCHET \(ARMADA\): ghost_count/ },
  },
  {
    id: 'memory-health',
    run: runMemoryHealth,
    expect: { good: /base de conhecimento saudável/, bad: /colidiu.*_INDEX-LIFECYCLE/ },
  },
  {
    // anchor-lint --check resolve segmento-paths contra process.cwd() (anchor-lint.mjs:33,72) →
    // a fixture é um SANDBOX por cwd (igual knowledge-drift): good = anchor p/ path existente
    // (anchored_ok, exit 0), bad = anchor p/ path inexistente (anchored_dead, exit 1 · ADR 0273 §2).
    // good regex: linha-resumo sempre impressa no exit 0. bad regex: a LÁPIDE 💀 da US morta —
    // específica do anchored_dead (a legenda genérica "💀 anchored_dead =" sai nos dois; a US não).
    id: 'anchor-lint',
    run: (kind) => runNode(script('anchor-lint', 'scripts/governance/anchor-lint.mjs'), ['--check'], join(FIX, 'anchor-lint', kind)),
    expect: { good: /ANCHOR COVERAGE GLOBAL/, bad: /💀 US-DA-001/ },
  },
];

const results = [];
for (const c of CATRACAS) {
  if (ONLY && c.id !== ONLY) continue;
  for (const kind of ['good', 'bad']) {
    const r = c.run(kind);
    const out = (r.stdout || '') + (r.stderr || '') + (r.error ? String(r.error) : '');
    const want = kind === 'good' ? 0 : 1;
    const exitOk = r.status === want;
    const msgOk = c.expect[kind].test(out);
    results.push({ catraca: c.id, fixture: kind, exit: r.status, want, exitOk, msgOk, ok: exitOk && msgOk, out });
  }
}

const fails = results.filter((r) => !r.ok);
if (JSON_OUT) {
  console.log(JSON.stringify({ ok: fails.length === 0, cases: results.map(({ out, ...r }) => r) }, null, 2));
  process.exit(fails.length ? 1 : 0);
}

console.log(`\n  GATE-SELFTEST — quem vigia os vigias (${results.length / 2} catracas × 2 fixtures)\n`);
for (const r of results) {
  console.log(`  ${r.ok ? '✓' : '✗'} ${r.catraca.padEnd(18)} ${r.fixture.padEnd(5)} exit ${r.exit} (esperado ${r.want})${r.msgOk ? '' : ' — saída NÃO bate com a acusação/OK esperado'}`);
  if (!r.ok) {
    if (r.fixture === 'bad' && r.exit === 0) console.log('      🔴 CATRACA PAROU DE MORDER — o caso ruim passou. NÃO mergear até restaurar.');
    if (r.fixture === 'good' && r.exit !== 0) console.log('      🔴 fixture boa avermelhou — gate quebrado ou fixture desatualizada.');
    console.log('      ── saída capturada ──\n' + r.out.split('\n').map((l) => '      ' + l).join('\n'));
  }
}
if (SUMMARY) {
  appendFileSync(SUMMARY, ['## Gate selftest (advisory · GT-G6)', '', '| catraca | good | bad |', '|---|---|---|',
    ...CATRACAS.filter((c) => !ONLY || c.id === ONLY).map((c) => {
      const g = results.find((r) => r.catraca === c.id && r.fixture === 'good');
      const b = results.find((r) => r.catraca === c.id && r.fixture === 'bad');
      return `| ${c.id} | ${g.ok ? '🟢 passa' : '🔴'} | ${b.ok ? '🟢 morde' : '🔴 NÃO morde'} |`;
    }), ''].join('\n'));
}
if (fails.length) { console.log(`\n  ✗ ${fails.length}/${results.length} caso(s) falharam — os vigias NÃO estão garantidos.\n`); process.exit(1); }
console.log(`\n  ✓ ${results.length}/${results.length} — todas as catracas verificadas MORDEM (boa passa, ruim falha pelo motivo certo).\n`);
