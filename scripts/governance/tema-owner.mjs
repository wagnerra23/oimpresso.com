#!/usr/bin/env node
// @ts-check
/**
 * tema-owner.mjs — detector ADVISORY de DONO-DE-TEMA por sobreposição de ENTIDADE.
 *
 * A DOR (pedido [W] 2026-07-21): *"como sei que a Maiara não está criando outra estrutura e
 * duplicando as coisas?"*. Hoje, ao criar um doc de estrutura novo (um tópico em
 * memory/requisitos/<Mod>/topicos/, um BRIEFING, um SPEC, ou qualquer .md sob memory/requisitos),
 * NADA aponta se aquele ASSUNTO já tem dono. A defesa contra estrutura-paralela é 100% manual/cultural.
 * Este detector fecha esse gap: dado o tema candidato, aponta o(s) DONO(s) existente(s) do
 * MESMO tema — *"esse assunto já é coberto por <arquivo/dono>"*.
 *
 * COMO MEDE "MESMO TEMA" (a parte que importa — trava §5 2026-06-30 ancora-guard):
 *   NÃO por nome de arquivo nem por pasta (critério sintático "incompleto por construção" —
 *   bloqueia o legítimo, deixa passar o disfarçado). Mede **sobreposição REAL de ENTIDADES
 *   DECLARADAS**: as mesmas tabelas / functions / models / controllers / telas / rotas que o
 *   doc candidato e um doc existente citam. Duas notas sobre o mesmo `tax_rates` +
 *   `calculateInvoiceTotal` são o mesmo tema por mais diferentes que sejam os nomes dos arquivos.
 *   Espelha a doutrina do `catalog-graph.mjs`: prosa é narrativa (ignorada); só entidade conta.
 *
 * FONTE ("quem cobre o quê" — trava §5 2026-07-09 "não duplicar régua consolidada"):
 *   CONSOME o `memory/governance/catalog.json` (grafo tipado, PR #4629 — hoje com 0 leitores) +
 *   os tópicos memory/requisitos/<Mod>/topicos/ (anchors do `topico.schema.json`, ADR 0345). NÃO recria
 *   grafo, NÃO é um 2º `dup-detector` (aquele é arquivo-EXATO em PR aberto — trabalho concorrente,
 *   não tema) nem um `preflight-new-capability` (aquele é CÓDIGO por nome de arquivo).
 *
 * NÃO É presence-gate (trava §5 2026-07-20 `briefing-completeness` refutado): não afirma
 * "existe arquivo que cobre logo está coberto". MEDE e MOSTRA quais entidades concretas colidem,
 * e recomenda o humano estender o dono. Existência do doc ≠ correção — só o humano julga.
 *
 * ADVISORY DE NASCENÇA (ADR 0224/0314): imprime e sai 0. NUNCA bloqueia a criação. Bloquear
 * criação legítima é pior que o problema.
 *
 * Uso:
 *   node scripts/governance/tema-owner.mjs --tema-arquivo <path.md>   (extrai sinais do frontmatter/corpo)
 *   node scripts/governance/tema-owner.mjs --tabelas a,b --functions app/X.php::y --module M
 *   node scripts/governance/tema-owner.mjs ... --json                 (saída estruturada)
 *   node scripts/governance/tema-owner.mjs --selftest                 (roda tema-owner.test.mjs)
 *
 * Refs: ADR 0345 (tópicos vivos) · ADR 0256 (derivado sobrevive) · catalog-graph.mjs (irmão) ·
 *       proibicoes §5 (2026-06-30 ancora-guard · 2026-07-09 régua consolidada · 2026-07-20 presence-gate).
 */
import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const CATALOG_REL = 'memory/governance/catalog.json';
const REQUISITOS_REL = 'memory/requisitos';

/** Tipos de âncora que contam como SINAL DE TEMA (entidade concreta). ADR de propósito FORA:
 *  transversal (0093 aparece em quase todo tópico) → viraria ruído, não é sinal de tema. */
export const ENTITY_TYPES = ['tables', 'functions', 'models', 'controllers', 'routes', 'screens'];

/** Peso por tipo — quão forte é a co-ocorrência como sinal de "mesmo tema". */
export const TYPE_WEIGHT = { tables: 3, functions: 3, models: 2, controllers: 2, screens: 2, routes: 2 };

