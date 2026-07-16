#!/usr/bin/env node
// @ts-check
/**
 * agent-cost-per-pr.mjs — CUSTO ESTIMADO POR PR do agente (USD/tokens · advisory).
 *
 * Objetivo (ranking adversarial da grade de réguas 2026-07-12, item #7): fechar a
 * última régua "só verbal" — "economize crédito" é valor cultural do Wagner SEM
 * NÚMERO (custo-eficiência nota 3). Este script dá o número: custo estimado por PR
 * mergeado do agente, na janela dos últimos N PRs (default 20).
 *
 * ── FONTE (verificada, não assumida) ─────────────────────────────────────────────
 *   O adversário DERRUBOU a fonte Langfuse (traceia runtime da Jana por conversa,
 *   sem dimensão PR/branch — impossível por modelo de dados). Fonte viável: os
 *   JSONL locais do Claude Code (~/.claude/projects/**\/*.jsonl) — cada mensagem
 *   assistant carrega `gitBranch`, `message.model` e `message.usage` (input/output/
 *   cache_read/cache_creation com breakdown 5m/1h). É a mesma fonte que o
 *   cc-watcher (skill oimpresso-cc-watcher-setup) ingere pro MCP server.
 *
 * ── JOIN HEURÍSTICO EM 2 SINAIS (o buraco é DECLARADO, não escondido) ────────────
 *   Sinal 1 (forte)  : gitBranch da mensagem == headRefName do PR.
 *   Sinal 2 (fallback): a SESSÃO cita a URL `/pull/N` do PR no transcript (o
 *     `gh pr create` imprime a URL) — necessário porque o padrão real do projeto é
 *     gastar tokens na branch da WORKTREE (claude/<nome-sessao>) e criar a branch
 *     de tópico só no fim (verificado 2026-07-12: join só-por-branch dava 100%
 *     sem-match = métrica morta). Custo da sessão é DILUÍDO igualmente entre os
 *     PRs da janela que ela cita; sessão já casada por branch não re-atribui.
 *   Mesma classe de gaps que agent-pr-outcomes.mjs declara no output. PR sem
 *   nenhum dos 2 sinais = SEM MATCH, e o % sai no relatório.
 *
 * ── GAPS (impressos + em --json.gaps) ────────────────────────────────────────────
 *   G1 sinal-branch: consolidação do parent noutra branch, rename/squash, sessão
 *      sem branch → escapa. O % de PRs sem match publicado é o tamanho do buraco.
 *   G2 custo é da SESSÃO/BRANCH inteira: superconta exploração descartada e pode
 *      fundir PRs que reusaram a branch.
 *   G3 fonte = JSONL DESTA máquina: sessões cloud (claude.ai/code), CI e outros
 *      devs NÃO aparecem → SUBconta. Rodar na máquina de cada dev soma visão.
 *   G4 preços hardcoded (snapshot 2026-07-12, tabela oficial Anthropic): modelo
 *      desconhecido → tokens contados, USD null + declarado. Não modela server
 *      tools nem fast-mode premium.
 *   G5 CI não enxerga o JSONL local: o workflow semanal roda o selftest hermético
 *      e renderiza o ÚLTIMO snapshot commitado (--snapshot local), com staleness
 *      declarada. Próximo degrau: agregação server-side no ingest do cc-watcher.
 *   G6 sinal-citação: citar /pull/N ≠ ser autor (sessão babysitter que LÊ PRs
 *      também cita) — por isso o custo é diluído por N PRs citados e o relatório
 *      separa matched_por_branch de matched_por_citacao.
 *
 * Modos:
 *   (default)          texto humano
 *   --json             relatório completo (com gaps)
 *   --brief            seção markdown pro brief semanal
 *   --prs <N>          janela: últimos N PRs mergeados do agente (default 20)
 *   --marker <s>       marcador de PR-de-agente (default "[CC]")
 *   --repo <o/r>       override do repo pro gh
 *   --projects-dir <d> override de ~/.claude/projects
 *   --project-filter <s> só escaneia dirs cujo nome contém s (default "oimpresso")
 *   --fixture <f.json> {prs:[...], usage:[...]} hermético (não chama gh nem fs scan)
 *   --snapshot [path]  roda ao vivo E grava o JSON em scripts/governance/data/
 *   --render-snapshot <path>  renderiza brief a partir de snapshot commitado
 *   --selftest         fixtures-armadilha (join morde/libera, preço de cache confere)
 *
 * ADVISORY (ADR 0271/0275): só MEDE, nunca bloqueia. Sem valores R$ — custo em
 * USD/tokens; nada aqui mapeia pra faturamento/receita.
 *
 * Refs: agent-pr-outcomes.mjs (irmão DORA, PR #4024) · ADR 0226 (brief) ·
 *       skill oimpresso-cc-watcher-setup (schema do JSONL) · proibicoes §claim.
 */
