#!/usr/bin/env node
// @ts-check
/**
 * outcome-metrics.mjs — MEDIDOR DE ACEITAÇÃO do transporte Cowork→code (Onda O1).
 *
 * Objetivo: substituir a "%" estimada do Wagner por NÚMERO REAL de maturidade do
 * loop de design. Mede retrabalho/revert/first-pass cruzando DUAS fontes honestas:
 *
 *   FONTE A (proxy fraco — texto livre): prototipo-ui/SYNC_LOG.md
 *     Timeline append-only `YYYY-MM-DD HH:MM [SIGLA] <evento>`. NÃO tem campo
 *     machine-readable "tela X entregue em T". Tem menções soltas a telas + PRs +
 *     palavras de entrega (MERGED/approved/PR draft) e de retrabalho (revert/fix/
 *     RE-LAND/incidente/pass-2/slice/regress). Extraímos eventos por regex e
 *     classificamos. É um PROXY: depende de o evento citar a tela com vocabulário
 *     reconhecível. O que ESCAPA do vocabulário não é contado (declarado no rodapé).
 *
 *   FONTE B (proxy medido — git): git log dos .tsx em resources/js/Pages
 *     Conta commits por arquivo DEPOIS do 1º commit (proxy de "entregue") e detecta
 *     commits de revert/fix subsequentes. É MEDIDO (não texto livre) mas é PROXY:
 *     um 2º commit pode ser melhoria planejada, não retrabalho de falha. Mitigamos
 *     separando "fix/revert" (sinal forte de retrabalho) de "edição-posterior"
 *     (sinal fraco). Ambos reportados separados — não somados cegamente.
 *
 * MÉTRICAS (todas declaram proxy vs medido no --json campo `confianca`):
 *   - rework_rate    : % de telas editadas DE NOVO depois de entregues
 *                      (SYNC_LOG: evento de retrabalho pós-entrega · git: >N commits
 *                       após o 1º OU commit fix/revert). N = REWORK_COMMIT_THRESHOLD.
 *   - revert_rate    : % de telas com revert/fix logo após entrega (subconjunto forte).
 *   - first_pass_rate: % de telas entregues SEM nenhum sinal de retrabalho.
 *
 * HONESTIDADE (o que falta instrumentar — impresso no texto e no --json.gaps):
 *   G1 SYNC_LOG não tem ID-de-tela canônico → casamento por token de nome (frágil).
 *   G2 SYNC_LOG não liga evento→arquivo .tsx → as duas fontes medem coisas próximas,
 *      não idênticas; reportadas LADO A LADO, nunca fundidas num número só.
 *   G3 git "2º commit" ≠ necessariamente retrabalho-de-falha (pode ser evolução).
 *   G4 entrega no SYNC_LOG ≠ data exata do 1º commit do arquivo (sem âncora cruzada).
 *   Recomendação de instrumentação no rodapé (event canônico done={page-id,sha}).
 *
 * Funções puras exportadas → testáveis sem git/fs (outcome-metrics.test.mjs).
 * Execução de I/O só quando rodado direto (import.meta.url === argv[1]).
 *
 * Modos:
 *   (default)  texto humano legível no stdout
 *   --json     objeto pro Daily Brief: { ok, generated, sync, git, gaps }
 *   --sync-only só a fonte A (SYNC_LOG) — não chama git
 *   --git-only  só a fonte B (git) — não lê SYNC_LOG
 *
 * Uso:
 *   node scripts/governance/outcome-metrics.mjs
 *   node scripts/governance/outcome-metrics.mjs --json
 *
 * Refs: ADR 0294 (loop/porta de saída) · 0256 (fonte gerada/catraca) · 0271/0275
 *       (gate nasce advisory) · 0226 (Daily Brief) · 0105 (sinal qualificado).
 */
import { execFileSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = join(__dirname, '..', '..');

// ── constantes (exportadas pra teste) ─────────────────────────────────────────
export const SYNC_LOG_PATH = 'prototipo-ui/SYNC_LOG.md';
export const PAGES_GLOB = 'resources/js/Pages';
/** >N commits após o 1º (= entrega) conta como retrabalho via git (proxy fraco). */
export const REWORK_COMMIT_THRESHOLD = 1;

/** Palavras que sinalizam ENTREGA de uma tela na timeline (proxy texto). */
export const DELIVERY_WORDS = /\b(MERGED|merged|approved screenshot|approved compare|PR #\d+ draft|F3 .*implementad|entregue fim-a-fim|VALIDADO AO VIVO|SMOKE OK)\b/;
/** Palavras que sinalizam RETRABALHO/falha pós-entrega (proxy texto, sinal forte). */
export const REWORK_WORDS = /\b(revert|REVERT|RE-LAND|re-land|INCIDENTE|incidente|regress|REGRESS|hotfix|pass-2|PASS-2|stale|STALE|recovery|conserto|consertaram|CORRECAO|CORREÇÃO|quebrad|broke|footgun|bug|BUG)\b/;
/** Commits git de retrabalho (sinal forte). */
export const GIT_REWORK_WORDS = /\b(revert|fix|hotfix|regress|conserto|consertar|incidente|re-land|reland)\b/i;

/**
 * Telas canônicas rastreadas no loop (proxy de casamento de nome — G1).
 * Cada tela: { id, label, tokens } — tokens = regexes que aparecem no SYNC_LOG.
 * Lista derivada das menções reais do SYNC_LOG (Sells, Financeiro, Jana, etc).
 * Aditiva: telas novas entram aqui; o que não casa fica em "naoCasado" (honesto).
 */
export const TRACKED_SCREENS = [
  { id: 'sells-index', label: 'Sells/Index', tokens: [/\bsells\b/i, /\/sells\b/i, /Sells\/Index/] },
  { id: 'cliente-drawer', label: 'Cliente drawer 760', tokens: [/cliente-drawer/i, /cliente drawer/i] },
  { id: 'jana-cockpit', label: 'Jana/Cockpit', tokens: [/jana.?cockpit/i, /Cockpit\.tsx/, /chat-jana/i] },
  { id: 'caixa-unificada', label: 'Caixa Unificada', tokens: [/caixa-unificada/i, /caixa unificada/i] },
  { id: 'financeiro-drawer', label: 'Financeiro drawer', tokens: [/fin-drawer/i, /drawer.*financeiro/i, /\bFA-5\b/, /\bdrawer 975\b/i] },
  { id: 'financeiro-unificado', label: 'Financeiro Unificado', tokens: [/financeiro-unificado/i, /Unificado\/Index/i] },
  { id: 'compras-grade', label: 'Compras grade-matrix', tokens: [/compras-grade/i, /grade-matrix/i, /GradeMatrixInput/] },
  { id: 'producao-oficina', label: 'Produção Oficina', tokens: [/producao-oficina/i, /produção oficina/i, /ProducaoOficina/] },
  { id: 'integracao-vendas-oficina', label: 'Integração Vendas×Oficina', tokens: [/integra[cç][aã]o vendas/i, /vendas.?oficina/i] },
];

// ── FONTE A: parsing do SYNC_LOG (puro, sem fs) ───────────────────────────────

/** Linha de evento: `2026-05-31 ~00:30 [CL] texto...` → {date, sigla, text}. */
export function parseSyncLine(line) {
  const m = line.match(/^(\d{4}-\d{2}-\d{2})\s+[~\d:]*\s*\[([A-Z0-9/]+)\]\s+(.*)$/);
  if (!m) return null;
  return { date: m[1], sigla: m[2], text: m[3].trim() };
}

/** Texto bruto → lista de eventos estruturados (descarta cabeçalho/tabela). */
export function parseSyncLog(content) {
  const out = [];
  for (const raw of content.split(/\r?\n/)) {
    const ev = parseSyncLine(raw);
    if (ev) out.push(ev);
  }
  return out;
}

/** Quais telas TRACKED são citadas neste texto de evento (G1 — casamento frágil). */
export function screensInText(text, screens = TRACKED_SCREENS) {
  const hits = [];
  for (const s of screens) {
    if (s.tokens.some((re) => re.test(text))) hits.push(s.id);
  }
  return hits;
}

/**
 * Classifica eventos por tela: 1ª entrega (delivery) e retrabalhos posteriores.
 * Retorna Map<screenId, { delivered, deliveryDate, reworkEvents[] }>.
 * Retrabalho SÓ conta se vier DEPOIS (>=) da 1ª entrega da tela (cronológico).
 */
export function classifySyncEvents(events, screens = TRACKED_SCREENS) {
  const acc = new Map();
  for (const s of screens) acc.set(s.id, { id: s.id, delivered: false, deliveryDate: null, reworkEvents: [] });
  // eventos já vêm em ordem cronológica (SYNC_LOG é append-only); ordenamos por garantia.
  const sorted = [...events].sort((a, b) => a.date.localeCompare(b.date));
  for (const ev of sorted) {
    const ids = screensInText(ev.text, screens);
    if (ids.length === 0) continue;
    const isDelivery = DELIVERY_WORDS.test(ev.text);
    const isRework = REWORK_WORDS.test(ev.text);
    for (const id of ids) {
      const rec = acc.get(id);
      if (!rec) continue;
      if (isDelivery && !rec.delivered) {
        rec.delivered = true;
        rec.deliveryDate = ev.date;
        // se a MESMA linha já fala de rework (ex: "RE-LAND" + "MERGED"), conta retrabalho também
        if (isRework) rec.reworkEvents.push({ date: ev.date, text: ev.text });
      } else if (rec.delivered && isRework && ev.date >= rec.deliveryDate) {
        rec.reworkEvents.push({ date: ev.date, text: ev.text });
      }
    }
  }
  return acc;
}

/** Métricas agregadas da FONTE A (SYNC_LOG). */
export function syncMetrics(events, screens = TRACKED_SCREENS) {
  const cls = classifySyncEvents(events, screens);
  const delivered = [...cls.values()].filter((r) => r.delivered);
  const reworked = delivered.filter((r) => r.reworkEvents.length > 0);
  const firstPass = delivered.filter((r) => r.reworkEvents.length === 0);
  return {
    fonte: 'SYNC_LOG (proxy texto livre — G1/G2)',
    confianca: 'proxy',
    telas_rastreadas: screens.length,
    telas_entregues: delivered.length,
    telas_retrabalhadas: reworked.length,
    rework_rate: pct(reworked.length, delivered.length),
    first_pass_rate: pct(firstPass.length, delivered.length),
    detalhe: [...cls.values()].map((r) => ({
      id: r.id,
      entregue: r.delivered,
      entrega_data: r.deliveryDate,
      retrabalhos: r.reworkEvents.length,
    })),
  };
}

// ── FONTE B: análise de commits git por .tsx (puro a partir de registros) ─────

/**
 * Recebe registros já coletados do git: [{ file, commits: [{sha, subject, date}] }]
 * (commits em ordem cronológica ASC — 1º = mais antigo = "entrega"). Puro/testável.
 * - 1º commit = entrega (proxy G4).
 * - commits posteriores = edição-pós-entrega; se subject casa GIT_REWORK_WORDS = revert/fix.
 */
export function gitMetrics(records, { threshold = REWORK_COMMIT_THRESHOLD, shallow = false } = {}) {
  let entregues = 0, reworked = 0, reverted = 0, firstPass = 0;
  const detalhe = [];
  for (const r of records) {
    const commits = r.commits || [];
    if (commits.length === 0) continue;
    entregues++;
    const posEntrega = commits.slice(1); // tudo após o 1º
    const fixCommits = posEntrega.filter((c) => GIT_REWORK_WORDS.test(c.subject || ''));
    const temRework = posEntrega.length > threshold - 1 && posEntrega.length > 0;
    const temRevert = fixCommits.length > 0;
    if (temRevert) reverted++;
    if (temRework || temRevert) reworked++; else firstPass++;
    detalhe.push({
      file: r.file,
      commits: commits.length,
      pos_entrega: posEntrega.length,
      fix_revert: fixCommits.length,
      retrabalho: temRework || temRevert,
    });
  }
  return {
    fonte: 'git log .tsx em Pages (proxy medido — G3/G4)',
    confianca: 'medido-mas-proxy',
    // Em clone SHALLOW (CI sem fetch-depth:0 · worktree raso) o histórico por-arquivo
    // some e o rework vira FALSO 0% — sinalizamos em vez de mentir número (G5).
    shallow,
    confiavel: !shallow,
    aviso_shallow: shallow
      ? 'HISTÓRICO RASO (shallow clone) — rework via git NÃO confiável aqui; rode com fetch-depth:0. Número abaixo é PISO, não medida.'
      : null,
    threshold_commits_pos_entrega: threshold,
    telas_entregues: entregues,
    telas_retrabalhadas: reworked,
    telas_com_fix_revert: reverted,
    rework_rate: pct(reworked, entregues),
    revert_rate: pct(reverted, entregues),
    first_pass_rate: pct(firstPass, entregues),
    detalhe: detalhe.sort((a, b) => b.commits - a.commits).slice(0, 20),
  };
}

// ── util ──────────────────────────────────────────────────────────────────────
/** Percentual inteiro 0-100; n/d (null) quando denominador zero (honesto). */
export function pct(num, den) {
  if (!den) return null;
  return Math.round((num / den) * 1000) / 10;
}

export const GAPS = [
  'G1: SYNC_LOG não tem ID-de-tela canônico — casamento por token de nome é frágil (falsos negativos prováveis).',
  'G2: SYNC_LOG não liga evento→arquivo .tsx — fontes A e B medem perto, não igual; reportadas lado a lado, nunca fundidas.',
  'G3: git "2º commit" pode ser evolução planejada, não retrabalho-de-falha — por isso separamos fix/revert (forte) de edição-posterior (fraco).',
  'G4: entrega no SYNC_LOG ≠ data do 1º commit do .tsx — sem âncora cruzada evento↔sha.',
  'INSTRUMENTAR: emitir evento canônico `done={page-id, sha, fase:F3}` no SYNC_LOG (ou num done-log.json) fecha G1+G2+G4 e troca proxy por medição.',
];

// ── I/O layer (só quando rodado direto) ───────────────────────────────────────

/** Lê o SYNC_LOG do disco. */
function readSyncLog() {
  const p = join(REPO_ROOT, SYNC_LOG_PATH);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

/** true se o repo é um clone raso (shallow) — histórico por-arquivo é incompleto. */
function isShallowRepo() {
  try {
    const out = execFileSync('git', ['rev-parse', '--is-shallow-repository'], { cwd: REPO_ROOT, encoding: 'utf8' });
    return out.trim() === 'true';
  } catch {
    return false;
  }
}

/** Coleta registros git: por .tsx em Pages (exclui _components/__tests__), commits ASC. */
function collectGitRecords() {
  let list;
  try {
    list = execFileSync('git', ['ls-files', `${PAGES_GLOB}/**/*.tsx`], { cwd: REPO_ROOT, encoding: 'utf8' })
      .split(/\r?\n/).filter(Boolean);
  } catch {
    return [];
  }
  const records = [];
  for (const file of list) {
    if (/\/(_components|__tests__)\//.test(file)) continue; // só telas, não subcomponentes
    let log;
    try {
      log = execFileSync('git', ['log', '--reverse', '--format=%H%s%cI', '--', file], { cwd: REPO_ROOT, encoding: 'utf8' });
    } catch {
      continue;
    }
    const commits = log.split(/\r?\n/).filter(Boolean).map((l) => {
      const [sha, subject, date] = l.split('');
      return { sha, subject, date };
    });
    if (commits.length) records.push({ file, commits });
  }
  return records;
}

function buildReport({ syncOnly, gitOnly } = {}) {
  const report = { ok: true, generated: new Date().toISOString(), gaps: GAPS };
  if (!gitOnly) {
    const content = readSyncLog();
    if (content == null) {
      report.sync = { erro: `${SYNC_LOG_PATH} não encontrado` };
      report.ok = false;
    } else {
      report.sync = syncMetrics(parseSyncLog(content));
    }
  }
  if (!syncOnly) {
    const records = collectGitRecords();
    report.git = gitMetrics(records, { shallow: isShallowRepo() });
  }
  return report;
}

function fmtPct(v) { return v == null ? 'n/d (sem entregas)' : `${v}%`; }

function printHuman(r) {
  const L = [];
  L.push('═══════════════════════════════════════════════════════════════');
  L.push(' MEDIDOR DE ACEITAÇÃO — transporte Cowork→code (Onda O1)');
  L.push(` gerado: ${r.generated}`);
  L.push('═══════════════════════════════════════════════════════════════');
  if (r.sync) {
    L.push('');
    L.push('▸ FONTE A — SYNC_LOG.md (PROXY texto livre)');
    if (r.sync.erro) {
      L.push(`  ✗ ${r.sync.erro}`);
    } else {
      L.push(`  telas rastreadas ......: ${r.sync.telas_rastreadas}`);
      L.push(`  telas entregues .......: ${r.sync.telas_entregues}`);
      L.push(`  telas retrabalhadas ...: ${r.sync.telas_retrabalhadas}`);
      L.push(`  REWORK-RATE ...........: ${fmtPct(r.sync.rework_rate)}  (telas mexidas de novo pós-entrega)`);
      L.push(`  FIRST-PASS-RATE .......: ${fmtPct(r.sync.first_pass_rate)}  (F1→F3 sem retrabalho)`);
    }
  }
  if (r.git) {
    L.push('');
    L.push('▸ FONTE B — git log .tsx em Pages (PROXY medido)');
    if (r.git.aviso_shallow) L.push(`  ⚠ ${r.git.aviso_shallow}`);
    L.push(`  telas (.tsx) entregues : ${r.git.telas_entregues}`);
    L.push(`  com fix/revert ........: ${r.git.telas_com_fix_revert}`);
    L.push(`  REWORK-RATE ...........: ${fmtPct(r.git.rework_rate)}  (>${r.git.threshold_commits_pos_entrega - 1} commit pós-1º OU fix/revert)`);
    L.push(`  REVERT-RATE ...........: ${fmtPct(r.git.revert_rate)}  (commit fix/revert subsequente)`);
    L.push(`  FIRST-PASS-RATE .......: ${fmtPct(r.git.first_pass_rate)}`);
  }
  L.push('');
  L.push('▸ HONESTIDADE (proxy vs medido — o que falta instrumentar)');
  for (const g of r.gaps) L.push(`  • ${g}`);
  L.push('');
  L.push('  NOTA: as duas fontes NÃO são somadas — medem coisas próximas, não iguais.');
  L.push('  A "%" honesta hoje é uma FAIXA entre as duas, não um número único.');
  L.push('═══════════════════════════════════════════════════════════════');
  return L.join('\n');
}

// entry-point guard
if (import.meta.url === pathToFileURL(process.argv[1] || '').href) {
  const args = new Set(process.argv.slice(2));
  const report = buildReport({ syncOnly: args.has('--sync-only'), gitOnly: args.has('--git-only') });
  if (args.has('--json')) {
    process.stdout.write(JSON.stringify(report, null, 2) + '\n');
  } else {
    process.stdout.write(printHuman(report) + '\n');
  }
  // advisory: nunca falha o processo por causa do número (gate nasce advisory).
  // exit 1 só se a fonte sumiu (erro de instrumentação, não de métrica).
  process.exit(report.ok ? 0 : 1);
}
