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
// COMO: compara o commit SERVIDO (campo `commit` de /api/mcp/version) com o tip VIVO de
// main (fetch na hora). Não precisa de tailscale; lê o endpoint DEDICADO com o token
// próprio MCP_DRIFT_TOKEN (secret do GH) — sem user, sem RBAC, sem disclosure pública
// (repo é público). Vazar o token só revela o SHA.
//
// CONTRA O QUE COMPARAR (corrigido 2026-08-07 — falso-positivo real, ver §5 abaixo):
//   Comparava contra `HEAD` do checkout. `actions/checkout` materializa `github.sha` —
//   o tip de main CONGELADO no instante em que a run foi CRIADA, não o tip vivo. Como
//   as runs agendadas ficam na fila do GitHub, o congelado envelhece: qualquer merge
//   dentro da janela deixa o servidor legitimamente À FRENTE do checkout.
//
//   Pior, o teste era ASSIMÉTRICO: `isAncestor(served, HEAD)` só sabia dizer "servido é
//   ancestral" (atrás/igual) ou "não é" (alarme). O terceiro estado — servidor à frente
//   do meu ref — caía no ramo de alarme como "história reescrita", sendo SAUDÁVEL.
//
// Incidente que provou (run 31205724975, dados reais):
//   18:10:24Z run criada  → github.sha congela em f8794b9d3 (tip de main naquele instante)
//   18:11:51Z ca7dddb38 (#5394) mergeia em main            — DENTRO da janela
//   18:18:22Z job executa (505s de fila) → checkout materializa o SHA CONGELADO
//   18:18:44Z script roda; servidor já servia ca7dddb38 (descendente de f8794b9d3)
//     teste antigo (served ancestral de HEAD?) → não → 🚨 ALARME falso
//     teste atual  (4 estados, ver classificarPosicao) →  ✅ A_FRENTE, sem alarme
//   Não havia drift: o servidor estava À FRENTE do snapshot, não atrás. Nas 12 runs
//   daquele dia, 11 executaram em 28-67s e só essa levou 505s — foi a fila que abriu a
//   janela. Fixtures dos 4 estados preservadas no `--selftest` (bite-test com git real).
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
import { appendFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const HEALTH_URL = process.env.MCP_HEALTH_URL || 'https://mcp.oimpresso.com/api/mcp/version';
const TOKEN = (process.env.MCP_DRIFT_TOKEN || '').trim(); // token dedicado (GH secret) — sem user/RBAC
const MAX_LAG_HOURS = Number(process.env.MCP_DRIFT_MAX_LAG_HOURS || 6); // cron roda /15min; 6h = host parou de curar há horas
const JSON_OUT = process.argv.includes('--json');

function git(cmd, cwd) {
  try { return execSync(`git ${cmd}`, { stdio: ['ignore', 'pipe', 'ignore'], cwd }).toString().trim(); }
  catch { return null; }
}
function isAncestor(a, b, cwd) {
  try { execSync(`git merge-base --is-ancestor ${a} ${b}`, { stdio: 'ignore', cwd }); return true; }
  catch { return false; }
}
function commitEpoch(sha) { const v = git(`show -s --format=%ct ${sha}`); return v ? Number(v) : null; }

/**
 * Resolve o tip de main. Preferência ABSOLUTA pelo oráculo VIVO (fetch na hora) — o HEAD
 * do checkout é `github.sha`, congelado quando a run foi criada, e envelhece na fila.
 * Se o fetch falhar, cai no congelado MAS declara isso na saída (instrumento que não
 * conseguiu medir o ideal não pode fingir que mediu).
 */
export function resolveMainRef(cwd) {
  if (git('fetch --quiet origin main', cwd) !== null) {
    const sha = git('rev-parse FETCH_HEAD', cwd);
    if (sha) return { sha, fonte: 'origin/main (fetch vivo)' };
  }
  const sha = git('rev-parse HEAD', cwd);
  if (sha) return { sha, fonte: 'HEAD do checkout (fetch falhou — ref pode estar CONGELADO no trigger)' };
  return { sha: null, fonte: 'indeterminável (git indisponível)' };
}

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

/**
 * Ramo de DECISÃO da posição servidor × main — puro, testável sem git/rede.
 * São QUATRO estados possíveis, e só UM é drift. O teste antigo conhecia dois e
 * jogava o "à frente" no balde de alarme (era o falso-positivo de 2026-08-07).
 *
 * @param {{iguais:boolean, servedEhAncestral:boolean, mainEhAncestral:boolean}} rel
 * @returns {'EM_DIA'|'ATRAS'|'A_FRENTE'|'DIVERGENTE'}
 */
export function classificarPosicao({ iguais, servedEhAncestral, mainEhAncestral }) {
  if (iguais) return 'EM_DIA';                 // servidor == main
  if (servedEhAncestral) return 'ATRAS';        // servidor devendo → mede o lag
  if (mainEhAncestral) return 'A_FRENTE';       // servidor à frente do meu ref → NÃO é drift
  return 'DIVERGENTE';                          // histórias sem relação → alarme legítimo
}

if (process.argv.includes('--selftest')) {
  const H = 3600;

  /**
   * Bite-test com git REAL (repo temporário, sem rede): exercita `isAncestor` + o ramo de
   * decisão juntos — que é por onde o FP de 2026-08-07 passou. O `--selftest` antigo só
   * cobria `calcularLagHoras`; a ancestralidade não tinha teste nenhum.
   * Sem literal de path específico de SO (tmpdir + join): roda igual no Windows e no CI Linux.
   */
  const biteTestGit = () => {
    const dir = mkdtempSync(join(tmpdir(), 'mcp-drift-'));
    const g = (cmd) => execSync(`git ${cmd}`, { cwd: dir, stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
    const commit = (msg) => g(`-c commit.gpgsign=false commit --quiet --allow-empty -m ${msg}`);
    try {
      g('init --quiet');
      g('config user.email teste@local');
      g('config user.name teste');
      commit('A'); const A = g('rev-parse HEAD');
      commit('B'); const B = g('rev-parse HEAD');
      g(`checkout --quiet ${A}`);              // ramo divergente a partir de A
      commit('C'); const C = g('rev-parse HEAD');
      const rel = (served, main) => classificarPosicao({
        iguais: served === main,
        servedEhAncestral: isAncestor(served, main, dir),
        mainEhAncestral: isAncestor(main, served, dir),
      });
      return [
        ['bite/git: servidor ATRÁS de main → ATRAS (drift real, mede lag)', rel(A, B) === 'ATRAS'],
        ['bite/git: servidor À FRENTE do ref → A_FRENTE (era o FP de 2026-08-07)', rel(B, A) === 'A_FRENTE'],
        ['controle/git: servidor em dia → EM_DIA', rel(B, B) === 'EM_DIA'],
        ['controle/git: histórias divergentes AINDA alarmam → DIVERGENTE', rel(C, B) === 'DIVERGENTE'],
        ['controle/git: A_FRENTE e ATRAS não colapsam (assimetria preservada)', rel(A, B) !== rel(B, A)],
      ];
    } finally { try { rmSync(dir, { recursive: true, force: true }); } catch {} }
  };

  /**
   * Bite-test da PERNA 1 (oráculo vivo): simula o cenário exato da run 31205724975 —
   * o checkout tem um HEAD velho (github.sha congelado) e a origem já avançou.
   * `resolveMainRef` TEM que devolver o tip vivo, não o congelado.
   */
  const biteTestOraculoVivo = () => {
    const base = mkdtempSync(join(tmpdir(), 'mcp-drift-oraculo-'));
    const origem = join(base, 'origem');
    const clone = join(base, 'clone');
    const g = (cmd, cwd) => execSync(`git ${cmd}`, { cwd, stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
    const commit = (msg, cwd) => g(`-c commit.gpgsign=false commit --quiet --allow-empty -m ${msg}`, cwd);
    try {
      execSync(`git init --quiet -b main "${origem}"`, { stdio: 'ignore' });
      g('config user.email teste@local', origem); g('config user.name teste', origem);
      commit('velho', origem);
      const congelado = g('rev-parse HEAD', origem);
      execSync(`git clone --quiet "${origem}" "${clone}"`, { stdio: 'ignore' });
      commit('novo', origem);                       // a origem anda DEPOIS do clone
      const vivo = g('rev-parse HEAD', origem);
      const r = resolveMainRef(clone);
      return [
        ['bite/oráculo: fixture válida (o tip vivo difere do congelado)', congelado !== vivo],
        ['bite/oráculo: resolveMainRef devolve o tip VIVO, não o congelado', r.sha === vivo],
        ['bite/oráculo: NÃO devolve o HEAD congelado do checkout', r.sha !== congelado],
        ['bite/oráculo: declara a fonte usada (fetch vivo)', /fetch vivo/.test(r.fonte)],
        ['controle/oráculo: o clone realmente tinha o HEAD velho', g('rev-parse HEAD', clone) === congelado],
      ];
    } finally { try { rmSync(base, { recursive: true, force: true }); } catch {} }
  };

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
    // --- posição servidor × main: tabela-verdade PURA dos 4 estados (só 1 é drift) ---
    ['posição: iguais → EM_DIA', classificarPosicao({ iguais: true, servedEhAncestral: true, mainEhAncestral: true }) === 'EM_DIA'],
    ['posição: servido é ancestral → ATRAS', classificarPosicao({ iguais: false, servedEhAncestral: true, mainEhAncestral: false }) === 'ATRAS'],
    ['posição: main é ancestral → A_FRENTE (NÃO é drift)', classificarPosicao({ iguais: false, servedEhAncestral: false, mainEhAncestral: true }) === 'A_FRENTE'],
    ['posição: nenhum é ancestral → DIVERGENTE (alarme legítimo)', classificarPosicao({ iguais: false, servedEhAncestral: false, mainEhAncestral: false }) === 'DIVERGENTE'],
    // --- bite-test com git REAL: o ramo que o FP de 2026-08-07 atravessou ---
    ...biteTestGit(),
    ...biteTestOraculoVivo(),
  ];
  let falhas = 0;
  for (const [nome, ok] of casos) { if (!ok) falhas++; console.log(`  ${ok ? '✓' : '✗'} ${nome}`); }
  console.log(`\n${casos.length - falhas}/${casos.length} passaram`);
  process.exit(falhas ? 1 : 0);
}

const mainRef = resolveMainRef();
const head = mainRef.sha;
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
// Sem tip de main → nada pôde ser comparado. Não inventa veredito (nem verde nem alarme).
if (!head) {
  emit('WARN', { reason: 'tip de main indeterminável — NADA foi comparado', detail: mainRef.fonte, served: served.commit.slice(0, 9) });
  process.exit(0);
}

const servedShort = served.commit.slice(0, 9);

// QUATRO estados, só um é drift. Ver classificarPosicao + cabeçalho.
const posicao = classificarPosicao({
  iguais: served.commit === head,
  servedEhAncestral: isAncestor(served.commit, head),
  mainEhAncestral: isAncestor(head, served.commit),
});

if (posicao === 'EM_DIA') {
  emit('OK', { served: servedShort, main_head: headShort, main_ref: mainRef.fonte, lag: '0 (em main)' });
  process.exit(0);
}
// Servidor À FRENTE do nosso ref: o ref é que está velho (checkout congelado no trigger,
// ou main andou entre o fetch e a leitura do endpoint). NÃO é drift — o drift é o servidor
// ficar PRA TRÁS. Alarmar aqui era o falso-positivo de 2026-08-07.
if (posicao === 'A_FRENTE') {
  const ahead = git(`rev-list --count ${head}..${served.commit}`) || '?';
  emit('OK', { reason: 'servidor à FRENTE do nosso ref de main (ref velho, não drift)', served: servedShort, main_head: headShort, main_ref: mainRef.fonte, ahead_commits: ahead, lag: '0 (nada devendo)' });
  process.exit(0);
}
// Histórias sem relação de ancestralidade nos dois sentidos → reescrita/branch estranho.
if (posicao === 'DIVERGENTE') {
  emit('ALARM', { reason: 'commit servido DIVERGE de main nos dois sentidos (história reescrita ou branch estranho)', served: servedShort, main_head: headShort, main_ref: mainRef.fonte });
  process.exit(1);
}
// ATRAS → mede HÁ QUANTO TEMPO o servidor está devendo: idade do commit mais
// ANTIGO ainda não servido. Período tranquilo não conta (não há commit devendo);
// rajada após silêncio conta minutos, não o silêncio. Ver cabeçalho.
const behind = git(`rev-list --count ${served.commit}..${head}`) || '?';
const maisAntigoDevendo = (git(`rev-list --reverse ${served.commit}..${head}`) || '').split('\n')[0] || null;
const lagH = calcularLagHoras(maisAntigoDevendo ? commitEpoch(maisAntigoDevendo) : null, Math.floor(Date.now() / 1000));
const lagStr = lagH == null ? '?' : `${lagH.toFixed(2)}h`;

if (lagH != null && lagH > MAX_LAG_HOURS) {
  emit('ALARM', { reason: `servidor DEVENDO há ${lagStr} (> ${MAX_LAG_HOURS}h) — self-update.sh parou de curar?`, served: servedShort, main_head: headShort, main_ref: mainRef.fonte, behind_commits: behind, mais_antigo_devendo: (maisAntigoDevendo || '?').slice(0, 9) });
  process.exit(1);
}
emit('OK', { served: servedShort, main_head: headShort, main_ref: mainRef.fonte, behind_commits: behind, lag: lagStr });
process.exit(0);
