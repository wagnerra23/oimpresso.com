#!/usr/bin/env node
// @ts-check
/**
 * agent-pr-outcomes.mjs — EVALS DE OUTCOME dos PRs do agente (DORA-style).
 *
 * Objetivo (dossiê "grade das réguas" 2026-07-09, card #0 "Evals de outcome",
 * Roubo #4): substituir "zero evals de outcome" por NÚMERO REAL. Enquanto os 24
 * required checks dizem só "o processo passou?", nada dizia se o AGENTE entrega
 * MELHOR este mês que no passado. A régua publicada (OpenAI Codex / Cognition) é
 * DORA-de-agente: change-failure-rate, ratio aceito/rejeitado, time-to-merge dos
 * PRs — e itera as instruções COM DADOS.
 *
 * ── DECONFLITO com outcome-metrics.mjs (NÃO confundir — eixos diferentes) ────────
 *   • outcome-metrics.mjs (Onda O1) = retrabalho do LOOP DE DESIGN (uma TELA foi
 *     re-mexida depois de entregue?), fonte SYNC_LOG.md + git das Pages/*.tsx.
 *   • ESTE (agent-pr-outcomes) = DORA dos PRs do AGENTE (o PR do bot deu certo?),
 *     fonte `gh pr list` (GitHub). Mede aceitação/falha/velocidade do transporte
 *     PR→merge, não fidelidade de tela. São métricas próximas de nome, distantes de
 *     escopo — 1 fato = 1 lugar (mesma disciplina do deconflito dos 3 mapas
 *     design↔código, RUNBOOK-aplicar-prototipo §Deconflito).
 *
 * ── MÉTRICAS (todas declaram proxy no --json.confianca + --json.gaps) ────────────
 *   - time_to_merge   : mediana + p90 de horas createdAt→mergedAt (PRs do agente
 *                        MERGEADOS na janela). Velocidade do loop.
 *   - accept_rate     : merged ÷ (merged + rejeitado), onde rejeitado = fechado-sem-
 *                        merge. Ratio aceito/rejeitado que a régua publica.
 *   - change_failure  : % de PRs mergeados seguidos, em ≤48h, por um PR de
 *                        fix/hotfix/revert que REFERENCIA o nº (#N) do PR original.
 *                        É o CFR — o proxy mais honesto computável só da lista de PRs.
 *
 * ── HONESTIDADE (o que o número NÃO vê — impresso + em --json.gaps) ──────────────
 *   G1 CFR é PISO: um hotfix que não cita #N no título/corpo, ou não é tipado
 *      fix/hotfix/revert, ESCAPA (subconta). Nunca superconta (só conta referência
 *      explícita dentro de 48h).
 *   G2 "rejeitado" = fechado-sem-merge, mas PR fechado por superseded/duplicado NÃO
 *      é rejeição de qualidade → accept_rate SUBconta aceitação. Reportado, não fundido.
 *   G3 time_to_merge inclui a espera por review humano (R10) — não é só "qualidade
 *      do agente"; é o loop ponta-a-ponta. Comparar com o próprio histórico, não absoluto.
 *   G4 janela por mergedAt/closedAt; PR ainda ABERTO não entra (nem no denominador).
 *   G5 marcador de agente = título contém MARKER (default "[CC]") OU autor bot. Pode
 *      perder PR do agente sem marcador / incluir PR humano que cita "[CC]".
 *
 * Funções puras exportadas → testáveis SEM gh/rede (agent-pr-outcomes.test.mjs).
 * A camada de rede (`gh pr list`) só roda quando invocado direto e sem --fixture.
 *
 * Modos:
 *   (default)   texto humano no stdout
 *   --json      objeto pro Daily/Weekly Brief: { ok, generated, window, agent, metrics, gaps }
 *   --brief     seção markdown pronta pro brief semanal (o "próximo degrau" do card)
 *   --fixture <f.json>  usa um array de PRs de arquivo (hermético — não chama gh)
 *   --days <N>  janela (default 30); ou --since <ISO>
 *   --marker <s>  marcador de PR-de-agente (default "[CC]")
 *   --repo <o/r>  override do repo (default: gh infere do cwd)
 *   --selftest  fixtures-armadilha (morde CFR fora de 48h / sem #N / tipo errado)
 *
 * ADVISORY (ADR 0271/0275): só MEDE, nunca bloqueia. Não é gate de PR — é insumo do
 * brief semanal (workflow schedule + dispatch, agent-pr-outcomes.yml).
 *
 * Refs: dossiê grade-das-réguas card #0 · ADR 0226 (Daily Brief) · 0271/0275 (advisory)
 *       · 0106 (velocidade IA-pair) · outcome-metrics.mjs (eixo irmão, design-loop).
 */
