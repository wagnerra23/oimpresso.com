#!/usr/bin/env node
// @ts-check
/**
 * fluxo-sistema.mjs — MÉTRICAS DE FLUXO DO SISTEMA INTEIRO (DORA + Flow), medidas.
 *
 * Por que existe (2026-09-07, [W]: "quais fluxos eu deveria ter? compare com as grandes"):
 * os 45 required checks dizem "o processo passou?"; nada dizia, num lugar só, se o
 * sistema ENTREGA e RECUPERA como as réguas de mercado medem. As quatro chaves DORA
 * (State of DevOps, Google Cloud) + o eixo de FILA/WIP do Flow Framework (Kersten 2018)
 * + adoção do que foi entregue (sinal 0105). Cada métrica declara `confianca` e o
 * que NÃO conseguiu medir sai `not_yet_measured` com a FONTE futura nomeada — mentir
 * "0" seria pior (mesma doutrina do sdd-scorecard.mjs).
 *
 * ── DECONFLITO (1 fato = 1 lugar — NÃO duplica os donos vizinhos) ─────────────────
 *   • agent-pr-outcomes.mjs = DORA dos PRs DO AGENTE (população: PR com marcador de
 *     agente). Aqui a população é TODOS os PRs + os RUNS DE DEPLOY + a fila de US.
 *   • outcome-metrics.mjs   = retrabalho do LOOP DE DESIGN (tela re-mexida). Aqui o
 *     retrabalho é de CÓDIGO (commits `fix`/`hotfix`/`revert` sobre o total).
 *   • charter-live-signal   = sinal "servido" POR TELA. Aqui é a fração agregada.
 *
 * ── MÉTRICAS ─────────────────────────────────────────────────────────────────────
 *   deploy_frequency        runs de deploy.yml na janela (gh run list) — elite: sob demanda
 *   lead_time_pr            createdAt→mergedAt de TODOS os PRs mergeados (gh pr list)
 *   cfr_pipeline            runs de deploy com conclusion=failure / total
 *   recovery_pipeline       min do run falho até o próximo run bom (mediana + p90)
 *   rework_rate             commits `fix(`/`fix:`/hotfix/revert sobre o total (git log)
 *   pr_size_compliance      PRs > 300 linhas (commit-discipline) / mergeados
 *   fila_us                 US não atribuída / sem dono / mais antiga (BRIEF via MCP — proxy)
 *   wip_por_pessoa          itens EM VOO por pessoa vs WIP máximo do TEAM.md (BRIEF — proxy)
 *   adocao_telas            telas com hits>0 no governance/route-hits.json / telas Inertia
 *   cfr_cliente · recovery_cliente · flow_time_us  → not_yet_measured (fonte declarada)
 *
 * ── HONESTIDADE DE MEDIÇÃO (§5 2026-07-29 · 2026-08-13) ────────────────────────
 *   "não consegui medir" NUNCA vira número: sem `gh` → status 'nao_medido'; sem token
 *   MCP → fila/wip 'nao_medido'; route-hits mais velho que a janela → 'stale' declarado.
 *   `gh` no Windows devolve BOM — toda leitura passa por stripBom(). Nada de /tmp
 *   (Bash e Node não concordam no Windows — §5 2026-08-21).
 *
 * Uso (raiz do repo):
 *   node scripts/governance/fluxo-sistema.mjs               # relatório humano
 *   node scripts/governance/fluxo-sistema.mjs --json        # estruturado
 *   node scripts/governance/fluxo-sistema.mjs --brief       # markdown (job summary / brief)
 *   node scripts/governance/fluxo-sistema.mjs --days 14     # janela (default 30)
 *   node scripts/governance/fluxo-sistema.mjs --no-mcp      # pula brief/MCP (CI sem token)
 *   node scripts/governance/fluxo-sistema.mjs --selftest
 */

import { spawnSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
export const REPO = join(HERE, '..', '..');
const ARGS = process.argv.slice(2);
const argVal = (f, d) => { const i = ARGS.indexOf(f); return i >= 0 && ARGS[i + 1] ? ARGS[i + 1] : d; };

export const stripBom = (s) => String(s).replace(/^﻿/, '');

/** Régua DORA (State of DevOps 2024, Google Cloud) — só as faixas que este script classifica. */
export const REGUA_DORA = {
  deploy_frequency: { elite: 'sob demanda (várias/dia)', high: 'diária a semanal', medium: 'semanal a mensal', low: 'mensal ou menos' },
  lead_time: { elite: '< 1 dia', high: '1 dia a 1 semana', medium: '1 semana a 1 mês', low: '> 1 mês' },
  change_failure: { elite: '≈5%', high: '≈10-20%', low: '≈40%' },
  recovery: { elite: '< 1 hora', high: '< 1 dia', medium: '1 dia a 1 semana', low: '> 1 semana' },
};

// ── helpers puros ────────────────────────────────────────────────────────────────
export const pct = (n, d) => (d ? Math.round((1000 * n) / d) / 10 : null);
export function quantil(arr, q) {
  if (!arr.length) return null;
  const a = [...arr].sort((x, y) => x - y);
  return a[Math.min(a.length - 1, Math.floor(a.length * q))];
}
const r1 = (x) => (x == null ? null : Math.round(x * 10) / 10);

/** deploy runs → frequência, CFR do pipeline, recuperação até o próximo run bom. */
export function medirDeploys(runs, dias) {
  const j = [...runs].sort((a, b) => a.createdAt.localeCompare(b.createdAt));
  const ok = j.filter((x) => x.conclusion === 'success').length;
  const fail = j.filter((x) => x.conclusion === 'failure').length;
  const diasComDeploy = new Set(j.map((x) => x.createdAt.slice(0, 10))).size;
  const rec = [];
  for (let i = 0; i < j.length; i++) {
    if (j[i].conclusion !== 'failure') continue;
    const nx = j.slice(i + 1).find((x) => x.conclusion === 'success');
    if (nx) rec.push((Date.parse(nx.createdAt) - Date.parse(j[i].createdAt)) / 60000);
  }
  const porDia = dias ? j.length / dias : null;
  const nivelFreq = porDia == null ? null : porDia >= 1 ? 'elite' : porDia >= 1 / 7 ? 'high' : porDia >= 1 / 30 ? 'medium' : 'low';
  const cfr = pct(fail, j.length);
  const recP50 = quantil(rec, 0.5);
  return {
    deploy_frequency: { status: j.length ? 'measured' : 'nao_medido', total: j.length, ok, fail, outros: j.length - ok - fail, dias_com_deploy: diasComDeploy, por_dia: r1(porDia), nivel_dora: nivelFreq, unit: 'runs de deploy.yml na janela', regua: REGUA_DORA.deploy_frequency },
    cfr_pipeline: { status: j.length ? 'measured' : 'nao_medido', value: cfr, unit: '% de runs de deploy com failure', nivel_dora: cfr == null ? null : cfr <= 5 ? 'elite' : cfr <= 20 ? 'high' : 'low', confianca: 'PIPELINE, não cliente: mede o workflow quebrar, não o usuário ficar sem tela', regua: REGUA_DORA.change_failure },
    recovery_pipeline: { status: rec.length ? 'measured' : 'nao_medido', p50_min: r1(recP50), p90_min: r1(quantil(rec, 0.9)), recuperacoes: rec.length, nivel_dora: recP50 == null ? null : recP50 < 60 ? 'elite' : recP50 < 1440 ? 'high' : 'medium', unit: 'minutos do run falho ao próximo run bom', confianca: 'PIPELINE, não cliente', regua: REGUA_DORA.recovery },
  };
}

/** PRs mergeados → lead time (createdAt→mergedAt) + tamanho vs teto 300 linhas. */
export function medirPrs(prs, { limite = null } = {}) {
  const h = prs.map((p) => (Date.parse(p.mergedAt) - Date.parse(p.createdAt)) / 3600000);
  const sz = prs.map((p) => (p.additions || 0) + (p.deletions || 0));
  const p50 = quantil(h, 0.5);
  const acima = sz.filter((x) => x > 300).length;
  return {
    lead_time_pr: { status: prs.length ? 'measured' : 'nao_medido', prs: prs.length, amostra_no_teto: limite != null && prs.length >= limite, p50_h: r1(p50), p75_h: r1(quantil(h, 0.75)), p90_h: r1(quantil(h, 0.9)), max_h: r1(quantil(h, 1)), nivel_dora: p50 == null ? null : p50 < 24 ? 'elite' : p50 < 168 ? 'high' : p50 < 720 ? 'medium' : 'low', unit: 'horas PR aberto → merge (merge → prod é o deploy automático)', confianca: 'mede o TRANSPORTE do código, não do pedido ao cliente (ver flow_time_us)', regua: REGUA_DORA.lead_time },
    pr_size_compliance: { status: prs.length ? 'measured' : 'nao_medido', p50_linhas: quantil(sz, 0.5), acima_300: acima, pct_acima_300: pct(acima, prs.length), unit: 'PRs acima do teto de 300 linhas do commit-discipline' },
  };
}

/** commits da janela → taxa de retrabalho (DORA 2024 "rework rate", proxy por convenção de commit). */
export function medirRetrabalho(subjects) {
  const total = subjects.length;
  const fix = subjects.filter((s) => /^(fix|hotfix)(\(|:|!)/i.test(s)).length;
  const hot = subjects.filter((s) => /hotfix|revert/i.test(s)).length;
  return { rework_rate: { status: total ? 'measured' : 'nao_medido', commits: total, fix: fix, hotfix_ou_revert: hot, pct_fix: pct(fix, total), unit: '% de commits fix/hotfix sobre o total', confianca: 'proxy por convenção conventional-commit; não distingue bug de prod de ajuste de PR' } };
}

/** brief (markdown do MCP) → fila de US + itens em voo por pessoa. Formato: system prompt do BriefGeneratorService. */
export function parseBrief(md) {
  const t = stripBom(md);
  const emVoo = [];
  const sec = t.split(/^## EM VOO AGORA\s*$/m)[1];
  if (sec) {
    for (const l of sec.split(/^## /m)[0].split(/\r?\n/)) {
      const m = /^\s*\d+\.\s+(\S+)\s+@\s+(.+?)\s+—\s+(.+?),\s*([\d.,]+\s*[dhm])\s*$/.exec(l);
      if (m) emVoo.push({ owner: m[1], alvo: m[2], intent: m[3], idade: m[4] });
    }
  }
  const flag = /US não atribuída:\s*(\d+)\s*\((\d+)\s*sem dono\)[^\n]*?\((\d+)d\)/.exec(t);
  return { emVoo, fila: flag ? { us_nao_atribuida: +flag[1], sem_dono: +flag[2], mais_antiga_dias: +flag[3] } : null };
}

/** TEAM.md → WIP máximo por sigla (tabela §2). Derivado, não copiado. */
export function parseWipTeam(teamMd) {
  const out = {};
  for (const l of stripBom(teamMd).split(/\r?\n/)) {
    const m = /^\|\s*([^|]+?)\s*\[([A-Z])\]\s*\|\s*(\d+)\s*\|/.exec(l);
    if (m) out[m[2]] = { nome: m[1].trim(), wip_max: +m[3] };
  }
  return out;
}

const SIGLA = { wagner: 'W', felipe: 'F', maiara: 'M', luiz: 'L', eliana: 'E' };
export function medirWip(emVoo, wipTeam) {
  const porOwner = {};
  for (const i of emVoo) porOwner[i.owner] = (porOwner[i.owner] || 0) + 1;
  const violacoes = [];
  for (const [owner, n] of Object.entries(porOwner)) {
    const lim = wipTeam[SIGLA[owner.toLowerCase()] || owner.toUpperCase()];
    if (lim && n > lim.wip_max) violacoes.push({ owner, em_voo: n, wip_max: lim.wip_max });
  }
  return { por_owner: porOwner, violacoes };
}

/** route-hits.json + total de telas → fração de telas servidas na janela do ledger. */
export function medirAdocao(routeHits, totalTelas, agoraMs = Date.now()) {
  const pages = routeHits && routeHits.pages ? routeHits.pages : {};
  const comHits = Object.values(pages).filter((p) => p && p.hits > 0).length;
  const gerado = routeHits && routeHits._meta && (routeHits._meta.gerado_em || routeHits._meta.generated_at);
  const idadeD = gerado ? Math.floor((agoraMs - Date.parse(gerado)) / 86400000) : null;
  const janela = routeHits && routeHits.janela_dias;
  const stale = idadeD != null && janela != null && idadeD > janela;
  return { adocao_telas: { status: totalTelas ? (stale ? 'stale' : 'measured') : 'nao_medido', telas_com_hits: comHits, telas_no_ledger: Object.keys(pages).length, telas_inertia: totalTelas, pct_servidas: pct(comHits, totalTelas), janela_dias: janela ?? null, ledger_gerado_em: gerado || null, ledger_idade_dias: idadeD, unit: '% das telas Inertia com ≥1 hit real em prod na janela do ledger', confianca: 'route-hits.json é commit manual (route-hits:export --write no host de prod); idade declarada' } };
}

export const NAO_MEDIDAS = {
  cfr_cliente: { status: 'not_yet_measured', value: null, fonte_futura: 'registro de incidente com deploy SHA (1 linha por evento: início, fim, causa) — não existe; skill incident-done-checklist é prosa', regua: REGUA_DORA.change_failure },
  recovery_cliente: { status: 'not_yet_measured', value: null, fonte_futura: 'mesmo registro de incidente (fim − início)', regua: REGUA_DORA.recovery },
  flow_time_us: { status: 'not_yet_measured', value: null, fonte_futura: 'tool MCP tasks-list não expõe created_at/done_at (Modules/Jana/Mcp/Tools/TasksListTool.php devolve texto); extensão da tool ou tool nova = decisão [W]/[F]', regua: 'Flow Framework (Kersten 2018): tempo de fluxo do pedido → produção' },
};

// ── coletores (spawn; injetáveis) ────────────────────────────────────────────────
function gh(args) {
  const r = spawnSync('gh', args, { encoding: 'utf8', cwd: REPO, windowsHide: true, maxBuffer: 64 * 1024 * 1024 });
  if (r.error || r.status !== 0) return { ok: false, motivo: r.error ? `gh ausente (${r.error.code || r.error.message})` : `gh exit ${r.status}` };
  try { return { ok: true, data: JSON.parse(stripBom(r.stdout)) }; } catch { return { ok: false, motivo: 'gh devolveu JSON ilegível' }; }
}
function git(args) {
  const r = spawnSync('git', args, { encoding: 'utf8', cwd: REPO, windowsHide: true, maxBuffer: 64 * 1024 * 1024 });
  return r.status === 0 ? { ok: true, out: stripBom(r.stdout) } : { ok: false, motivo: `git exit ${r.status}` };
}
/** Denominador de telas = FONTE ÚNICA scripts/qa/page-path.mjs (mesmo escopo de casos:report e
 *  screen-coverage). Escrever o escopo à mão aqui deu 181 vs 218 na 1ª medição (2026-09-07) —
 *  exatamente o vetor que a page-path documenta. */
async function contarTelas() {
  const r = git(['ls-files', '*.tsx']);
  if (!r.ok) return null;
  try {
    const pp = await import(pathToFileURL(join(REPO, 'scripts', 'qa', 'page-path.mjs')).href);
    return r.out.split(/\r?\n/).filter((p) => p && pp.isUnderPagesRoot(p) && pp.isPageScreenPath(p)).length;
  } catch { return null; }
}
async function fetchBriefMcp() {
  try {
    const hook = await import(pathToFileURL(join(REPO, '.claude', 'hooks', 'brief-fetch-curl.mjs')).href);
    const p = hook.resolveSettingsPath(REPO);
    if (!p || !existsSync(p)) return { ok: false, motivo: 'settings.local.json sem token MCP' };
    const auth = hook.readAuthHeader(readFileSync(p, 'utf8'));
    if (!auth) return { ok: false, motivo: 'token MCP ausente/placeholder' };
    const r = await hook.fetchBrief({ authHeader: auth });
    if (!r.ok) return { ok: false, motivo: r.reason };
    const c = r.json && r.json.result && r.json.result.content;
    const text = Array.isArray(c) ? c.filter((b) => b && b.type === 'text').map((b) => b.text).join('\n') : '';
    return text ? { ok: true, text } : { ok: false, motivo: 'brief sem texto' };
  } catch (e) { return { ok: false, motivo: `brief indisponível (${(e && e.name) || 'Error'})` }; }
}

export async function medirTudo({ dias = 30, semMcp = false, agoraMs = Date.now() } = {}) {
  const lim = agoraMs - dias * 86400000;
  const out = { _meta: { schema: 'fluxo-sistema/v1', janela_dias: dias, medido_em: new Date(agoraMs).toISOString(), fontes: ['gh run list (deploy.yml)', 'gh pr list --state merged', 'git log', 'governance/route-hits.json', 'TEAM.md §2', 'brief-fetch (MCP, proxy)'] }, metricas: {}, nao_medido: {} };
  const runs = gh(['run', 'list', '--workflow=deploy.yml', '--limit', '1000', '--json', 'createdAt,conclusion']);
  if (runs.ok) Object.assign(out.metricas, medirDeploys(runs.data.filter((x) => Date.parse(x.createdAt) > lim), dias));
  else out.nao_medido.deploys = runs.motivo;
  const LIM_PR = 800;
  const prs = gh(['pr', 'list', '--state', 'merged', '--limit', String(LIM_PR), '--json', 'createdAt,mergedAt,additions,deletions']);
  if (prs.ok) Object.assign(out.metricas, medirPrs(prs.data.filter((p) => p.mergedAt && Date.parse(p.mergedAt) > lim), { limite: LIM_PR }));
  else out.nao_medido.prs = prs.motivo;
  const log = git(['log', 'origin/main', `--since=${dias}.days`, '--format=%s']);
  if (log.ok) Object.assign(out.metricas, medirRetrabalho(log.out.split(/\r?\n/).filter(Boolean)));
  else out.nao_medido.commits = log.motivo;
  try {
    const rh = JSON.parse(stripBom(readFileSync(join(REPO, 'governance', 'route-hits.json'), 'utf8')));
    // O _meta do ledger não carrega data: a idade vem do último commit do arquivo. Em clone
    // RASO a data de git é o piso, não a história (§5 2026-07-24) — aí fica não medida.
    if (!(rh._meta && (rh._meta.gerado_em || rh._meta.generated_at))) {
      const shallow = git(['rev-parse', '--is-shallow-repository']);
      const c = git(['log', '-1', '--format=%cI', '--', 'governance/route-hits.json']);
      if (shallow.ok && shallow.out.trim() === 'false' && c.ok && c.out.trim()) rh._meta = { ...(rh._meta || {}), gerado_em: c.out.trim(), gerado_em_fonte: 'git log -1 do arquivo' };
      else out.nao_medido.adocao_idade = shallow.ok && shallow.out.trim() === 'true' ? 'clone raso: data do ledger não confiável' : 'sem data no _meta nem no git';
    }
    Object.assign(out.metricas, medirAdocao(rh, await contarTelas(), agoraMs));
  } catch { out.nao_medido.adocao = 'governance/route-hits.json ausente/ilegível'; }
  let wipTeam = {};
  try { wipTeam = parseWipTeam(readFileSync(join(REPO, 'TEAM.md'), 'utf8')); } catch { /* sem TEAM.md */ }
  if (semMcp) out.nao_medido.brief = 'pulado (--no-mcp)';
  else {
    const b = await fetchBriefMcp();
    if (!b.ok) out.nao_medido.brief = b.motivo;
    else {
      const pb = parseBrief(b.text);
      out.metricas.fila_us = pb.fila ? { status: 'measured', ...pb.fila, unit: 'US do backlog sem atribuição (brief MCP)', confianca: 'proxy: brief renderizado por LLM a partir do payload do BriefGeneratorService' } : { status: 'nao_medido', motivo: 'linha "US não atribuída" ausente no brief' };
      const w = medirWip(pb.emVoo, wipTeam);
      out.metricas.wip_por_pessoa = { status: pb.emVoo.length ? 'measured' : 'nao_medido', em_voo_total: pb.emVoo.length, ...w, wip_max: Object.fromEntries(Object.entries(wipTeam).map(([k, v]) => [k, v.wip_max])), unit: 'itens EM VOO por pessoa vs WIP máximo do TEAM.md', confianca: 'proxy: o brief lista até 12 itens (+N outros) — subconta' };
    }
  }
  Object.assign(out.metricas, NAO_MEDIDAS);
  return out;
}

export function formatBrief(res) {
  const m = res.metricas; const L = [];
  L.push(`### Fluxo do sistema — DORA + Flow (janela ${res._meta.janela_dias}d · medido ${res._meta.medido_em.slice(0, 10)})`, '');
  L.push('| Métrica | Valor | Nível DORA | Onde mede |', '|---|---|---|---|');
  const row = (n, v, nv, onde) => L.push(`| ${n} | ${v} | ${nv || '—'} | ${onde} |`);
  if (m.deploy_frequency) row('Frequência de deploy', m.deploy_frequency.status === 'measured' ? `${m.deploy_frequency.total} runs · ${m.deploy_frequency.por_dia}/dia · ${m.deploy_frequency.dias_com_deploy} dias` : 'NÃO MEDIDO', m.deploy_frequency.nivel_dora, 'pipeline');
  if (m.lead_time_pr) row('Lead time (PR → merge)', m.lead_time_pr.status === 'measured' ? `p50 ${m.lead_time_pr.p50_h}h · p90 ${m.lead_time_pr.p90_h}h${m.lead_time_pr.amostra_no_teto ? ' (amostra no teto)' : ''}` : 'NÃO MEDIDO', m.lead_time_pr.nivel_dora, 'transporte');
  if (m.cfr_pipeline) row('Falha de mudança', m.cfr_pipeline.status === 'measured' ? `${m.cfr_pipeline.value}% dos deploys` : 'NÃO MEDIDO', m.cfr_pipeline.nivel_dora, 'pipeline');
  if (m.recovery_pipeline) row('Recuperação', m.recovery_pipeline.status === 'measured' ? `p50 ${m.recovery_pipeline.p50_min} min · p90 ${m.recovery_pipeline.p90_min} min` : 'NÃO MEDIDO', m.recovery_pipeline.nivel_dora, 'pipeline');
  row('Falha de mudança (cliente)', 'NÃO MEDIDO — sem registro de incidente', '—', 'cliente');
  row('Recuperação (cliente)', 'NÃO MEDIDO — sem registro de incidente', '—', 'cliente');
  if (m.rework_rate) row('Retrabalho (fix/commits)', m.rework_rate.status === 'measured' ? `${m.rework_rate.pct_fix}% (${m.rework_rate.fix}/${m.rework_rate.commits}) · hotfix/revert ${m.rework_rate.hotfix_ou_revert}` : 'NÃO MEDIDO', '—', 'código');
  if (m.pr_size_compliance) row('PRs acima de 300 linhas', m.pr_size_compliance.status === 'measured' ? `${m.pr_size_compliance.pct_acima_300}% (${m.pr_size_compliance.acima_300}) · p50 ${m.pr_size_compliance.p50_linhas} linhas` : 'NÃO MEDIDO', '—', 'disciplina');
  if (m.fila_us) row('Fila de US', m.fila_us.status === 'measured' ? `${m.fila_us.us_nao_atribuida} não atribuídas · ${m.fila_us.sem_dono} sem dono · mais antiga ${m.fila_us.mais_antiga_dias}d` : `NÃO MEDIDO (${m.fila_us.motivo || res.nao_medido.brief || ''})`, '—', 'fila (proxy)');
  else row('Fila de US', `NÃO MEDIDO (${res.nao_medido.brief || 'sem brief'})`, '—', 'fila');
  if (m.wip_por_pessoa) row('WIP vs limite', m.wip_por_pessoa.status === 'measured' ? (m.wip_por_pessoa.violacoes.length ? m.wip_por_pessoa.violacoes.map((v) => `${v.owner} ${v.em_voo}/${v.wip_max}`).join(' · ') : 'dentro do limite') : 'NÃO MEDIDO', '—', 'WIP (proxy)');
  row('Tempo de fluxo (US → prod)', 'NÃO MEDIDO — tasks-list sem datas', '—', 'fila');
  if (m.adocao_telas) row('Telas servidas em prod', m.adocao_telas.status !== 'nao_medido' ? `${m.adocao_telas.pct_servidas}% (${m.adocao_telas.telas_com_hits}/${m.adocao_telas.telas_inertia})${m.adocao_telas.status === 'stale' ? ` · ledger STALE (${m.adocao_telas.ledger_idade_dias}d)` : ''}` : 'NÃO MEDIDO', '—', 'adoção');
  const nm = Object.entries(res.nao_medido);
  if (nm.length) L.push('', `> Não medido nesta execução: ${nm.map(([k, v]) => `${k} (${v})`).join(' · ')}`);
  L.push('', '> Régua: DORA State of DevOps 2024 (elite: deploy sob demanda · lead time < 1 dia · falha ≈5% · recuperação < 1h). Pipeline ≠ cliente: as duas chaves de falha/recuperação no nível do CLIENTE seguem `not_yet_measured` até existir registro de incidente.');
  return L.join('\n');
}

export function formatHumano(res) {
  return formatBrief(res).replace(/^\| /gm, '  ').replace(/ \|$/gm, '').replace(/ \| /g, '  ·  ').replace(/^\|---.*$/gm, '');
}

// ── entry ────────────────────────────────────────────────────────────────────────
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (ARGS.includes('--selftest')) {
    const r = spawnSync(process.execPath, [join(HERE, 'fluxo-sistema.test.mjs')], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  const dias = parseInt(argVal('--days', '30'), 10) || 30;
  medirTudo({ dias, semMcp: ARGS.includes('--no-mcp') }).then((res) => {
    if (ARGS.includes('--json')) process.stdout.write(JSON.stringify(res, null, 2) + '\n');
    else if (ARGS.includes('--brief')) process.stdout.write(formatBrief(res) + '\n');
    else process.stdout.write(formatHumano(res) + '\n');
    process.exit(0);
  }).catch((e) => { console.error(`fluxo-sistema: ${e && e.message}`); process.exit(2); });
}
