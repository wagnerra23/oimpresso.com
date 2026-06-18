#!/usr/bin/env node
// Teste do write-side do floor (ADR 0279 PR-2). Importa computeFloor/validRuns
// (guard isMain garante que o import não roda o CLI) e prova:
//   - runs mortos (sem summary), malformados e incoerentes são EXCLUÍDOS
//   - floor = INTERSEÇÃO dos arquivos-que-falham entre os válidos
//   - <2 válidos → floor_count null (read-side → not_yet_measured)
import { computeFloor, validRuns } from './floor-compute.mjs';
import { mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

const root = join(tmpdir(), `floor-test-${process.pid}`);
const mkRun = (name, files, opts = {}) => {
  const d = join(root, name); mkdirSync(d, { recursive: true });
  if (opts.dead) return; // run morto (junit 0b → sem summary.json)
  const body = opts.malformed ? '{bad json' : JSON.stringify({
    coherent: !opts.incoherent, n_testcases: opts.empty ? 0 : 100,
    totals: { failed: files.length, errors: 0, skipped: 0 },
    files: files.map((f) => ({ file: f, failed: 1, errors: 0 })),
  });
  writeFileSync(join(d, 'summary.json'), body);
};

try {
  mkRun('20260101-020000', ['F1', 'F2', 'F3']);
  mkRun('20260102-020000', ['F2', 'F3', 'F4']);          // ∩ com o 1º = {F2,F3}
  mkRun('20260103-020000', [], { dead: true });           // morto → excluído
  mkRun('20260104-020000', ['X'], { malformed: true });   // malformado → excluído
  mkRun('20260105-020000', ['Y'], { incoherent: true });  // incoerente → excluído

  const runs = validRuns(root);
  ok(runs.length === 2, `só 2 runs válidos (morto/malformado/incoerente excluídos) — got ${runs.length}`);

  const f = computeFloor(runs, 3);
  ok(f.floor_count === 2, `floor = interseção {F2,F3} = 2 — got ${f.floor_count}`);
  ok(f.intersection_of === 2, 'intersection_of === 2');
  ok(typeof f.floor_files_hash === 'string' && f.floor_files_hash.length === 16, 'floor_files_hash 16 chars');
  ok(f.runs.length === 2 && f.runs[0].failed === 3, 'runs[] carrega counts por run');

  // 1 run válido → notYet
  const root1 = join(tmpdir(), `floor-test1-${process.pid}`);
  mkdirSync(join(root1, '20260201-020000'), { recursive: true });
  writeFileSync(join(root1, '20260201-020000', 'summary.json'), JSON.stringify({ coherent: true, n_testcases: 10, totals: { failed: 1, errors: 0, skipped: 0 }, files: [{ file: 'F1', failed: 1, errors: 0 }] }));
  ok(computeFloor(validRuns(root1), 3).floor_count === null, '<2 válidos → floor_count null (notYet)');
  rmSync(root1, { recursive: true, force: true });

  ok(computeFloor([], 3).floor_count === null, '0 runs → floor_count null');
} finally { rmSync(root, { recursive: true, force: true }); }

console.log(fails === 0 ? '\n  floor-compute (ADR 0279 PR-2): OK\n' : `\n  floor-compute: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
