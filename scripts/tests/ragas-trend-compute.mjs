#!/usr/bin/env node
// ragas-trend-compute.mjs — write-side do trend do RAGAS real (ADR 0318 + pattern
// de transporte nightly-floor ADR 0279 Opção A).
//
// Lê o report JSON da última run de `jana:ragas-real-eval` (CT 100 staging, dom
// 07:00 BRT — Kernel.php) e faz MERGE idempotente no trend acumulado, keyed por
// SEMANA (domingo da run): re-publicar a mesma semana substitui a entrada, nunca
// duplica. O trend vive na branch ÓRFÃ governance/ragas-real-trend (publicada por
// ct100-ragas-publish.sh) e o read-side (sdd-scorecard.mjs measureRagasRealUptime)
// calcula uptime = % de semanas com run VÁLIDO (mediu de verdade: gate pass/fail
// com n_evaluated>0; SKIP honesto = run inválido — conta contra o uptime).
//
// Determinístico: para o MESMO (existing, report), mesmo output (sem timestamp
// gerado aqui — ran_at vem do report). Sem deps, node puro.
// Uso: node scripts/tests/ragas-trend-compute.mjs --report <run.json> [--existing <trend.json>] [--out <file>]
import { readFileSync, existsSync, writeFileSync, mkdirSync } from 'node:fs';
import { join, dirname } from 'node:path';

const arg = (k, d) => { const i = process.argv.indexOf(k); return i >= 0 ? process.argv[i + 1] : d; };
const REPORT = arg('--report', null);
const EXISTING = arg('--existing', null);
const OUT = arg('--out', join(process.cwd(), 'governance', 'ragas-real-trend.json'));

// 1ª execução agendada do cron (Kernel.php weeklyOn(0,'07:00') · deploy 2026-07-01).
export const FIRST_SCHEDULED = '2026-07-05';

// domingo on-or-before da data (YYYY-MM-DD, UTC date-math — determinístico)
export function weekOf(dateStr) {
  const m = String(dateStr ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!m) return null;
  const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
  d.setUTCDate(d.getUTCDate() - d.getUTCDay()); // getUTCDay(): 0=domingo
  return d.toISOString().slice(0, 10);
}

// run VÁLIDO = mediu de verdade (mesma definição do read-side — não confiar em flag)
export function isValidRun(e) {
  return (e.gate_status === 'pass' || e.gate_status === 'fail') && (e.n_evaluated ?? 0) > 0;
}

// report do comando → entrada de semana do trend (campos mínimos, sem PII)
export function entryFromReport(report) {
  const week = weekOf(report.ran_at);
  if (!week) throw new Error(`report sem ran_at parseável (YYYY-MM-DD...): ${JSON.stringify(report.ran_at)}`);
  const e = {
    week,
    ran_at: report.ran_at,
    gate_status: report.gate_status ?? 'unknown',
    n_evaluated: report.n_evaluated ?? 0,
    n_no_context: report.n_no_context ?? null,
    n_synth_failed: report.n_synth_failed ?? null,
    faithfulness_avg: report.faithfulness_avg ?? null,
    relevancy_avg: report.relevancy_avg ?? null,
    context_recall_avg: report.context_recall_avg ?? null,
    reason: report.reason ?? report.error ?? null,
  };
  e.valid = isValidRun(e);
  return e;
}

// merge idempotente: mesma semana → substitui; ordena por semana asc
export function mergeTrend(existing, report) {
  const base = existing && Array.isArray(existing.weeks) ? existing : { weeks: [] };
  const entry = entryFromReport(report);
  const weeks = base.weeks.filter((w) => w.week !== entry.week).concat([entry])
    .sort((a, b) => String(a.week).localeCompare(String(b.week)));
  return {
    schema: 'ragas-real-trend/v1 (ADR 0318 · transporte pattern nightly-floor ADR 0279)',
    first_scheduled: base.first_scheduled ?? FIRST_SCHEDULED,
    cadence: 'weekly-sunday 07:00 BRT (app/Console/Kernel.php · jana:ragas-real-eval)',
    weeks,
    note: 'run válido = gate pass/fail com n_evaluated>0; skipped (sem OPENAI/contexto) = inválido — conta contra o uptime. Semana ausente = transporte/cron down = inválida (read-side conta pelo gap).',
  };
}

// CLI (só quando executado direto; importável p/ teste)
import { realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const isMain = (() => { try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); } catch { return false; } })();
if (isMain) {
  if (!REPORT || !existsSync(REPORT)) { console.error('uso: ragas-trend-compute.mjs --report <run.json> [--existing <trend.json>] [--out <file>]'); process.exit(1); }
  const report = JSON.parse(readFileSync(REPORT, 'utf8'));
  let existing = null;
  if (EXISTING && existsSync(EXISTING)) { try { existing = JSON.parse(readFileSync(EXISTING, 'utf8')); } catch { existing = null; } }
  const trend = mergeTrend(existing, report);
  mkdirSync(dirname(OUT), { recursive: true });
  writeFileSync(OUT, JSON.stringify(trend, null, 2) + '\n', 'utf8');
  const last = trend.weeks[trend.weeks.length - 1];
  console.log(`ragas-trend-compute → ${OUT}: ${trend.weeks.length} semana(s), última ${last.week} (${last.gate_status}${last.valid ? ' · válida' : ' · INVÁLIDA'})`);
}
