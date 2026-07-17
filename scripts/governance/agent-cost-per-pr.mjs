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
 * ── A UNIDADE DE CUSTO É A SESSÃO (revisão 2026-07-17, tudo medido) ──────────────
 *   Sinal 1 (autoria): alguma branch da SESSÃO == headRefName de um PR da janela.
 *   Sinal 2 (fallback): a SESSÃO cita `/pull/N` no transcript (o `gh pr create`
 *     imprime a URL). Só vale sem sinal de branch — citar ≠ ser autor (G6).
 *   Casado o alvo, o custo INTEIRO da sessão (inclusive o da branch da worktree,
 *   onde o trabalho de fato aconteceu) vai pros PRs que ela produziu, dividido.
 *
 *   Por que NÃO é branch-por-mensagem: o `gitBranch` é gravado por mensagem, e o
 *   padrão real é gastar na worktree e criar a branch de tópico só no fim — o
 *   branch do PR marcava só a CAUDA. O desenho antigo atribuía a cauda e DESCARTAVA
 *   o corpo: PRs de sessão inteira saíam a $4,22 ao lado de $444 casados por
 *   citação (medido 2026-07-17 → hoje $37,64 e $55,57; a mediana virou sinal).
 *
 * ── COBERTURA É DO LADO DO DINHEIRO (FinOps allocation coverage) ─────────────────
 *   O `sem_match_pct` mede o lado do PR e dizia ~0% ("join perfeito") enquanto ~88%
 *   do dinheiro não tinha dono. A manchete agora é `cobertura_alocacao_pct` =
 *   atribuído ÷ ESCANEADO, e o resíduo vem DECOMPOSTO por categoria.
 *   Histórico honesto: 3,2% (snapshot 07-13) → 81% (07-17), pela soma de janela
 *   coerente (G7) + sessão-como-unidade (G2) + cap do fetch declarado.
 *
 * ── GAPS (impressos + em --json.gaps) ────────────────────────────────────────────
 *   G1 branch marca só a cauda da sessão → unidade é a sessão (G2).
 *   G2 custo da SESSÃO inteira: superconta exploração descartada dentro de sessão
 *      que também entregou PR.
 *   G3 fonte = JSONL DESTA máquina: sessões cloud (claude.ai/code), CI e outros
 *      devs NÃO aparecem → SUBconta. Rodar na máquina de cada dev soma visão.
 *   G4 preços hardcoded (snapshot 2026-07-12, tabela oficial Anthropic): modelo
 *      desconhecido → tokens contados, USD null + declarado. Não modela server
 *      tools nem fast-mode premium.
 *   G5 CI não enxerga o JSONL local: o workflow semanal roda o selftest hermético
 *      e renderiza o ÚLTIMO snapshot commitado (--snapshot local), com staleness
 *      declarada. Por isso `--pr N` roda LOCAL (o número nasce onde o dado mora).
 *   G6 citar /pull/N ≠ ser autor (sessão que só LÊ PRs também cita) → fallback.
 *   G7 janela COERENTE: sessões e PRs na MESMA janela de tempo (`--days`).
 *   G8 o resíduo NÃO é buraco de join: é sessão que não produziu PR (5 das 6
 *      branches de maior resíduo não têm PR algum no GitHub — medido 07-17).
 *
 * Modos:
 *   (default)          texto humano
 *   --json             relatório completo (com gaps)
 *   --brief            seção markdown pro brief semanal
 *   --pr <N>           bloco markdown do custo DESTE PR, pro corpo do próprio PR
 *   --days <N>         janela de tempo, os DOIS lados (default 14) — G7
 *   --prs <N>          quantos PRs aparecem na TABELA (default 20; não afeta a
 *                      atribuição, que usa todos os PRs da janela)
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
/** janela de tempo (dias) — a MESMA pros dois lados: sessões escaneadas e PRs do universo (G7). */
export const DEFAULT_DAYS = 14;

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
  'G1 sinal-branch (gitBranch==headRefName): o gitBranch é gravado POR MENSAGEM, e o padrão real do projeto é gastar na branch da worktree e criar a branch de tópico só no fim — o branch do PR marca apenas a CAUDA da sessão. Por isso a unidade de custo é a SESSÃO, não a mensagem (G2).',
  'G2 a SESSÃO é a unidade: o custo inteiro dela vai pros PRs que ela produziu (dividido igualmente). Superconta exploração descartada dentro de sessão que também entregou PR.',
  'G3 fonte = JSONL desta máquina: cloud/CI/outros devs não aparecem → subconta.',
  'G4 preços hardcoded (2026-07-12); modelo desconhecido → USD null declarado; sem breakdown de cache → tudo vira write 5m; não modela server tools/fast-mode.',
  'G5 CI só roda selftest + snapshot commitado (staleness declarada); medição viva é local.',
  'G6 sinal-citação (/pull/N no transcript): citar ≠ ser autor (sessão que só LÊ PRs também cita) — só vale como fallback quando não há sinal de branch; custo diluído por N PRs citados.',
  'G7 janela COERENTE: sessões e PRs usam a MESMA janela de tempo. Sessão iniciada antes da janela entra pelo mtime e pode ter produzido PR fora dela → cai no resíduo, não no numerador. (Antes de 2026-07-17 o scan cobria ~8.8d contra ~1.8d de PRs — ~28 pontos do "órfão" eram esse artefato de bookkeeping.)',
  'G8 o RESÍDUO não é buraco de join: a maior parte é sessão que não produziu PR nenhum (exploração, análise, sub-agente cujo pai commita, PR ainda aberto). Medido 2026-07-17: 5 das 6 branches de maior resíduo não têm PR algum no GitHub — não há aresta pra achar, nem por SHA nem por API.',
];