const BACKSLASH = String.fromCharCode(92);
/** posix + trim + lowercase (paths comparáveis cross-plataforma). */
export function toPosixLower(s) { return String(s || '').split(BACKSLASH).join('/').trim().toLowerCase(); }

// ─────────────────────────────────────────────────────────────────────────────
// PARTE 1 — extração de sinais de tema do doc candidato (puro).
// ─────────────────────────────────────────────────────────────────────────────

/** Isola o bloco de frontmatter YAML (entre a 1ª e a 2ª linha `---`). '' se não houver. */
export function frontmatterBlock(txt) {
  const norm = String(txt || '').replace(/\r\n/g, '\n');
  if (!norm.startsWith('---\n')) return '';
  const end = norm.indexOf('\n---', 4);
  return end === -1 ? '' : norm.slice(4, end + 1);
}

/** Escalar top-level do frontmatter (ex. `module:`), sem aspas. null se ausente. */
export function scalarField(fmBlock, key) {
  const m = fmBlock.match(new RegExp(`^${key}:\\s*(.+)$`, 'm'));
  if (!m) return null;
  let v = m[1].trim();
  if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) v = v.slice(1, -1);
  return v || null;
}

/**
 * Lê o bloco `anchors:` do frontmatter → { tables:[], functions:[], models:[], ... }.
 * Parser focado (zero-dep, robusto em qualquer cwd do hook): pega as sub-chaves conhecidas
 * e seus itens `- valor`. Ignora estruturas irmãs (review/claims/critiques).
 */
export function anchorsFromFrontmatter(fmBlock) {
  /** @type {Record<string,string[]>} */
  const out = {};
  for (const t of ENTITY_TYPES) out[t] = [];
  const lines = fmBlock.split('\n');
  let inAnchors = false;
  let curKey = null; // sub-chave corrente dentro de anchors (ex 'tables')
  let anchorsIndent = -1;
  for (const line of lines) {
    if (/^anchors:\s*$/.test(line)) { inAnchors = true; anchorsIndent = 0; curKey = null; continue; }
    if (!inAnchors) continue;
    // saiu do bloco anchors: linha top-level (sem indentação) que não é item nem sub-chave
    if (/^[A-Za-z_]/.test(line)) { inAnchors = false; curKey = null; continue; }
    // sub-chave: `  screens:` ou `  tables: []` ou `  tables: [a, b]`
    const sub = line.match(/^\s+([a-z_]+):\s*(.*)$/);
    if (sub) {
      const name = sub[1];
      const inline = sub[2].trim();
      curKey = ENTITY_TYPES.includes(name) ? name : null;
      if (curKey && inline && inline !== '[]') {
        // lista inline `[a, b]`
        const arr = inline.replace(/^\[|\]$/g, '').split(',').map((x) => x.trim()).filter(Boolean);
        for (const x of arr) out[curKey].push(unq(x));
      }
      continue;
    }
    // item de lista: `    - valor`
    const item = line.match(/^\s+-\s+(.+)$/);
    if (item && curKey) out[curKey].push(unq(item[1].trim()));
  }
  return out;
}
function unq(s) {
  const t = String(s).trim();
  if ((t.startsWith('"') && t.endsWith('"')) || (t.startsWith("'") && t.endsWith("'"))) return t.slice(1, -1);
  return t;
}

/**
 * Extrai entidades também do CORPO — mas SÓ tokens PROVADAMENTE entidades reais (anti-ruído,
 * anti-presence-gate): paths de código (`app/..php`, `Modules/..php`, `resources/js/Pages/..tsx`),
 * `Arquivo.php::funcao`, e nomes de tabela que EXISTEM no conjunto de tabelas conhecidas.
 * Palavra genérica do texto NÃO entra. `knownTables` vem do catálogo (Set de nomes reais).
 * @param {string} body
 * @param {Set<string>} knownTables
 */
