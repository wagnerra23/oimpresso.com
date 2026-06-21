#!/usr/bin/env node
// Meta-teste do read-side do coverage (SDD P07 · ADR 0275 §2 catraca C2).
// Espelho de sdd-floor-read.test.mjs. Importa measureCoverage de sdd-scorecard.mjs
// (guard isMain garante que o import NÃO dispara o scorecard inteiro) e prova os 4
// lados — a prova de que o read-side NÃO mente 0 (counterfactual do roadmap P07:
// um diff que faça measureCoverage() retornar 0 com arquivo ausente DEVE dar exit 1):
//   A) arquivo ausente            → not_yet_measured (fallback honesto, zero-risco)
//   B) nightly-coverage.json válido → measured (value = coverage_pct, sobe)
//   C) arquivo presente malformado → not_yet_measured (graceful, não estoura)
//   D) sem coverage_pct numérico   → not_yet_measured
// Uso: node scripts/governance/sdd-coverage-read.test.mjs
import { measureCoverage } from './sdd-scorecard.mjs';
import { writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

let fails = 0;
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { console.error(`  ✗ ${msg}`); fails++; } };

// ── A: ausente → notYet (NÃO mente 0 — núcleo do counterfactual P07) ─────────
const absent = measureCoverage(join(tmpdir(), `coverage-inexistente-${process.pid}.json`));
ok(absent.status === 'not_yet_measured', 'arquivo ausente → not_yet_measured');
ok(absent.value === null, 'ausente → value null (não mente 0 — DoD P07)');

// ── B: válido → measured ────────────────────────────────────────────────────
const okPath = join(tmpdir(), `coverage-ok-${process.pid}.json`);
writeFileSync(okPath, JSON.stringify({
  schema: 'nightly-coverage/v1 (SDD P07 · ADR 0275)',
  coverage_pct: 42.7, covered: 4270, total: 10000,
  runs: [{ sha: 'a1', ts: '20260622-020000', coverage_pct: 42.7, covered: 4270, total: 10000 }],
  computed_at: '20260622-020000', measured_of: 3,
}));
try {
  const m = measureCoverage(okPath);
  ok(m.status === 'measured', 'json válido → measured');
  ok(m.value === 42.7, 'value === coverage_pct (42.7)');
  ok(m.direction === 'up', 'coverage SOBE (catraca C2)');
  ok(m.detail.total === 10000 && m.detail.covered === 4270, 'detail preserva covered/total');
  ok(m.detail.measured_of === 3, 'detail preserva measured_of');
} finally { rmSync(okPath, { force: true }); }

// ── C: malformado → notYet (graceful) ───────────────────────────────────────
const badPath = join(tmpdir(), `coverage-bad-${process.pid}.json`);
writeFileSync(badPath, 'isto não é json {');
try {
  ok(measureCoverage(badPath).status === 'not_yet_measured', 'json malformado → not_yet_measured (graceful)');
} finally { rmSync(badPath, { force: true }); }

// ── D: sem coverage_pct numérico → notYet ───────────────────────────────────
const noPctPath = join(tmpdir(), `coverage-nopct-${process.pid}.json`);
writeFileSync(noPctPath, JSON.stringify({ runs: [], coverage_pct: null }));
try {
  ok(measureCoverage(noPctPath).status === 'not_yet_measured', 'sem coverage_pct numérico → not_yet_measured');
} finally { rmSync(noPctPath, { force: true }); }

console.log(fails === 0 ? '\n  coverage read-side (SDD P07 · ADR 0275): OK\n' : `\n  coverage read-side: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
