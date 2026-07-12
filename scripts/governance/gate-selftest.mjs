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
// anchor-lint-wired --check (wired/zombie + testado-fantasma · ADR 0303 SA-A2-bis: existir ≠ estar vivo) ·
// anchor-lint-verde --junit --check-verde (G1b Phase B: verde-por-arquivo via JUnit; skipped != passed).
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
import { appendFileSync, cpSync, existsSync, mkdirSync, mkdtempSync, renameSync, rmSync } from 'node:fs';
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

// P14 (avaliação 2026-07-01, defeito nº 1): prova o caminho REAL do armed — b.armed===true
// no BASELINE da fixture, SEM SDD_RATCHET_ARM — pra métrica de fonte externa (floor).
// Dois counterfactuals: regressão medida morde (floor 299 > 298) E fonte-ausente-com-armada
// morde (fail-red em vez do skip silencioso que deixava floor=298 ser lei só no papel).
function runScorecardFloor(fixture) {
  return (kind) => {
    const sb = mkdtempSync(join(tmpdir(), `gate-selftest-${fixture}-${kind}-`));
    try {
      cpSync(join(FIX, fixture, kind), sb, { recursive: true });
      mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
      cpSync(script('sdd-scorecard', 'scripts/governance/sdd-scorecard.mjs'), join(sb, 'scripts', 'governance', 'sdd-scorecard.mjs'));
      cpSync(join(ROOT, 'scripts', 'governance', 'knowledge-drift.mjs'), join(sb, 'scripts', 'governance', 'knowledge-drift.mjs'));
      cpSync(join(ROOT, 'scripts', 'governance', 'anchor-lint.mjs'), join(sb, 'scripts', 'governance', 'anchor-lint.mjs'));
      return runNode(join(sb, 'scripts', 'governance', 'sdd-scorecard.mjs'), ['--ratchet'], sb);
    } finally { rmSync(sb, { recursive: true, force: true }); }
  };
}

// P14 carona 2 — sqlite_corruptors ARMADA pelo caminho real (SEM SDD_RATCHET_ARM), fusão
// no GT-G3 (lei de fusões ADR 0314: sinal vira métrica no ratchet já-required, não gate novo).
// Mesmo sandbox dos irmãos scorecard + a dep extra scripts/audit/sqlite-test-corruptors.mjs
// (a fonte da métrica). good = sem corruptor (0 = baseline 0); bad = teste com Schema::drop
// de tabela CORE não-guardado (corruptsOnMysql tier S) → mede 1 > 0 → catraca morde.
function runScorecardCorruptors(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-sdd-scorecard-corruptors-${kind}-`));
  try {
    cpSync(join(FIX, 'sdd-scorecard-corruptors', kind), sb, { recursive: true });
    // REGRA DURA do README: nenhum .php sob as fixtures — em git o corruptor vive como
    // .php.txt (o auditor REAL varre tests/ do repo e contaria a fixture como corruptor
    // vivo, avermelhando a métrica armada contra si mesma). Materializa .php SÓ no sandbox.
    const corruptorTxt = join(sb, 'tests', 'Feature', 'CorruptorDemoTest.php.txt');
    if (existsSync(corruptorTxt)) renameSync(corruptorTxt, join(sb, 'tests', 'Feature', 'CorruptorDemoTest.php'));
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    mkdirSync(join(sb, 'scripts', 'audit'), { recursive: true });
    cpSync(script('sdd-scorecard', 'scripts/governance/sdd-scorecard.mjs'), join(sb, 'scripts', 'governance', 'sdd-scorecard.mjs'));
    cpSync(join(ROOT, 'scripts', 'governance', 'knowledge-drift.mjs'), join(sb, 'scripts', 'governance', 'knowledge-drift.mjs'));
    cpSync(join(ROOT, 'scripts', 'governance', 'anchor-lint.mjs'), join(sb, 'scripts', 'governance', 'anchor-lint.mjs'));
    cpSync(script('sqlite-test-corruptors', 'scripts/audit/sqlite-test-corruptors.mjs'), join(sb, 'scripts', 'audit', 'sqlite-test-corruptors.mjs'));
    return runNode(join(sb, 'scripts', 'governance', 'sdd-scorecard.mjs'), ['--ratchet'], sb);
  } finally { rmSync(sb, { recursive: true, force: true }); }
}

// protection-drift WATCHDOG staleness da órfã (V5 · avaliação 2026-07-12 risco nº1): o frescor
// do floor vem do computed_at do CONTEÚDO, não do tip (que avança com [skip ci] mesmo com a suíte
// morta por OOM). Sandbox por cwd (governance/*-baseline.json próprios) + o script REAL copiado.
// PROTECTION_DRIFT_NOW fixa o relógio. good = computed_at 24h atrás → 🟢 (exit 0); bad = TIP FRESCO
// mas computed_at 6d atrás → 🔴 (exit 1) — o mesmo caso PROVA que ignora o tip (tip_committed_at
// é hoje na fixture). O required-checks-baseline embutido casa os contexts do live ⇒ compareProtection
// não avermelha o good por drift acidental.
function runProtectionStaleFloor(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-protection-drift-stale-floor-${kind}-`));
  const fx = join(FIX, 'protection-drift-stale-floor');
  try {
    mkdirSync(join(sb, 'governance'), { recursive: true });
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    cpSync(script('protection-drift', 'scripts/governance/protection-drift.mjs'), join(sb, 'scripts', 'governance', 'protection-drift.mjs'));
    cpSync(join(fx, 'required-checks-baseline.json'), join(sb, 'governance', 'required-checks-baseline.json'));
    cpSync(join(fx, 'sdd-scorecard-baseline.json'), join(sb, 'governance', 'sdd-scorecard-baseline.json'));
    return runNode(join(sb, 'scripts', 'governance', 'protection-drift.mjs'),
      ['--fixture', join(fx, kind, 'live.json')], sb, { PROTECTION_DRIFT_NOW: '2026-07-12T12:00:00Z' });
  } finally { rmSync(sb, { recursive: true, force: true }); }
}

