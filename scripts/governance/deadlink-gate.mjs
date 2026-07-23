#!/usr/bin/env node
// deadlink-gate.mjs — catraca de integridade referencial doc↔doc (links markdown mortos).
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// ADR 0256 (knowledge survival): derivado+enforçado sobrevive; escrito+lembrado apodrece.
// Origem: sessão 2026-07-23 — medição no corpus vivo achou ~7,5% de links internos
// mortos em memory/ (ex.: ADR 0022→requisitos/Copiloto/ renomeado pra Jana ADR 0088;
// ADR 0023→arquivos da auto-mem purgada 2026-06-07). Não existia NENHUM dead-link
// check no repo (0 workflows; ref-integrity.mjs cobre rota↔código, não doc↔doc).
// Grade estado-da-arte 2026-07-23: D1 integridade referencial era a única dimensão 🔴
// do oimpresso (35/100) — padrão lychee/Docusaurus/Antora: link quebrado FALHA o build.
//
// DESENHO (por que ratchet e não zero-tolerância):
//  - O corpus já carrega dívida (~1k links mortos), e boa parte vive em docs
//    APPEND-ONLY (ADRs aceitas, handoffs) que é PROIBIDO editar (proibicoes.md).
//    Logo: baseline por-arquivo GRANDFATHERS a dívida existente; o gate só morde
//    quem PIORA (arquivo novo com link morto, ou arquivo existente que ganha mais
//    links mortos que o baseline registra). Dívida grandfathered NÃO deve ser paga
//    editando ADR/handoff antigo — ela fica registrada e morre com o arquivo.
//  - Per-arquivo (não total global): um total global deixaria "+1 aqui, -1 ali"
//    passar em silêncio.
//  - Case-sensitive SEMPRE (mesmo no Windows, FS case-insensitive): o CI roda em
//    Linux; link com case errado renderiza 404 no GitHub (lição "nudge morto por
//    case kb/×KB" — proibicoes.md §5 2026-07-17).
//
// ESCOPO:
//  - VIVO (enforça): memory/** exceto dirs de história + *.md da raiz do repo.
//  - HISTÓRIA (só reporta, nunca enforça): memory/{handoffs,sessions,sprints,
//    audits,research,reguas}/ — são fósseis datados append-only; link morto lá é
//    registro histórico, não regressão.
//
// MODOS:
//   node scripts/governance/deadlink-gate.mjs --scan             # relatório (exit 0)
//   node scripts/governance/deadlink-gate.mjs --check            # ratchet vs baseline (exit 1 se piorou)
//   node scripts/governance/deadlink-gate.mjs --write-baseline   # (re)grava governance/deadlink-baseline.json
//   ... [--root <dir>] pra corpus alternativo (testes herméticos)
//
// ADVISORY por lei (ADR 0314/0275: required = só Tier-0 + exceção via emenda + flip
// [W]). O job pode ficar VERMELHO sem bloquear merge — hard-fail em job advisory é
// desenho intencional (proibicoes.md §5 2026-07-09). Promoção a required = decisão [W]
// após mordidas reais colecionadas (doutrina ADR 0336).