/** categoria do resíduo de uma sessão sem PR na janela (honestidade do denominador). */
export function categoriaResiduo(porBranch) {
  const nomes = [...porBranch.keys()];
  if (nomes.length && nomes.every((b) => b === 'main' || b === 'HEAD')) {
    return 'main/HEAD (trabalho fora de branch de PR)';
  }
  return 'sessão sem PR na janela (exploração/análise/sub-agente/PR aberto)';
}

/**
 * monta o relatório (puro — sem I/O).
 *
 * A unidade de custo é a SESSÃO (1 sessão = 1 unidade de trabalho que produz N PRs),
 * NÃO a branch-por-mensagem: o gasto acontece na branch da worktree e só a cauda
 * pós-`checkout -B` carrega o branch do PR (G1). Atribuir por mensagem fazia um PR de
 * sessão inteira aparecer com $4,22 enquanto o irmão casado por citação aparecia com
 * $444 — a mediana virava ruído (medido 2026-07-17).
 *
 * Janela COERENTE (G7): o universo de ATRIBUIÇÃO são TODOS os PRs do agente mergeados
 * na janela de `days`; `prWindow` só decide quantos aparecem na tabela. Antes, o scan
 * de sessões cobria ~8.8d contra uma janela de ~1.8d de PRs — ~28 pontos do "órfão"
 * eram esse artefato.
 *
 * Cobertura é do lado do DINHEIRO (FinOps allocation coverage), não do lado do PR: o
 * `sem_match_pct` antigo dizia 0% (todo PR recebia algum custo) enquanto ~88% do
 * dinheiro não tinha dono. Denominador = custo ESCANEADO.
 *
 * @param {{prs:any[], sessions:Array<{id?:string, entries:any[], pr_mentions?:number[]}>,
 *          marker?:string, prWindow?:number, days?:number, nowIso?:string, generated?:string}} o
 */