// memory-health varre process.cwd() (memory/decisions/) e lê o registro de colisões em
// governance/adr-collisions-baseline.json (ADR 0274 §3) + o baseline de segredos em
// scripts/governance/.memory-health-baseline.json → mesmo padrão sandbox: fixture (só
// memory/decisions/, SEM governance/adr-collisions-baseline.json) + o script REAL copiado
// por cima. Sem o baseline de colisões no sandbox, toda colisão é NÃO-registrada ⇒ o
// Check A morde no fixture bad (prova o gate sem grandfather mascarar). Fixture isola o
// Check A — sem memory/{requisitos,sessions,reference} nem .github/workflows, as outras
// checagens nem disparam.
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

// anchor-lint --check-covers (G1a · ADR 0303 emenda): teste citado em `**Testado em:**`
// que EXISTE mas não declara `// @covers-us <US-ID>`. good = teste declara → exit 0;
// bad = teste sem o marcador → exit 1, acusação "não declara @covers-us". Fixture
// ISOLADA (SelftestCovers) — não cruza com anchor-lint/anchor-lint-wired.
function runAnchorLintCovers(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-anchor-lint-covers-${kind}-`));
  try {
    cpSync(join(FIX, 'anchor-lint-covers', kind), sb, { recursive: true });
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    cpSync(script('anchor-lint', 'scripts/governance/anchor-lint.mjs'), join(sb, 'scripts', 'governance', 'anchor-lint.mjs'));
    return runNode(join(sb, 'scripts', 'governance', 'anchor-lint.mjs'), ['--check-covers', 'memory/requisitos/SelftestCovers/SPEC.md'], sb);
  } finally { rmSync(sb, { recursive: true, force: true }); }
}

// anchor-lint --check-entry (G1b gate de entrada): US que se diz implementada SEM
// aceite/DoD ou SEM teste que declare @covers-us dela. good = US com DoD + teste que
// cobre → exit 0; bad = sem aceite + sem teste-que-cobre → exit 1. Fixture ISOLADA.
function runAnchorLintEntry(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-anchor-lint-entry-${kind}-`));
  try {
    cpSync(join(FIX, 'anchor-lint-entry', kind), sb, { recursive: true });
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    cpSync(script('anchor-lint', 'scripts/governance/anchor-lint.mjs'), join(sb, 'scripts', 'governance', 'anchor-lint.mjs'));
    return runNode(join(sb, 'scripts', 'governance', 'anchor-lint.mjs'), ['--check-entry', 'memory/requisitos/SelftestEntry/SPEC.md'], sb);
  } finally { rmSync(sb, { recursive: true, force: true }); }
}

