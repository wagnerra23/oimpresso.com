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
 *   G10 lista do dia ≠ o dia    → `gh pr list --search` erra com exit 0 de duas formas (vazio total ~10% das chamadas;
 *                                 1ª página só — 100 de 114 em 2026-09-03). Cada dia é conferido contra o issueCount
 *                                 do próprio dia, retentado, e o que não fecha reprova NOMEANDO o dia (2026-09-09)
 *   G7 merged ≠ entregue        → reconcilia pares de revert (líquido zero)
 *   G9 ruído de agrupamento     → aliases de scope + normalize NFD (acento)
 *   G8 merge ≠ deploy           → cruza /api/mcp/version (SHA+data do deploy de produção) → marca 🚀 no-ar / ⏳ aguardando
 *
 * Rótulo HONESTO: lista o que foi MERGEADO em `main`, e cruza com o deploy real (G8): 🚀 no-ar / ⏳ aguardando-deploy.
 * Limites conhecidos declarados no doc: área = scope do título (G5 paths-por-PR fora por custo); janela via args/cron (G6 MCP-live pendente);
 * deploy marcado por DATA do último deploy (aproximação barata, não ancestralidade por-PR).
 * Limite do G10: 1 chamada GraphQL por dia (cost 1, cota 5000/h — não toca os 30/min do Search REST, que
 * segue exclusivo do cross-check agregado). Numa janela de 100 dias são ~100 pontos e ~1,5min a mais.
 * Oráculo mudo NÃO vira verde por omissão: com a lista vazia o dia é suspeito e reprova; com itens, é
 * aceito e contado à parte — aí quem guarda é o cross-check agregado, que continua intacto.
 *
 * Funções puras exportadas → testáveis sem rede (shipped-log-generate.test.mjs). Execução só quando rodado direto.
 *
 * Modos:
 *   (default) dry-run no stdout
 *   --write   grava memory/governance/shipped/<CYCLE>.md
 *   --check   gate CI: falha (exit 1) se o shipped-log VIVO (o que cobre a janela mais recente — o
 *             único que o cron regenera) está STALE (> FRESH_DAYS desde `generated`). Log de cycle
 *             fechado é registro final e não conta freshness — ver evalShippedHealth.
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
    const row = { n: pr.number, type, subject, date: (pr.mergedAt || '').slice(0, 10), ds, rev: reverted.get(pr.number), deployed: pr._deployed };
    if (NOISE.has(type)) b.noise += 1; else b.meaningful.push(row);
    if (ds) dsAll.push(row);
  }
  const sorted = [...areas.entries()].sort((a, b) => (b[1].meaningful.length - a[1].meaningful.length) || a[0].localeCompare(b[0]));
  const totalMean = [...areas.values()].reduce((s, a) => s + a.meaningful.length, 0);
  const totalNoise = [...areas.values()].reduce((s, a) => s + a.noise, 0);
  return { sorted, dsAll, totalMean, totalNoise };
}

/**
 * Veredito sobre a lista de UM dia, confrontada com o `issueCount` do mesmo dia (oráculo).
 * Existe porque `gh pr list --search` erra de duas formas, ambas com exit 0 (medido 2026-09-09):
 *   (a) devolve `[]` — indistinguível de "esse dia não teve PR";
 *   (b) devolve a 1ª página e para — 2026-09-03 voltou 100 de 114, e o agregado só via o rombo.
 * Um só confronto cobre as duas. O oráculo é pedido ANTES da lista de propósito: no dia corrente
 * um merge no meio da coleta faz n > esperado ('acima', benigno) em vez de fabricar 'incompleto'.
 *   'completo'   → n === esperado.
 *   'incompleto' → n < esperado: a listagem perdeu itens (vazio total ou página parcial).
 *   'acima'      → n > esperado: mergearam depois do oráculo. Benigno.
 *   'sem-oraculo-vazio' / 'sem-oraculo-com-itens' → não medi; o vazio não-medido NUNCA vira zero.
 * Pura de propósito (sem rede) — é a política, o I/O fica em collectPRs.
 */
export function classificaDia(nColetado, esperado) {
  if (esperado === null || esperado === undefined || !Number.isFinite(esperado)) {
    return nColetado > 0 ? 'sem-oraculo-com-itens' : 'sem-oraculo-vazio';
  }
  if (nColetado === esperado) return 'completo';
  return nColetado < esperado ? 'incompleto' : 'acima';
}

