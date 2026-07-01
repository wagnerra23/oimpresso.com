#!/usr/bin/env node
// @ts-check
/**
 * cron-watchdog.mjs — G6: heartbeat dos crons de governança (generaliza o auto-canário
 * single-cron do memory-health, ADR 0317 §2 — "quem vigia o vigia").
 *
 * O GitHub DESABILITA workflow agendado após 60d sem atividade no repo — EM SILÊNCIO.
 * Sem vigia, o schedule morre, as sentinelas (memory-health, drift, ragas…) param de
 * rodar e o brief fica verde = regressão disfarçada de saúde. Este roda em PR (sempre
 * ativo) e checa a idade da última run AGENDADA de CADA workflow com `schedule:`,
 * descobertos DINAMICAMENTE (não hardcode → cobre cron novo/removido automaticamente,
 * sem lista que drifta). Real-time DE PROPÓSITO (liveness ≠ conteúdo reproduzível, ao
 * contrário dos Checks de memory-health).
 *
 * 🔴 cron morto (última run agendada > limite por cadência) · 🟡 bootstrap (sem run ainda).
 * Limite: semanal 10d · mensal 35d · diário/frequente 3d.
 *
 * Uso: node scripts/governance/cron-watchdog.mjs   (precisa de `gh` autenticado + actions:read)
 * Refs: ADR 0317 §2 (auto-canário) · 0256 (sentinela). Molde: memory-health.yml job cron-liveness.
 */
import { readdirSync, readFileSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';

const ROOT = process.cwd();
const WF_DIR = '.github/workflows';

// Descobre workflows com trigger `schedule:` real (bloco com `- cron:`, não a palavra
// solta num comentário) e extrai o primeiro cron. Robusto a cron novo/removido.
function scheduledWorkflows() {
  const out = [];
  for (const f of readdirSync(join(ROOT, WF_DIR)).filter((x) => /\.ya?ml$/.test(x)).sort()) {
    let txt;
    try { txt = readFileSync(join(ROOT, WF_DIR, f), 'utf8'); } catch { continue; }
    const sched = txt.match(/^\s*schedule:\s*\n((?:\s*#.*\n|\s*-\s*cron:.*\n)+)/mi);
    if (!sched) continue;
    const crons = [...sched[1].matchAll(/cron:\s*['"]([^'"]+)['"]/g)].map((m) => m[1]);
    if (crons.length) out.push({ file: f, cron: crons[0] });
  }
  return out;
}

// Limite (dias) por cadência: DOW setado → semanal (10) · DOM setado → mensal (35) ·
// senão diário/frequente (3). Generoso o bastante p/ não dar falso-positivo, apertado
// o bastante p/ pegar morte de vários dias (o GitHub só desabilita aos 60d).
function thresholdDays(cron) {
  const parts = cron.trim().split(/\s+/);
  const dom = parts[2], dow = parts[4];
  if (dow && dow !== '*' && dow !== '?') return 10;
  if (dom && dom !== '*' && dom !== '?') return 35;
  return 3;
}

function gh(args) {
  try {
    return execSync(`gh ${args}`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  } catch {
    return '';
  }
}

// Última run AGENDADA (event=schedule, qualquer conclusão — liveness ≠ sucesso).
// Filtra o JSON em JS (sem `--jq`): evita o quoting de aspas simples que o cmd.exe do
// Windows quebra (execSync usa cmd no Win, sh no CI) — cross-platform + testável local.
function lastScheduledRun(file) {
  const raw = gh(`run list --workflow ${file} --event schedule --status completed --limit 1 --json createdAt`);
  if (!raw) return '';
  try { return JSON.parse(raw)[0]?.createdAt || ''; } catch { return ''; }
}

const wfs = scheduledWorkflows();
if (!wfs.length) {
  console.log('cron-watchdog: nenhum workflow agendado encontrado (nada a vigiar).');
  process.exit(0);
}

const dead = [], boot = [], alive = [];
const nowMs = Date.now(); // liveness real (não determinístico de propósito)
for (const { file, cron } of wfs) {
  const thr = thresholdDays(cron);
  const last = lastScheduledRun(file);
  if (!last) { boot.push(`${file} (cron '${cron}') — sem run agendada ainda (bootstrap; arma na 1ª execução)`); continue; }
  const age = Math.floor((nowMs - new Date(last).getTime()) / 86400000);
  if (age > thr) dead.push(`${file} (cron '${cron}') MORTO há ${age}d (limite ${thr}d) — última agendada: ${last}`);
  else alive.push(`${file} ${age}d/${thr}d`);
}

console.log(`🩺 cron-watchdog — ${wfs.length} crons agendados · ${alive.length} vivos · ${boot.length} bootstrap · ${dead.length} 🔴 mortos`);
for (const b of boot) console.log(`🟡 ${b}`);
for (const a of alive) console.log(`   ✓ ${a}`);
for (const d of dead) console.error(`🔴 ${d}`);
if (dead.length) {
  console.error(`\n✗ ${dead.length} cron(s) de governança MORTO(s) — o GitHub desabilitou o schedule (60d sem atividade) ou o workflow quebra na origem. Um push no repo re-ativa; confirme run agendada nova. (ADR 0317 §2 — o heartbeat que vigia os heartbeats).`);
  process.exit(1);
}
console.log(`✓ todos os ${wfs.length} crons de governança com heartbeat < limite.`);