export function entitiesFromBody(body, knownTables) {
  const text = String(body || '');
  /** @type {Record<string,string[]>} */
  const out = { tables: [], functions: [], models: [], controllers: [], routes: [], screens: [] };
  // functions: Arquivo.php::simbolo
  for (const m of text.matchAll(/([A-Za-z0-9_\/\.]+\.php)::([A-Za-z0-9_]+)/g)) out.functions.push(`${m[1]}::${m[2]}`);
  // paths de código soltos → controllers/models/screens por heurística de pasta
  for (const m of text.matchAll(/\b((?:app|Modules)\/[A-Za-z0-9_\/]+\.php)\b/g)) {
    const p = m[1];
    if (/Controllers?\//i.test(p)) out.controllers.push(p);
    else if (/(Entities|Models)\//i.test(p) || /^app\/[A-Z][A-Za-z0-9]*\.php$/.test(p)) out.models.push(p);
  }
  for (const m of text.matchAll(/\b(resources\/js\/Pages\/[A-Za-z0-9_\/]+\.tsx)\b/g)) out.screens.push(m[1]);
  // tabelas: só se o token EXISTE no catálogo (prova de que é entidade real, não palavra)
  if (knownTables && knownTables.size) {
    for (const m of text.matchAll(/\b([a-z][a-z0-9_]{2,})\b/g)) {
      if (knownTables.has(m[1])) out.tables.push(m[1]);
    }
  }
  return out;
}

/** Normaliza uma entidade → chave canônica comparável `${type}:${canon}`. */
export function entityKey(type, raw) {
  const v = toPosixLower(raw);
  if (!v) return null;
  if (type === 'tables') return `tables:${v}`;
  if (type === 'functions') {
    // basename.php::simbolo (ignora a pasta — a função é a mesma em qualquer caminho relativo)
    const mm = v.match(/([^/]+\.php)::([a-z0-9_]+)/);
    return mm ? `functions:${mm[1]}::${mm[2]}` : `functions:${v}`;
  }
  // model/controller/screen/route: basename do path (a mesma entidade referida por paths relativos distintos)
  const base = v.split('/').pop() || v;
  return `${type}:${base}`;
}

/**
 * Sinais de tema de um doc → { module, keys:Set<entityKey>, byType:{...} }.
 * @param {string} txt conteúdo do doc
 * @param {Set<string>} knownTables tabelas reais (do catálogo), pra filtrar corpo
 */
export function signalsFromDoc(txt, knownTables) {
  const fm = frontmatterBlock(txt);
  const body = fm ? String(txt).slice(String(txt).indexOf('\n---', 4) + 4) : String(txt);
  const module = fm ? scalarField(fm, 'module') : null;
  const fromFm = fm ? anchorsFromFrontmatter(fm) : { tables: [], functions: [], models: [], controllers: [], routes: [], screens: [] };
  const fromBody = entitiesFromBody(body, knownTables);
  const keys = new Set();
  /** @type {Record<string,Set<string>>} */
  const byType = {};
  for (const t of ENTITY_TYPES) byType[t] = new Set();
  for (const t of ENTITY_TYPES) {
    for (const raw of [...(fromFm[t] || []), ...(fromBody[t] || [])]) {
      const k = entityKey(t, raw);
      if (k) { keys.add(k); byType[t].add(k); }
    }
  }
  return { module: module || null, keys, byType };
}

// ─────────────────────────────────────────────────────────────────────────────
// PARTE 2 — corpus de donos existentes (impuro na borda; núcleo puro).
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Índice do catálogo: entityKey (`tables:*`) → { owners:Set } + Set de tabelas conhecidas.
 * Puro (recebe o objeto catalog). Só TABELA hoje (owners + legacy_views) — é a entidade que o
 * catálogo indexa de forma confiável e que casa 1:1 com a âncora `tables` do tópico. Componentes
 * (nomes livres) e apis (prefixo, match ruidoso) ficam de fora de propósito: o cruzamento por
 * function/model/tela vem dos TÓPICOS, não do catálogo (o catálogo não indexa path de arquivo).
 */
export function indexCatalog(catalog) {
  const byKey = new Map(); // entityKey -> { owners:Set<string> }
  const knownTables = new Set();
  const nodes = (catalog && catalog.nodes) || [];
  for (const n of nodes) {
    if (n.type !== 'table') continue;
    knownTables.add(n.name);
    const owners = new Set([...(n.owners || []), ...(n.legacy_views || [])]);
    if (owners.size) byKey.set(`tables:${toPosixLower(n.name)}`, { owners });
  }
  return { byKey, knownTables };
}

/** Lista os arquivos de tópico existentes: memory/requisitos/<Mod>/topicos/*.md. */
export function listTopicoFiles(root) {
  const base = join(root, REQUISITOS_REL);
  if (!existsSync(base)) return [];
  const out = [];
  for (const mod of readdirSync(base)) {
    const dir = join(base, mod, 'topicos');
    if (!existsSync(dir)) continue;
    for (const f of readdirSync(dir)) {
      if (f.endsWith('.md')) out.push(join(dir, f));
    }
  }
  return out.sort();
}

/**
 * Índice dos tópicos: [{ path, module, keys:Set }]. Puro (recebe [{path, txt}]).
 * @param {{path:string, txt:string}[]} docs
 * @param {Set<string>} knownTables
 */
export function indexTopicos(docs, knownTables) {
  return docs.map(({ path, txt }) => {
    const s = signalsFromDoc(txt, knownTables);
    return { path, module: s.module, keys: s.keys };
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// PARTE 3 — matching (puro).
// ─────────────────────────────────────────────────────────────────────────────

/** rótulo humano de um entityKey (`tables:tax_rates` → "tabela tax_rates"). */
export function labelKey(k) {
  const [type, ...rest] = k.split(':');
  const val = rest.join(':');
  const noun = { tables: 'tabela', functions: 'função', models: 'model', controllers: 'controller', screens: 'tela', routes: 'rota' }[type] || type;
  return `${noun} ${val}`;
}

/**
 * Acha donos existentes que SOBREPÕEM o tema candidato.
 * @param {{module:string|null, keys:Set<string>}} signals
 * @param {{catalogIndex:ReturnType<typeof indexCatalog>, topicos:ReturnType<typeof indexTopicos>}} corpus
 * @param {string|null} selfPath path do próprio doc (self-exclude)
 * @returns {{topicoOverlaps:Array, catalogOwners:Array, moduleHint:string|null, hasSignals:boolean}}
 */
export function findOwners(signals, corpus, selfPath = null) {
  const selfPosix = selfPath ? toPosixLower(selfPath) : null;

  // 3a — tópicos que compartilham ≥1 entidade concreta (sinal FORTE: mesmo tema).
  const topicoOverlaps = [];
  for (const t of corpus.topicos) {
    if (selfPosix && toPosixLower(t.path) === selfPosix) continue; // self-exclude
    const shared = [...signals.keys].filter((k) => t.keys.has(k));
    if (!shared.length) continue;
    const score = shared.reduce((acc, k) => acc + (TYPE_WEIGHT[k.split(':')[0]] || 1), 0);
    topicoOverlaps.push({ path: t.path, module: t.module, shared, score });
  }
  topicoOverlaps.sort((a, b) => b.score - a.score || a.path.localeCompare(b.path));

  // 3b — entidades do tema que já têm DONO declarado no catálogo (sinal MÉDIO: dono de módulo).
  const ownerAgg = new Map(); // owner -> Set<entityKey>
  for (const k of signals.keys) {
    const hit = corpus.catalogIndex.byKey.get(k);
    if (!hit) continue;
    for (const o of hit.owners) {
      if (!ownerAgg.has(o)) ownerAgg.set(o, new Set());
      ownerAgg.get(o).add(k);
    }
  }
  const catalogOwners = [...ownerAgg.entries()]
    .map(([owner, keys]) => ({ owner, shared: [...keys].sort() }))
    .sort((a, b) => b.shared.length - a.shared.length || a.owner.localeCompare(b.owner));

  return {
    topicoOverlaps,
    catalogOwners,
    moduleHint: signals.module || null,
    hasSignals: signals.keys.size > 0,
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// PARTE 4 — carga do corpus do disco + relatório.
// ─────────────────────────────────────────────────────────────────────────────

/** Carrega catálogo + tópicos do disco (borda impura). */
export function loadCorpus(root) {
  const catAbs = join(root, CATALOG_REL);
  const catalog = existsSync(catAbs) ? JSON.parse(readFileSync(catAbs, 'utf8')) : { nodes: [] };
  const catalogIndex = indexCatalog(catalog);
  const files = listTopicoFiles(root);
  const docs = files.map((p) => ({ path: relFromRoot(root, p), txt: readFileSync(p, 'utf8') }));
  const topicos = indexTopicos(docs, catalogIndex.knownTables);
  return { catalogIndex, topicos };
}
function relFromRoot(root, abs) { return toPosixLower(abs).replace(toPosixLower(root) + '/', ''); }

/** Monta as linhas de texto do advisory (puro, testável). '' se nada a dizer. */
export function renderAdvisory(result, temaLabel = 'o tema candidato') {
  const { topicoOverlaps, catalogOwners, moduleHint, hasSignals } = result;
  if (!hasSignals) {
    return `ℹ️  tema-owner: ${temaLabel} não declara entidade (tabela/função/model/tela) reconhecível — não dá pra medir sobreposição. Declare anchors no frontmatter pra checar dono.`;
  }
  const lines = [];
  if (topicoOverlaps.length) {
    lines.push(`⚠️  tema-owner: ${temaLabel} SOBREPÕE tópico(s) existente(s) — considere ESTENDER em vez de criar paralelo:`);
    for (const t of topicoOverlaps.slice(0, 5)) {
      lines.push(`   ↔ ${t.path}  (compartilha: ${t.shared.map(labelKey).join(', ')})`);
    }
  }
  if (catalogOwners.length) {
    lines.push(`ℹ️  entidades com DONO no catálogo (memory/governance/catalog.json):`);
    for (const o of catalogOwners.slice(0, 5)) {
      lines.push(`   • módulo ${o.owner} é dono de: ${o.shared.map(labelKey).join(', ')}`);
    }
  }
  if (!topicoOverlaps.length && !catalogOwners.length) {
    const hint = moduleHint ? ` (declara module: ${moduleHint} — confira BRIEFING/SCOPE/SPEC de ${moduleHint} antes de criar)` : '';
    lines.push(`✅ tema-owner: ${temaLabel} — nenhuma entidade declarada colide com dono existente; aparenta ser tema NOVO${hint}.`);
  } else {
    lines.push(`   (advisory — não bloqueia. A decisão de estender-vs-criar é sua; existência de dono ≠ que ele já cobre CERTO.)`);
  }
  return lines.join('\n');
}

// ── CLI ──
function arg(name, d = '') { const h = process.argv.find((a) => a.startsWith(`--${name}=`)); if (h) return h.slice(name.length + 3); const i = process.argv.indexOf(`--${name}`); return i !== -1 && process.argv[i + 1] && !process.argv[i + 1].startsWith('--') ? process.argv[i + 1] : d; }
function listArg(name) { const v = arg(name); return v ? v.split(',').map((x) => x.trim()).filter(Boolean) : []; }

function main() {
  const ROOT = process.cwd();
  const asJson = process.argv.includes('--json');
  const corpus = loadCorpus(ROOT);

  let signals;
  let temaLabel;
  const temaFile = arg('tema-arquivo');
  if (temaFile) {
    if (!existsSync(temaFile)) { console.error(`✗ arquivo não encontrado: ${temaFile}`); process.exit(2); }
    signals = signalsFromDoc(readFileSync(temaFile, 'utf8'), corpus.catalogIndex.knownTables);
    temaLabel = temaFile;
  } else {
    // sinais explícitos
    const byType = {};
    for (const t of ENTITY_TYPES) byType[t] = new Set();
    const keys = new Set();
    const map = { tables: 'tabelas', functions: 'functions', models: 'models', controllers: 'controllers', routes: 'routes', screens: 'screens' };
    for (const [t, flag] of Object.entries(map)) {
      for (const raw of listArg(flag)) { const k = entityKey(t, raw); if (k) { keys.add(k); byType[t].add(k); } }
    }
    signals = { module: arg('module') || null, keys, byType };
    temaLabel = 'o tema informado';
    if (!keys.size && !signals.module) { console.error('uso: --tema-arquivo <path.md> | --tabelas a,b [--functions X::y --models .. --module M]'); process.exit(2); }
  }

  const selfPath = temaFile ? relFromRoot(ROOT, join(ROOT, temaFile)) : null;
  const result = findOwners(signals, corpus, selfPath);

  if (asJson) {
    process.stdout.write(JSON.stringify({
      tema: temaLabel,
      topico_overlaps: result.topicoOverlaps,
      catalog_owners: result.catalogOwners,
      module_hint: result.moduleHint,
      has_signals: result.hasSignals,
    }, null, 2) + '\n');
    return;
  }
  console.log(renderAdvisory(result, temaLabel));
}

if (process.argv[1] && pathToFileURL(process.argv[1]).href === import.meta.url) {
  if (process.argv.includes('--selftest')) {
    const { spawnSync } = await import('node:child_process');
    const test = new URL('./tema-owner.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
