#!/usr/bin/env node
// Teste do write-side do coverage (SDD P07 · ADR 0275). Espelho de
// floor-compute.test.mjs. Importa computeCoverage/validCoverageRuns/parseCloverLineRate
// (guard isMain garante que o import não roda o CLI) e prova:
//   - parseCloverLineRate extrai o <metrics> AGREGADO (último do <project>)
//   - runs sem clover / clover malformado / sem statements são EXCLUÍDOS
//   - coverage_pct = line-rate do clover mais recente válido na janela
//   - <1 válido → coverage_pct null (read-side → not_yet_measured, nunca 0)
import { computeCoverage, validCoverageRuns, parseCloverLineRate } from './coverage-compute.mjs';
import { mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

// clover.xml mínimo: <file> com métricas próprias + <project> com o agregado.
// parseCloverLineRate deve pegar o AGREGADO (último <metrics>), não o do <file>.
const clover = (covered, total) => `<?xml version="1.0"?>
<coverage><project>
  <file name="x.php"><metrics statements="3" coveredstatements="1"/></file>
  <metrics files="1" loc="100" ncloc="80" statements="${total}" coveredstatements="${covered}" elements="${total}" coveredelements="${covered}"/>
</project></coverage>`;

// ── unit: parseCloverLineRate ───────────────────────────────────────────────
const lr = parseCloverLineRate(clover(427, 1000));
ok(lr && lr.covered === 427 && lr.total === 1000, 'parse pega o <metrics> AGREGADO (covered=427/total=1000)');
ok(lr && lr.pct === 42.7, 'line-rate = covered/total = 42.7%');
ok(parseCloverLineRate('<coverage></coverage>') === null, 'sem <metrics> → null');
ok(parseCloverLineRate('<coverage><project><metrics statements="0" coveredstatements="0"/></project></coverage>') === null, 'statements=0 → null (evita divisão por zero)');

const root = join(tmpdir(), `coverage-test-${process.pid}`);
const mkRun = (name, opts = {}) => {
  const d = join(root, name); mkdirSync(d, { recursive: true });
  if (opts.noClover) return;                                  // run sem clover (pcov off)
  writeFileSync(join(d, 'clover.xml'), opts.malformed ? '<not clover' : clover(opts.covered, opts.total));
};

try {
  mkRun('20260101-020000', { covered: 100, total: 1000 });    // 10.0%
  mkRun('20260102-020000', { noClover: true });               // sem clover → excluído
  mkRun('20260103-020000', { malformed: true });              // malformado → excluído
  mkRun('20260104-020000', { covered: 500, total: 1000 });    // 50.0% (mais recente válido)

  const runs = validCoverageRuns(root);
  ok(runs.length === 2, `só 2 runs com clover válido (sem-clover/malformado excluídos) — got ${runs.length}`);

  const cov = computeCoverage(runs, 3);
  ok(cov.coverage_pct === 50, `coverage_pct = clover mais recente válido = 50% — got ${cov.coverage_pct}`);
  ok(cov.covered === 500 && cov.total === 1000, 'covered/total do mais recente');
  ok(cov.measured_of === 2, 'measured_of === 2');
  ok(cov.runs.length === 2 && cov.runs[0].coverage_pct === 10, 'runs[] carrega histórico por run');

  // 0 runs válidos → coverage_pct null (notYet)
  const rootEmpty = join(tmpdir(), `coverage-test-empty-${process.pid}`);
  mkdirSync(join(rootEmpty, '20260201-020000'), { recursive: true }); // dir sem clover
  ok(computeCoverage(validCoverageRuns(rootEmpty), 3).coverage_pct === null, '<1 válido → coverage_pct null (notYet)');
  rmSync(rootEmpty, { recursive: true, force: true });

  ok(computeCoverage([], 3).coverage_pct === null, '0 runs → coverage_pct null');
} finally { rmSync(root, { recursive: true, force: true }); }

console.log(fails === 0 ? '\n  coverage-compute (SDD P07 · ADR 0275): OK\n' : `\n  coverage-compute: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
