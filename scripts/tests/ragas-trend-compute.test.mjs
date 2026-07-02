#!/usr/bin/env node
// Meta-teste do write-side do trend RAGAS real (ragas-trend-compute.mjs · ADR 0318).
// Espelha floor-compute.test.mjs: prova weekOf (normalização pra domingo),
// idempotência do merge (mesma semana substitui, nunca duplica), ordenação e a
// honestidade do SKIP (entra como inválida, nunca é descartada).
// Uso: node scripts/tests/ragas-trend-compute.test.mjs
import { weekOf, entryFromReport, mergeTrend, isValidRun, FIRST_SCHEDULED } from './ragas-trend-compute.mjs';

let fails = 0;
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { console.error(`  ✗ ${msg}`); fails++; } };

// ── weekOf: domingo on-or-before ────────────────────────────────────────────
ok(weekOf('2026-07-05T07:05:00-03:00') === '2026-07-05', 'domingo → o próprio domingo');
ok(weekOf('2026-07-08T12:00:00-03:00') === '2026-07-05', 'quarta → domingo anterior');
ok(weekOf('2026-07-11') === '2026-07-05', 'sábado (só data) → domingo anterior');
ok(weekOf('lixo') === null && weekOf(null) === null, 'não-data → null (graceful)');
ok(FIRST_SCHEDULED === '2026-07-05', 'first_scheduled = 1ª execução do cron (Kernel.php)');

// ── entryFromReport + isValidRun ────────────────────────────────────────────
const passReport = { gate_status: 'pass', n_evaluated: 51, faithfulness_avg: 0.69, relevancy_avg: 0.8, context_recall_avg: 0.38, ran_at: '2026-07-05T07:05:00-03:00' };
const skipReport = { gate_status: 'skipped', reason: 'OPENAI_API_KEY ausente', ran_at: '2026-07-12T07:05:00-03:00' };
const failReport = { gate_status: 'fail', n_evaluated: 51, faithfulness_avg: 0.5, relevancy_avg: 0.6, ran_at: '2026-07-19T07:05:00-03:00' };
ok(entryFromReport(passReport).valid === true, 'pass com n_evaluated>0 → válida');
ok(entryFromReport(skipReport).valid === false, 'skipped → INVÁLIDA (SKIP honesto conta contra uptime)');
ok(entryFromReport(failReport).valid === true, 'gate fail MAS mediu → run válido (uptime ≠ qualidade)');
ok(isValidRun({ gate_status: 'pass', n_evaluated: 0 }) === false, 'pass com n_evaluated=0 → inválida (não mediu nada)');

// ── merge: 1ª publicação → schema + 1 semana ────────────────────────────────
const t1 = mergeTrend(null, passReport);
ok(t1.weeks.length === 1 && t1.weeks[0].week === '2026-07-05', '1ª publicação → 1 semana');
ok(t1.first_scheduled === '2026-07-05', 'first_scheduled default preservado');
ok(/ragas-real-trend\/v1/.test(t1.schema), 'schema ragas-real-trend/v1');

// ── merge: append de semanas + ordenação ────────────────────────────────────
const t2 = mergeTrend(mergeTrend(t1, failReport), skipReport);
ok(t2.weeks.length === 3, 'append → 3 semanas');
ok(t2.weeks.map((w) => w.week).join(',') === '2026-07-05,2026-07-12,2026-07-19', 'semanas ordenadas asc');

// ── merge: MESMA semana substitui (idempotente, nunca duplica) ──────────────
const rerun = { ...passReport, ran_at: '2026-07-05T09:00:00-03:00', faithfulness_avg: 0.71 };
const t3 = mergeTrend(t2, rerun);
ok(t3.weeks.length === 3, 're-publicar mesma semana NÃO duplica');
ok(t3.weeks[0].faithfulness_avg === 0.71, 're-publicar substitui a entrada da semana');

// ── determinismo: mesmo input → mesmo output ────────────────────────────────
ok(JSON.stringify(mergeTrend(t2, rerun)) === JSON.stringify(t3), 'determinístico (mesmo input = mesmo output)');

// ── report sem ran_at → erro explícito (nunca entrada fantasma) ─────────────
let threw = false;
try { entryFromReport({ gate_status: 'pass', n_evaluated: 51 }); } catch { threw = true; }
ok(threw, 'report sem ran_at → throw (nunca inventa semana)');

console.log(fails === 0 ? '\n  ragas trend write-side (ADR 0318): OK\n' : `\n  ragas trend write-side: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
