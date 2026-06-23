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
// baseline-tamper-guard (anti-grandfather, vetor #2848: afrouxa baseline + toca código
// no MESMO PR — sandbox git real, P05 fecha o grandfather dos baselines-ratchet) ·
// anchor-lint --check (anchored_dead = anchor morto · ADR 0273 §2 · P08) ·
// doneness-lint --check (conflito status:×âncora — done-sem-âncora / aberto-com-âncora · ADR 0302) ·
// anchor-lint-wired --check (wired/zombie + testado-fantasma · ADR 0303 SA-A2-bis: existir ≠ estar vivo).
//
// USO (na raiz do repo):
//   node scripts/governance/gate-selftest.mjs              # N catracas × 2 fixtures
//   node scripts/governance/gate-selftest.mjs --json
//   node scripts/governance/gate-selftest.mjs --only ledger-check
//   node scripts/governance/gate-selftest.mjs --script knowledge-drift=<path>
//     ↑ sanity: aponte pra uma CÓPIA temp com o exit 1 neutralizado e prove que o
//       selftest pega (caso ruim passa → selftest exit 1). Nunca neutralize o real.
// Node puro (fs + spawnSync). Sem deps, sem DB, sem rede. Segundos.

import { spawnSync } from 'node:child_process';
import { appendFileSync, cpSync, existsSync, mkdirSync, mkdtempSync, rmSync } from 'node:fs';
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

// memory-health Check N (colisão de US-ID · ADR 0304): mesmo sandbox por cwd, mas a fixture
// traz memory/requisitos/<mod>/SPEC.md. good = US-IDs únicos → exit 0 ("saudável"); bad = US-ID
// duplicado (heading `### US-...` repetido) → exit 1, acusação 🔴 [N]. Baseline ausente no
// sandbox ⇒ todo dup é NOVO ⇒ morde (prova o ratchet sem grandfather mascarar).
function runMemoryHealthUs(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-memory-health-us-${kind}-`));
  try {
    cpSync(join(FIX, 'memory-health-us', kind), sb, { recursive: true });
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    cpSync(script('memory-health', 'scripts/governance/memory-health.mjs'), join(sb, 'scripts', 'governance', 'memory-health.mjs'));
    return runNode(join(sb, 'scripts', 'governance', 'memory-health.mjs'), [], sb);
  } finally { rmSync(sb, { recursive: true, force: true }); }
}

// baseline-tamper-guard depende de HISTÓRIA git (diff/show/log BASE..HEAD), não só
// de cwd como os outros. Por isso o runner monta um sandbox git de verdade:
//   commit base  = baseline APERTADO (ghost_count armado) + o script REAL copiado;
//   commit head  = baseline AFROUXADO (ghost_count desarmado) — good ISOLADO,
//                  bad PAREADO com um arquivo de "código" dummy (sem BASELINE-ABSORB).
// Roda o tamper-guard com --base <sha-base> dentro do sandbox. good → exit 0
// (afrouxamento isolado = curadoria), bad → exit 1 (afrouxou + tocou código = vetor #2848).
function runTamperGuard(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-tamper-${kind}-`));
  const fx = join(FIX, 'baseline-tamper-guard');
  const g = (cmd) => spawnSync('git', cmd, { cwd: sb, encoding: 'utf8', env: { ...process.env, GIT_TERMINAL_PROMPT: '0' } });
  try {
    g(['init', '-q', '-b', 'main']);
    g(['config', 'user.email', 'selftest@oimpresso.local']);
    g(['config', 'user.name', 'gate-selftest']);
    g(['config', 'commit.gpgsign', 'false']);
    mkdirSync(join(sb, 'governance'), { recursive: true });
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    cpSync(script('baseline-tamper-guard', 'scripts/governance/baseline-tamper-guard.mjs'), join(sb, 'scripts', 'governance', 'baseline-tamper-guard.mjs'));
    // commit base — baseline apertado
    cpSync(join(fx, 'base', 'sdd-scorecard-baseline.json'), join(sb, 'governance', 'sdd-scorecard-baseline.json'));
    g(['add', '-A']); g(['commit', '-q', '-m', 'base: baseline apertado + tamper-guard']);
    const baseSha = g(['rev-parse', 'HEAD']).stdout.trim();
    // commit head — baseline afrouxado (sob governance/) + código dummy só no caso bad
    cpSync(join(fx, kind, 'sdd-scorecard-baseline.json'), join(sb, 'governance', 'sdd-scorecard-baseline.json'));
    if (existsSync(join(fx, kind, 'code-touched.txt'))) cpSync(join(fx, kind, 'code-touched.txt'), join(sb, 'code-touched.txt'));
    g(['add', '-A']); g(['commit', '-q', '-m', `head ${kind}: afrouxa ghost_count (armed:true->false)`]);
    return runNode(join(sb, 'scripts', 'governance', 'baseline-tamper-guard.mjs'), ['--base', baseSha], sb);
  } finally { rmSync(sb, { recursive: true, force: true }); }
}

