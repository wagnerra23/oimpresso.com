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
// A RÉGUA É main VIVO, NÃO O HEAD DO RUNNER (corrigido 2026-08-07 — 2º falso-positivo real):
//   O checkout do runner é PINADO em `github.sha` — snapshot de main no instante em que a run
//   foi CRIADA. Todo merge que cai entre a criação da run e o `git pull` do host deixa o
//   servidor 1+ commit À FRENTE desse snapshot. Aí `merge-base --is-ancestor served HEAD` dá
//   falso — não porque a história foi reescrita, mas porque o servido é DESCENDENTE. A
//   sentinela lia isso como "história reescrita ou muito antigo": o diagnóstico exatamente
//   INVERTIDO (o servidor estava mais FRESCO que a régua).
//
// Medição que provou (2026-08-07): das 34 runs vermelhas, 6 são dessa forma, e 6/6 têm o
//   servido como DESCENDENTE do headSha pinado daquela run — 100% falso positivo:
//     31205724975 (ca7dddb38, +1) · 30312629877 (fab0d5ec1, +1) · 30307638584 (e33c0521e, +3)
//     30295939652 (2d9241eb5, +3) · 29614431978 (62991cd95, +1) · 28379239901 (05f32d4ae, +3)
//   A janela é "criação da run → pull do host": o host cura /15min e a run espera fila.
//
// AGORA a topologia é classificada em 5 estados (`classificarTopologia`) e cada um tem veredito
// EXPLÍCITO em `VEREDITO_POR_TOPOLOGIA` — que é o map que o FLUXO usa, então o assert sobre ele
// prova o comportamento do pipeline, não só o helper:
//   IGUAL        → OK
//   ATRASADO     → mede o lag (regra do bloco acima)
//   ADIANTE      → WARN. Não há como distinguir "main andou depois do nosso fetch" de "host numa
//                  branch" — então NÃO se afirma nenhum dos dois. Host preso em branch alheia
//                  vira DIVERGENTE (→ ALARM) no minuto em que main avançar: o caso ruim se
//                  auto-escala, o benigno passa quieto.
//   DIVERGENTE   → ALARM (história reescrita / host em branch alheia — o alarme real desta forma)
//   DESCONHECIDO → WARN. Objeto ausente do clone = NÃO CONSEGUI MEDIR, e instrumento que não
//                  mediu não afirma veredito (antes caía no `catch` de isAncestor e ALARMAVA).
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
function gitOk(cmd) {
  try { execSync(`git ${cmd}`, { stdio: 'ignore' }); return true; } catch { return false; }
}
// Separados de propósito: `merge-base --is-ancestor` sai 1 pra "não é ancestral" e 128 pra
// "objeto desconhecido". Colapsar os dois num `false` foi o que fez a sentinela ALARMAR sem
// ter conseguido medir — quem pergunta ancestralidade já checou que o objeto existe.
function objetoConhecido(sha) { return gitOk(`cat-file -e ${sha}`); }
function ehAncestral(a, b) { return gitOk(`merge-base --is-ancestor ${a} ${b}`); }
function commitEpoch(sha) { const v = git(`show -s --format=%ct ${sha}`); return v ? Number(v) : null; }

/**
 * Resolve a régua: main VIVO (fetch agora), NÃO o HEAD pinado do runner (ver cabeçalho).
 * Se o fetch falhar, degrada pro snapshot — e a saída DIZ qual régua foi usada (`ref_vivo`),
 * porque veredito com régua velha não pode se passar por veredito com régua fresca.
 * @returns {{ref: string|null, vivo: boolean}}
 */