/**
 * Cross-check anti-truncação: coletado deve bater com a contagem independente do Search.
 * `diasSuspeitos` chega de collectPRs — dias cuja lista o oráculo do dia NÃO confirmou
 * (vazia ou parcial). Ele vem ANTES do diff agregado de propósito: nomear o dia que não
 * fechou diz ONDE está o buraco, enquanto "diff 160" só diz que ele existe.
 */
export function crossCheck(collectedInUtcRange, independentTotal, anyDayHitCap, diasSuspeitos = []) {
  if (anyDayHitCap) return { ok: false, reason: `uma sub-janela bateu no teto de 1000 — janela de dia densa demais` };
  if (diasSuspeitos.length) {
    const det = diasSuspeitos.map((d) => `${d.day} (${d.motivo})`).join('; ');
    return { ok: false, reason: `coleta incompleta em ${diasSuspeitos.length} dia(s) — ${det}` };
  }
  if (independentTotal == null) return { ok: true, reason: 'sem total independente (cross-check pulado)' };
  const diff = Math.abs(collectedInUtcRange - independentTotal);
  if (diff > 0) return { ok: false, reason: `coletado(${collectedInUtcRange}) ≠ Search total_count(${independentTotal}) — diff ${diff}` };
  return { ok: true, reason: 'bate com total_count' };
}

/**
 * Marca cada PR como no-ar via DATA do deploy (aproximação barata — sem 1 git/PR).
 * O deploy faz `git reset --hard origin/main` num instante → PR mergeado ATÉ deployed_at
 * está no ar; mergeado depois = aguardando deploy. Seta pr._deployed e devolve contagem.
 */
export function markDeployed(prs, deployedAtIso) {
  if (!deployedAtIso) { for (const p of prs) p._deployed = null; return { onAir: null, waiting: null }; }
  const dep = Date.parse(deployedAtIso);
  let onAir = 0, waiting = 0;
  for (const p of prs) {
    p._deployed = Date.parse(p.mergedAt) <= dep;
    if (p._deployed) onAir++; else waiting++;
  }
  return { onAir, waiting };
}