// baseline-tamper-grow — irmão do tamper-guard pro casos-coverage-baseline (audit 2026-06-22
// #4): crescer aquele baseline (absorver violação nova) exige o trailer `BASELINE-GROW:` no
// commit que o toca, SEMPRE — nem o ramo isolado nem o label dão verde sem ele. Mesmo sandbox
// git real, mas o afrouxamento é SÓ-DE-BASELINE (isolado) nos dois casos; a diferença é o
// trailer na mensagem do commit head: good TEM → exit 0; bad NÃO tem → exit 1 (bypass fechado).
function runTamperGrow(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-tamper-grow-${kind}-`));
  const fx = join(FIX, 'baseline-tamper-grow');
  const g = (cmd) => spawnSync('git', cmd, { cwd: sb, encoding: 'utf8', env: { ...process.env, GIT_TERMINAL_PROMPT: '0' } });
  try {
    g(['init', '-q', '-b', 'main']);
    g(['config', 'user.email', 'selftest@oimpresso.local']);
    g(['config', 'user.name', 'gate-selftest']);
    g(['config', 'commit.gpgsign', 'false']);
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    cpSync(script('baseline-tamper-guard', 'scripts/governance/baseline-tamper-guard.mjs'), join(sb, 'scripts', 'governance', 'baseline-tamper-guard.mjs'));
    // commit base — casos-coverage com 1 violação legada
    cpSync(join(fx, 'base', 'casos-coverage-baseline.json'), join(sb, 'scripts', 'casos-coverage-baseline.json'));
    g(['add', '-A']); g(['commit', '-q', '-m', 'base: casos-coverage apertado (1 violação)']);
    const baseSha = g(['rev-parse', 'HEAD']).stdout.trim();
    // commit head — casos-coverage CRESCE (2 violações), ISOLADO; good leva o trailer
    cpSync(join(fx, kind, 'casos-coverage-baseline.json'), join(sb, 'scripts', 'casos-coverage-baseline.json'));
    g(['add', '-A']);
    const msg = kind === 'good'
      ? 'chore: cresce casos-coverage conscientemente\n\nBASELINE-GROW: tela Fixture/SmuggledDebt absorvida (selftest)'
      : 'chore: cresce casos-coverage (sem trailer)';
    g(['commit', '-q', '-m', msg]);
    return runNode(join(sb, 'scripts', 'governance', 'baseline-tamper-guard.mjs'), ['--base', baseSha], sb);
  } finally { rmSync(sb, { recursive: true, force: true }); }
}

// anchor-lint --check SA-A2-bis (ADR 0303): existir ≠ estar vivo. Sandbox por cwd (igual
// knowledge-drift) + o script REAL copiado por cima. good = tela VIVA (controller referenciado
// nas rotas + teste existente) → exit 0; bad = tela ZUMBI (controller fora das rotas) +
// teste-fantasma → exit 1, acusação "tela DESLIGADA". Dir de fixture ISOLADO de anchor-lint/
// (DemoAnchor/anchored_dead) — a catraca anchor-lint varre sem-path o cwd, então não cruzam.
function runAnchorLintWired(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-anchor-lint-wired-${kind}-`));
  try {
    cpSync(join(FIX, 'anchor-lint-wired', kind), sb, { recursive: true });
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    cpSync(script('anchor-lint', 'scripts/governance/anchor-lint.mjs'), join(sb, 'scripts', 'governance', 'anchor-lint.mjs'));
    return runNode(join(sb, 'scripts', 'governance', 'anchor-lint.mjs'), ['--check', 'memory/requisitos/SelftestAnchor/SPEC.md'], sb);
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
    // Check N (colisão de US-ID · ADR 0304) — sibling do Check A pra histórias.
    id: 'memory-health-us',
    run: runMemoryHealthUs,
    expect: { good: /base de conhecimento saudável/, bad: /\[N\][^\n]*duplicado/ },
  },
  {
    id: 'baseline-tamper-guard',
    run: runTamperGuard,
    expect: {
      good: /afrouxamento isolado|nenhum baseline guardado afrouxado/,
      bad: /baseline AFROUXADO no mesmo PR que toca código/,
    },
  },
  {
    // casos-coverage CRESCE (audit 2026-06-22 #4): bypass era o ramo "isolado" dar verde com
    // violação nova injetada. good = crescer COM trailer `BASELINE-GROW:` (exit 0); bad = crescer
    // isolado SEM trailer (exit 1, acusação "CRESCEU … sem o trailer auditável BASELINE-GROW").
    id: 'baseline-tamper-grow',
    run: runTamperGrow,
    expect: {
      good: /afrouxamento isolado|nenhum baseline guardado afrouxado/,
      bad: /CRESCEU.*sem o trailer auditável `BASELINE-GROW`/s,
    },
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
  {
    // doneness-lint --check resolve âncora-paths contra process.cwd() (igual anchor-lint) →
    // sandbox por cwd. good = status×âncora consistentes + zona-cinza TOLERADA (exit 0, imprime
    // "CONFLITOS (mordem em --check): 0"); bad = status=done sem âncora viva → conflito_done_sem_ancora
    // (exit 1 · ADR 0302). bad regex = a acusação da US morta (a linha-resumo "CONFLITOS … : N" sai
    // nos dois; a ⚠️ da US específica só no conflito).
    id: 'doneness-lint',
    run: (kind) => runNode(script('doneness-lint', 'scripts/governance/doneness-lint.mjs'), ['--check'], join(FIX, 'doneness', kind)),
    expect: { good: /CONFLITOS \(mordem em --check\): 0/, bad: /US-DD-001.*conflito_done_sem_ancora/ },
  },
  {
    // SA-A2-bis (ADR 0303): existir ≠ estar vivo. good = tela VIVA; bad = tela ZUMBI
    // (controller fora das rotas) + teste-fantasma → exit 1 "tela DESLIGADA". COMPLEMENTA
    // a catraca anchor-lint (anchored_dead · ADR 0273 §2 · P08), não a substitui.
    id: 'anchor-lint-wired',
    run: runAnchorLintWired,
    expect: { good: /ANCHOR COVERAGE GLOBAL/, bad: /tela DESLIGADA/ },
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