function resolveRefMain() {
  if (gitOk('fetch --no-tags --quiet origin main')) {
    const sha = git('rev-parse FETCH_HEAD');
    if (sha) return { ref: sha, vivo: true };
  }
  return { ref: git('rev-parse HEAD'), vivo: false };
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
  // Publica o veredito/razão REAIS pro passo do alarme — antes o corpo da issue era fixo
  // ("serve um commit atrás de main") e afirmava um diagnóstico que o script podia não ter
  // concluído. Sanitizado a uma linha porque GITHUB_OUTPUT é `chave=valor`.
  if (process.env.GITHUB_OUTPUT) {
    const r = String(fields.reason || verdict).replace(/[\r\n]+/g, ' ').slice(0, 300);
    try { appendFileSync(process.env.GITHUB_OUTPUT, `verdict=${verdict}\nreason=${r}\n`); } catch {}
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
 * Topologia PURA entre o commit servido e a régua — 5 estados mutuamente exclusivos.
 * Sem git, sem rede, sem relógio: dá pra testar cada ramo.
 * @returns {'IGUAL'|'ATRASADO'|'ADIANTE'|'DIVERGENTE'|'DESCONHECIDO'}
 */
export function classificarTopologia({ conhecido, servedIgualRef, servedEhAncestralDaRef, refEhAncestralDoServed }) {
  if (!conhecido) return 'DESCONHECIDO';   // não consegui medir — precede tudo
  if (servedIgualRef) return 'IGUAL';
  if (servedEhAncestralDaRef) return 'ATRASADO';
  if (refEhAncestralDoServed) return 'ADIANTE';
  return 'DIVERGENTE';
}

/**
 * Veredito por topologia. O FLUXO abaixo despacha por este map (não por `if` solto), então
 * o assert sobre ele no --selftest prova o comportamento do pipeline. Ver cabeçalho.
 */
export const VEREDITO_POR_TOPOLOGIA = {
  IGUAL: 'OK',
  ATRASADO: 'MEDIR_LAG',
  ADIANTE: 'WARN',
  DIVERGENTE: 'ALARM',
  DESCONHECIDO: 'WARN',
};

if (process.argv.includes('--selftest')) {
  const H = 3600;
  // Fixtures do incidente REAL 2026-08-04T11:38:21Z (served ec2d7f852 · head 121db910a).
  const agora = 1785843501;         // 11:38:21Z
  const maisAntigoDevendo = 1785843313; // 18bb68834 @ 11:35:13Z — 3 min antes
  const servedCommit = 1785791604;  // ec2d7f852 — 14.43h antes do head
  const topo = (o) => classificarTopologia({ servedIgualRef: false, ...o });
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

    // ── topologia — falso positivo 2026-08-07 ("servidor à frente" lido como rewrite) ──
    // BITE-TEST do caso REAL (run 31205724975): served ca7dddb38 era DESCENDENTE do headSha
    // pinado f8794b9d3 (+1 commit). Antes: ALARM "história reescrita". Agora: ADIANTE → WARN.
    ['FP real: servidor à frente classifica ADIANTE (não "não-ancestral")',
      topo({ conhecido: true, servedEhAncestralDaRef: false, refEhAncestralDoServed: true }) === 'ADIANTE'],
    ['e ADIANTE não alarma — era este o veredito dos 6 FP medidos',
      VEREDITO_POR_TOPOLOGIA.ADIANTE !== 'ALARM'],

    // controles negativos — o alarme REAL desta forma continua vivo:
    ['divergente (rewrite / host em branch alheia) → DIVERGENTE',
      topo({ conhecido: true, servedEhAncestralDaRef: false, refEhAncestralDoServed: false }) === 'DIVERGENTE'],
    ['e DIVERGENTE ALARMA (o conserto não virou carimbo verde)',
      VEREDITO_POR_TOPOLOGIA.DIVERGENTE === 'ALARM'],
    ['servidor atrás → ATRASADO, que segue pro lag (drift real segue medido)',
      topo({ conhecido: true, servedEhAncestralDaRef: true, refEhAncestralDoServed: false }) === 'ATRASADO'
      && VEREDITO_POR_TOPOLOGIA.ATRASADO === 'MEDIR_LAG'],
    ['served == ref → IGUAL/OK',
      topo({ conhecido: true, servedIgualRef: true, servedEhAncestralDaRef: true, refEhAncestralDoServed: true }) === 'IGUAL'],
    ['objeto desconhecido → DESCONHECIDO/WARN (não afirmo o que não medi)',
      topo({ conhecido: false, servedEhAncestralDaRef: false, refEhAncestralDoServed: false }) === 'DESCONHECIDO'
      && VEREDITO_POR_TOPOLOGIA.DESCONHECIDO === 'WARN'],
    ['todo estado tem veredito (nenhum despacha em undefined)',
      ['IGUAL', 'ATRASADO', 'ADIANTE', 'DIVERGENTE', 'DESCONHECIDO'].every((k) => VEREDITO_POR_TOPOLOGIA[k])],
  ];
  let falhas = 0;
  for (const [nome, ok] of casos) { if (!ok) falhas++; console.log(`  ${ok ? '✓' : '✗'} ${nome}`); }
  console.log(`\n${casos.length - falhas}/${casos.length} passaram`);
  process.exit(falhas ? 1 : 0);
}

const { ref, vivo: refVivo } = resolveRefMain();
const headShort = ref ? ref.slice(0, 9) : '?';
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

// Topologia servido↔régua em 5 estados, com veredito despachado pelo map (ver cabeçalho).
const conhecido = objetoConhecido(served.commit);
const topologia = classificarTopologia({
  conhecido,
  servedIgualRef: served.commit === ref,
  servedEhAncestralDaRef: conhecido && ehAncestral(served.commit, ref),
  refEhAncestralDoServed: conhecido && ehAncestral(ref, served.commit),
});
const base = { served: servedShort, main_head: headShort, ref_vivo: refVivo, topologia };

switch (VEREDITO_POR_TOPOLOGIA[topologia]) {
  case 'OK':
    emit('OK', { ...base, lag: '0 (em main)' });
    process.exit(0);
  case 'WARN':
    emit('WARN', {
      reason: topologia === 'ADIANTE'
        // NÃO é drift: o servido DESCENDE da régua, logo é mais fresco que ela. Se o host
        // estiver preso numa branch alheia, main avança e isto vira DIVERGENTE → ALARM.
        ? `servidor À FRENTE da régua em ${git(`rev-list --count ${ref}..${served.commit}`) || '?'} commit(s) — main andou depois do fetch, ou host em branch. Não afirmo drift; se persistir vira DIVERGENTE (alarme).`
        : 'commit servido não existe neste clone — NÃO consegui medir, então não afirmo veredito',
      ...base,
    });
    process.exit(0);
  case 'ALARM':
    emit('ALARM', { reason: 'commit servido DIVERGE de main (nem ancestral nem descendente) — história reescrita ou host em branch alheia', ...base });
    process.exit(1);
}

// ATRASADO → mede HÁ QUANTO TEMPO o servidor está devendo: idade do commit mais
// ANTIGO ainda não servido. Período tranquilo não conta (não há commit devendo);
// rajada após silêncio conta minutos, não o silêncio. Ver cabeçalho.
const behind = git(`rev-list --count ${served.commit}..${ref}`) || '?';
const maisAntigoDevendo = (git(`rev-list --reverse ${served.commit}..${ref}`) || '').split('\n')[0] || null;
const lagH = calcularLagHoras(maisAntigoDevendo ? commitEpoch(maisAntigoDevendo) : null, Math.floor(Date.now() / 1000));
const lagStr = lagH == null ? '?' : `${lagH.toFixed(2)}h`;

if (lagH != null && lagH > MAX_LAG_HOURS) {
  emit('ALARM', { reason: `servidor DEVENDO há ${lagStr} (> ${MAX_LAG_HOURS}h) — self-update.sh parou de curar?`, ...base, behind_commits: behind, mais_antigo_devendo: (maisAntigoDevendo || '?').slice(0, 9) });
  process.exit(1);
}
emit('OK', { ...base, behind_commits: behind, lag: lagStr });
process.exit(0);