export function buildReport({ prs, sessions, marker = DEFAULT_MARKER, prWindow = DEFAULT_PR_WINDOW, days = DEFAULT_DAYS, nowIso, generated, fonteTruncada = false }) {
  const now = nowIso ? Date.parse(nowIso) : (generated ? Date.parse(generated) : Date.now());
  const since = now - days * 86400000;

  const universo = prs
    .filter((p) => isAgentPR(p, marker) && p.mergedAt && Date.parse(p.mergedAt) >= since)
    .sort((a, b) => Date.parse(b.mergedAt) - Date.parse(a.mergedAt));
  const exibidos = universo.slice(0, prWindow);
  const branchesDosPRs = new Map(universo.map((p) => [p.headRefName, p.number]));
  const numerosDosPRs = new Set(universo.map((p) => p.number));

  const modelosDesconhecidos = new Set();
  let semBranchMsgs = 0;
  let usdEscaneado = 0;
  /** categoria → usd (o resíduo é DECOMPOSTO, não só declarado) */
  const residuo = new Map();
  /** por PR: { usd, tokens, incompleto, sinais:Set } */
  const acum = new Map(universo.map((p) => [p.number, { usd: 0, tokens: 0, incompleto: false, sinais: new Set() }]));

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

    const total = custoDoAgregado(aggregatePorModelo(entries));
    usdEscaneado += total.usd;

    // Sinal 1 (AUTORIA, forte): alguma branch desta sessão é o headRef de um PR da janela.
    const alvosPorBranch = new Set();
    for (const b of porBranch.keys()) {
      const n = branchesDosPRs.get(b);
      if (n != null) alvosPorBranch.add(n);
    }
    // Sinal 2 (fallback, fraco): a sessão citou /pull/N. Só vale sem sinal de branch (G6).
    const alvos = alvosPorBranch.size
      ? alvosPorBranch
      : new Set((sess.pr_mentions || []).filter((n) => numerosDosPRs.has(n)));
    const sinal = alvosPorBranch.size ? 'branch' : 'citacao';

    if (!alvos.size) {
      const cat = categoriaResiduo(porBranch);
      residuo.set(cat, (residuo.get(cat) || 0) + total.usd);
      continue;
    }
    // a SESSÃO é a unidade: o custo INTEIRO (inclusive o da branch da worktree, onde o
    // trabalho de fato aconteceu) vai pros PRs que ela produziu, dividido igualmente.
    for (const n of alvos) {
      const a = acum.get(n);
      a.usd += total.usd / alvos.size;
      a.tokens += Math.round(total.tokens / alvos.size);
      a.incompleto ||= total.incompleto;
      a.sinais.add(sinal);
    }
  }

  const linhaPR = (pr) => {
    const a = acum.get(pr.number);
    const matched = a.sinais.size > 0;
    return {
      pr: pr.number, title: pr.title, branch: pr.headRefName, matched,
      sinais: [...a.sinais],
      tokens: matched ? a.tokens : 0,
      usd: matched ? round2(a.usd) : null,
      usd_incompleto: (matched && a.incompleto) || undefined,
    };
  };
  const porPR = exibidos.map(linhaPR);
  const todosPR = universo.map(linhaPR);

  const matched = todosPR.filter((p) => p.matched);
  const semMatch = todosPR.length - matched.length;
  const custos = matched.filter((p) => p.usd != null && !p.usd_incompleto).map((p) => p.usd);
  const top = [...matched].filter((p) => p.usd != null).sort((a, b) => b.usd - a.usd).slice(0, 3);
  const usdAtribuido = matched.reduce((a, p) => a + (p.usd || 0), 0);
  const usdResiduo = [...residuo.values()].reduce((a, v) => a + v, 0);

  return {
    ok: true,
    generated: generated || new Date(now).toISOString().slice(0, 10),
    janela: {
      dias: days, desde: new Date(since).toISOString().slice(0, 10),
      prs_no_universo: universo.length, prs_exibidos: porPR.length, marker,
      // se o fetch bateu no cap, o universo está incompleto e o RESÍDUO vem inflado
      // por artefato (PR ausente = sessão sem dono). Declarado, nunca silencioso.
      fonte_truncada: fonteTruncada || undefined,
    },
    custo: {
      total_usd_escaneado: round2(usdEscaneado),
      total_usd_atribuido: round2(usdAtribuido),
      cobertura_alocacao_pct: usdEscaneado ? round2((usdAtribuido / usdEscaneado) * 100) : null,
      mediana_usd_por_pr: round2(median(custos)),
      top_prs: top.map((p) => ({ pr: p.pr, usd: p.usd, title: p.title })),
    },
    residuo: {
      usd: round2(usdResiduo),
      pct: usdEscaneado ? round2((usdResiduo / usdEscaneado) * 100) : null,
      por_categoria: Object.fromEntries(
        [...residuo.entries()].sort((a, b) => b[1] - a[1]).map(([k, v]) => [k, round2(v)])
      ),
    },
    join: {
      sem_match: semMatch,
      sem_match_pct: todosPR.length ? round2((semMatch / todosPR.length) * 100) : null,
      matched_por_branch: todosPR.filter((p) => p.sinais.includes('branch')).length,
      matched_por_citacao: todosPR.filter((p) => p.sinais.includes('citacao')).length,
      msgs_sem_branch: semBranchMsgs,
      modelos_desconhecidos: [...modelosDesconhecidos],
    },
    por_pr: porPR,
    confianca: 'proxy (JSONL local → SESSÃO → PR por branch OU citação /pull/N; ver gaps)',
    gaps: GAPS,
  };
}