export function buildDoc({ cycle, since, until, prs, direct, reverted, partial, deploy }) {
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
  L.push(`> 🚀 = no ar (mergeado ≤ deploy de produção) · ⏳ = mergeado, aguardando deploy (G8, via /api/mcp/version, por data). Limite: área = scope do título (G5 paths-por-PR fora por custo).`);
  L.push('');
  L.push('## Contagem');
  L.push('');
  L.push(`- **${prs.length} PRs** mergeados em \`main\` · ${totalMean} de produto · ${totalNoise} de manutenção (docs/chore/test/ci/build)`);
  L.push(`- **${direct.length} entregas push-direto** (commits sem objeto-PR — invisíveis a query de PR)`);
  L.push(`- **${reverted.size} revert reconciliado** (par riscado — entrega líquida zero)`);
  L.push(`- **${dsAll.length} tocam Design System**`);
  if (deploy && deploy.commit_short) {
    L.push(`- 🚀 **Deploy de produção:** \`${deploy.commit_short}\` (${deploy.deployed_at || '?'}) · **${deploy.onAir ?? '?'}** no ar · **${deploy.waiting ?? '?'}** mergeados **aguardando deploy**`);
  } else {
    L.push(`- ⏳ Deploy: status indisponível (sem MCP_DRIFT_TOKEN/endpoint) — PRs não marcados 🚀/⏳`);
  }
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
      const dep = r.deployed === true ? ' 🚀' : r.deployed === false ? ' ⏳' : '';
      L.push(`- ${r.type}: ${r.subject} (#${r.n})${ds}${dep}${rev}`);
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

/** Frontmatter mínimo de cada log (puro). `until` = fim da janela; cai pra `generated` se ausente. */
export function parseShippedMeta(files) {
  return files.map(({ name, text }) => {
    const gen = (text.match(/^generated:\s*"?(\d{4}-\d{2}-\d{2})"?/m) || [])[1] || null;
    const until = (text.match(/^window:\s*"?\d{4}-\d{2}-\d{2}\.\.(\d{4}-\d{2}-\d{2})"?/m) || [])[1] || null;
    return { name, gen, until: until || gen };
  });
}

/**
 * Os logs VIVOS: os que cobrem a janela mais recente (maior `until`). É o único
 * conjunto que o cron regenera — ele roda `--until=hoje` sobre o cycle ATIVO.
 * Log de cycle anterior é registro FINAL, congelado: cobrar freshness dele daria
 * vermelho eterno (gate que não pode ficar verde = ruído que se aprende a ignorar).
 * Empate no `until` → todos são vivos (ninguém escapa por desempate arbitrário).
 *
 * ⚠️ Elege por FIM-DE-JANELA (fato derivado), NUNCA pelo rótulo `status`. Ao virar o
 * cycle, o log velho NÃO vira `ativo` — congela como `parcial`, porque o carimbo foi
 * calculado no dia em que ele foi gerado (`partial = until >= hoje`, avaliado então).
 * Medido 2026-08-04: `ativo` nunca existiu no registro — 13/13 versões `parcial`.
 * Discriminar por rótulo reabriria o buraco com outro sinal.
 *
 * Limite conhecido (fail-CLOSED, de propósito): um log gerado à mão com `--until` no
 * FUTURO vira o "vivo" pra sempre e reprova até ser regenerado ou removido. Preferido
 * ao inverso — eleger pelo `generated` mais novo deixaria alguém mascarar cron morto
 * regenerando um cycle antigo (falha ABERTA, pior). Saída legítima existe e é barata.
 */
export function pickLiveLogs(metas) {
  const medible = metas.filter((m) => m.gen);
  if (!medible.length) return [];
  const newest = medible.reduce((max, m) => (m.until > max ? m.until : max), '');
  return medible.filter((m) => m.until === newest);
}

/**
 * Saúde do registro versionado (pura): freshness do(s) log(s) VIVO(s).
 *
 * ⚠️ NÃO isentar por `status: parcial`. O gerador carimba `parcial` sempre que
 * `until >= hoje` (L~314/129) e o cron SEMPRE passa `--until=hoje` → todo log que
 * o cron produz nasce `parcial`. A isenção anterior (`if parcial continue`) era a
 * INVERSA da correta: esvaziava o conjunto verificado por construção — o gate
 * nunca teve como reprovar — e ao mesmo tempo cobrava os logs congelados, que são
 * os que legitimamente não são regenerados. Medido 2026-08-04: o auto-PR #5058
 * ficou 4 dias preso (30/07→03/08) com o registro envelhecendo, e o gate rodou
 * VERDE todo dia. Quem decide isenção agora é o papel no registro (vivo ×
 * histórico), derivado da janela — não o rótulo.
 */
export function evalShippedHealth(files, today) {
  const findings = [];
  const metas = parseShippedMeta(files);
  for (const m of metas) if (!m.gen) findings.push({ cycle: m.name, issue: 'sem campo generated', level: 'fail' });
  for (const m of pickLiveLogs(metas)) {
    const ageDays = Math.round((today - Date.parse(m.gen + 'T00:00:00Z')) / 86400000);
    if (ageDays > FRESH_DAYS) findings.push({ cycle: m.name, issue: `generated há ${ageDays}d (> ${FRESH_DAYS}d) — STALE`, level: 'fail' });
  }
  return findings;
}

// ── coleta (impura — gh) ────────────────────────────────────────────────────────
function gh(args) { return execFileSync('gh', args, { encoding: 'utf8', shell: false, maxBuffer: 96 * 1024 * 1024 }); }

/** Pausa síncrona (o loop de coleta é sequencial; sem dependência externa). */
function sleepSync(ms) { Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms); }

/** Backoff entre as retentativas de um dia que não fechou com o oráculo. Tamanho = nº de retentativas. */
export const BACKOFF_RETRY_MS = [400, 1200, 3000];

/**
 * Quantos PRs o GitHub diz que aquele dia tem — oráculo por dia. null = não medi.
 * GraphQL (cota 5000 pontos/h, cost 1) e NÃO o Search REST: aquele tem 30 req/min e um dia
 * por chamada estouraria a cota na primeira execução. A query espelha a que o `gh pr list
 * --state merged --search merged:<dia>` monta — SEM `base:main`, porque o filtro de base é
 * aplicado depois, no laço; medido em 2026-09-03: com base:main dá 113, sem dá 114, e a
 * lista bruta tem 114. Comparar contra a query errada fabricaria 'acima' todo dia.
 */
