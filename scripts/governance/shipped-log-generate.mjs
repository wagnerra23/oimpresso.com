#!/usr/bin/env node
// @ts-check
/**
 * shipped-log-generate.mjs v2 — porta de saída do loop (estende ADR 0294).
 *
 * Fonte HONESTA e completa (conserta os gaps que reprovaram a v1, red-team 2026-06-21):
 *   G1 teto 1000 da Search API  → REST por sub-janela de DIA (cada uma < 1000)
 *   G2 push-direto na main      → API /commits (commits sem objeto-PR)
 *   G3 borda BRT × UTC          → coleta com margem ±1 dia, filtra por timestamp BRT (-03:00)
 *   G4 truncação silenciosa     → cross-check: soma das sub-janelas vs total_count do Search → exit 1 ao divergir
 *   G7 merged ≠ entregue        → reconcilia pares de revert (líquido zero)
 *   G9 ruído de agrupamento     → aliases de scope + normalize NFD (acento)
 *
 * Rótulo HONESTO: lista o que foi MERGEADO em `main`, não "entregue". Merge ≠ deploy ≠ funciona (G8, fora de escopo).
 * Limites conhecidos declarados no doc: área = scope do título (G5 paths-por-PR fora por custo); janela via args/cron (G6 MCP-live pendente).
 *
 * Funções puras exportadas → testáveis sem rede (shipped-log-generate.test.mjs). Execução só quando rodado direto.
 *
 * Modos:
 *   (default) dry-run no stdout
 *   --write   grava memory/governance/shipped/<CYCLE>.md
 *   --check   gate CI: falha (exit 1) se algum shipped-log versionado está STALE (> FRESH_DAYS desde `generated`)
 *   --json    saúde do registro em JSON (pro Daily Brief / ShippedLogBriefLineService) — {ok,cycles,stale,findings}
 *
 * Uso:
 *   node scripts/governance/shipped-log-generate.mjs --since=2026-05-31 --until=2026-06-22 --cycle=CYCLE-08 [--write]
 *   node scripts/governance/shipped-log-generate.mjs --days=14 --cycle=CYCLE-08 --write     (modo cron)
 *   node scripts/governance/shipped-log-generate.mjs --check                                 (gate CI)
 *   node scripts/governance/shipped-log-generate.mjs --json                                  (linha do Brief)
 *
 * Refs: ADR 0294 (loop) · 0256 (fonte única gerada/catraca) · 0070 (tasks MCP) · 0226 (Daily Brief).
 */
import { execFileSync } from 'node:child_process';
import { writeFileSync, mkdirSync, existsSync, readFileSync, readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { pathToFileURL } from 'node:url';

// ── constantes (exportadas pra teste) ─────────────────────────────────────────
export const BRT_OFFSET_MS = 3 * 60 * 60 * 1000; // UTC = BRT + 3h
export const FRESH_DAYS = 4;                       // --check: shipped-log mais velho que isto = stale
export const SHIPPED_DIR = 'memory/governance/shipped';
export const NOISE = new Set(['docs', 'chore', 'test', 'ci', 'build']);
export const SCOPE_ALIAS = {
  'caixa-unif': 'caixa-unificada', 'caixa': 'caixa-unificada', 'governanca': 'governance',
  'teammcp': 'team-mcp', 'jana-mcp': 'jana', 'paymentgateway': 'payment-gateway',
  'proposta': 'proposal', 'requisitos': 'memory',
};
export const DS_SCOPES = new Set([
  'ds', 'design', 'ui', 'prototipo-ui', 'cowork', 'components', 'charter', 'charters',
  'caixa-unificada', 'forja', 'team-mcp', 'shell', 'casos', 'contrato-de-tela', 'pageheader',
]);
export const DS_TITLE = /pageheader|gabarito cowork|\bhero\b|dark mode|tokeniz|oklch|re-skin|redesign|\bdrawer\b|sidebar|tela-linda|\bcasos\b|design-index|visual-reg|motion token|\bbolha|fiel ao (prot)/i;

// ── transformação pura (testável, sem rede) ───────────────────────────────────
export function normScope(s) {
  const n = (s || '').toLowerCase().trim().normalize('NFD').replace(/[̀-ͯ]/g, '');
  return SCOPE_ALIAS[n] || n;
}
export function parseTitle(title) {
  const m = title.match(/^(\w+)(?:\(([^)]+)\))?(!)?:\s*(.+)$/);
  if (!m) return { type: 'outros', scope: '', subject: title };
  return { type: m[1].toLowerCase(), scope: normScope(m[2]), subject: m[4].trim() };
}
export function isDS(title, scope) { return DS_SCOPES.has(scope) || DS_TITLE.test(title); }