// ── camada de I/O (só quando invocado direto sem --fixture) ──────────────────────

/**
 * cap do fetch de PRs. Medido 2026-07-17: o repo mergeia ~30 PRs/dia, então `limit:200`
 * alcançava só ~4 dias — com janela de 14d o universo vinha 4× menor (104 vs 435 PRs
 * [CC]) e TODO PR ausente virava resíduo falso. O cap continua existindo; o que não pode
 * é ser SILENCIOSO — `fonte_truncada` é publicado no relatório (doutrina: no silent caps).
 */
export const PR_FETCH_LIMIT = 1000;

export function fetchMergedPRsViaGh({ repo, limit = PR_FETCH_LIMIT } = {}) {
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

/**
 * Meia-vida MEDIDA desta métrica: 4 dias. Não é chute — em 2026-07-17 a grade de réguas
 * citou "matched_por_branch: 0 · 96,8% órfão" como fato vivo; era o snapshot de 07-13, e
 * ao vivo o número era 12/20. Quatro dias bastaram pra INVERTER o diagnóstico e quase
 * fabricar o conserto errado (fonte por SHA — hoje lápide no §5 das proibições).
 */
export const IDADE_SUSPEITA_DIAS = 3;

/**
 * Linha de idade — SEMPRE colada nos números, nunca em rodapé.
 *
 * Por que não bastava o `generated`: ele JÁ existia no JSON e não impediu nada. Data
 * exige que o leitor faça a subtração contra hoje; IDADE não exige nada. E o limiar de
 * 14d do --render-snapshot não disparou porque o snapshot tinha 4 dias — "fresco" pela
 * régua e já errado. Por isso o alerta é calibrado pela meia-vida medida (4d), não por
 * um 14 arbitrário, e a idade aparece mesmo quando é 0 (dizer "ao vivo" é informação).
 */
/**
 * Aviso gravado como PRIMEIRA chave do snapshot (`_LEIA_PRIMEIRO`).
 * O leitor que rotou o diagnóstico em 2026-07-17 foi um agente lendo o JSON — markdown
 * bonito não o alcança. A defesa tem que morar no arquivo que ele abre.
 */
export function avisoSnapshot(generated) {
  return `SNAPSHOT de ${generated} — retrato, NÃO medição viva. Esta métrica já INVERTEU em 4 dias `
    + `(a grade de 2026-07-17 citou "matched_por_branch: 0 · 96,8% órfão" daqui como fato; ao vivo era 12/20 e o `
    + `diagnóstico estava errado). NÃO cite nenhum número deste arquivo sem rodar antes: `
    + `node scripts/governance/agent-cost-per-pr.mjs --json`;
}

export function linhaIdade(idadeDias) {
  if (idadeDias == null || !Number.isFinite(idadeDias)) return '⚠️ IDADE DESCONHECIDA — não dá pra saber se estes números valem hoje.';
  if (idadeDias <= 0) return '📍 MEDIDO AGORA — AO VIVO (não é retrato).';
  if (idadeDias <= IDADE_SUSPEITA_DIAS) return `⏳ Medido há ${idadeDias}d — retrato, não medição viva.`;
  return `⚠️ MEDIDO HÁ ${idadeDias}d — retrato VELHO, não medição viva. Esta métrica já inverteu em 4 dias (a grade de 2026-07-17 citou "96,8% órfão" de um snapshot de 07-13; ao vivo era outro número). NÃO cite os valores abaixo sem rodar \`node scripts/governance/agent-cost-per-pr.mjs\` antes.`;
}

export function renderHuman(r, idadeDias) {
  const L = [];
  L.push('═══════════════════════════════════════════════════════════════');
  L.push(' CUSTO POR PR — agente (USD estimado · advisory) · ' + r.generated);
  L.push(` janela ${r.janela.dias}d desde ${r.janela.desde} · ${r.janela.prs_no_universo} PRs ${r.janela.marker} mergeados`);
  L.push('═══════════════════════════════════════════════════════════════');
  L.push('');
  L.push('  ' + linhaIdade(idadeDias));
  L.push('');
  if (r.janela.fonte_truncada) {
    L.push('  ⚠️  FONTE TRUNCADA: o fetch bateu no cap de PRs — o universo NÃO cobre a');
    L.push('      janela inteira. PR ausente = sessão sem dono, então o resíduo abaixo');
    L.push(`      está INFLADO por artefato. Reduza --days ou suba PR_FETCH_LIMIT.`);
    L.push('');
  }
  L.push(`  COBERTURA DE ALOCAÇÃO : ${r.custo.cobertura_alocacao_pct ?? 'n/d'}%  — quanto do dinheiro tem dono`);
  L.push(`     ${usd(r.custo.total_usd_atribuido)} atribuído  de  ${usd(r.custo.total_usd_escaneado)} escaneado`);
  L.push(`  MEDIANA por PR ......: ${usd(r.custo.mediana_usd_por_pr)}   (${r.join.matched_por_branch} por branch · ${r.join.matched_por_citacao} por citação)`);
  L.push(`  PRs sem custo .......: ${r.join.sem_match}/${r.janela.prs_no_universo} (${r.join.sem_match_pct ?? 'n/d'}%)`);
  if (r.join.modelos_desconhecidos.length) L.push(`  modelos sem preço ...: ${r.join.modelos_desconhecidos.join(', ')}`);
  L.push('');
  L.push(`▸ RESÍDUO — ${usd(r.residuo.usd)} (${r.residuo.pct ?? 'n/d'}%) sem PR pra receber. NÃO é buraco de join (G8):`);
  for (const [cat, v] of Object.entries(r.residuo.por_categoria)) L.push(`  • ${usd(v).padStart(9)}  ${cat}`);
  L.push('');
  L.push(`▸ TOP ${r.por_pr.length} PRs mais recentes da janela`);
  for (const p of r.por_pr) {
    L.push(p.matched
      ? `  #${p.pr}  ${usd(p.usd)}${p.usd_incompleto ? ' (parcial)' : ''}  ${kTok(p.tokens)} tok  [${p.sinais.join('+')}]  ${String(p.title).slice(0, 55)}`
      : `  #${p.pr}  — sem custo —             ${String(p.title).slice(0, 55)}`);
  }
  L.push('');
  L.push('▸ HONESTIDADE (o número é PROXY — o que ele não vê)');
  for (const g of r.gaps) L.push(`  • ${g}`);
  L.push('═══════════════════════════════════════════════════════════════');
  return L.join('\n');
}

export function renderBriefMd(r, idadeDias) {
  const L = [];
  L.push('### Custo por PR — agente (USD estimado · advisory)');
  L.push('');
  L.push(`> ${linhaIdade(idadeDias)}`);
  L.push('');
  L.push(`_Janela ${r.janela.dias}d desde ${r.janela.desde} · ${r.janela.prs_no_universo} PRs \`${r.janela.marker}\` mergeados · gerado ${r.generated} · fonte JSONL local (G3)._`);
  L.push('');
  if (r.janela.fonte_truncada) {
    L.push('> ⚠️ **FONTE TRUNCADA** — o fetch bateu no cap de PRs; o universo não cobre a janela inteira, então o resíduo abaixo está inflado por artefato (PR ausente = sessão sem dono).');
    L.push('');
  }
  L.push('| Métrica | Valor | Leitura |');
  L.push('|---|---|---|');
  L.push(`| **Cobertura de alocação** | **${r.custo.cobertura_alocacao_pct ?? 'n/d'}%** | ${usd(r.custo.total_usd_atribuido)} de ${usd(r.custo.total_usd_escaneado)} tem dono (FinOps: crawl=50%) |`);
  L.push(`| Mediana por PR | **${usd(r.custo.mediana_usd_por_pr)}** | tendência por cycle é o sinal, não o absoluto |`);
  L.push(`| Resíduo sem PR | **${usd(r.residuo.usd)}** (${r.residuo.pct ?? 'n/d'}%) | sessão que não produziu PR — **não é buraco de join** (G8) |`);
  L.push(`| Join por sinal | ${r.join.matched_por_branch} branch · ${r.join.matched_por_citacao} citação | branch = autoria; citação = fallback diluído (G6) |`);
  if (Object.keys(r.residuo.por_categoria).length) {
    L.push('');
    L.push('Resíduo por categoria: ' + Object.entries(r.residuo.por_categoria).map(([c, v]) => `${usd(v)} ${c}`).join(' · '));
  }
  if (r.custo.top_prs.length) {
    L.push('');
    L.push('Top custo: ' + r.custo.top_prs.map((t) => `#${t.pr} ${usd(t.usd)}`).join(' · '));
  }
  L.push('');
  L.push('> Proxy (gaps no `--json`): unidade = sessão, fonte é local, preços snapshot 2026-07-12. NÃO é gate.');
  return L.join('\n');
}

/**
 * bloco markdown do custo de UM PR, pro corpo/comentário do próprio PR (`--pr N`).
 *
 * Por que aqui e não no CI: o CI NÃO enxerga o JSONL local (G3/G5) — o número só existe
 * na máquina que abriu o PR. Colar um agregado do cron no PR seria teatro (não é o custo
 * DESTE PR). Este bloco é o número real do PR, medido onde o dado mora.
 * RELATO, não gate (ADR 0271/0314): nada aqui bloqueia merge.
 */
export function renderPrBlockMd(r, p, idadeDias) {
  const L = [];
  L.push('<!-- agent-cost-per-pr -->');
  L.push('**Custo estimado deste PR** (advisory · não bloqueia): ' + (p && p.matched
    ? `**${usd(p.usd)}**${p.usd_incompleto ? ' (parcial — modelo sem preço)' : ''} · ${kTok(p.tokens)} tok · sinal \`${p.sinais.join('+')}\``
    : '_sem sessão local casada — não medido (G1/G3)_'));
  L.push('');
  L.push(`${linhaIdade(idadeDias)}`);
  L.push('');
  L.push(`_Mediana da janela ${r.janela.dias}d: **${usd(r.custo.mediana_usd_por_pr)}**/PR · cobertura de alocação **${r.custo.cobertura_alocacao_pct ?? 'n/d'}%** (${usd(r.custo.total_usd_atribuido)} de ${usd(r.custo.total_usd_escaneado)}). Proxy do JSONL local; unidade = sessão (G2). Sem valores em reais — custo em USD._`);
  if (r.janela.fonte_truncada) L.push('\n> ⚠️ fonte truncada — universo incompleto, mediana/cobertura subestimadas.');
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
    // idade é calculada no RENDER, nunca gravada: guardada no arquivo, ela mentiria
    // "há 0 dias" pra sempre. É o único jeito de envelhecer junto com o dado.
    const idadeDias = Math.floor((Date.now() - Date.parse(r.generated)) / 86400000);
    process.stdout.write(renderBriefMd(r, idadeDias) + '\n');
    if (idadeDias > IDADE_SUSPEITA_DIAS) {
      process.stdout.write(`\n> Pra atualizar: \`node scripts/governance/agent-cost-per-pr.mjs --snapshot\` (LOCAL — o CI não enxerga o JSONL, G5) e commite \`${renderSnapshot}\`.\n`);
    }
    process.exit(0);
  }

  const marker = argVal(argv, '--marker', DEFAULT_MARKER);
  const prWindow = Number(argVal(argv, '--prs', String(DEFAULT_PR_WINDOW))) || DEFAULT_PR_WINDOW;
  const days = Number(argVal(argv, '--days', String(DEFAULT_DAYS))) || DEFAULT_DAYS;
  const fixture = argVal(argv, '--fixture', null);

  let prs, sessions, fonteTruncada = false;
  try {
    if (fixture) {
      const f = JSON.parse(readFileSync(fixture, 'utf8'));
      prs = f.prs; sessions = f.sessions;
    } else {
      prs = fetchMergedPRsViaGh({ repo: argVal(argv, '--repo', null) });
      // JANELA COERENTE (G7): o scan de sessões usa a MESMA janela do universo de PRs.
      // Antes, o scan ia até `minCreated - 7d` (margem "sessão precede PR"), cobrindo
      // ~8.8d de gasto contra ~1.8d de PRs — ~28 pontos do "órfão" eram esse artefato.
      const sinceMs = Date.now() - days * 86400000;
      // truncada = bateu no cap E NÃO alcançou o início da janela. Bater no cap sozinho
      // não é defeito (o excedente é só mais velho que a janela) — seria falso-positivo.
      const maisAntigoPuxado = Math.min(...prs.map((p) => Date.parse(p.mergedAt)).filter(Number.isFinite));
      const alcancouJanela = Number.isFinite(maisAntigoPuxado) && maisAntigoPuxado <= sinceMs;
      fonteTruncada = prs.length >= PR_FETCH_LIMIT && !alcancouJanela;
      if (fonteTruncada) console.error(`[agent-cost-per-pr] ⚠️ fetch bateu no cap de ${PR_FETCH_LIMIT} PRs SEM alcançar ${days}d — universo incompleto`);
      const scan = scanSessionsLocal({
        projectsDir: argVal(argv, '--projects-dir', null),
        projectFilter: argVal(argv, '--project-filter', 'oimpresso'),
        sinceMs,
      });
      sessions = scan.sessions;
      console.error(`[agent-cost-per-pr] ${scan.files} sessão(ões) JSONL com usage escaneadas (janela ${days}d)`);
    }
  } catch (e) {
    console.error(`[agent-cost-per-pr] falha ao carregar fontes: ${e.message}`);
    console.error(fixture ? '  (fixture inválido)' : '  (gh ausente/sem auth ou ~/.claude/projects inacessível? use --fixture pra rodar offline)');
    process.exit(1);
  }
  if (!Array.isArray(prs) || !Array.isArray(sessions)) { console.error('[agent-cost-per-pr] fontes não são arrays'); process.exit(1); }

  // `--pr N`: bloco do custo DESTE PR, pro corpo do próprio PR (o número onde se decide).
  const prAlvo = Number(argVal(argv, '--pr', ''));
  if (prAlvo) {
    const rp = buildReport({ prs, sessions, marker, prWindow: Number.MAX_SAFE_INTEGER, days, fonteTruncada });
    process.stdout.write(renderPrBlockMd(rp, rp.por_pr.find((x) => x.pr === prAlvo), 0) + '\n');
    process.exit(0);
  }

  const r = buildReport({ prs, sessions, marker, prWindow, days, fonteTruncada });

  if (argv.includes('--snapshot')) {
    const out = argVal(argv, '--snapshot', null) || join(dirname(fileURLToPath(import.meta.url)), 'data', 'agent-cost-per-pr-snapshot.json');
    mkdirSync(dirname(out), { recursive: true });
    // O arquivo se AUTO-DENUNCIA. A grade de 2026-07-17 leu ESTE JSON (não o markdown) e
    // citou "96,8% órfão" como fato vivo: o `generated` estava lá, mas era só uma data no
    // meio do objeto — exigia o leitor subtrair contra hoje, e ninguém subtraiu. Este
    // aviso é a PRIMEIRA chave que qualquer leitor (humano ou agente) encontra.
    writeFileSync(out, JSON.stringify({ _LEIA_PRIMEIRO: avisoSnapshot(r.generated), ...r }, null, 2) + '\n');
    console.error(`[agent-cost-per-pr] snapshot gravado em ${out}`);
  }

  if (argv.includes('--json')) process.stdout.write(JSON.stringify(r, null, 2) + '\n');
  else if (argv.includes('--brief')) process.stdout.write(renderBriefMd(r, 0) + '\n');
  else process.stdout.write(renderHuman(r, 0) + '\n');
  process.exit(0);
}
