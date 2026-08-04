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
// COMO: compara o commit SERVIDO (campo `commit` de /api/mcp/version) com o HEAD de main
// no checkout do runner. Não precisa de tailscale; lê o endpoint DEDICADO com o token
// próprio MCP_DRIFT_TOKEN (secret do GH) — sem user, sem RBAC, sem disclosure pública
// (repo é público). Vazar o token só revela o SHA.
//
// O QUE O LAG MEDE (corrigido 2026-08-04 — falso-positivo real, ver §5 abaixo):
//   lag = AGORA − timestamp do commit MAIS ANTIGO ainda não servido
//   ou seja: "há quanto tempo o servidor está devendo alguma coisa".
//
// ⛔ NÃO é a distância entre os timestamps dos DOIS commits (`head − served`). Essa era
// a fórmula anterior, e o comentário dela afirmava ser "robusta a período tranquilo" —
// é o oposto exato. No silêncio ela dá 0 (ok), mas na PRIMEIRA RAJADA depois do
// silêncio ela devolve o tamanho do silêncio inteiro, de uma vez.
//
// Incidente que provou (2026-08-04T11:38:21Z, dados reais):
//   main ficou parado a noite toda; 2 commits caíram 11:35 e 11:37; a sentinela rodou
//   11:38 com o servidor legitimamente 3 min atrás (o cron roda a cada 15 min).
//     fórmula antiga (head − served)                → 14.43h → 🚨 ALARME falso
//     fórmula atual  (agora − mais antigo devendo)  →  0.05h → ✅ OK
//   O self-update.sh estava vivo e curando o tempo todo. O medidor é que media outra
//   coisa. Fixtures do caso preservadas em `--selftest`.
//
// Uso:  node scripts/governance/mcp-drift-sentinel.mjs            (humano)
//       node scripts/governance/mcp-drift-sentinel.mjs --json     (máquina)
// Saída: exit 0 = OK/WARN (sem alarme) · exit 1 = ALARME (drift real).
// Node puro (fetch global + git via execSync). Sem deps.

import { execSync } from 'node:child_process';
import { appendFileSync } from 'node:fs';

const HEALTH_URL = process.env.MCP_HEALTH_URL || 'https://mcp.oimpresso.com/api/mcp/version';
const TOKEN = (process.env.MCP_DRIFT_TOKEN || '').trim(); // token dedicado (GH secret) — sem user/RBAC
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
  if (!TOKEN) return { error: 'MCP_DRIFT_TOKEN ausente (secret não configurado)' };
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

/**
 * Lógica PURA do lag — testável sem git, sem rede, sem relógio real.
 * @param {number|null} epochMaisAntigoDevendo  timestamp (s) do commit mais antigo ainda não servido
 * @param {number} agoraEpoch                   timestamp (s) de agora
 * @returns {number|null} horas devendo (0 quando não deve nada); null se indeterminável
 */
export function calcularLagHoras(epochMaisAntigoDevendo, agoraEpoch) {
  if (!epochMaisAntigoDevendo) return 0; // nada não-servido → não deve nada
  if (!agoraEpoch) return null;
  return Math.max(0, (agoraEpoch - epochMaisAntigoDevendo) / 3600);
}

if (process.argv.includes('--selftest')) {
  const H = 3600;
  // Fixtures do incidente REAL 2026-08-04T11:38:21Z (served ec2d7f852 · head 121db910a).
  const agora = 1785843501;         // 11:38:21Z
  const maisAntigoDevendo = 1785843313; // 18bb68834 @ 11:35:13Z — 3 min antes
  const servedCommit = 1785791604;  // ec2d7f852 — 14.43h antes do head
  const casos = [
    ['incidente real: devendo há 3 min → NÃO alarma', calcularLagHoras(maisAntigoDevendo, agora) <= 6],
    ['incidente real: mede ~0.05h, não 14.4h', Math.abs(calcularLagHoras(maisAntigoDevendo, agora) - 0.05) < 0.02],
    ['a fórmula ANTIGA teria alarmado (regressão que não pode voltar)', (agora - servedCommit) / H > 6],
    ['nada devendo (served == head) → 0', calcularLagHoras(null, agora) === 0],
    ['devendo há 7h → alarma', calcularLagHoras(agora - 7 * H, agora) > 6],
    ['devendo há 5h59 → não alarma', calcularLagHoras(agora - 5.98 * H, agora) <= 6],
    ['silêncio longo NÃO conta: commit velho já servido não entra na conta', calcularLagHoras(null, agora) === 0],
    ['relógio indeterminável → null (não inventa veredito)', calcularLagHoras(maisAntigoDevendo, 0) === null],
    ['nunca negativo (commit no futuro por clock skew)', calcularLagHoras(agora + 999, agora) === 0],
  ];
  let falhas = 0;
  for (const [nome, ok] of casos) { if (!ok) falhas++; console.log(`  ${ok ? '✓' : '✗'} ${nome}`); }
  console.log(`\n${casos.length - falhas}/${casos.length} passaram`);
  process.exit(falhas ? 1 : 0);
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
// Ancestral → mede HÁ QUANTO TEMPO o servidor está devendo: idade do commit mais
// ANTIGO ainda não servido. Período tranquilo não conta (não há commit devendo);
// rajada após silêncio conta minutos, não o silêncio. Ver cabeçalho.
const behind = git(`rev-list --count ${served.commit}..HEAD`) || '?';
const maisAntigoDevendo = (git(`rev-list --reverse ${served.commit}..HEAD`) || '').split('\n')[0] || null;
const lagH = calcularLagHoras(maisAntigoDevendo ? commitEpoch(maisAntigoDevendo) : null, Math.floor(Date.now() / 1000));
const lagStr = lagH == null ? '?' : `${lagH.toFixed(2)}h`;

if (lagH != null && lagH > MAX_LAG_HOURS) {
  emit('ALARM', { reason: `servidor DEVENDO há ${lagStr} (> ${MAX_LAG_HOURS}h) — self-update.sh parou de curar?`, served: servedShort, main_head: headShort, behind_commits: behind, mais_antigo_devendo: (maisAntigoDevendo || '?').slice(0, 9) });
  process.exit(1);
}
emit('OK', { served: servedShort, main_head: headShort, behind_commits: behind, lag: lagStr });
process.exit(0);