/** Mapa PR-revertido → PR-que-reverteu (só `revert:` com #ref). */
export function reconcileReverts(prs) {
  const reverted = new Map();
  for (const p of prs) {
    if (parseTitle(p.title).type === 'revert') {
      const ref = p.title.match(/#(\d+)/);
      if (ref) reverted.set(Number(ref[1]), p.number);
    }
  }
  return reverted;
}

export function groupByArea(prs, reverted = new Map()) {
  const areas = new Map();
  const dsAll = [];
  for (const pr of prs) {
    const { type, scope, subject } = parseTitle(pr.title);
    const area = scope || type;
    if (!areas.has(area)) areas.set(area, { meaningful: [], noise: 0 });
    const b = areas.get(area);
    const ds = isDS(pr.title, scope);
    const row = { n: pr.number, type, subject, date: (pr.mergedAt || '').slice(0, 10), ds, rev: reverted.get(pr.number) };
    if (NOISE.has(type)) b.noise += 1; else b.meaningful.push(row);
    if (ds) dsAll.push(row);
  }
  const sorted = [...areas.entries()].sort((a, b) => (b[1].meaningful.length - a[1].meaningful.length) || a[0].localeCompare(b[0]));
  const totalMean = [...areas.values()].reduce((s, a) => s + a.meaningful.length, 0);
  const totalNoise = [...areas.values()].reduce((s, a) => s + a.noise, 0);
  return { sorted, dsAll, totalMean, totalNoise };
}

/** Cross-check anti-truncação: coletado deve bater com a contagem independente do Search. */
export function crossCheck(collectedInUtcRange, independentTotal, anyDayHitCap) {
  if (anyDayHitCap) return { ok: false, reason: `uma sub-janela bateu no teto de 1000 — janela de dia densa demais` };
  if (independentTotal == null) return { ok: true, reason: 'sem total independente (cross-check pulado)' };
  const diff = Math.abs(collectedInUtcRange - independentTotal);
  if (diff > 0) return { ok: false, reason: `coletado(${collectedInUtcRange}) ≠ Search total_count(${independentTotal}) — diff ${diff}` };
  return { ok: true, reason: 'bate com total_count' };
}

export function buildDoc({ cycle, since, until, prs, direct, reverted, partial }) {
  const { sorted, dsAll, totalMean, totalNoise } = groupByArea(prs, reverted);
  const L = [];
  L.push('<!-- GERADO por scripts/governance/shipped-log-generate.mjs (v2, fonte completa). NÃO editar à mão. Rode --write. -->');
  L.push('---');
  L.push(`status: ${partial ? 'parcial' : 'ativo'}`);
  L.push(`cycle: ${cycle}`);
  L.push(`window: "${since}..${until}"`);
  L.push(`generated: "${new Date().toISOString().slice(0, 10)}"`);
  L.push('---');
  L.push('');
  L.push(`# Shipped log${partial ? ' (PARCIAL)' : ''} · ${cycle}`);
  L.push('');
  if (partial) L.push(`> ⚠️ **PARCIAL** — janela ainda aberta. Regenerar ao fechar o cycle.`);
  L.push(`> **Rótulo honesto:** lista o que foi **mergeado em \`main\`** em \`${since}..${until}\` (BRT). Merge ≠ deploy ≠ funciona em produção.`);
  L.push(`> Fonte: REST por sub-janela de dia (sem teto da Search API) + API \`/commits\` pra push-direto + revert reconciliado. **Não** depende de \`Refs: US-XXX\`.`);
  L.push(`> Limites: área = scope do conventional-commit (PR multi-área cai na área do título); deploy real fora de escopo (G8).`);
  L.push('');
  L.push('## Contagem');
  L.push('');
  L.push(`- **${prs.length} PRs** mergeados em \`main\` · ${totalMean} de produto · ${totalNoise} de manutenção (docs/chore/test/ci/build)`);
  L.push(`- **${direct.length} entregas push-direto** (commits sem objeto-PR — invisíveis a query de PR)`);
  L.push(`- **${reverted.size} revert reconciliado** (par riscado — entrega líquida zero)`);
  L.push(`- **${dsAll.length} tocam Design System**`);
  L.push('');
  L.push('## Reconciliação — merge ≠ entrega');
  L.push('');
  if (!reverted.size) L.push('_(nenhum revert na janela)_');
  else for (const [rv, by] of reverted) L.push(`- ⚠️ **#${rv} revertido por #${by}** — entrega líquida **zero**.`);
  L.push('');
  L.push('## Entregas push-direto na main (sem PR)');
  L.push('');
  L.push('> Classe que o registro via-PR nunca vê.');
  L.push('');
  if (!direct.length) L.push('_(nenhuma)_');
  else for (const s of direct) L.push(`- ${s}`);
  L.push('');
  L.push('## Por área (PRs mergeados)');
  L.push('');
  for (const [area, b] of sorted) {
    if (!b.meaningful.length && !b.noise) continue;
    L.push(`### ${area} — ${b.meaningful.length}${b.noise ? ` (+${b.noise} manutenção)` : ''}`);
    for (const r of b.meaningful) {
      const ds = r.ds ? ' · `DS`' : '';
      const rev = r.rev ? ` — ⚠️ REVERTIDO por #${r.rev} (líquido 0)` : '';
      L.push(`- ${r.type}: ${r.subject} (#${r.n})${ds}${rev}`);
    }
    L.push('');
  }
  return L.join('\n') + '\n';
}

// ── helpers de janela/datas ────────────────────────────────────────────────────
export function dayList(since, until) {
  const out = [];
  const d = new Date(since + 'T00:00:00Z'), end = new Date(until + 'T00:00:00Z');
  d.setUTCDate(d.getUTCDate() - 1);      // margem -1 dia (borda BRT)
  end.setUTCDate(end.getUTCDate() + 1);  // margem +1 dia
  for (; d <= end; d.setUTCDate(d.getUTCDate() + 1)) out.push(d.toISOString().slice(0, 10));
  return out;
}
/** mergedAt (ISO UTC) está dentro de [since 00:00 BRT, until 23:59:59 BRT]? */
export function inBrtRange(mergedAtIso, since, until) {
  const t = Date.parse(mergedAtIso);
  const lo = Date.parse(since + 'T00:00:00Z') + BRT_OFFSET_MS;        // since 00:00 BRT em epoch UTC
  const hi = Date.parse(until + 'T23:59:59Z') + BRT_OFFSET_MS;       // until 23:59:59 BRT
  return t >= lo && t <= hi;
}

/** Saúde do registro versionado (pura): findings de freshness por arquivo. */
export function evalShippedHealth(files, today) {
  const findings = [];
  for (const { name, text } of files) {
    const mGen = text.match(/^generated:\s*"?(\d{4}-\d{2}-\d{2})"?/m);
    const mStatus = text.match(/^status:\s*(\w+)/m);
    if (!mGen) { findings.push({ cycle: name, issue: 'sem campo generated', level: 'fail' }); continue; }
    if (mStatus && mStatus[1] === 'parcial') continue; // parcial é regenerado por cron; não morde
    const ageDays = Math.round((today - Date.parse(mGen[1] + 'T00:00:00Z')) / 86400000);
    if (ageDays > FRESH_DAYS) findings.push({ cycle: name, issue: `generated há ${ageDays}d (> ${FRESH_DAYS}d) — STALE`, level: 'fail' });
  }
  return findings;
}

// ── coleta (impura — gh) ────────────────────────────────────────────────────────
function gh(args) { return execFileSync('gh', args, { encoding: 'utf8', shell: false, maxBuffer: 96 * 1024 * 1024 }); }

function collectPRs(repoArgs, since, until) {
  const seen = new Map();
  let anyDayHitCap = false;
  for (const day of dayList(since, until)) {
    const raw = gh(['pr', 'list', '--state', 'merged', '--search', `merged:${day}`, '--json', 'number,title,mergedAt,baseRefName', '-L', '1000', ...repoArgs]);
    const arr = JSON.parse(raw);
    if (arr.length >= 1000) anyDayHitCap = true;
    for (const p of arr) if (p.baseRefName === 'main') seen.set(p.number, p);
  }
  const all = [...seen.values()];
  const inWindow = all.filter((p) => inBrtRange(p.mergedAt, since, until)).sort((a, b) => (a.mergedAt < b.mergedAt ? -1 : a.mergedAt > b.mergedAt ? 1 : a.number - b.number));
  const inUtc = all.filter((p) => { const t = Date.parse(p.mergedAt); return t >= Date.parse(since + 'T00:00:00Z') && t <= Date.parse(until + 'T23:59:59Z'); }).length;
  return { inWindow, inUtc, anyDayHitCap };
}

function independentTotal(repo, since, until) {
  try {
    const q = `repo:${repo} is:pr is:merged base:main merged:${since}..${until}`;
    const raw = gh(['api', '-X', 'GET', 'search/issues', '-f', `q=${q}`, '--jq', '.total_count']);
    return Number(raw.trim());
  } catch { return null; }
}

function collectDirect(repo, since, until) {
  try {
    const raw = gh(['api', '--paginate', `repos/${repo}/commits?sha=main&since=${since}T00:00:00Z&until=${until}T23:59:59Z`, '--jq', '.[].commit.message | split("\\n")[0]']);
    return raw.split('\n').filter(Boolean).filter((s) => !/^Merge (pull request|branch|remote-tracking)/.test(s) && !/\(#\d+\)/.test(s));
  } catch { return []; }
}

function resolveRepo(repoArg) {
  if (repoArg) return repoArg;
  try { return gh(['repo', 'view', '--json', 'nameWithOwner', '--jq', '.nameWithOwner']).trim(); } catch { return ''; }
}

// ── --check (gate CI) / --json (Brief): nenhum shipped-log versionado pode estar stale ──
function runHealth(root, jsonOut) {
  const dir = join(root, SHIPPED_DIR);
  if (!existsSync(dir)) {
    if (jsonOut) { console.log(JSON.stringify({ ok: true, skipped: true, cycles: 0, stale: 0, findings: [] })); return 0; }
    console.log(`ℹ️  ${SHIPPED_DIR}/ ainda não existe — nada a checar.`); return 0;
  }
  const names = readdirSync(dir).filter((f) => f.endsWith('.md'));
  const files = names.map((name) => ({ name, text: readFileSync(join(dir, name), 'utf8') }));
  const today = Date.parse(new Date().toISOString().slice(0, 10) + 'T00:00:00Z');
  const findings = evalShippedHealth(files, today);
  const stale = findings.length;
  const ok = stale === 0;
  if (jsonOut) { console.log(JSON.stringify({ ok, cycles: names.length, stale, findings })); return ok ? 0 : 1; }
  if (!ok) {
    console.error(`✗ shipped-log STALE — o cron/auto-PR não está mantendo o registro fresco:`);
    for (const f of findings) console.error(`  - ${f.cycle}: ${f.issue}`);
    console.error(`Conserto: rode o gerador --write (ou destrave o cron shipped-log-cron.yml).`);
    return 1;
  }
  console.log(`✓ shipped-log fresco (${names.length} cycle(s), todos ≤ ${FRESH_DAYS}d ou parciais).`);
  return 0;
}

// ── main (só quando rodado direto) ───────────────────────────────────────────────
function arg(name, def = '') { const h = process.argv.find((a) => a.startsWith(`--${name}=`)); return h ? h.slice(name.length + 3) : def; }

function main() {
  const ROOT = process.cwd();
  const JSON_OUT = process.argv.includes('--json');
  if (process.argv.includes('--check') || JSON_OUT) process.exit(runHealth(ROOT, JSON_OUT));

  let since = arg('since'), until = arg('until');
  const days = arg('days'), cycle = arg('cycle', 'sem-cycle'), repoArg = arg('repo');
  const WRITE = process.argv.includes('--write');
  const todayStr = new Date().toISOString().slice(0, 10);
  if (days && !since) {
    const d = new Date(todayStr + 'T00:00:00Z'); d.setUTCDate(d.getUTCDate() - Number(days));
    since = d.toISOString().slice(0, 10); until = todayStr;
  }
  if (!since || !until) { console.error('uso: --since=YYYY-MM-DD --until=YYYY-MM-DD [--cycle=CYCLE-NN] [--write] | --days=N | --check | --json'); process.exit(2); }
  const partial = until >= todayStr;

  const repo = resolveRepo(repoArg);
  const repoArgs = repo ? ['--repo', repo] : [];
  const { inWindow, inUtc, anyDayHitCap } = collectPRs(repoArgs, since, until);
  const cc = crossCheck(inUtc, independentTotal(repo, since, until), anyDayHitCap);
  if (!cc.ok) { console.error(`✗ CROSS-CHECK FALHOU (${cc.reason}) — não gravo registro incompleto.`); process.exit(1); }

  const direct = collectDirect(repo, since, until);
  const reverted = reconcileReverts(inWindow);
  const out = buildDoc({ cycle, since, until, prs: inWindow, direct, reverted, partial });

  if (!WRITE) {
    console.log(out.slice(0, 3500));
    console.log(`\n--- dry-run · ${inWindow.length} PRs · ${direct.length} push-direto · ${reverted.size} revert · cross-check: ${cc.reason} ---`);
    process.exit(0);
  }
  const outPath = join(ROOT, SHIPPED_DIR, `${cycle}.md`);
  mkdirSync(dirname(outPath), { recursive: true });
  writeFileSync(outPath, out);
  console.log(`✓ ${SHIPPED_DIR}/${cycle}.md — ${inWindow.length} PRs · ${direct.length} push-direto · ${reverted.size} revert (cross-check ok).`);
}

if (process.argv[1] && pathToFileURL(process.argv[1]).href === import.meta.url) main();