import { readdirSync, readFileSync, existsSync, writeFileSync, mkdirSync } from 'node:fs';
import { join, dirname, resolve, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

// ── raiz do repo ─────────────────────────────────────────────────────────────
function findRepoRoot(startDir) {
  let d = startDir;
  for (let i = 0; i < 10; i++) {
    if (existsSync(join(d, 'memory')) && existsSync(join(d, 'CLAUDE.md'))) return d;
    const up = dirname(d);
    if (up === d) break;
    d = up;
  }
  return null;
}

const argv = process.argv.slice(2);
function argValue(flag) {
  const i = argv.indexOf(flag);
  return i >= 0 && argv[i + 1] ? argv[i + 1] : null;
}
const ROOT = argValue('--root')
  ? resolve(argValue('--root'))
  : findRepoRoot(dirname(fileURLToPath(import.meta.url)));
if (!ROOT) { console.error('[deadlink-gate] raiz do repo nao encontrada'); process.exit(2); }

const BASELINE_PATH = join(ROOT, 'governance', 'deadlink-baseline.json');

// dirs de história (append-only) — reporta, não enforça
const HISTORY_RE = /^memory[\\/](handoffs|sessions|sprints|audits|research|reguas)([\\/]|$)/;
// extensões de alvo que contam como "link interno de arquivo"
const TARGET_EXT_RE = /\.(md|markdown|json|mjs|cjs|js|ts|tsx|jsx|php|css|scss|yml|yaml|sh|ps1|sql|csv|txt|png|jpe?g|svg|webp|gif|pdf)$/i;

// ── coleta do corpus ─────────────────────────────────────────────────────────
function walkMd(dir, acc) {
  let entries;
  try { entries = readdirSync(dir, { withFileTypes: true }); } catch { return acc; }
  for (const e of entries) {
    if (e.name.startsWith('.') || e.name === 'node_modules' || e.name === 'vendor') continue;
    const p = join(dir, e.name);
    if (e.isDirectory()) walkMd(p, acc);
    else if (/\.md$/i.test(e.name)) acc.push(p);
  }
  return acc;
}

function corpus() {
  const files = [];
  const memDir = join(ROOT, 'memory');
  if (existsSync(memDir)) walkMd(memDir, files);
  // *.md da raiz do repo (README/CLAUDE/DESIGN/INFRA/TEAM/AGENTS/…)
  let rootEntries = [];
  try { rootEntries = readdirSync(ROOT, { withFileTypes: true }); } catch { /* noop */ }
  for (const e of rootEntries) {
    if (!e.isDirectory() && /\.md$/i.test(e.name)) files.push(join(ROOT, e.name));
  }
  return files;
}

// ── existência case-sensitive (paridade CI Linux mesmo rodando no Windows) ───
const dirCache = new Map();
function listDir(dir) {
  if (!dirCache.has(dir)) {
    try { dirCache.set(dir, new Set(readdirSync(dir))); } catch { dirCache.set(dir, null); }
  }
  return dirCache.get(dir);
}
function existsCaseSensitive(absPath) {
  const rel = relative(ROOT, absPath);
  if (rel.startsWith('..')) return existsSync(absPath); // fora do repo: melhor esforço
  const parts = rel.split(sep).filter(Boolean);
  let cur = ROOT;
  for (const part of parts) {
    const listing = listDir(cur);
    if (!listing || !listing.has(part)) return false;
    cur = join(cur, part);
  }
  return true;
}

// ── extração de links ────────────────────────────────────────────────────────
const LINK_RE = /\[[^\]]*\]\(([^)]+)\)/g;
function extractTargets(mdText) {
  const out = [];
  let m;
  while ((m = LINK_RE.exec(mdText))) {
    let t = m[1].trim();
    if (t.startsWith('<') && t.endsWith('>')) t = t.slice(1, -1).trim();
    if (/^(https?:|mailto:|ftp:|#|\/\/)/i.test(t)) continue;
    t = t.split('#')[0].split(/\s+/)[0]; // remove âncora e "title"
    if (!t) continue;
    if (/[<>{}*$|]/.test(t)) continue;   // template/placeholder (<Modulo>, {id}, glob)
    try { t = decodeURIComponent(t); } catch { /* mantém cru */ }
    if (!TARGET_EXT_RE.test(t)) continue; // só alvos-arquivo com extensão (dirs fora do escopo)
    out.push(t);
  }
  return out;
}

// ── scan ─────────────────────────────────────────────────────────────────────
function scan() {
  const vivo = new Map();   // rel -> [alvos quebrados]
  const hist = new Map();
  let totalLinks = 0;
  for (const f of corpus()) {
    const rel = relative(ROOT, f).split(sep).join('/');
    const isHist = HISTORY_RE.test(relative(ROOT, f));
    const text = readFileSync(f, 'utf8');
    for (const target of extractTargets(text)) {
      totalLinks++;
      const abs = resolve(dirname(f), target);
      if (!existsCaseSensitive(abs)) {
        const bucket = isHist ? hist : vivo;
        if (!bucket.has(rel)) bucket.set(rel, []);
        bucket.get(rel).push(target);
      }
    }
  }
  return { vivo, hist, totalLinks };
}

const count = (map) => [...map.values()].reduce((a, v) => a + v.length, 0);

function loadBaseline() {
  if (!existsSync(BASELINE_PATH)) return null;
  try { return JSON.parse(readFileSync(BASELINE_PATH, 'utf8')); } catch { return null; }
}

// ── modos ────────────────────────────────────────────────────────────────────
const mode = argv.find((a) => ['--scan', '--check', '--write-baseline', '--triage'].includes(a)) || '--scan';
const { vivo, hist, totalLinks } = scan();

if (mode === '--write-baseline') {
  const files = {};
  for (const [rel, targets] of [...vivo.entries()].sort()) files[rel] = targets.length;
  mkdirSync(dirname(BASELINE_PATH), { recursive: true });
  writeFileSync(BASELINE_PATH, JSON.stringify({
    _doc: 'Baseline RATCHET do deadlink-gate (so-desce). Dívida grandfathered de links mortos por arquivo VIVO. NAO editar a mao: regenerar com `node scripts/governance/deadlink-gate.mjs --write-baseline` (idealmente quando a divida DIMINUI). Historia (handoffs/sessions/sprints/audits/research/reguas) fica fora por ser append-only.',
    generated_at: new Date().toISOString().slice(0, 10),
    total_vivo: count(vivo),
    files,
  }, null, 2) + '\n', 'utf8');
  console.log(`[deadlink-gate] baseline gravado: ${count(vivo)} links mortos grandfathered em ${vivo.size} arquivos vivos -> ${relative(ROOT, BASELINE_PATH)}`);
  process.exit(0);
}

if (mode === '--scan') {
  console.log(`[deadlink-gate] corpus: ${totalLinks} links internos varridos`);
  console.log(`  VIVO    : ${count(vivo)} mortos em ${vivo.size} arquivos (enforçável)`);
  console.log(`  HISTORIA: ${count(hist)} mortos em ${hist.size} arquivos (append-only — nunca enforça)`);
  const sample = [...vivo.entries()].slice(0, 10);
  for (const [rel, targets] of sample) console.log(`    ${rel}  ->  ${targets[0]}${targets.length > 1 ? `  (+${targets.length - 1})` : ''}`);
  process.exit(0);
}

// --triage (PR3 do design 2026-07-23): classifica os mortos VIVOS em redirectable (o alvo
// mudou de PASTA — existe .md de mesmo basename em outro lugar) vs purged (sumiu de vez).
// Heurística por BASENAME: o move histórico (Copiloto→Jana etc.) NÃO foi rastreado por id,
// então não há prova — basename comum (SPEC.md/README.md) dá match ambíguo, sinalizado.
// Serve pra triar a dívida de 1105: redirectable = auto-religável; purged = allowlist/remover.
if (mode === '--triage') {
  const byBasename = new Map();
  for (const f of corpus()) {
    const rel = relative(ROOT, f).split(sep).join('/');
    const base = rel.split('/').pop().toLowerCase();
    if (!byBasename.has(base)) byBasename.set(base, []);
    byBasename.get(base).push(rel);
  }
  let redir = 0; let purged = 0; let ambiguous = 0;
  const rows = [];
  for (const [rel, targets] of vivo.entries()) {
    for (const t of targets) {
      if (!/\.(md|markdown)$/i.test(t)) { purged++; rows.push({ rel, t, verdict: 'purged', cands: [] }); continue; }
      const cands = (byBasename.get(t.split('/').pop().toLowerCase()) || []).filter((c) => `/${c.toLowerCase()}` !== `/${t.replace(/^\.?\//, '').toLowerCase()}`);
      if (!cands.length) { purged++; rows.push({ rel, t, verdict: 'purged', cands: [] }); }
      else if (cands.length > 3) { ambiguous++; redir++; rows.push({ rel, t, verdict: 'redirectable', cands: cands.slice(0, 3), ambiguous: true }); }
      else { redir++; rows.push({ rel, t, verdict: 'redirectable', cands }); }
    }
  }
  console.log(`[deadlink-gate --triage] ${count(vivo)} link(s) morto(s) vivo(s) triados:`);
  console.log(`  redirectable (alvo existe em outra pasta → auto-religável): ${redir}${ambiguous ? ` (${ambiguous} ambíguos por basename comum)` : ''}`);
  console.log(`  purged (não existe em lugar nenhum → allowlist/remover): ${purged}`);
  for (const r of rows.filter((r) => r.verdict === 'redirectable' && !r.ambiguous).slice(0, 8)) {
    console.log(`   ~ ${r.rel} -> ${r.t}   ⇒   ${r.cands[0]}`);
  }
  for (const r of rows.filter((r) => r.verdict === 'purged').slice(0, 5)) {
    console.log(`   ✗ ${r.rel} -> ${r.t}   (purgado)`);
  }
  process.exit(0);
}

// --check: ratchet por arquivo
const baseline = loadBaseline();
if (!baseline) {
  console.error(`[deadlink-gate] SEM baseline (${relative(ROOT, BASELINE_PATH)}). Rode --write-baseline primeiro. Sem baseline, qualquer link morto vivo reprova:`);
  if (count(vivo) > 0) {
    for (const [rel, targets] of [...vivo.entries()].slice(0, 20)) console.error(`  ${rel}  ->  ${targets.join(' , ')}`);
    process.exit(1);
  }
  console.log('[deadlink-gate] OK — corpus vivo sem links mortos.');
  process.exit(0);
}

const violations = [];
for (const [rel, targets] of vivo.entries()) {
  const allowed = baseline.files?.[rel] ?? 0;
  if (targets.length > allowed) {
    violations.push({ rel, now: targets.length, allowed, targets });
  }
}

const improved = Object.keys(baseline.files || {}).filter((rel) => (vivo.get(rel)?.length ?? 0) < baseline.files[rel]);

if (violations.length === 0) {
  console.log(`[deadlink-gate] OK — nenhum arquivo vivo piorou vs baseline (${count(vivo)}/${baseline.total_vivo} grandfathered).`);
  if (improved.length > 0) {
    console.log(`  info: ${improved.length} arquivo(s) MELHORARAM vs baseline — considere re-gravar (--write-baseline) pra travar o ganho (catraca so-desce).`);
  }
  process.exit(0);
}

console.error(`[deadlink-gate] FALHOU — ${violations.length} arquivo(s) vivos com MAIS links mortos que o baseline permite:`);
for (const v of violations) {
  console.error(`  ${v.rel}: ${v.now} morto(s) (baseline permite ${v.allowed})`);
  for (const t of v.targets) console.error(`      -> ${t}`);
}
console.error('');
console.error('Como resolver: corrija o link (alvo real), ou remova a referência morta.');
console.error('NÃO pague dívida grandfathered editando ADR aceita/handoff antigo (append-only) —');
console.error('a dívida antiga já está no baseline; este gate só reprova o que PIOROU.');
process.exit(1);
