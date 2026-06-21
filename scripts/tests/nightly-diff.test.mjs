#!/usr/bin/env node
// Teste do tripwire qualitativo do nightly (ROADMAP-SDD P15). Importa diffRuns/
// validRuns/extractFailureClasses (guard isMain garante que o import NÃO roda o CLI)
// e prova as 2 condições que o crítico de completude exigiu:
//   (a) noite com classe ESTÁVEL + mesmos arquivos → 0 tripwire (no-op);
//   (b) noite com arquivo NOVO falhando OU classe de falha INFLADA → detecta.
// Roda SEM CT100 (fixtures sintéticas em tmpdir). Espelha floor-compute.test.mjs.
import { diffRuns, validRuns, extractFailureClasses } from './nightly-diff.mjs';
import { mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const readXml = (dir) => readFileSync(join(dir, 'junit.xml'), 'utf8');

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

const root = join(tmpdir(), `nightly-diff-test-${process.pid}`);

// fixture de 1 run: summary.json (anti-PII, igual junit-summary) + junit.xml (fonte da classe).
// failingFiles = files c/ failed>0||errors>0; classes = type="..." dos <failure>/<error>.
const mkRun = (name, failingFiles, failures = []) => {
  const d = join(root, name); mkdirSync(d, { recursive: true });
  writeFileSync(join(d, 'summary.json'), JSON.stringify({
    schema: 'junit-summary/v1', coherent: true, n_testcases: 50,
    totals: { passed: 50 - failingFiles.length, failed: failingFiles.length, errors: 0, skipped: 0 },
    files: failingFiles.map((f) => ({ file: f, tests: 1, passed: 0, failed: 1, errors: 0, skipped: 0 }))
      .concat([{ file: 'tests/Feature/AlwaysGreenTest.php', tests: 1, passed: 1, failed: 0, errors: 0, skipped: 0 }]),
  }, null, 2));
  // junit.xml sintético com <failure type="..."> / <error type="...">
  const cases = failures.map((fl) => {
    const tag = fl.kind === 'error' ? 'error' : 'failure';
    const body = fl.sqlstate ? `SQLSTATE[${fl.sqlstate}]: blah blah (NOME DA PESSOA aqui seria PII)` : 'Failed asserting that false is true.';
    return `  <testcase name="t" file="${fl.file}::it x"><${tag} type="${fl.type}">${body}</${tag}></testcase>`;
  }).join('\n');
  writeFileSync(join(d, 'junit.xml'),
    `<?xml version="1.0"?>\n<testsuites>\n<testsuite name="all" tests="50">\n${cases}\n</testsuite>\n</testsuites>\n`);
};

try {
  // ── (a) 2 noites IDÊNTICAS → no-op (sem tripwire) ─────────────────────────
  const failsA = [
    { file: 'tests/Feature/FooTest.php', type: 'PHPUnit\\Framework\\ExpectationFailedException' },
    { file: 'tests/Feature/BarTest.php', kind: 'error', type: 'Illuminate\\Database\\QueryException', sqlstate: '42S02' },
  ];
  mkRun('20260101-020000', ['tests/Feature/FooTest.php', 'tests/Feature/BarTest.php'], failsA);
  mkRun('20260102-020000', ['tests/Feature/FooTest.php', 'tests/Feature/BarTest.php'], failsA);
  const runs = validRuns(root);
  ok(runs.length === 2, `2 runs válidos carregados — got ${runs.length}`);

  const stable = diffRuns(runs[1], runs[0]);
  ok(stable.comparable === true, 'noites estáveis: comparable');
  ok(stable.tripwire === false, '(a) classe estável + mesmos arquivos → 0 tripwire (no-op)');
  ok(stable.newly_failing_files.length === 0, '(a) nenhum arquivo novo falhando');
  ok(Object.keys(stable.by_failure_class).length === 0, '(a) by_failure_class vazio (delta zero)');

  // anti-PII: a classe extraída é só o nome curto da exception (+ SQLSTATE), nunca a msg
  const cls = extractFailureClasses(
    '<failure type="Illuminate\\Database\\QueryException">SQLSTATE[42S02]: Base table not found — segredo aqui</failure>');
  ok(JSON.stringify(cls) === '{"QueryException#SQLSTATE[42S02]":1}',
    `anti-PII: classe = QueryException#SQLSTATE[42S02] (sem msg livre) — got ${JSON.stringify(cls)}`);

  // ── (b1) ARQUIVO NOVO no floor → detecta ──────────────────────────────────
  mkRun('20260103-020000',
    ['tests/Feature/FooTest.php', 'tests/Feature/BarTest.php', 'tests/Feature/NovoTest.php'],
    failsA.concat([{ file: 'tests/Feature/NovoTest.php', type: 'PHPUnit\\Framework\\ExpectationFailedException' }]));
  const r3 = validRuns(root);
  const newFileDiff = diffRuns(r3[2], r3[1]);
  ok(newFileDiff.tripwire === true, '(b) arquivo NOVO falhando → tripwire dispara');
  ok(newFileDiff.newly_failing_files.includes('tests/Feature/NovoTest.php'),
    '(b) NovoTest.php em newly_failing_files');
  ok(newFileDiff.recovered_files.length === 0, '(b) nenhum recovered nesse passo');

  // ── (b2) CLASSE INFLADA (mesmos arquivos) → detecta ───────────────────────
  // N-1: 1 ExpectationFailed; N: 3 ExpectationFailed no MESMO arquivo (classe inflou 1→3).
  const baseN1 = join(tmpdir(), `nd-inflate-prev-${process.pid}`);
  const baseN = join(tmpdir(), `nd-inflate-curr-${process.pid}`);
  const mk = (dir, n) => {
    mkdirSync(dir, { recursive: true });
    writeFileSync(join(dir, 'summary.json'), JSON.stringify({
      coherent: true, n_testcases: 50, totals: { failed: 1, errors: 0, skipped: 0 },
      files: [{ file: 'tests/Feature/FlakyTest.php', failed: 1, errors: 0 }],
    }));
    const cases = Array.from({ length: n }, () =>
      '  <testcase name="t" file="tests/Feature/FlakyTest.php::it x"><failure type="PHPUnit\\Framework\\ExpectationFailedException">Failed asserting</failure></testcase>').join('\n');
    writeFileSync(join(dir, 'junit.xml'),
      `<?xml version="1.0"?>\n<testsuites><testsuite name="all" tests="50">\n${cases}\n</testsuite></testsuites>\n`);
  };
  mk(baseN1, 1); mk(baseN, 3);
  const inflated = diffRuns(
    { ts: 'N', failingFiles: ['tests/Feature/FlakyTest.php'], failureClasses: extractFailureClasses(readXml(baseN)) },
    { ts: 'N-1', failingFiles: ['tests/Feature/FlakyTest.php'], failureClasses: extractFailureClasses(readXml(baseN1)) });
  ok(inflated.tripwire === true, '(b) classe INFLADA (1→3) com mesmos arquivos → tripwire dispara');
  ok(inflated.newly_failing_files.length === 0, '(b) classe inflada sem arquivo novo: newly vazio');
  ok(inflated.by_failure_class['ExpectationFailedException']?.delta === 2,
    `(b) delta da classe = +2 — got ${JSON.stringify(inflated.by_failure_class)}`);
  rmSync(baseN1, { recursive: true, force: true });
  rmSync(baseN, { recursive: true, force: true });

  // ── borda: <2 runs válidos → não comparável (não mente regressão) ─────────
  ok(diffRuns(runs[0], null).comparable === false, '<2 runs → comparable=false (não mente tripwire)');
  ok(diffRuns(runs[0], null).tripwire === false, '<2 runs → tripwire=false');
} finally {
  rmSync(root, { recursive: true, force: true });
}

console.log(fails === 0 ? '\n  nightly-diff (ROADMAP-SDD P15): OK\n' : `\n  nightly-diff: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