// anchor-lint --junit <summary.json> --check-verde (G1b Phase B): verde-por-arquivo. Mesmo
// sandbox por cwd; a fixture carrega o JUnit summary (junit-summary/v1) em junit/ (NÃO em
// test-results/ — esse path é gitignored, o summary não sobreviveria ao commit). good =
// arquivo-de-teste-que-cobre VERDE (passed) → exit 0; bad = mesmo SPEC/teste, mas o summary só
// marca skipped (markTestSkipped) → NÃO-verde (skipped != passed) → exit 1, acusação 🟥. Fixture
// ISOLADA (SelftestVerde) — good/bad diferem SÓ no summary JSON (prova que a regra é a verde).
function runAnchorLintVerde(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-anchor-lint-verde-${kind}-`));
  try {
    cpSync(join(FIX, 'anchor-lint-verde', kind), sb, { recursive: true });
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    cpSync(script('anchor-lint', 'scripts/governance/anchor-lint.mjs'), join(sb, 'scripts', 'governance', 'anchor-lint.mjs'));
    return runNode(join(sb, 'scripts', 'governance', 'anchor-lint.mjs'),
      ['--junit', 'junit/pest-verde-junit.summary.json', '--check-verde', 'memory/requisitos/SelftestVerde/SPEC.md'], sb);
  } finally { rmSync(sb, { recursive: true, force: true }); }
}

// V6-A (avaliação SDD 2026-07-12 · risco #2): RESILIÊNCIA do loadJunit. Reusa o SPEC+Service+Test da
// fixture verde/good (US implementada+coberta) e SÓ sobrepõe o junit/ da fixture -resilient — isola a
// variável "estado do JUnit". good = --junit é um MARCADOR de run inválido (fullsuite-summary-invalid/v1)
// → --check-verde ARMADO degrada a behavior_unknown → exit 0 (sem crash exit 2, sem false-red exit 1);
// bad = MESMO SPEC/teste, mas junit COERENTE marca o teste-que-cobre só skipped → ainda MORDE (exit 1 🟥),
// prova que a resiliência não desarmou o gate verde.
function runAnchorLintVerdeResilient(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-anchor-lint-verde-resilient-${kind}-`));
  try {
    cpSync(join(FIX, 'anchor-lint-verde', 'good'), sb, { recursive: true }); // SPEC+Service+Test compartilhados
    cpSync(join(FIX, 'anchor-lint-verde-resilient', kind, 'junit'), join(sb, 'junit'), { recursive: true }); // overlay do junit da variante
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    cpSync(script('anchor-lint', 'scripts/governance/anchor-lint.mjs'), join(sb, 'scripts', 'governance', 'anchor-lint.mjs'));
    return runNode(join(sb, 'scripts', 'governance', 'anchor-lint.mjs'),
      ['--junit', 'junit/pest-verde-junit.summary.json', '--check-verde', 'memory/requisitos/SelftestVerde/SPEC.md'], sb);
  } finally { rmSync(sb, { recursive: true, force: true }); }
}