import { execFileSync, spawnSync } from 'node:child_process';
import { readFileSync, readdirSync, statSync, existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { homedir } from 'node:os';
import { pathToFileURL, fileURLToPath } from 'node:url';
import { isAgentPR, DEFAULT_MARKER, median } from './agent-pr-outcomes.mjs';

// ── preços (USD por MTok · snapshot 2026-07-12 da tabela oficial — G4) ──────────
export const PRECOS_USD_MTOK = {
  'claude-fable-5': { input: 10, output: 50 },
  'claude-opus-4-8': { input: 5, output: 25 },
  'claude-opus-4-7': { input: 5, output: 25 },
  'claude-opus-4-6': { input: 5, output: 25 },
  'claude-opus-4-5': { input: 5, output: 25 },
  'claude-sonnet-5': { input: 3, output: 15 },
  'claude-sonnet-4-6': { input: 3, output: 15 },
  'claude-sonnet-4-5': { input: 3, output: 15 },
  'claude-haiku-4-5': { input: 1, output: 5 },
};
/** multiplicadores sobre o preço de INPUT: cache read 0.1× · write 5m 1.25× · write 1h 2×. */
export const CACHE_MULT = { read: 0.1, write_5m: 1.25, write_1h: 2.0 };
export const DEFAULT_PR_WINDOW = 20;

const round2 = (v) => (v == null ? null : Math.round(v * 100) / 100);

/** resolve preço por prefixo (JSONL pode trazer id com sufixo de data). */
export function resolvePreco(modelId) {
  if (!modelId) return null;
  const keys = Object.keys(PRECOS_USD_MTOK).sort((a, b) => b.length - a.length);
  const hit = keys.find((k) => String(modelId).startsWith(k));
  return hit ? PRECOS_USD_MTOK[hit] : null;
}

/**
 * 1 linha JSONL → entrada de usage {branch, model, input, output, cache_read,
 * cache_5m, cache_1h} ou null (linha sem usage de assistant).
 */
export function parseUsageLine(raw) {
  if (!raw || !raw.includes('"usage"')) return null;
  let obj;
  try { obj = JSON.parse(raw); } catch { return null; }
  if (obj.type !== 'assistant') return null;
  const u = obj.message && obj.message.usage;
  if (!u) return null;
  const cc = u.cache_creation || null;
  // sem breakdown 5m/1h → trata tudo como 5m (subestima o write 1h; declarado em G4)
  const c5m = cc ? (cc.ephemeral_5m_input_tokens || 0) : (u.cache_creation_input_tokens || 0);
  const c1h = cc ? (cc.ephemeral_1h_input_tokens || 0) : 0;
  return {
    branch: obj.gitBranch || null,
    model: (obj.message && obj.message.model) || null,
    input: u.input_tokens || 0,
    output: u.output_tokens || 0,
    cache_read: u.cache_read_input_tokens || 0,
    cache_5m: c5m,
    cache_1h: c1h,
  };
}

/** custo USD de um agregado de tokens num modelo. null se preço desconhecido. */
export function custoUSD(t, modelId) {
  const p = resolvePreco(modelId);
  if (!p) return null;
  return (
    t.input * p.input +
    t.output * p.output +
    t.cache_read * p.input * CACHE_MULT.read +
    t.cache_5m * p.input * CACHE_MULT.write_5m +
    t.cache_1h * p.input * CACHE_MULT.write_1h
  ) / 1e6;
}

/** agrega entradas por branch → { porModelo: Map<model, sums>, sem_branch: n }. */
export function aggregatePorBranch(entries) {
  const porBranch = new Map();
  let semBranch = 0;
  for (const e of entries) {
    if (!e) continue;
    if (!e.branch) { semBranch++; continue; }
    let porModelo = porBranch.get(e.branch);
    if (!porModelo) { porModelo = new Map(); porBranch.set(e.branch, porModelo); }
    const key = e.model || '(sem modelo)';
    const s = porModelo.get(key) || { input: 0, output: 0, cache_read: 0, cache_5m: 0, cache_1h: 0 };
    s.input += e.input; s.output += e.output; s.cache_read += e.cache_read;
    s.cache_5m += e.cache_5m; s.cache_1h += e.cache_1h;
    porModelo.set(key, s);
  }
  return { porBranch, sem_branch_msgs: semBranch };
}

const somaTokens = (s) => s.input + s.output + s.cache_read + s.cache_5m + s.cache_1h;

/** agrega entradas por modelo (ignorando branch) → Map<model, sums>. */
export function aggregatePorModelo(entries) {
  const porModelo = new Map();
  for (const e of entries) {
    if (!e) continue;
    const key = e.model || '(sem modelo)';
    const s = porModelo.get(key) || { input: 0, output: 0, cache_read: 0, cache_5m: 0, cache_1h: 0 };
    s.input += e.input; s.output += e.output; s.cache_read += e.cache_read;
    s.cache_5m += e.cache_5m; s.cache_1h += e.cache_1h;
    porModelo.set(key, s);
  }
  return porModelo;
}

/** URLs de PR citadas num texto (o `gh pr create` imprime a URL no transcript). */
export function extractPrMentions(text) {
  const out = new Set();
  const re = /\/pull\/(\d+)(?!\d)/g;
  let m;
  while ((m = re.exec(String(text))) !== null) out.add(Number(m[1]));
  return [...out];
}

const GAPS = [
  'G1 sinal-branch (gitBranch==headRefName): consolidação parent / rename / sessão sem branch escapam — % de PRs sem match publicado é o tamanho do buraco.',
  'G2 custo é da SESSÃO/BRANCH inteira: superconta exploração descartada e funde PRs que reusaram branch.',
  'G3 fonte = JSONL desta máquina: cloud/CI/outros devs não aparecem → subconta.',
  'G4 preços hardcoded (2026-07-12); modelo desconhecido → USD null declarado; sem breakdown de cache → tudo vira write 5m; não modela server tools/fast-mode.',
  'G5 CI só roda selftest + snapshot commitado (staleness declarada); medição viva é local.',
  'G6 sinal-citação (/pull/N no transcript): citar ≠ ser autor — custo diluído por N PRs citados; matched_por_citacao é reportado separado do matched_por_branch.',
];

/**
 * monta o relatório (puro — sem I/O).
 * @param {{prs:any[], sessions:Array<{id?:string, entries:any[], pr_mentions?:number[]}>,
 *          marker?:string, prWindow?:number, generated?:string}} o
 */
export function buildReport({ prs, sessions, marker = DEFAULT_MARKER, prWindow = DEFAULT_PR_WINDOW, generated }) {
  const agentMerged = prs
    .filter((p) => isAgentPR(p, marker) && p.mergedAt)
    .sort((a, b) => Date.parse(b.mergedAt) - Date.parse(a.mergedAt))
    .slice(0, prWindow);
  const prPorNumero = new Map(agentMerged.map((p) => [p.number, p]));
  const branchesDosPRs = new Map(agentMerged.map((p) => [p.headRefName, p.number]));

  const modelosDesconhecidos = new Set();
  let semBranchMsgs = 0;
  /** por PR: { usd, tokens, incompleto, sinais:Set } */
  const acum = new Map(agentMerged.map((p) => [p.number, { usd: 0, tokens: 0, incompleto: false, sinais: new Set() }]));
  let usdForaDaJanela = 0;

  const custoDoAgregado = (porModelo) => {
    let usd = 0, tokens = 0, incompleto = false;
    for (const [model, sums] of porModelo) {
      tokens += somaTokens(sums);
      const c = custoUSD(sums, model);
      if (c == null) { incompleto = true; modelosDesconhecidos.add(model); }
      else usd += c;
    }
    return { usd, tokens, incompleto };
  };

  for (const sess of sessions) {
    const entries = (sess.entries || []).map((e) => (typeof e === 'string' ? parseUsageLine(e) : e)).filter(Boolean);
    if (!entries.length) continue;
    const { porBranch, sem_branch_msgs } = aggregatePorBranch(entries);
    semBranchMsgs += sem_branch_msgs;

    // Sinal 1 — branch da mensagem casa com headRef de PR da janela
    let casouPorBranch = false;
    let usdNaoCasadoDaSessao = 0;
    for (const [branch, porModelo] of porBranch) {
      const prNum = branchesDosPRs.get(branch);
      const parcial = custoDoAgregado(porModelo);
      if (prNum != null) {
        casouPorBranch = true;
        const a = acum.get(prNum);
        a.usd += parcial.usd; a.tokens += parcial.tokens; a.incompleto ||= parcial.incompleto;
        a.sinais.add('branch');
      } else {
        usdNaoCasadoDaSessao += parcial.usd;
      }
    }
    if (casouPorBranch) {
      // não re-atribui por citação (evita dupla contagem); resto da sessão é declarado
      usdForaDaJanela += usdNaoCasadoDaSessao;
      continue;
    }

    // Sinal 2 — sessão cita /pull/N de PR(s) da janela → custo diluído
    const citados = (sess.pr_mentions || []).filter((n) => prPorNumero.has(n));
    if (citados.length) {
      const totalSessao = custoDoAgregado(aggregatePorModelo(entries));
      for (const n of citados) {
        const a = acum.get(n);
        a.usd += totalSessao.usd / citados.length;
        a.tokens += Math.round(totalSessao.tokens / citados.length);
        a.incompleto ||= totalSessao.incompleto;
        a.sinais.add('citacao');
      }
    } else {
      // nem branch nem citação — custo fica fora da janela (declarado)
      usdForaDaJanela += custoDoAgregado(aggregatePorModelo(entries)).usd;
    }
  }

  const porPR = agentMerged.map((pr) => {
    const a = acum.get(pr.number);
    const matched = a.sinais.size > 0;
    return {
      pr: pr.number, title: pr.title, branch: pr.headRefName, matched,
      sinais: [...a.sinais],
      tokens: matched ? a.tokens : 0,
      usd: matched ? round2(a.usd) : null,
      usd_incompleto: (matched && a.incompleto) || undefined,
    };
  });

  const matched = porPR.filter((p) => p.matched);
  const semMatch = porPR.length - matched.length;
  const custos = matched.filter((p) => p.usd != null && !p.usd_incompleto).map((p) => p.usd);
  const top = [...matched].filter((p) => p.usd != null).sort((a, b) => b.usd - a.usd).slice(0, 3);

  return {
    ok: true,
    generated: generated || new Date().toISOString().slice(0, 10),
    janela: { prs: porPR.length, pedidos: prWindow, marker },
    custo: {
      total_usd_matched: round2(matched.reduce((a, p) => a + (p.usd || 0), 0)),
      mediana_usd_por_pr: round2(median(custos)),
      top_prs: top.map((p) => ({ pr: p.pr, usd: p.usd, title: p.title })),
      usd_fora_da_janela: round2(usdForaDaJanela),
    },
    join: {
      sem_match: semMatch,
      sem_match_pct: porPR.length ? round2((semMatch / porPR.length) * 100) : null,
      matched_por_branch: porPR.filter((p) => p.sinais.includes('branch')).length,
      matched_por_citacao: porPR.filter((p) => p.sinais.includes('citacao') && !p.sinais.includes('branch')).length,
      msgs_sem_branch: semBranchMsgs,
      modelos_desconhecidos: [...modelosDesconhecidos],
    },
    por_pr: porPR,
    confianca: 'proxy (JSONL local → branch OU citação /pull/N → PR; ver gaps)',
    gaps: GAPS,
  };
}

// ── camada de I/O (só quando invocado direto sem --fixture) ──────────────────────

export function fetchMergedPRsViaGh({ repo, limit = 200 } = {}) {
  const args = ['pr', 'list', '--state', 'merged', '--limit', String(limit),
    '--json', 'number,title,author,headRefName,createdAt,mergedAt'];
  if (repo) args.push('--repo', repo);
  const out = execFileSync('gh', args, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  const arr = JSON.parse(out);
  if (!Array.isArray(arr)) throw new Error('gh pr list não devolveu array');
  return arr;
}

/**
 * escaneia ~/.claude/projects/<dirs com filter>/*.jsonl com mtime >= sinceMs.
 * 1 arquivo JSONL = 1 sessão → {id, entries, pr_mentions} (menções /pull/N do
 * transcript inteiro alimentam o Sinal 2 do join).
 */
export function scanSessionsLocal({ projectsDir, projectFilter = 'oimpresso', sinceMs = 0 } = {}) {
  const root = projectsDir || join(homedir(), '.claude', 'projects');
  const sessions = [];
  if (!existsSync(root)) return { sessions, files: 0 };
  for (const dir of readdirSync(root)) {
    if (projectFilter && !dir.toLowerCase().includes(projectFilter.toLowerCase())) continue;
    const dirPath = join(root, dir);
    let names;
    try { names = readdirSync(dirPath); } catch { continue; }
    for (const name of names) {
      if (!name.endsWith('.jsonl')) continue;
      const filePath = join(dirPath, name);
      let st;
      try { st = statSync(filePath); } catch { continue; }
      if (st.mtimeMs < sinceMs) continue;
      let data;
      try { data = readFileSync(filePath, 'utf8'); } catch { continue; }
      const entries = [];
      for (const line of data.split('\n')) {
        const e = parseUsageLine(line);
        if (e) entries.push(e);
      }
      if (!entries.length) continue;
      sessions.push({ id: name.replace(/\.jsonl$/, ''), entries, pr_mentions: extractPrMentions(data) });
    }
  }
  return { sessions, files: sessions.length };
}

// ── render ────────────────────────────────────────────────────────────────────────
const usd = (v) => (v == null ? 'n/d' : `$${v.toFixed(2)}`);
const kTok = (n) => `${Math.round(n / 1000)}k`;

export function renderHuman(r) {
  const L = [];
  L.push('═══════════════════════════════════════════════════════════════');
  L.push(' CUSTO POR PR — agente (USD estimado · advisory) · ' + r.generated);
  L.push(` janela: últimos ${r.janela.prs} PRs mergeados ${r.janela.marker}`);
  L.push('═══════════════════════════════════════════════════════════════');
  L.push('');
  L.push(`  MEDIANA por PR : ${usd(r.custo.mediana_usd_por_pr)}   TOTAL (matched): ${usd(r.custo.total_usd_matched)}`);
  L.push(`  JOIN ..........: ${r.join.matched_por_branch} por branch · ${r.join.matched_por_citacao} por citação /pull/N`);
  L.push(`  SEM MATCH .....: ${r.join.sem_match}/${r.janela.prs} (${r.join.sem_match_pct ?? 'n/d'}%) — buraco do join, declarado`);
  if (r.custo.usd_fora_da_janela) L.push(`  fora da janela : ${usd(r.custo.usd_fora_da_janela)} em branches sem PR nesta janela`);
  if (r.join.modelos_desconhecidos.length) L.push(`  modelos sem preço: ${r.join.modelos_desconhecidos.join(', ')}`);
  L.push('');
  for (const p of r.por_pr) {
    L.push(p.matched
      ? `  #${p.pr}  ${usd(p.usd)}${p.usd_incompleto ? ' (parcial)' : ''}  ${kTok(p.tokens)} tok  [${p.sinais.join('+')}]  ${String(p.title).slice(0, 55)}`
      : `  #${p.pr}  — sem match —              ${String(p.title).slice(0, 55)}`);
  }
  L.push('');
  L.push('▸ HONESTIDADE (o número é PROXY — o que ele não vê)');
  for (const g of r.gaps) L.push(`  • ${g}`);
  L.push('═══════════════════════════════════════════════════════════════');
  return L.join('\n');
}

export function renderBriefMd(r) {
  const L = [];
  L.push('### Custo por PR — agente (USD estimado · advisory)');
  L.push('');
  L.push(`_Janela: últimos ${r.janela.prs} PRs mergeados \`${r.janela.marker}\` · gerado ${r.generated} · fonte JSONL local (G3)._`);
  L.push('');
  L.push('| Métrica | Valor | Leitura |');
  L.push('|---|---|---|');
  L.push(`| Mediana por PR | **${usd(r.custo.mediana_usd_por_pr)}** | tendência por cycle é o sinal |`);
  L.push(`| Total (matched) | **${usd(r.custo.total_usd_matched)}** | só PRs com sessão local na branch |`);
  L.push(`| PRs sem match | **${r.join.sem_match}/${r.janela.prs}** (${r.join.sem_match_pct ?? 'n/d'}%) | buraco do join, declarado (não escondido) |`);
  L.push(`| Join por sinal | ${r.join.matched_por_branch} branch · ${r.join.matched_por_citacao} citação | citação = /pull/N no transcript (G6, diluído) |`);
  if (r.custo.top_prs.length) {
    L.push('');
    L.push('Top custo: ' + r.custo.top_prs.map((t) => `#${t.pr} ${usd(t.usd)}`).join(' · '));
  }
  L.push('');
  L.push('> Proxy (gaps no `--json`): custo é da branch, fonte é local, preços snapshot 2026-07-12. NÃO é gate.');
  return L.join('\n');
}

// ── entry-point ───────────────────────────────────────────────────────────────────
function argVal(argv, flag, def) { const i = argv.indexOf(flag); return i >= 0 && argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[i + 1] : def; }

if (import.meta.url === pathToFileURL(process.argv[1] || '').href) {
  const argv = process.argv.slice(2);
  if (argv.includes('--selftest')) {
    const test = new URL('./agent-cost-per-pr.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [fileURLToPath(test)], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }

  const renderSnapshot = argVal(argv, '--render-snapshot', null);
  if (renderSnapshot) {
    const r = JSON.parse(readFileSync(renderSnapshot, 'utf8'));
    const idadeDias = Math.floor((Date.now() - Date.parse(r.generated)) / 86400000);
    process.stdout.write(renderBriefMd(r) + '\n');
    if (idadeDias > 14) process.stdout.write(`\n> ⚠️ snapshot com ${idadeDias}d — rode local: \`node scripts/governance/agent-cost-per-pr.mjs --snapshot\` e commite.\n`);
    process.exit(0);
  }

  const marker = argVal(argv, '--marker', DEFAULT_MARKER);
  const prWindow = Number(argVal(argv, '--prs', String(DEFAULT_PR_WINDOW))) || DEFAULT_PR_WINDOW;
  const fixture = argVal(argv, '--fixture', null);

  let prs, sessions;
  try {
    if (fixture) {
      const f = JSON.parse(readFileSync(fixture, 'utf8'));
      prs = f.prs; sessions = f.sessions;
    } else {
      prs = fetchMergedPRsViaGh({ repo: argVal(argv, '--repo', null) });
      const agentMerged = prs.filter((p) => isAgentPR(p, marker) && p.mergedAt)
        .sort((a, b) => Date.parse(b.mergedAt) - Date.parse(a.mergedAt)).slice(0, prWindow);
      const minCreated = Math.min(...agentMerged.map((p) => Date.parse(p.createdAt)).filter(Number.isFinite));
      const sinceMs = Number.isFinite(minCreated) ? minCreated - 7 * 86400000 : 0; // margem 7d (sessão precede PR)
      const scan = scanSessionsLocal({
        projectsDir: argVal(argv, '--projects-dir', null),
        projectFilter: argVal(argv, '--project-filter', 'oimpresso'),
        sinceMs,
      });
      sessions = scan.sessions;
      console.error(`[agent-cost-per-pr] ${scan.files} sessão(ões) JSONL com usage escaneadas`);
    }
  } catch (e) {
    console.error(`[agent-cost-per-pr] falha ao carregar fontes: ${e.message}`);
    console.error(fixture ? '  (fixture inválido)' : '  (gh ausente/sem auth ou ~/.claude/projects inacessível? use --fixture pra rodar offline)');
    process.exit(1);
  }
  if (!Array.isArray(prs) || !Array.isArray(sessions)) { console.error('[agent-cost-per-pr] fontes não são arrays'); process.exit(1); }

  const r = buildReport({ prs, sessions, marker, prWindow });

  if (argv.includes('--snapshot')) {
    const out = argVal(argv, '--snapshot', null) || join(dirname(fileURLToPath(import.meta.url)), 'data', 'agent-cost-per-pr-snapshot.json');
    mkdirSync(dirname(out), { recursive: true });
    writeFileSync(out, JSON.stringify(r, null, 2) + '\n');
    console.error(`[agent-cost-per-pr] snapshot gravado em ${out}`);
  }

  if (argv.includes('--json')) process.stdout.write(JSON.stringify(r, null, 2) + '\n');
  else if (argv.includes('--brief')) process.stdout.write(renderBriefMd(r) + '\n');
  else process.stdout.write(renderHuman(r) + '\n');
  process.exit(0);
}