import { execFileSync, spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { pathToFileURL, fileURLToPath } from 'node:url';

// ── constantes (exportadas pra teste) ─────────────────────────────────────────
export const DEFAULT_MARKER = '[CC]';
export const CFR_WINDOW_HOURS = 48;
/** títulos que contam como hotfix pro CFR (o PR de conserto do merge anterior). */
export const HOTFIX_TITLE_RE = /^\s*(fix|hotfix|revert)\b/i;
/** autores tratados como agente mesmo sem marcador no título. */
export const AGENT_LOGINS = new Set(['app/github-actions', 'github-actions[bot]', 'claude', 'claude[bot]']);

const MS_PER_HOUR = 3600 * 1000;

// ── helpers puros ─────────────────────────────────────────────────────────────

/** É PR do agente? título tem o marcador OU autor está na lista de bots. */
export function isAgentPR(pr, marker = DEFAULT_MARKER) {
  const title = String(pr.title || '');
  if (marker && title.includes(marker)) return true;
  const login = pr && pr.author && pr.author.login ? String(pr.author.login) : '';
  return AGENT_LOGINS.has(login);
}

/** estado terminal normalizado: 'merged' | 'rejected' (fechado sem merge) | 'open'. */
export function terminalState(pr) {
  if (pr.mergedAt || pr.state === 'MERGED' || pr.merged === true) return 'merged';
  if (pr.closedAt || pr.state === 'CLOSED') return 'rejected';
  return 'open';
}

/** referencia o nº do PR `n` no título ou corpo? (#123 com fronteira, não #1234). */
export function referencesPR(text, n) {
  if (!text || !n) return false;
  return new RegExp('#' + n + '(?!\\d)').test(String(text));
}

/** mediana de um array de números (>=1 elemento). null se vazio. */
export function median(nums) {
  if (!nums.length) return null;
  const s = [...nums].sort((a, b) => a - b);
  const mid = Math.floor(s.length / 2);
  return s.length % 2 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
}

/** percentil p (0..100) por interpolação nearest-rank. null se vazio. */
export function percentile(nums, p) {
  if (!nums.length) return null;
  const s = [...nums].sort((a, b) => a - b);
  const rank = Math.ceil((p / 100) * s.length) - 1;
  return s[Math.min(Math.max(rank, 0), s.length - 1)];
}

/** arredonda pra 1 casa (ou null). */
const round1 = (v) => (v == null ? null : Math.round(v * 10) / 10);

// ── métricas ──────────────────────────────────────────────────────────────────

/** time-to-merge (horas) dos PRs mergeados: { count, median_h, p90_h }. */
export function timeToMerge(mergedPRs) {
  const horas = [];
  for (const pr of mergedPRs) {
    const c = Date.parse(pr.createdAt);
    const m = Date.parse(pr.mergedAt);
    if (Number.isFinite(c) && Number.isFinite(m) && m >= c) horas.push((m - c) / MS_PER_HOUR);
  }
  return { count: horas.length, median_h: round1(median(horas)), p90_h: round1(percentile(horas, 90)) };
}

/** aceito/rejeitado: { merged, rejected, accept_rate }. accept_rate = merged/(merged+rejected). */
export function acceptReject(terminalPRs) {
  let merged = 0, rejected = 0;
  for (const pr of terminalPRs) {
    const st = terminalState(pr);
    if (st === 'merged') merged++;
    else if (st === 'rejected') rejected++;
  }
  const denom = merged + rejected;
  return { merged, rejected, accept_rate: denom ? round1((merged / denom) * 100) : null };
}

/**
 * change-failure-rate: pra cada PR mergeado X, existe um PR mergeado Y com
 * mergedAt em (X.mergedAt, X.mergedAt + 48h], título fix/hotfix/revert, que
 * referencia #X? Conta X como falha. Retorna { merged_count, failures, cfr, hits[] }.
 * O universo de "hotfixes candidatos" = TODOS os PRs mergeados (agente ou não —
 * o conserto pode vir de humano; o que importa é o PR original ser do agente).
 */
export function changeFailure(mergedAgentPRs, allMergedPRs) {
  const candidatos = allMergedPRs
    .filter((p) => HOTFIX_TITLE_RE.test(String(p.title || '')) && Number.isFinite(Date.parse(p.mergedAt)))
    .map((p) => ({ n: p.number, t: Date.parse(p.mergedAt), title: p.title, body: p.body }));
  const hits = [];
  for (const x of mergedAgentPRs) {
    const tx = Date.parse(x.mergedAt);
    if (!Number.isFinite(tx)) continue;
    const culpado = candidatos.find((y) =>
      y.n !== x.number &&
      y.t > tx && y.t <= tx + CFR_WINDOW_HOURS * MS_PER_HOUR &&
      (referencesPR(y.title, x.number) || referencesPR(y.body, x.number))
    );
    if (culpado) hits.push({ pr: x.number, hotfix: culpado.n, horas: round1((culpado.t - tx) / MS_PER_HOUR) });
  }
  const merged_count = mergedAgentPRs.length;
  return { merged_count, failures: hits.length, cfr: merged_count ? round1((hits.length / merged_count) * 100) : null, hits };
}

/**
 * Conjunto dos nºs de PR que NÃO sobreviveram (change-failure): mergeado E seguido, em
 * ≤48h, por um PR fix/hotfix/revert citando #N. É a DEFINIÇÃO canônica de "não sobreviveu"
 * — mora AQUI, no oráculo de outcome (1 fato = 1 lugar), pra quem cruza custo com revert
 * (agent-cost-per-pr.mjs → custo-por-PR-sobrevivente) consumir sem redefinir. Aceita o
 * retorno de changeFailure() OU o array `.hits`. É PISO pela mesma razão do CFR (G1): só
 * enxerga o revert explícito (#N + tipo fix/hotfix/revert ≤48h) — nunca superconta.
 * @param {{hits:Array<{pr:number}>}|Array<{pr:number}>} cfr
 * @returns {Set<number>}
 */
export function failedPRNumbers(cfr) {
  const hits = Array.isArray(cfr) ? cfr : (cfr && cfr.hits) || [];
  return new Set(hits.map((h) => h.pr));
}

const GAPS = [
  'G1 CFR é PISO: hotfix que não cita #N ou não é fix/hotfix/revert escapa (subconta, nunca superconta).',
  'G2 "rejeitado" = fechado-sem-merge; superseded/duplicado infla rejeição → accept_rate é piso de aceitação.',
  'G3 time_to_merge inclui espera por review humano (R10) — comparar com o próprio histórico, não absoluto.',
  'G4 janela por mergedAt/closedAt; PR ainda aberto não entra.',
  'G5 marcador de agente = título tem MARKER ou autor bot; pode perder/incluir por texto.',
];

/**
 * monta o relatório a partir de uma LISTA de PRs já carregada (pura — sem I/O).
 * @param {{prs:any[], nowIso?:string, sinceIso?:string, days?:number, marker?:string}} o
 */
export function buildReport({ prs, nowIso, sinceIso, days = 30, marker = DEFAULT_MARKER }) {
  const now = nowIso ? Date.parse(nowIso) : Date.now();
  const since = sinceIso ? Date.parse(sinceIso) : now - days * 24 * MS_PER_HOUR;
  const inWindow = (iso) => { const t = Date.parse(iso); return Number.isFinite(t) && t >= since; };

  const agentPRs = prs.filter((p) => isAgentPR(p, marker));
  // terminal na janela: mergeados por mergedAt, rejeitados por closedAt
  const agentTerminal = agentPRs.filter((p) => {
    const st = terminalState(p);
    if (st === 'merged') return inWindow(p.mergedAt);
    if (st === 'rejected') return inWindow(p.closedAt);
    return false;
  });
  const agentMerged = agentTerminal.filter((p) => terminalState(p) === 'merged');
  const allMerged = prs.filter((p) => terminalState(p) === 'merged'); // universo de hotfixes (agente OU humano)

  const metrics = {
    time_to_merge: timeToMerge(agentMerged),
    accept: acceptReject(agentTerminal),
    change_failure: changeFailure(agentMerged, allMerged),
  };

  return {
    ok: true,
    generated: nowIso || new Date(now).toISOString().slice(0, 10),
    window: { since: new Date(since).toISOString().slice(0, 10), days },
    agent: { marker, total_terminais: agentTerminal.length, mergeados: agentMerged.length },
    metrics,
    confianca: 'proxy (DORA de PR via gh; ver gaps)',
    gaps: GAPS,
  };
}

// ── camada de rede (só quando invocado direto sem --fixture) ─────────────────────

/** puxa os PRs via `gh pr list` (array-form, sem shell/`--jq` — lição Windows/proibicoes). */
export function fetchPRsViaGh({ repo, limit = 300 } = {}) {
  const args = ['pr', 'list', '--state', 'all', '--limit', String(limit),
    '--json', 'number,title,body,author,createdAt,mergedAt,closedAt,state'];
  if (repo) { args.push('--repo', repo); }
  const out = execFileSync('gh', args, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  const arr = JSON.parse(out);
  if (!Array.isArray(arr)) throw new Error('gh pr list não devolveu array');
  return arr;
}

// ── render ──────────────────────────────────────────────────────────────────────

const pct = (v) => (v == null ? 'n/d' : `${v}%`);
const hrs = (v) => (v == null ? 'n/d' : `${v}h`);

export function renderHuman(r) {
  const m = r.metrics;
  const L = [];
  L.push('═══════════════════════════════════════════════════════════════');
  L.push(' EVALS DE OUTCOME — PRs do agente (DORA) · ' + r.generated);
  L.push(` janela: desde ${r.window.since} (${r.window.days}d) · marcador ${r.agent.marker}`);
  L.push('═══════════════════════════════════════════════════════════════');
  L.push('');
  L.push(`  PRs do agente (terminais) : ${r.agent.total_terminais}  (${r.agent.mergeados} mergeados)`);
  L.push('');
  L.push(`  TIME-TO-MERGE  mediana ${hrs(m.time_to_merge.median_h)} · p90 ${hrs(m.time_to_merge.p90_h)}  (n=${m.time_to_merge.count})`);
  L.push(`  ACCEPT-RATE ...: ${pct(m.accept.accept_rate)}  (${m.accept.merged} merged ÷ ${m.accept.merged + m.accept.rejected} terminais; ${m.accept.rejected} rejeitados)`);
  L.push(`  CHANGE-FAILURE : ${pct(m.change_failure.cfr)}  (${m.change_failure.failures}/${m.change_failure.merged_count} mergeados com hotfix ≤${CFR_WINDOW_HOURS}h)`);
  if (m.change_failure.hits.length) {
    for (const h of m.change_failure.hits) L.push(`      ↳ #${h.pr} consertado por #${h.hotfix} em ${h.horas}h`);
  }
  L.push('');
  L.push('▸ HONESTIDADE (o número é PROXY — o que ele não vê)');
  for (const g of r.gaps) L.push(`  • ${g}`);
  L.push('═══════════════════════════════════════════════════════════════');
  return L.join('\n');
}

/** seção markdown pro brief semanal (o "próximo degrau" do card #0). */
export function renderBriefMd(r) {
  const m = r.metrics;
  const L = [];
  L.push('### Evals de outcome — PRs do agente (DORA · advisory)');
  L.push('');
  L.push(`_Janela ${r.window.days}d desde ${r.window.since} · ${r.agent.mergeados} PRs mergeados · marcador \`${r.agent.marker}\`._`);
  L.push('');
  L.push('| Métrica | Valor | Régua (Codex/Cognition) |');
  L.push('|---|---|---|');
  L.push(`| Change-failure-rate | **${pct(m.change_failure.cfr)}** (${m.change_failure.failures}/${m.change_failure.merged_count}) | menor = melhor; iterar instruções com o dado |`);
  L.push(`| Accept-rate | **${pct(m.accept.accept_rate)}** (${m.accept.merged}/${m.accept.merged + m.accept.rejected}) | ratio aceito/rejeitado publicado |`);
  L.push(`| Time-to-merge (mediana) | **${hrs(m.time_to_merge.median_h)}** · p90 ${hrs(m.time_to_merge.p90_h)} | comparar vs cycle anterior |`);
  L.push('');
  L.push('> Proxy (ver gaps no `--json`). Plotar por cycle: a tendência é o sinal, não o valor absoluto.');
  return L.join('\n');
}

// ── entry-point ──────────────────────────────────────────────────────────────────
function argVal(argv, flag, def) { const i = argv.indexOf(flag); return i >= 0 && argv[i + 1] ? argv[i + 1] : def; }

if (import.meta.url === pathToFileURL(process.argv[1] || '').href) {
  const argv = process.argv.slice(2);
  if (argv.includes('--selftest')) {
    // spawnSync (não import) pra evitar import circular: o test importa ESTE módulo, que
    // ainda está em execução no entry-point → módulo parcialmente avaliado. Spawn isola,
    // e propaga o exit do test (spawnSync não lança em status≠0, ao contrário de execFileSync).
    // fileURLToPath (não `.pathname`): no Windows `URL.pathname` vira `/D:/...` e o node
    // resolvia pra `D:\D:\...` (MODULE_NOT_FOUND) — o --selftest morria só nesta máquina, o
    // CI (Linux) nunca via. Mesmo padrão cross-plataforma do agent-cost-per-pr.mjs.
    const test = fileURLToPath(new URL('./agent-pr-outcomes.test.mjs', import.meta.url));
    const r = spawnSync(process.execPath, [test], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }

  const marker = argVal(argv, '--marker', DEFAULT_MARKER);
  const days = Number(argVal(argv, '--days', '30')) || 30;
  const sinceIso = argVal(argv, '--since', null);
  const fixture = argVal(argv, '--fixture', null);
  const repo = argVal(argv, '--repo', null);

  let prs;
  try {
    prs = fixture ? JSON.parse(readFileSync(fixture, 'utf8')) : fetchPRsViaGh({ repo });
  } catch (e) {
    console.error(`[agent-pr-outcomes] falha ao carregar PRs: ${e.message}`);
    console.error(fixture ? '  (fixture inválido)' : '  (gh ausente/sem auth? use --fixture <arquivo> pra rodar offline)');
    process.exit(1);
  }
  if (!Array.isArray(prs)) { console.error('[agent-pr-outcomes] fonte de PRs não é array'); process.exit(1); }

  const r = buildReport({ prs, sinceIso, days, marker });
  if (argv.includes('--json')) process.stdout.write(JSON.stringify(r, null, 2) + '\n');
  else if (argv.includes('--brief')) process.stdout.write(renderBriefMd(r) + '\n');
  else process.stdout.write(renderHuman(r) + '\n');
  process.exit(0);
}