// ARMING grandfather (SA-A2-ter · ADR 0275): --check-entry --baseline. Prova o no-new-lie:
// good = US violadora MAS grandfatherada no baseline → exit 0; bad = MESMA US violadora, baseline
// só com decoy → exit 1 ("regra de entrada"). Isola a variável estar-no-baseline (mesmo SPEC).
// É a prova de que armar o gate NÃO avermelha o legado, mas continua mordendo mentira nova.
function runAnchorLintEntryBaseline(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-anchor-lint-entry-baseline-${kind}-`));
  try {
    cpSync(join(FIX, 'anchor-lint-entry-baseline', kind), sb, { recursive: true });
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    cpSync(script('anchor-lint', 'scripts/governance/anchor-lint.mjs'), join(sb, 'scripts', 'governance', 'anchor-lint.mjs'));
    return runNode(join(sb, 'scripts', 'governance', 'anchor-lint.mjs'),
      ['--check-entry', '--baseline', 'governance/anchor-entry-baseline.json', 'memory/requisitos/SelftestEntryBaseline/SPEC.md'], sb);
  } finally { rmSync(sb, { recursive: true, force: true }); }
}

// ARMING grandfather doneness (ADR 0302/0275): --check --baseline. Irmão do entry-baseline pro
// doneness-lint. good = US em conflito (conflito_done_sem_ancora) MAS grandfatherada no baseline →
// exit 0; bad = MESMA US em conflito, baseline só com decoy → exit 1 (acusação ⚠️). Isola a variável
// estar-no-baseline (mesma SPEC). Prova que armar o doneness NÃO avermelha o conflito legado, mas
// continua mordendo conflito NOVO. Sandbox por cwd (igual doneness-lint resolve âncora-paths).
function runDonenessBaseline(kind) {
  const sb = mkdtempSync(join(tmpdir(), `gate-selftest-doneness-baseline-${kind}-`));
  try {
    cpSync(join(FIX, 'doneness-baseline', kind), sb, { recursive: true });
    mkdirSync(join(sb, 'scripts', 'governance'), { recursive: true });
    cpSync(script('doneness-lint', 'scripts/governance/doneness-lint.mjs'), join(sb, 'scripts', 'governance', 'doneness-lint.mjs'));
    return runNode(join(sb, 'scripts', 'governance', 'doneness-lint.mjs'),
      ['--check', '--baseline', 'governance/doneness-baseline.json', 'memory/requisitos/SelftestDonenessBaseline/SPEC.md'], sb);
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
    // P14 — counterfactual da métrica de fonte externa: floor regredido (299>298) morde
    // pelo caminho real do baseline (armed:true, sem SDD_RATCHET_ARM).
    id: 'sdd-scorecard-floor',
    run: runScorecardFloor('sdd-scorecard-floor'),
    expect: { good: /nenhuma regressão/, bad: /RATCHET \(ARMADA\): full_suite_pass_rate/ },
  },
  {
    // P14 — fail-red: fonte ausente com métrica ARMADA sai exit 1 (não skip silencioso).
    id: 'sdd-scorecard-floor-ausente',
    run: runScorecardFloor('sdd-scorecard-floor-ausente'),
    expect: { good: /nenhuma regressão/, bad: /ARMADA no baseline.*not_yet_measured/ },
  },
  {
    // P14 carona 2 — corruptor NOVO do MySQL persistente morde no GT-G3 required (fusão
    // ADR 0314, não gate novo): bad = Schema::drop('business') não-guardado → 1 > baseline 0.
    id: 'sdd-scorecard-corruptors',
    run: runScorecardCorruptors,
    expect: { good: /nenhuma regressão/, bad: /RATCHET \(ARMADA\): sqlite_corruptors/ },
  },
  {
    // V5 (avaliação 2026-07-12 risco nº1) — o watchdog GT-G4 mede o frescor do floor pelo
    // computed_at do CONTEÚDO, não pelo tip da órfã. good = computed_at 24h atrás (🟢 veredito ok);
    // bad = TIP FRESCO + computed_at 6d atrás → 🔴 (sem run verde há >48h). O bad é a prova de
    // "tip fresco mas computed_at stale ⇒ ignora o tip".
    id: 'protection-drift-stale-floor',
    run: runProtectionStaleFloor,
    expect: { good: /veredito: 🟢 ok/, bad: /full_suite_pass_rate: fonte .*sem run verde há >48h/ },
  },
  {
    id: 'memory-health',
    run: runMemoryHealth,
    expect: { good: /base de conhecimento saudável/, bad: /colidiu.*adr-collisions-baseline/ },
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
  {
    // G1a (ADR 0303 emenda): testado_sem_covers — teste existe mas não declara @covers-us da US
    id: 'anchor-lint-covers',
    run: runAnchorLintCovers,
    expect: { good: /Testado sem covers[^\n]*: 0/, bad: /não declara @covers-us/ },
  },
  {
    // G1b gate de entrada: req_sem_aceite / req_sem_covering_test (regra nova sem aceite/teste)
    id: 'anchor-lint-entry',
    run: runAnchorLintEntry,
    expect: { good: /Gate de entrada \(advisory\): 0 US/, bad: /regra de entrada|regra sem teste/ },
  },
  {
    // G1b verde-por-arquivo (Phase B): --junit summary + --check-verde. good = teste-que-cobre VERDE
    // → "Gate verde (advisory): 0 US"; bad = teste só skipped (skipped != passed) → 🟥 US-SLFV-001.
    // A linha-resumo "Gate verde … : N US" sai nos dois; a 🟥 da US só no req_teste_vermelho.
    id: 'anchor-lint-verde',
    run: runAnchorLintVerde,
    expect: { good: /Gate verde \(advisory\): 0 US/, bad: /🟥 US-SLFV-001/ },
  },
  {
    // V6-A (avaliação SDD 2026-07-12 · risco #2): loadJunit RESILIENTE. good = --junit marcador de run
    // inválido (fullsuite-summary-invalid/v1) com --check-verde ARMADO → behavior_unknown (exit 0, sem
    // crash exit 2 nem false-red exit 1); bad = MESMO SPEC/teste mas junit coerente-skipped → ainda MORDE
    // (exit 1 🟥). Prova que junit inválido não avermelha E que a resiliência não desarmou o gate verde.
    id: 'anchor-lint-verde-resilient',
    run: runAnchorLintVerdeResilient,
    expect: { good: /Gate verde \(advisory\): behavior_unknown/, bad: /🟥 US-SLFV-001/ },
  },
  {
    // ARMING grandfather (SA-A2-ter · ADR 0275): baseline ISENTA o legado MAS morde mentira NOVA.
    // good = US violadora grandfatherada → exit 0; bad = mesma US fora do baseline (só decoy) → exit 1.
    id: 'anchor-lint-entry-baseline',
    run: runAnchorLintEntryBaseline,
    expect: { good: /Gate de entrada \(advisory\): 0 US/, bad: /regra de entrada|regra sem teste/ },
  },
  {
    // ARMING grandfather doneness (ADR 0302/0275): baseline ISENTA o conflito legado MAS morde o NOVO.
    // good = conflito grandfatherado → exit 0 ("CONFLITOS … : 0"); bad = mesmo conflito fora do baseline
    // (só decoy) → exit 1 (⚠️ US-SLDB-001 → conflito_done_sem_ancora). Isola estar-no-baseline.
    id: 'doneness-baseline',
    run: runDonenessBaseline,
    expect: { good: /CONFLITOS \(mordem em --check\): 0/, bad: /US-SLDB-001.*conflito_done_sem_ancora/ },
  },
  {
    // charter-live-signal (proposta SDD 2026-06-24): `status: live` precisa de sinal de prod.
    // sandbox por cwd (igual anchor-lint): a fixture traz governance/prod-flags.json + o charter.
    // good = charter live com component em prod-flags.json `live` → live_ok (exit 0); bad = charter
    // live sem entrada nem `smoke:` → live_sem_sinal (exit 1). bad regex = a acusação ⚠️ "SEM sinal
    // de prod" (a linha-resumo sai nos dois; o ✓ só no good, o ⚠️ só no bad).
    id: 'charter-live-signal',
    run: (kind) => runNode(script('charter-live-signal', 'scripts/governance/charter-live-signal.mjs'), ['--check'], join(FIX, 'charter-live-signal', kind)),
    expect: { good: /carrega sinal de prod/, bad: /SEM sinal de prod/ },
  },
  {
    // G1c (item b · proposta 2026-06-24): teste-que-cobre FORA das lanes de JUnit → verde impossível.
    // sandbox por cwd (igual anchor-lint): good = .github/ci-sqlite-pest.list lista o teste → req_sem_lane
    // 0 (exit 0); bad = lista vazia → req_sem_lane 1 → exit 1 ("NENHUM numa lane de JUnit"). A linha-resumo
    // "fora de lane … : N US" sai nos dois; o ✓ (: 0 US) só no good, o 🚦 da US só no bad.
    id: 'anchor-lint-lane',
    run: (kind) => runNode(script('anchor-lint', 'scripts/governance/anchor-lint.mjs'), ['--check-lane'], join(FIX, 'anchor-testado-sem-lane', kind)),
    expect: { good: /fora de lane[^\n]*: 0 US/, bad: /NENHUM numa lane de JUnit/ },
  },
  {
    // detectar-telas (gate de import Fase 0/0.5 · B2 audit 2026-06-24): "0 telas perdidas em
    // silêncio". NÃO precisa de sandbox — detectar-telas.mjs recebe --staging/--repo explícitos e
    // resolve seu próprio dir via import.meta.url (não usa process.cwd()) → roda o script REAL no
    // lugar apontando os dois flags pros subtrees da fixture. good = staging onde vendas-page.jsx
    // resolve via CHARTER (bundle_source no good/repo/.../Sells/Index.charter.md) → Sells/Index.tsx
    // (SEMANTICO) ⇒ 0 órfãos (exit 0, "OK — 0 telas órfãs"). Charter-first desde musing-elion
    // 2026-06-30 (ALIAS 7→2; antes resolvia via ALIAS).
    // bad = staging com mistero-page.jsx órfão (sem charter nem alias) ⇒ exit 1 ("GATE FALHOU").
    // A acusação sai em stderr — runNode captura stdout+stderr juntos, então o regex casa.
    id: 'detectar-telas',
    run: (kind) => {
      const fx = join(FIX, 'detectar-telas', kind);
      return runNode(script('detectar-telas', 'prototipo-ui/detectar-telas.mjs'),
        ['--staging', join(fx, 'staging'), '--repo', join(fx, 'repo')], ROOT);
    },
    expect: { good: /0 telas órfãs/, bad: /GATE FALHOU/ },
  },
  {
    // handoff-changed (portão barato Fase −1 · zero LLM · Wagner 2026-06-29): "o bundle mudou vs
    // o snapshot já aceito?". Sem sandbox — recebe --staging/--baseline explícitos e resolve seu
    // dir via import.meta.url (não usa process.cwd()). good = staging idêntico ao baseline → exit 0
    // ("IDÊNTICO ao baseline"); bad = MESMO baseline mas staging com 1 arquivo ALTERADO → exit 1
    // ("MUDOU"). Prova que o gate LIBERA o igual (não dispara consumo à toa) e MORDE a mudança.
    id: 'handoff-changed',
    run: (kind) => runNode(script('handoff-changed', 'prototipo-ui/handoff-changed.mjs'),
      ['--staging', join(FIX, 'handoff-changed', kind), '--baseline', join(FIX, 'handoff-changed', 'baseline.json')], ROOT),
    expect: { good: /IDÊNTICO ao baseline/, bad: /MUDOU/ },
  },
  {
    // universe-gate (shards-plan --verify · SDD P04 · ADR 0279): o conjunto de shards cobre
    // EXATAMENTE o universo de dirs de teste — nenhum some no particionamento (=teste perdido).
    // Fixture sob scripts/tests/fixtures/ (fora dos roots prod tests,Modules → não contamina a
    // suíte real, igual foundation-ratchet). Roda com cwd=fixture, --roots tests (relativo).
    // good = SEM --plan (plano auto-computado sempre cobre) → exit 0 "universe-gate OK". bad =
    // --plan tamper.json que DROPA tests/Unit → exit 1 "PERDIDO" (o vetor: shard some no plano).
    id: 'shards-universe',
    run: (kind) => {
      const fx = join(ROOT, 'scripts', 'tests', 'fixtures', 'shards-universe', kind);
      const argv = ['--verify', '--roots', 'tests'];
      if (existsSync(join(fx, 'plan.json'))) argv.push('--plan', join(fx, 'plan.json'));
      return runNode(script('shards-plan', 'scripts/tests/shards-plan.mjs'), argv, fx);
    },
    expect: { good: /universe-gate OK/, bad: /PERDIDO|universe-gate FALHOU/ },
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
