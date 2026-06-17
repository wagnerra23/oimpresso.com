#!/usr/bin/env node
// mcp-drift-sentinel.mjs — sentinela EXTERNA de drift do MCP server (ADR 0256 + 0062).
//
// POR QUE EXISTE: o MCP server (mcp.oimpresso.com, container no CT 100) roda código
// vindo de um `git pull` no host — fora de qualquer pipeline. No incidente 2026-06-17
// ele ficou ~17 dias servindo código velho SEM ninguém notar (os dados, da DB
// compartilhada, ficam frescos e mascaram o drift do código). O self-update.sh
// (cron no host) cura o drift; ESTA sentinela roda no GitHub (fora do tailnet) e
// GRITA se a cura parar de funcionar — é o "loop fechado por métrica" aplicado ao
// deploy: o drift nunca mais fica silencioso.
//
// COMO: compara o commit SERVIDO (campo `commit` de /api/mcp/health/auth) com o HEAD
// de main no checkout do runner. Não precisa de tailscale; lê o endpoint AUTENTICADO
// com um token read-only (MCP_SENTINEL_TOKEN, secret do GH) — o repo é público, então
// o SHA exato NÃO fica num endpoint anônimo (disclosure de versão). Tolera o lag normal
// do cron (minutos) via janela de tempo ENTRE commits (não relógio de parede), então
// período tranquilo no main não dá falso-positivo.
//
// Uso:  node scripts/governance/mcp-drift-sentinel.mjs            (humano)
//       node scripts/governance/mcp-drift-sentinel.mjs --json     (máquina)
// Saída: exit 0 = OK/WARN (sem alarme) · exit 1 = ALARME (drift real).
// Node puro (fetch global + git via execSync). Sem deps.

import { execSync } from 'node:child_process';
import { appendFileSync } from 'node:fs';

const HEALTH_URL = process.env.MCP_HEALTH_URL || 'https://mcp.oimpresso.com/api/mcp/health/auth';
const TOKEN = (process.env.MCP_SENTINEL_TOKEN || '').trim(); // token read-only mcp_* (GH secret)
const MAX_LAG_HOURS = Number(process.env.MCP_DRIFT_MAX_LAG_HOURS || 6); // cron roda /15min; 6h = host parou de curar há horas
const JSON_OUT = process.argv.includes('--json');

function git(cmd) {
  try { return execSync(`git ${cmd}`, { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim(); }
  catch { return null; }
}
function isAncestor(sha) {
  try { execSync(`git merge-base --is-ancestor ${sha} HEAD`, { stdio: 'ignore' }); return true; }
  catch { return false; }
}
function commitEpoch(sha) { const v = git(`show -s --format=%ct ${sha}`); return v ? Number(v) : null; }

async function fetchServedCommit() {
  if (!TOKEN) return { error: 'MCP_SENTINEL_TOKEN ausente (secret não configurado)' };
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), 12000);
  try {
    const r = await fetch(HEALTH_URL, {
      signal: ctrl.signal,
      headers: { 'user-agent': 'mcp-drift-sentinel', authorization: `Bearer ${TOKEN}` },
    });
    if (!r.ok) return { error: `HTTP ${r.status}` };
    const j = await r.json();
    return { commit: (j.commit || '').trim() || null, version: j.version ?? null };
  } catch (e) {
    return { error: String(e?.message || e) };
  } finally { clearTimeout(t); }
}

function emit(verdict, fields) {
  const payload = { verdict, health_url: HEALTH_URL, max_lag_hours: MAX_LAG_HOURS, ...fields };
  if (JSON_OUT) { console.log(JSON.stringify(payload, null, 2)); }
  else {
    const icon = verdict === 'ALARM' ? '🚨' : verdict === 'WARN' ? '⚠️' : '✅';
    console.log(`${icon} mcp-drift-sentinel: ${verdict}`);
    for (const [k, v] of Object.entries(fields)) console.log(`   ${k}: ${v}`);
  }
  if (process.env.GITHUB_STEP_SUMMARY) {
    const lines = [`### mcp-drift-sentinel — ${verdict}`, '', `- health: \`${HEALTH_URL}\``];
    for (const [k, v] of Object.entries(fields)) lines.push(`- ${k}: \`${v}\``);
    try { appendFileSync(process.env.GITHUB_STEP_SUMMARY, lines.join('\n') + '\n'); } catch {}
  }
}

const head = git('rev-parse HEAD');
const headShort = head ? head.slice(0, 9) : '?';
const served = await fetchServedCommit();

// Endpoint inalcançável → WARN (transitório; não derruba CI por um curl que falhou).
if (served.error) {
  emit('WARN', { reason: 'health endpoint inalcançável', detail: served.error, main_head: headShort });
  process.exit(0);
}
// Sem campo `commit` → endpoint anterior a este PR (graça de rollout) → WARN.
if (!served.commit) {
  emit('WARN', { reason: 'health não expõe `commit` ainda (endpoint pré-rollout deste PR)', main_head: headShort });
  process.exit(0);
}

const servedShort = served.commit.slice(0, 9);

if (served.commit === head) {
  emit('OK', { served: servedShort, main_head: headShort, lag: '0 (em main)' });
  process.exit(0);
}
// Commit servido não está na história de main → reescrita/branch estranho/muito velho.
if (!isAncestor(served.commit)) {
  emit('ALARM', { reason: 'commit servido NÃO é ancestral de main (história reescrita ou muito antigo)', served: servedShort, main_head: headShort });
  process.exit(1);
}
// Ancestral → mede a janela de tempo ENTRE os commits (robusto a período tranquilo).
const behind = git(`rev-list --count ${served.commit}..HEAD`) || '?';
const sEpoch = commitEpoch(served.commit);
const hEpoch = commitEpoch(head);
const lagH = sEpoch && hEpoch ? (hEpoch - sEpoch) / 3600 : null;
const lagStr = lagH == null ? '?' : `${lagH.toFixed(1)}h`;

if (lagH != null && lagH > MAX_LAG_HOURS) {
  emit('ALARM', { reason: `servido ${lagStr} atrás de main (> ${MAX_LAG_HOURS}h) — self-update.sh parou de curar?`, served: servedShort, main_head: headShort, behind_commits: behind });
  process.exit(1);
}
emit('OK', { served: servedShort, main_head: headShort, behind_commits: behind, lag: lagStr });
process.exit(0);
