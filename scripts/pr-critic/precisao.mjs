#!/usr/bin/env node
// @ts-check
/**
 * precisao.mjs — MEDIDOR DE PRECISÃO DO PRÓPRIO pr-critic (fecha o loop).
 *
 * Contrato-âncora: grade v3 — a última fraqueza da grade ("critic no loop de PR",
 * 3→6): sem medir a própria precisão, ninguém sabe se o critic AJUDA ou ATRAPALHA,
 * e um verificador que gera ruído perde a confiança e vira ignorado (lição #4038
 * adr-proposto-parado). Régua: Cognition/Jules medem aceitação do critic ao longo
 * do tempo. Este medidor responde: dos achados que o critic surfacou, o humano
 * AGIU (o PR mudou o arquivo apontado DEPOIS do comentário) ou IGNOROU?
 *
 * ── DECONFLITO (NÃO confundir — 4 medidores, eixos distintos, 1 fato = 1 lugar) ──
 *   • outcome-metrics.mjs        = retrabalho do LOOP DE DESIGN (tela re-mexida?)   — SYNC_LOG + git Pages
 *   • agent-pr-outcomes.mjs      = DORA dos PRs do AGENTE (o PR do bot deu certo?)  — gh pr list
 *   • casos-gate (ADR 0264)      = COBERTURA de casos da tela (tem teste/UC?)        — outro gate
 *   • ESTE (precisao.mjs)        = PRECISÃO do pr-critic (o achado foi acionado?)    — comentário do critic + commits
 *   São métricas próximas de nome, distantes de escopo. Este NÃO mede cobertura nem
 *   DORA nem retrabalho de tela — mede se o CRÍTICO acerta (insumo pra manter/podar).
 *
 * ── COMO MEDE (o registro durável é o PRÓPRIO comentário do critic) ─────────────
 *   O critic embute em cada comentário um bloco machine-readable
 *   `<!-- pr-critic-data: {...} -->` com os achados SOBREVIVENTES (id/arquivo/sev).
 *   O comentário persiste no gh pra sempre — não depende de artifact que expira.
 *   Este medidor, por PR que o critic comentou: pega o createdAt do comentário e os
 *   arquivos tocados por commits POSTERIORES ao comentário; um achado conta como
 *   AGIU se o arquivo apontado mudou depois do comentário, senão IGNORADO.
 *
 * ── HONESTIDADE (o número é PROXY — impresso + em --json.gaps) ───────────────────
 *   G1 "arquivo mudou após o comentário" ≠ necessariamente POR CAUSA do achado
 *      (pode ser mudança não-relacionada no mesmo arquivo) → precisão é PISO otimista
 *      no numerador e piso pessimista de captura (ver G2). Reportado, não fundido.
 *   G2 o humano pode CORRIGIR o problema em OUTRO arquivo (ou fechar o PR) → conta
 *      como IGNORADO mesmo tendo agido. Subconta ação.
 *   G3 IGNORADO ≠ ERRADO: o critic é ADVISORY (ADR 0314); o humano pode ignorar um
 *      achado CORRETO por decisão consciente. "precisão" aqui = taxa-de-ação, não
 *      taxa-de-verdade. É o proxy computável sem rótulo humano.
 *   G4 arquivos truncados pela GitHub API em commits gigantes (>300 files) escapam.
 *   G5 janela por merge/close; PR aberto não entra. Comentário editado: usamos o
 *      createdAt (1ª versão) — findings adicionados em edição posterior podem
 *      ter janela de ação subestimada.
 *
 * Funções puras exportadas → testáveis SEM gh/rede (precisao.test.mjs).
 * A camada de rede (gh pr list/view + gh api commits) só roda quando invocado
 * direto e sem --fixture.
 *
 * Modos:
 *   (default)   texto humano no stdout
 *   --json      objeto: { ok, generated, window, resumo, por_severidade, prs, gaps }
 *   --brief     seção markdown pro brief semanal
 *   --fixture <f.json>  usa prRecords de arquivo (hermético — não chama gh)
 *   --ledger <f.jsonl>  ANEXA 1 linha JSONL por PR medido (registro versionável opt-in)
 *   --days <N>  janela (default 60); ou --since <ISO>
 *   --limit <N> teto de PRs varridos (default 120)
 *   --repo <o/r>  override do repo
 *   --selftest  fixtures-armadilha (agiu/ignorado morde)
 *
 * ADVISORY (ADR 0271/0275/0314): só MEDE, nunca bloqueia. Insumo do brief/decisão
 * de manter-ou-podar o critic (o zelador cobra via promote_by).
 *
 * Refs: grade v3 (critic no loop) · #4029 (pr-critic) · ADR 0314 (advisory) ·
 *       agent-pr-outcomes.mjs (medidor irmão, DORA) · #4038 (verificador ruidoso).
 */
