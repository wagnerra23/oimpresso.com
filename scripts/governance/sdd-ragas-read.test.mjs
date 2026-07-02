#!/usr/bin/env node
// Meta-teste do read-side do ragas_real_uptime (ADR 0318 · transporte pattern
// nightly-floor ADR 0279). Espelha sdd-floor-read.test.mjs: importa
// measureRagasRealUptime de sdd-scorecard.mjs (guard isMain garante que o import
// NÃO dispara o scorecard inteiro) e prova os lados:
//   A) arquivo ausente               → not_yet_measured (fallback honesto)
//   B) trend válido com gap+skip     → measured, uptime conta gap e skip como inválidos
//   C) arquivo presente malformado   → not_yet_measured (graceful, não estoura)
//   D) weeks[] vazio                 → not_yet_measured
//   E) todas válidas                 → 100%
//   F) counterfactual anti-mentira   → só skips = measured 0% (transporte up, eval down — NUNCA notYet nem 100)
// Uso: node scripts/governance/sdd-ragas-read.test.mjs
import { measureRagasRealUptime } from './sdd-scorecard.mjs';
import { writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

let fails = 0;
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { console.error(`  ✗ ${msg}`); fails++; } };
const tmp = (name, body) => { const p = join(tmpdir(), `ragas-${name}-${process.pid}.json`); writeFileSync(p, typeof body === 'string' ? body : JSON.stringify(body)); return p; };
const week = (w, extra = {}) => ({ week: w, ran_at: `${w}T07:05:00-03:00`, gate_status: 'pass', n_evaluated: 51, faithfulness_avg: 0.69, relevancy_avg: 0.8, context_recall_avg: 0.38, ...extra });

// ── A: ausente → notYet ─────────────────────────────────────────────────────
const absent = measureRagasRealUptime(join(tmpdir(), `ragas-inexistente-${process.pid}.json`));
ok(absent.status === 'not_yet_measured', 'arquivo ausente → not_yet_measured');
ok(absent.value === null, 'ausente → value null (não mente 0 nem 100)');

// ── B: 4 semanas agendadas, 2 válidas (1 skip + 1 gap) → 50% ────────────────
const p1 = tmp('gap', {
  first_scheduled: '2026-07-05',
  weeks: [
    week('2026-07-05'),
    week('2026-07-12', { gate_status: 'skipped', n_evaluated: 0, reason: 'OPENAI_API_KEY ausente' }),
    // 2026-07-19 AUSENTE (cron/transporte down) — conta como inválida pelo gap
    week('2026-07-26', { gate_status: 'fail' }), // gate fail MAS mediu → run VÁLIDO (uptime ≠ qualidade)
  ],
});
try {
  const m = measureRagasRealUptime(p1);
  ok(m.status === 'measured', 'trend válido → measured');
  ok(m.value === 50, `2 válidas de 4 agendadas (skip + gap = inválidas) → 50% (got ${m.value})`);
  ok(m.detail.weeks_expected === 4 && m.detail.weeks_valid === 2, 'detail: expected=4, valid=2');
  ok(m.direction === 'up' && m.target === 95, 'uptime SOBE pra ≥95');
} finally { rmSync(p1, { force: true }); }

// ── C: malformado → notYet (graceful) ───────────────────────────────────────
const p2 = tmp('bad', 'isto não é json {');
try { ok(measureRagasRealUptime(p2).status === 'not_yet_measured', 'json malformado → not_yet_measured (graceful)'); }
finally { rmSync(p2, { force: true }); }

// ── D: weeks vazio → notYet ─────────────────────────────────────────────────
const p3 = tmp('empty', { first_scheduled: '2026-07-05', weeks: [] });
try { ok(measureRagasRealUptime(p3).status === 'not_yet_measured', 'weeks[] vazio → not_yet_measured'); }
finally { rmSync(p3, { force: true }); }

// ── E: todas válidas → 100% ─────────────────────────────────────────────────
const p4 = tmp('full', { first_scheduled: '2026-07-05', weeks: [week('2026-07-05'), week('2026-07-12')] });
try {
  const m = measureRagasRealUptime(p4);
  ok(m.status === 'measured' && m.value === 100, `2/2 válidas → 100% (got ${m.value})`);
} finally { rmSync(p4, { force: true }); }

// ── F: counterfactual — só skips NÃO vira notYet nem 100 (mediria mentira) ──
const p5 = tmp('skips', {
  first_scheduled: '2026-07-05',
  weeks: [week('2026-07-05', { gate_status: 'skipped', n_evaluated: 0 })],
});
try {
  const m = measureRagasRealUptime(p5);
  ok(m.status === 'measured' && m.value === 0, `só skips → measured 0% — transporte funciona, eval não roda (got status=${m.status} value=${m.value})`);
} finally { rmSync(p5, { force: true }); }

console.log(fails === 0 ? '\n  ragas read-side (ADR 0318 · transporte 0279): OK\n' : `\n  ragas read-side: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