function issueCountDoDia(repo, day) {
  if (!repo) return null;
  try {
    const raw = gh(['api', 'graphql', '-f', 'query=query($q:String!){search(query:$q,type:ISSUE,first:1){issueCount}}',
      '-f', `q=repo:${repo} is:pr is:merged merged:${day}`, '--jq', '.data.search.issueCount']);
    const n = Number(String(raw).trim());
    return Number.isFinite(n) ? n : null;
  } catch { return null; }
}

/**
 * Coleta por sub-janela de DIA (G1), com a lista de cada dia CONFERIDA contra o oráculo daquele
 * dia. Antes ela era aceita como veio, e `gh pr list --search` erra de duas formas, ambas com
 * exit 0 (medido 2026-09-09 · 104 dias × 3 rodadas):
 *   vazio total  → ~10% das chamadas devolveram `[]`, indistinguível de "dia sem PR";
 *   página única → 2026-09-03 devolveu 100 de 114 (o 100 é o tamanho de página do gh).
 * Perder um dia inteiro custava ~45 PRs e o agregado só via o rombo (diff 160 no cron de 09-09);
 * a página parcial custava ~14 e passava despercebida no ruído. O confronto por dia pega as duas
 * e, o que é o ponto, DIZ QUAL DIA — o diff agregado só dizia que faltava alguma coisa.
 */
function collectPRs(repoArgs, since, until, repo) {
  const seen = new Map();
  let anyDayHitCap = false;
  const diasSuspeitos = [];
  let conferidos = 0, retentados = 0, recuperados = 0, semOraculo = 0;
  const listaDoDia = (day) => JSON.parse(gh(['pr', 'list', '--state', 'merged', '--search', `merged:${day}`, '--json', 'number,title,mergedAt,baseRefName', '-L', '1000', ...repoArgs]));
  const MOTIVO = {
    incompleto: (n, e) => `a listagem trouxe ${n} de ${e} — incompleta mesmo após ${BACKOFF_RETRY_MS.length} retentativa(s)`,
    'sem-oraculo-vazio': () => 'veio vazia e o oráculo do dia não respondeu — vazio NÃO verificado',
  };

  for (const day of dayList(since, until)) {
    const esperado = issueCountDoDia(repo, day);   // ANTES da lista: merge no meio vira 'acima', não 'incompleto'
    let arr = listaDoDia(day);
    let v = classificaDia(arr.length, esperado);
    if (v === 'incompleto' || v === 'sem-oraculo-vazio') {
      retentados++;
      for (const espera of BACKOFF_RETRY_MS) {
        sleepSync(espera);
        arr = listaDoDia(day);
        v = classificaDia(arr.length, esperado);
        if (v !== 'incompleto' && v !== 'sem-oraculo-vazio') { recuperados++; break; }
      }
    }
    if (v === 'incompleto' || v === 'sem-oraculo-vazio') diasSuspeitos.push({ day, motivo: MOTIVO[v](arr.length, esperado) });
    else if (v === 'sem-oraculo-com-itens') semOraculo++;
    else conferidos++;
    if (arr.length >= 1000) anyDayHitCap = true;
    for (const p of arr) if (p.baseRefName === 'main') seen.set(p.number, p);
  }
  console.error(`· dias conferidos contra o oráculo: ${conferidos} · retentados: ${retentados} (recuperados: ${recuperados}) · sem oráculo (aceitos, com itens): ${semOraculo} · suspeitos: ${diasSuspeitos.length}`);
  const all = [...seen.values()];
  const inWindow = all.filter((p) => inBrtRange(p.mergedAt, since, until)).sort((a, b) => (a.mergedAt < b.mergedAt ? -1 : a.mergedAt > b.mergedAt ? 1 : a.number - b.number));
  const inUtc = all.filter((p) => { const t = Date.parse(p.mergedAt); return t >= Date.parse(since + 'T00:00:00Z') && t <= Date.parse(until + 'T23:59:59Z'); }).length;
  return { inWindow, inUtc, anyDayHitCap, diasSuspeitos };
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

/**
 * SHA + data do deploy de produção via /api/mcp/version (G8). Mesmo padrão da
 * sentinela mcp-drift-sentinel.mjs: endpoint público, Bearer MCP_DRIFT_TOKEN, sem RBAC.
 * Degrada → null (sem token / endpoint fora / pré-rollout): o doc sai sem marcação.
 */
async function fetchDeployed() {
  const token = (process.env.MCP_DRIFT_TOKEN || '').trim();
  if (!token || typeof fetch !== 'function') return null;
  const url = process.env.MCP_HEALTH_URL || 'https://mcp.oimpresso.com/api/mcp/version';
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), 12000);
  try {
    const r = await fetch(url, { signal: ctrl.signal, headers: { 'user-agent': 'shipped-log-generate', authorization: `Bearer ${token}` } });
    if (!r.ok) return null;
    const j = await r.json();
    const commit = (j.commit || '').trim() || null;
    if (!commit) return null;
    return { commit, commit_short: j.commit_short || commit.slice(0, 9), deployed_at: j.deployed_at || null };
  } catch { return null; }
  finally { clearTimeout(t); }
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
  // Diretório existe mas vazio = registro apagado. Sair 0 aqui seria gate mudo
  // (parece cobertura). O caso pré-rollout já saiu acima, por !existsSync.
  // Limite declarado: este fail é VISÍVEL no CI e INVISÍVEL no Daily Brief —
  // ShippedLogBriefLineService::line() devolve null quando `cycles === 0`, antes de
  // olhar `stale`. O gate é o enforcement; a linha do brief é conveniência.
  const findings = names.length
    ? evalShippedHealth(files, today)
    : [{ cycle: SHIPPED_DIR, issue: 'diretório existe mas não tem nenhum log — registro apagado?', level: 'fail' }];
  const stale = findings.length;
  const ok = stale === 0;
  if (jsonOut) { console.log(JSON.stringify({ ok, cycles: names.length, stale, findings })); return ok ? 0 : 1; }
  if (!ok) {
    console.error(`✗ shipped-log STALE — o cron/auto-PR não está mantendo o registro fresco:`);
    for (const f of findings) console.error(`  - ${f.cycle}: ${f.issue}`);
    console.error(`Conserto: rode o gerador --write (ou destrave o cron shipped-log-cron.yml).`);
    return 1;
  }
  const vivos = pickLiveLogs(parseShippedMeta(files));
  const quem = vivos.map((m) => `${m.name} (generated ${m.gen})`).join(', ');
  const hist = names.length - vivos.length;
  console.log(`✓ shipped-log vivo fresco: ${quem} ≤ ${FRESH_DAYS}d · ${names.length} cycle(s) no registro${hist ? `, ${hist} de cycle fechado (registro final, freshness não se aplica)` : ''}.`);
  return 0;
}