import { execFileSync, spawnSync } from 'node:child_process';
import { appendFileSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const MS_PER_HOUR = 3600 * 1000;
/** marcador do bloco machine-readable embutido pelo critica.mjs (mantém em sincronia). */
export const MARCADOR_DADOS = '<!-- pr-critic-data:';

// ── helpers puros ─────────────────────────────────────────────────────────────

/** arredonda pra 1 casa (ou null). */
const round1 = (v) => (v == null ? null : Math.round(v * 10) / 10);

/**
 * Extrai o bloco `<!-- pr-critic-data: {json} -->` do corpo de um comentário.
 * Retorna { modelo, achados:[{id,arquivo,severidade,verificado}] } ou null.
 */
export function parseBlocoDados(body) {
  if (!body || !body.includes(MARCADOR_DADOS)) return null;
  const ini = body.indexOf(MARCADOR_DADOS) + MARCADOR_DADOS.length;
  const fim = body.indexOf('-->', ini);
  if (fim === -1) return null;
  try {
    const obj = JSON.parse(body.slice(ini, fim).trim());
    if (!obj || !Array.isArray(obj.achados)) return null;
    return { modelo: obj.modelo || null, achados: obj.achados };
  } catch {
    return null;
  }
}

/** Acha, numa lista de comentários, o comentário do critic (o que tem o bloco). */
export function acharComentarioCritic(comments) {
  for (const c of comments || []) {
    if (c && typeof c.body === 'string' && c.body.includes(MARCADOR_DADOS)) return c;
  }
  return null;
}

/**
 * Classifica cada achado de UM PR em agiu/ignorado.
 * @param {{achados:any[], touchedAfter:string[]}} rec  touchedAfter = arquivos tocados
 *        por commits POSTERIORES ao comentário do critic.
 */
export function classificarPR({ achados, touchedAfter }) {
  const tocados = new Set(touchedAfter || []);
  const detalhe = (achados || []).map((a) => ({
    id: a.id,
    arquivo: a.arquivo,
    severidade: a.severidade,
    acao: tocados.has(a.arquivo) ? 'agiu' : 'ignorado',
  }));
  const agiu = detalhe.filter((d) => d.acao === 'agiu').length;
  return { detalhe, total: detalhe.length, agiu, ignorado: detalhe.length - agiu };
}

/** taxa inteira 0-100 (1 casa) ou null se denominador zero. */
export function taxa(num, den) {
  return den ? round1((num / den) * 100) : null;
}

const SEVERIDADES = ['alta', 'media', 'baixa'];

/**
 * Relatório a partir de prRecords JÁ montados (PURO — sem I/O).
 * prRecords: [{ number, modelo?, achados:[{id,arquivo,severidade}], touchedAfter:[] }]
 */
export function buildReport({ prRecords, nowIso, sinceIso, days = 60 }) {
  const prs = [];
  let total = 0, agiu = 0;
  const porSev = Object.fromEntries(SEVERIDADES.map((s) => [s, { total: 0, agiu: 0 }]));
  let prsComAchado = 0, prsFirstPass = 0;

  for (const rec of prRecords) {
    const cls = classificarPR(rec);
    if (cls.total === 0) continue; // critic comentou "sem incoerência" — nada a medir
    prsComAchado++;
    if (cls.agiu === cls.total) prsFirstPass++;
    total += cls.total;
    agiu += cls.agiu;
    for (const d of cls.detalhe) {
      const bucket = porSev[d.severidade];
      if (bucket) { bucket.total++; if (d.acao === 'agiu') bucket.agiu++; }
    }
    prs.push({ number: rec.number, modelo: rec.modelo || null, total: cls.total, agiu: cls.agiu, ignorado: cls.ignorado, detalhe: cls.detalhe });
  }

  return {
    ok: true,
    generated: nowIso || new Date().toISOString().slice(0, 10),
    window: { since: sinceIso || null, days },
    resumo: {
      prs_com_achado: prsComAchado,
      achados: total,
      agiu,
      ignorado: total - agiu,
      precisao_acao: taxa(agiu, total),
      first_pass_prs: taxa(prsFirstPass, prsComAchado),
    },
    por_severidade: Object.fromEntries(SEVERIDADES.map((s) => [s, { ...porSev[s], taxa_acao: taxa(porSev[s].agiu, porSev[s].total) }])),
    prs,
    confianca: 'proxy (taxa-de-AÇÃO via comentário do critic + commits; ver gaps — não é taxa-de-verdade)',
    gaps: GAPS,
  };
}

export const GAPS = [
  'G1 "arquivo mudou após o comentário" ≠ necessariamente por causa do achado (mudança não-relacionada no mesmo arquivo infla ação).',
  'G2 humano pode corrigir em OUTRO arquivo ou fechar o PR → conta IGNORADO mesmo tendo agido (subconta ação).',
  'G3 IGNORADO ≠ ERRADO: critic é ADVISORY; ignorar achado correto é decisão válida. Isto é taxa-de-AÇÃO, não taxa-de-verdade.',
  'G4 arquivos truncados pela GitHub API em commits gigantes (>300 files) escapam do conjunto tocado.',
  'G5 janela por merge/close; comentário editado usa createdAt da 1ª versão.',
];

// ── camada de rede (só quando invocado direto sem --fixture) ─────────────────────

/** lista de PRs terminais na janela: [{number, mergedAt, closedAt, state}]. */
export function fetchPRsViaGh({ repo, limit = 120 } = {}) {
  const args = ['pr', 'list', '--state', 'all', '--limit', String(limit),
    '--json', 'number,mergedAt,closedAt,state'];
  if (repo) args.push('--repo', repo);
  const out = execFileSync('gh', args, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  const arr = JSON.parse(out);
  if (!Array.isArray(arr)) throw new Error('gh pr list não devolveu array');
  return arr;
}

/** detalhe de 1 PR: comentários + commits (com data). */
function fetchPRDetalhe(number, repo) {
  const args = ['pr', 'view', String(number), '--json', 'comments,commits'];
  if (repo) args.push('--repo', repo);
  const out = execFileSync('gh', args, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  return JSON.parse(out);
}

/** arquivos tocados por 1 commit (via REST — pulls/commits não traz files). */
function fetchArquivosDoCommit(oid, repo) {
  const slug = repo || inferRepoSlug();
  const args = ['api', `repos/${slug}/commits/${oid}`, '--jq', '[.files[].filename]'];
  try {
    const out = execFileSync('gh', args, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
    const arr = JSON.parse(out);
    return Array.isArray(arr) ? arr : [];
  } catch {
    return [];
  }
}

function inferRepoSlug() {
  const out = execFileSync('gh', ['repo', 'view', '--json', 'nameWithOwner', '--jq', '.nameWithOwner'], { encoding: 'utf8' });
  return out.trim();
}

/** true se o timestamp ISO `a` é depois de `b`. */
function depoisDe(aIso, bIso) {
  const a = Date.parse(aIso), b = Date.parse(bIso);
  return Number.isFinite(a) && Number.isFinite(b) && a > b;
}

/**
 * Monta prRecords ao vivo: varre PRs terminais na janela, acha o comentário do
 * critic, e resolve os arquivos tocados por commits posteriores ao comentário.
 */
export function collectPrRecords({ repo, days = 60, sinceIso, limit = 120 } = {}) {
  const since = sinceIso ? Date.parse(sinceIso) : Date.now() - days * 24 * MS_PER_HOUR;
  const inWindow = (pr) => {
    const t = Date.parse(pr.mergedAt || pr.closedAt || '');
    return Number.isFinite(t) && t >= since;
  };
  const lista = fetchPRsViaGh({ repo, limit }).filter((p) => (p.mergedAt || p.closedAt) && inWindow(p));
  const records = [];
  for (const p of lista) {
    let det;
    try { det = fetchPRDetalhe(p.number, repo); } catch { continue; }
    const comentario = acharComentarioCritic(det.comments);
    if (!comentario) continue;
    const dados = parseBlocoDados(comentario.body);
    if (!dados || !dados.achados.length) continue; // "sem incoerência" — nada a medir
    const commitsDepois = (det.commits || []).filter((c) => depoisDe(c.committedDate, comentario.createdAt));
    const touched = new Set();
    for (const c of commitsDepois) for (const f of fetchArquivosDoCommit(c.oid, repo)) touched.add(f);
    records.push({ number: p.number, modelo: dados.modelo, achados: dados.achados, touchedAfter: [...touched] });
  }
  return records;
}

// ── ledger versionável (opt-in) ──────────────────────────────────────────────────

/** anexa 1 linha JSONL por PR medido (registro append-only). */
export function anexarLedger(path, report) {
  const linhas = report.prs.map((pr) => JSON.stringify({
    generated: report.generated,
    pr: pr.number,
    modelo: pr.modelo,
    precisao_acao: taxa(pr.agiu, pr.total),
    achados: pr.detalhe,
  }));
  if (linhas.length) appendFileSync(path, linhas.join('\n') + '\n');
  return linhas.length;
}

// ── render ──────────────────────────────────────────────────────────────────────

const pct = (v) => (v == null ? 'n/d' : `${v}%`);

export function renderHuman(r) {
  const s = r.resumo;
  const L = [];
  L.push('═══════════════════════════════════════════════════════════════');
  L.push(' PRECISÃO DO pr-critic — taxa-de-AÇÃO dos achados · ' + r.generated);
  L.push(` janela: ${r.window.days}d${r.window.since ? ` (desde ${r.window.since})` : ''}`);
  L.push('═══════════════════════════════════════════════════════════════');
  L.push('');
  L.push(`  PRs com achado ....: ${s.prs_com_achado}`);
  L.push(`  achados totais ....: ${s.achados}  (${s.agiu} agiu · ${s.ignorado} ignorado)`);
  L.push(`  PRECISÃO (ação) ...: ${pct(s.precisao_acao)}  (achados que o humano acionou)`);
  L.push(`  FIRST-PASS (PRs) ..: ${pct(s.first_pass_prs)}  (PRs onde TODO achado foi acionado)`);
  L.push('');
  L.push('  por severidade:');
  for (const sev of SEVERIDADES) {
    const b = r.por_severidade[sev];
    L.push(`    ${sev.padEnd(6)}: ${pct(b.taxa_acao)}  (${b.agiu}/${b.total})`);
  }
  L.push('');
  L.push('▸ HONESTIDADE (é taxa-de-AÇÃO, não taxa-de-verdade — o número é PROXY)');
  for (const g of r.gaps) L.push(`  • ${g}`);
  L.push('═══════════════════════════════════════════════════════════════');
  return L.join('\n');
}

export function renderBriefMd(r) {
  const s = r.resumo;
  const L = [];
  L.push('### Precisão do pr-critic — taxa-de-ação (advisory)');
  L.push('');
  L.push(`_Janela ${r.window.days}d · ${s.prs_com_achado} PRs com achado · ${s.achados} achados._`);
  L.push('');
  L.push('| Métrica | Valor | Leitura |');
  L.push('|---|---|---|');
  L.push(`| Precisão (ação) | **${pct(s.precisao_acao)}** (${s.agiu}/${s.achados}) | achados acionados pelo humano — ↑ = critic útil |`);
  L.push(`| First-pass (PRs) | **${pct(s.first_pass_prs)}** | PRs onde todo achado foi acionado |`);
  L.push(`| Alta | ${pct(r.por_severidade.alta.taxa_acao)} (${r.por_severidade.alta.agiu}/${r.por_severidade.alta.total}) | severidade que MAIS deveria ser acionada |`);
  L.push('');
  L.push('> Taxa-de-AÇÃO, não taxa-de-verdade (critic é advisory — ignorar achado correto é válido). Proxy — ver gaps no `--json`. A tendência por cycle é o sinal.');
  return L.join('\n');
}

// ── entry-point ──────────────────────────────────────────────────────────────────
function argVal(argv, flag, def) { const i = argv.indexOf(flag); return i >= 0 && argv[i + 1] ? argv[i + 1] : def; }

if (import.meta.url === pathToFileURL(process.argv[1] || '').href) {
  const argv = process.argv.slice(2);
  if (argv.includes('--selftest')) {
    // spawnSync (não import) pra evitar import circular (o test importa ESTE módulo).
    const test = new URL('./precisao.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }

  const days = Number(argVal(argv, '--days', '60')) || 60;
  const sinceIso = argVal(argv, '--since', null);
  const limit = Number(argVal(argv, '--limit', '120')) || 120;
  const repo = argVal(argv, '--repo', null);
  const fixture = argVal(argv, '--fixture', null);
  const ledger = argVal(argv, '--ledger', null);

  let prRecords;
  try {
    prRecords = fixture ? JSON.parse(readFileSync(fixture, 'utf8')) : collectPrRecords({ repo, days, sinceIso, limit });
  } catch (e) {
    console.error(`[precisao] falha ao carregar registros: ${e.message}`);
    console.error(fixture ? '  (fixture inválido)' : '  (gh ausente/sem auth? use --fixture <arquivo> pra rodar offline)');
    process.exit(1);
  }
  if (!Array.isArray(prRecords)) { console.error('[precisao] fonte de registros não é array'); process.exit(1); }

  const r = buildReport({ prRecords, sinceIso, days });
  if (ledger) {
    const n = anexarLedger(ledger, r);
    console.error(`[precisao] ${n} linha(s) anexada(s) ao ledger ${ledger}`);
  }
  if (argv.includes('--json')) process.stdout.write(JSON.stringify(r, null, 2) + '\n');
  else if (argv.includes('--brief')) process.stdout.write(renderBriefMd(r) + '\n');
  else process.stdout.write(renderHuman(r) + '\n');
  process.exit(0);
}
