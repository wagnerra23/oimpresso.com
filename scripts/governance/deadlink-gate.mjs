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
//  - VIVO (enforça): memory/** exceto dirs de história + *.md da raiz do repo
//    + resources/js/Pages/**/*.charter.md (charters de tela).
//  - CHARTERS entraram em 2026-08-10. Motivo: charter é ARTEFATO DE CONTRATO e estava
//    fora de qualquer checker. FP MEDIDO ANTES de ligar (disciplina §5 — 5 lápides de
//    guard sintático que reprovava o legítimo): 209 charters, +501 links varridos,
//    +2 mortos, e os 2 são dívida REAL (Atendimento/JanaTemplates: path relativo mal
//    formado que renderiza 404 no GitHub; Produto/SellingPrices: irmão declarado
//    "parkeado na branch" pelo próprio autor). Zero falso-positivo. Os 2 entraram no
//    baseline como grandfathered — forward-only, a catraca só-desce cobra o conserto.
//  - ⚠️ LIMITE MEDIDO — o gate lê o CORPO markdown, NÃO o frontmatter YAML. O LINK_RE
//    casa `[texto](alvo)`; uma lista `related_charters:\n  - path.md` NÃO casa (provado
//    com controle positivo em 2026-08-10). Logo o caso que motivou a ampliação
//    (Jana/Index.charter.md apontando pra Cockpit.charter.md APAGADO via frontmatter)
//    segue INVISÍVEL a este gate. Validar `related_charters` é OUTRO eixo — exigiria
//    parser de frontmatter, e o dono natural seria o memory-schema-gate (que já lê o
//    frontmatter dos charters), NÃO este. Travado por check no .test.mjs (seção 9).
//  - HISTÓRIA (só reporta, nunca enforça): memory/{handoffs,sessions,sprints,
//    audits,research,reguas}/ — são fósseis datados append-only; link morto lá é
//    registro histórico, não regressão.
//  - `memory/decisions/` fica DE PROPÓSITO no escopo vivo (há infra dedicada:
//    adr-tombstones.json + 5 checks do selftest). A dívida que RENAME de pasta gera
//    numa ADR imutável é absorvida pelo ledger de path-alias abaixo — não movendo a
//    ADR pra história (isso desligaria o tombstone junto).
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
import { raizesDePages } from '../qa/page-path.mjs';
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
  // charters de tela (resources/js/Pages/**/*.charter.md) — ARTEFATO DE CONTRATO, vivo.
  // Entraram no corpus em 2026-08-10 (FP medido: ver docblock do topo). ATENÇÃO: só o
  // CORPO markdown é varrido; o frontmatter YAML (`related_charters:`) NÃO é lido por
  // este gate — o LINK_RE casa `[texto](alvo)`, não lista YAML. Ver §Escopo no topo.
  const pagesDir = join(ROOT, 'resources', 'js', 'Pages');
  if (existsSync(pagesDir)) {
    const all = [];
    walkMd(pagesDir, all);
    for (const p of all) if (/\.charter\.md$/i.test(p)) files.push(p);
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

// ── tombstone-aware (ADR 0316 × ADR 0347) ────────────────────────────────────
// POR QUE ISTO EXISTE: a ADR 0316 decidiu esquecimento REAL de ADR morta (`git rm` +
// entrada em governance/adr-tombstones.json). A ADR 0347 tornou este gate REQUIRED.
// As duas juntas travavam: `git rm` de uma ADR fabricava link morto em TODO doc que a
// citava — e parte desses docs são ADRs aceitas, que o append-only PROÍBE editar pra
// consertar o link. Deadlock medido em 2026-07-29 com a 0101-tests: 123 arquivos vivos
// reprovavam, 11 deles ADR aceita (intocável). Resultado prático: o ledger ficou VAZIO
// — a 0316 foi lei por 4 semanas e nunca pôde rodar.
//
// A saída não é afrouxar o gate: é reconhecer que link pra ADR TOMBADA **não é link
// morto**. Ele resolve — pelo ledger (número, slug, sucessora, sha) + git history, que
// a 0316 define como a auditoria primária. Só slug explicitamente tombado resolve; ADR
// que sumiu SEM lápide continua morta (é o caso que este gate tem que pegar).
const TOMBSTONE_PATH = join(ROOT, 'governance', 'adr-tombstones.json');
const DECISIONS_DIR = join(ROOT, 'memory', 'decisions');
function loadTombstonedSlugs() {
  if (!existsSync(TOMBSTONE_PATH)) return new Set();
  try {
    const j = JSON.parse(readFileSync(TOMBSTONE_PATH, 'utf8'));
    return new Set((j.tombstones || []).map((t) => String(t.slug || '').trim()).filter(Boolean));
  } catch { return new Set(); }
}
const TOMBSTONED = loadTombstonedSlugs();
/** true se `absPath` aponta pra um `memory/decisions/<slug>.md` registrado no ledger. */
function resolvesViaTombstone(absPath) {
  if (TOMBSTONED.size === 0) return false;
  if (dirname(absPath) !== DECISIONS_DIR) return false; // só o diretório canônico de ADR
  const base = absPath.split(sep).pop();
  if (!/\.md$/i.test(base)) return false;
  return TOMBSTONED.has(base.replace(/\.md$/i, ''));
}

// ── rename-aware (mesma doutrina do tombstone acima) ─────────────────────────
// POR QUE ISTO EXISTE: rename de módulo/pasta é rotina no projeto (Copiloto→Jana,
// PontoWr2→Ponto, MemCofre→SRS, ProjectMgmt→Forja) e SEMPRE fabrica link morto em
// ADR aceita — que o append-only PROÍBE editar. É o MESMO deadlock que a 0316×0347
// criou pro tombstone, por outra porta: a única "saída" virava inflar o baseline a
// cada migração, o oposto do ratchet só-desce. Medido no rename ProjectMgmt→Forja
// (2026-07-30): +11 links mortos em 2 ADRs imutáveis (0099, 0100).
//
// A saída, de novo, NÃO é afrouxar o gate nem jogar `decisions/` pra história (isso
// desligaria o tombstone junto): é reconhecer que link pra path RENOMEADO **não é
// link morto** — ele resolve pelo rename-map CURADO (governance/ghost-rename-map.json,
// classe A = rename 1:1 com evidência dura ADR+commit; o próprio `_doc` do arquivo diz
// que nome sem evidência fica em `excluded` e nunca é tocado).
//
// DUAS travas pra não virar escape genérico:
//   1. só classe A (curada, com evidência) — `excluded`/AMBIGUO nunca resolve;
//   2. só se o destino reescrito EXISTIR de fato no disco. Renomeou pra lugar nenhum
//      (ou o link já era falso), continua morto — que é o caso que o gate deve pegar.
const RENAME_MAP_PATH = join(ROOT, 'governance', 'ghost-rename-map.json');
function loadClassARenames() {
  if (!existsSync(RENAME_MAP_PATH)) return [];
  try {
    const j = JSON.parse(readFileSync(RENAME_MAP_PATH, 'utf8'));
    return Object.entries(j.renames || {})
      .filter(([, v]) => v && v.class === 'A' && v.to)
      .map(([from, v]) => [String(from), String(v.to)]);
  } catch { return []; }
}
const CLASS_A_RENAMES = loadClassARenames();
/**
 * true se `absPath` (inexistente) passa a existir ao trocar um SEGMENTO de path
 * `<Antigo>` por `<Novo>` segundo o rename-map classe A.
 * Casa segmento inteiro — `memory/requisitos/Forja/SCOPE.md` resolve, mas um arquivo
 * chamado `ProjectMgmtLegado.md` não (evita match por substring).
 */
function resolvesViaRename(absPath) {
  if (CLASS_A_RENAMES.length === 0) return false;
  const segs = absPath.split(sep);
  for (const [from, to] of CLASS_A_RENAMES) {
    for (let i = 0; i < segs.length; i++) {
      if (segs[i] !== from) continue;
      const alt = [...segs];
      alt[i] = to;
      if (existsSync(alt.join(sep))) return true;
    }
  }
  return false;
}

/**
 * true se `absPath` (inexistente) é um link para tela que MIGROU para dentro do módulo dono.
 *
 * Desde 2026-08-12 uma Page pode morar no núcleo (`resources/js/Pages/<ns>/…`) OU em
 * `Modules/<X>/Resources/js/Pages/<ns>/…` — o resolver do Inertia mescla os dois globs
 * (`resources/js/app.tsx` + `ssr.tsx`). Um doc que aponta para o caminho do núcleo NÃO tem
 * link morto: o arquivo existe, mudou de raiz. Sem isto, migrar uma tela quebraria todo doc
 * que a cita — inclusive ADR, que é append-only e não pode ser corrigida sem decisão [W].
 *
 * As duas travas do `resolvesViaRename` valem igual aqui:
 *   1. só reescreve o PREFIXO exato `resources/js/Pages/` (não casa por substring);
 *   2. só resolve se o destino EXISTIR de fato no disco — apontar pra lugar nenhum
 *      continua morto, que é o caso que o gate deve pegar.
 */
function resolvesViaRaizDeModulo(absPath) {
  const rel = relative(ROOT, absPath).split(sep).join('/');
  const PREFIXO = 'resources/js/Pages/';
  if (!rel.startsWith(PREFIXO)) return false;
  const namespaceEmDiante = rel.slice(PREFIXO.length);

  // As duas resoluções COMPÕEM. O link da ADR 0110 aponta pra `Pages/ProjectMgmt/Board/…`:
  // no main ele resolvia porque o rename-map classe A troca `ProjectMgmt`→`Forja` e o alvo
  // existia no núcleo. Com a tela migrada, resolver exige as DUAS etapas — rename do
  // segmento E a raiz do módulo. Tratar só uma delas deixaria o link morto sem que nada
  // tivesse de fato sumido, e a ADR é append-only (não dá pra "consertar" o texto).
  const candidatos = [namespaceEmDiante];
  const segs = namespaceEmDiante.split('/');
  for (const [from, to] of CLASS_A_RENAMES) {
    for (let i = 0; i < segs.length; i++) {
      if (segs[i] !== from) continue;
      const alt = [...segs];
      alt[i] = to;
      candidatos.push(alt.join('/'));
    }
  }

  for (const raiz of raizesDePages(ROOT)) {
    // `raizesDePages` devolve o núcleo também; ele já foi testado por `existsCaseSensitive`.
    if (!raiz.replace(/\\/g, '/').includes('/Modules/')) continue;
    for (const cand of candidatos) if (existsSync(join(raiz, cand))) return true;
  }
  return false;
}

/**
 * Migration que trocou de MÓDULO dono. Irmão do `resolvesViaRaizDeModulo` acima, no eixo
 * schema: `Modules/<A>/Database/Migrations/<arquivo>` → o mesmo `<arquivo>` sob
 * `Modules/<B>/Database/Migrations/`. O arquivo NÃO sumiu — mudou de casa, e a tabela
 * `migrations` do Laravel casa por NOME, então mover preservando o nome é o caminho
 * canônico (é por isso que ele é único e serve de chave aqui).
 *
 * Motivo de existir (2026-08-13): a ADR 0366 §D-C item 4 mandou as `mcp_*` da Jana pro
 * Forja. As 61 migrations moveram; ADR 0073 e 0084 as citam e são APPEND-ONLY — não dá
 * pra "consertar o texto". Sem isto, executar uma decisão de fronteira quebraria o gate
 * por dívida que a própria proibicoes.md manda NÃO pagar editando ADR aceita.
 *
 * As mesmas duas travas do `resolvesViaRename`:
 *   1. só casa o padrão EXATO `Modules/<X>/Database/Migrations/<arquivo>` — nada de substring;
 *   2. só resolve se o arquivo EXISTIR de fato sob outro módulo. Migration que nunca
 *      existiu, ou que foi DELETADA, continua morta — que é o caso que o gate deve pegar.
 */
const MIGRATION_RE = /^Modules\/[A-Za-z]\w*\/Database\/Migrations\/([^/]+)$/;
function resolvesViaMigrationDeOutroModulo(absPath) {
  const rel = relative(ROOT, absPath).split(sep).join('/');
  const m = rel.match(MIGRATION_RE);
  if (!m) return false;
  const arquivo = m[1];
  const modulesDir = join(ROOT, 'Modules');
  let mods;
  try { mods = readdirSync(modulesDir, { withFileTypes: true }); } catch { return false; }
  for (const mod of mods) {
    if (!mod.isDirectory()) continue;
    if (existsSync(join(modulesDir, mod.name, 'Database', 'Migrations', arquivo))) return true;
  }
  return false;
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
  let tombstoned = 0;       // alvos ausentes que RESOLVEM via ledger (ADR 0316) — não são mortos
  let renamed = 0;
  let migradas = 0;      // alvos que RESOLVEM na raiz do modulo dono (migracao 2026-08-12)          // alvos ausentes que RESOLVEM via rename-map classe A — idem
  let migrations = 0;    // migrations que trocaram de MODULO dono preservando o nome (2026-08-13)
  for (const f of corpus()) {
    const rel = relative(ROOT, f).split(sep).join('/');
    const isHist = HISTORY_RE.test(relative(ROOT, f));
    const text = readFileSync(f, 'utf8');
    for (const target of extractTargets(text)) {
      totalLinks++;
      const abs = resolve(dirname(f), target);
      if (!existsCaseSensitive(abs)) {
        if (resolvesViaTombstone(abs)) { tombstoned++; continue; }
        if (resolvesViaRename(abs)) { renamed++; continue; }
        if (resolvesViaRaizDeModulo(abs)) { migradas++; continue; }
        if (resolvesViaMigrationDeOutroModulo(abs)) { migrations++; continue; }
        const bucket = isHist ? hist : vivo;
        if (!bucket.has(rel)) bucket.set(rel, []);
        bucket.get(rel).push(target);
      }
    }
  }
  return { vivo, hist, totalLinks, tombstoned, renamed, migradas, migrations };
}

const count = (map) => [...map.values()].reduce((a, v) => a + v.length, 0);

function loadBaseline() {
  if (!existsSync(BASELINE_PATH)) return null;
  try { return JSON.parse(readFileSync(BASELINE_PATH, 'utf8')); } catch { return null; }
}

// ── modos ────────────────────────────────────────────────────────────────────
const mode = argv.find((a) => ['--scan', '--check', '--write-baseline', '--triage'].includes(a)) || '--scan';
const { vivo, hist, totalLinks, tombstoned, renamed, migradas, migrations } = scan();
// Visível SEMPRE que houver: mecanismo que absorve caso em silêncio vira cobertura falsa
// (§5 presence-gate / verde-por-não-execução). Aqui o número aparece em todo modo.
const tombLine = tombstoned > 0
  ? `[deadlink-gate] ${tombstoned} referência(s) a ADR TOMBADA resolvidas pelo ledger (${TOMBSTONED.size} slug(s) em governance/adr-tombstones.json — ADR 0316); não contam como mortas.`
  : null;
if (tombLine) console.log(tombLine);
const renameLine = renamed > 0
  ? `[deadlink-gate] ${renamed} referência(s) a PATH RENOMEADO resolvidas pelo rename-map (${CLASS_A_RENAMES.length} rename(s) classe A em governance/ghost-rename-map.json); não contam como mortas.`
  : null;
if (renameLine) console.log(renameLine);
const migrationLine = migrations > 0
  ? `[deadlink-gate] ${migrations} referência(s) a MIGRATION que trocou de módulo dono (mesmo nome de arquivo sob outro Modules/*/Database/Migrations/); não contam como mortas.`
  : null;
if (migrationLine) console.log(migrationLine);

if (mode === '--write-baseline') {
  const files = {};
  for (const [rel, targets] of [...vivo.entries()].sort()) files[rel] = targets.length;
  mkdirSync(dirname(BASELINE_PATH), { recursive: true });
  writeFileSync(BASELINE_PATH, JSON.stringify({
    _doc: 'Baseline RATCHET do deadlink-gate (so-desce). Dívida grandfathered de links mortos por arquivo VIVO. NAO editar a mao: regenerar com `node scripts/governance/deadlink-gate.mjs --write-baseline` (idealmente quando a divida DIMINUI). Historia (handoffs/sessions/sprints/audits/research/reguas) fica fora por ser append-only. NAO entram aqui (resolvem, nao sao mortos): referencia a ADR TOMBADA via governance/adr-tombstones.json (ADR 0316) e referencia a PATH RENOMEADO via governance/ghost-rename-map.json classe A (2026-07-30) — os dois evitam que esquecimento/rename inflem o baseline por divida que o append-only proibe corrigir.',
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