// ── main (só quando rodado direto) ───────────────────────────────────────────────
function arg(name, def = '') { const h = process.argv.find((a) => a.startsWith(`--${name}=`)); return h ? h.slice(name.length + 3) : def; }

async function main() {
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
  const { inWindow, inUtc, anyDayHitCap, diasSuspeitos } = collectPRs(repoArgs, since, until, repo);
  const cc = crossCheck(inUtc, independentTotal(repo, since, until), anyDayHitCap, diasSuspeitos);
  if (!cc.ok) { console.error(`✗ CROSS-CHECK FALHOU (${cc.reason}) — não gravo registro incompleto.`); process.exit(1); }

  const direct = collectDirect(repo, since, until);
  const reverted = reconcileReverts(inWindow);
  const deployedInfo = await fetchDeployed();
  const { onAir, waiting } = markDeployed(inWindow, deployedInfo?.deployed_at);
  const deploy = deployedInfo ? { ...deployedInfo, onAir, waiting } : null;
  const out = buildDoc({ cycle, since, until, prs: inWindow, direct, reverted, partial, deploy });

  if (!WRITE) {
    console.log(out.slice(0, 3500));
    const depStr = deploy ? `${deploy.commit_short} (${onAir} no ar/${waiting} aguard)` : 'indisponível';
    console.log(`\n--- dry-run · ${inWindow.length} PRs · ${direct.length} push-direto · ${reverted.size} revert · deploy: ${depStr} · cross-check: ${cc.reason} ---`);
    process.exit(0);
  }
  const outPath = join(ROOT, SHIPPED_DIR, `${cycle}.md`);
  mkdirSync(dirname(outPath), { recursive: true });
  writeFileSync(outPath, out);
  console.log(`✓ ${SHIPPED_DIR}/${cycle}.md — ${inWindow.length} PRs · ${direct.length} push-direto · ${reverted.size} revert (cross-check ok).`);
}

if (process.argv[1] && pathToFileURL(process.argv[1]).href === import.meta.url) main().catch((e) => { console.error(e); process.exit(1); });
