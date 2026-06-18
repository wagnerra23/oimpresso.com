#!/usr/bin/env node
// Meta-teste do read-side do floor (ADR 0279 / US-GOV-023, PR-1).
// Importa measureFullSuiteFloor de sdd-scorecard.mjs (guard isMain garante que o
// import NÃO dispara o scorecard inteiro) e prova os 2+1 lados:
//   A) arquivo ausente            → not_yet_measured (fallback honesto, zero-risco)
//   B) governance/nightly-floor.json válido → measured (value = floor_count, desce p/ 0)
//   C) arquivo presente malformado → not_yet_measured (graceful, não estoura)
// Uso: node scripts/governance/sdd-floor-read.test.mjs
import { measureFullSuiteFloor } from './sdd-scorecard.mjs';
import { writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

let fails = 0;
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { console.error(`  ✗ ${msg}`); fails++; } };

// ── A: ausente → notYet ─────────────────────────────────────────────────────
const absent = measureFullSuiteFloor(join(tmpdir(), `floor-inexistente-${process.pid}.json`));
ok(absent.status === 'not_yet_measured', 'arquivo ausente → not_yet_measured');
ok(absent.value === null, 'ausente → value null (não mente 0)');

// ── B: válido → measured ────────────────────────────────────────────────────
const okPath = join(tmpdir(), `floor-ok-${process.pid}.json`);
writeFileSync(okPath, JSON.stringify({
  floor_count: 1280, floor_files_hash: 'deadbeef',
  runs: [{ sha: 'a1', ts: '2026-06-19T05:00Z', failed: 448, errors: 832, skipped: 1985 }],
  computed_at: '2026-06-19T05:10Z', intersection_of: 2,
}));
try {
  const m = measureFullSuiteFloor(okPath);
  ok(m.status === 'measured', 'json válido → measured');
  ok(m.value === 1280, 'value === floor_count (1280)');
  ok(m.direction === 'down' && m.target === 0, 'floor DESCE pra 0');
  ok(m.detail.intersection_of === 2, 'detail preserva intersection_of');
} finally { rmSync(okPath, { force: true }); }

// ── C: malformado → notYet (graceful) ───────────────────────────────────────
const badPath = join(tmpdir(), `floor-bad-${process.pid}.json`);
writeFileSync(badPath, 'isto não é json {');
try {
  ok(measureFullSuiteFloor(badPath).status === 'not_yet_measured', 'json malformado → not_yet_measured (graceful)');
} finally { rmSync(badPath, { force: true }); }

// ── D: sem floor_count numérico → notYet ────────────────────────────────────
const noCountPath = join(tmpdir(), `floor-nocount-${process.pid}.json`);
writeFileSync(noCountPath, JSON.stringify({ runs: [] }));
try {
  ok(measureFullSuiteFloor(noCountPath).status === 'not_yet_measured', 'sem floor_count → not_yet_measured');
} finally { rmSync(noCountPath, { force: true }); }

console.log(fails === 0 ? '\n  floor read-side (ADR 0279 PR-1): OK\n' : `\n  floor read-side: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
