#!/usr/bin/env node
// @ts-check
/**
 * catalog-graph.mjs — GERADOR determinístico do GRAFO TIPADO de módulos.
 *
 * A DOR (grade catálogo/IDP 7,0, memory/sessions/2026-07-21-grade-catalogo-aprendizado-vs-mercado.md,
 * chip #2): o `memory/requisitos/<X>/SCOPE.md` é o descritor por módulo (estilo Backstage `catalog-info.yaml`),
 * mas hoje é markdown lido por humano/IA — NÃO um grafo tipado consultável. Backstage/Cortex/Port
 * são graph-native (`dependsOn`/`providesApi`/`partOf`), o que deixa perguntar "que módulo quebra se
 * a tabela X (ou o módulo Y) mudar". Este gerador DERIVA as arestas dos SCOPE.md e emite um
 * `catalog.json` consultável (nós + arestas tipadas).
 *
 * DOUTRINA (ADR 0256): derivado sobrevive; escrito+lembrado apodrece. O grafo é 100% recalculado
 * dos SCOPE.md, SUPERFICIE.md Classe B e do frontmatter de memory/decisions/*.md (linhagem ADR→ADR)
 * — nada à mão. NÃO INVENTA relação que as fontes não declaram:
 * campos estruturados do frontmatter (`depends_on`, `db_tables_owned`/`db_tables_consumed`/`db_tables_legacy_views`,
 * `related_adrs`/`charter_adr`, `url_prefixes`, `contains`, e os cross-refs `→ Modules/X` que vivem
 * DENTRO de `not_contains` + `drift_alerts.pertence_a`). Prosa do corpo markdown é ignorada de
 * propósito (não é declaração — é narrativa, e não-uniforme).
 *
 * SEM data volátil no corpo (igual `module-surface.mjs`): o frescor é provado por `--check`
 * (committed == regerado), não por timestamp que apodrece (§5 2026-07-17 — recibo é query
 * re-rodável, não afirmação atemporal). Logo o JSON é byte-determinístico.
 *
 * ENFORCEMENT: o dono é `governance/required-checks-baseline.json` — não este cabeçalho. Fato datado:
 * nasceu advisory em 2026-07 (ADR 0314/0275, "required = só Tier-0") e o job `catalog.json == SCOPEs
 * + Classes B` foi PROMOVIDO a required em 2026-08-05 (ADR 0370, 6 mordidas medidas). Logo `--check`
 * vermelho (drift OU aresta pendurada) hoje BLOQUEIA — consulte o baseline, não a memória.
 *
 * O que ele NÃO faz (delega): superfície de código por papel é do `module-surface.mjs`; cobertura/nota
 * de tela é do `screen-coverage`/`casos-gate`. Aqui é só o GRAFO de fronteiras entre módulos.
 *
 * Uso:
 *   node scripts/governance/catalog-graph.mjs            (dry-run: resumo + diagnósticos, exit 0)
 *   node scripts/governance/catalog-graph.mjs --write    (grava memory/governance/catalog.json)
 *   node scripts/governance/catalog-graph.mjs --check     (CI advisory: exit 1 se DRIFT ou aresta pendurada)
 *   node scripts/governance/catalog-graph.mjs --json      (imprime o catalog.json no stdout, não grava)
 *   node scripts/governance/catalog-graph.mjs --mermaid   (VISTA: diagrama de fluxo entre módulos, stdout)
 *   node scripts/governance/catalog-graph.mjs --mermaid --focus=Financeiro   (vizinhança de 1 salto)
 *   node scripts/governance/catalog-graph.mjs --acoplamento  (ADVISORY: fronteira REAL (import) vs DECLARADA; exit 0 sempre)
 *
 * Refs: ADR 0256 (survival, fonte única gerada) · ADR 0314/0275 (advisory-primeiro) ·
 *       grade 2026-07-21 (chip #2 "arestas tipadas no catálogo") · irmão `module-surface.mjs`.
 */
import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { execFileSync } from 'node:child_process';

const ROOT = process.cwd();
const args = process.argv.slice(2);
const MODE = args.includes('--write') ? 'write' : args.includes('--check') ? 'check' : 'dry';
const PRINT_JSON = args.includes('--json');
const OUT_REL = 'memory/governance/catalog.json';

/** Tipos declarados (documentação viva do modelo — também vai no header do JSON). */
const NODE_TYPES = ['module', 'table', 'adr', 'component', 'api'];
const EDGE_TYPES = [
  'ownsTable',       // module → table        (db_tables_owned)
  'ownsLegacyView',  // module → table(view)  (db_tables_legacy_views)
  'consumesTable',   // module → table        (db_tables_consumed)
  'providesApi',     // module → api          (url_prefixes)
  'charteredByAdr',  // module → adr          (charter_adr)
  'governedByAdr',   // module → adr          (related_adrs)
  'hasComponent',    // module → component    (contains)
  'dependsOn',       // module → module       (fronteira declarada OU tabela consumida→dono)
  'delegatesTo',     // module → module       (not_contains "→ Modules/X" — fronteira declarada)
  'migratesTo',      // module → module       (drift_alerts.pertence_a "Modules/X")
  'supersedes',          // adr → adr         (frontmatter `supersedes` de memory/decisions/*.md)
  'supersedesPartially', // adr → adr         (`supersedes_partially` — emenda parcial, ADR 0317)
  'supersededBy',        // adr → adr         (`superseded_by` — declaração do lado sucedido)
];

// ─────────────────────────────────────────────────────────────────────────────
// Parser de frontmatter (flat: escalares + listas-de-string). Blocos aninhados
// (drift_alerts com `- controller:`) ficam como itens-string crus e são lidos à
// parte via regex. Suficiente pros campos que consumimos — sem dependência de YAML.
// ─────────────────────────────────────────────────────────────────────────────

/** Tira aspas simples/duplas de um valor escalar. */
function unquote(s) {
  const t = s.trim();
  if ((t.startsWith('"') && t.endsWith('"')) || (t.startsWith("'") && t.endsWith("'"))) {
    return t.slice(1, -1);
  }
  return t;
}

/**
 * @param {string} txt conteúdo do SCOPE.md
 * @returns {{ fields: Record<string, string|string[]>, raw: string }}
 *   fields: escalares como string, listas como string[] (comentários `#` e `[]` tratados).
 *   raw: o texto do frontmatter (pra regex de campos aninhados como pertence_a).
 */
function parseFrontmatter(txt) {
  const norm = txt.replace(/\r\n/g, '\n');
  if (!norm.startsWith('---\n')) return { fields: {}, raw: '' };
  const end = norm.indexOf('\n---', 4);
  const raw = end === -1 ? norm.slice(4) : norm.slice(4, end + 1);
  const lines = raw.split('\n');
  /** @type {Record<string, string|string[]>} */
  const fields = {};
  let key = null;
  /** @type {string[]|null} */
  let list = null;
  for (const line of lines) {
    // Item de lista: `  - valor` (ou `  # comentário` → pula). Só quando estamos num bloco.
    if (list && /^\s+-\s+/.test(line)) {
      list.push(unquote(line.replace(/^\s+-\s+/, '')));
      continue;
    }
    if (list && /^\s+#/.test(line)) continue; // comentário dentro do bloco de lista
    if (list && /^\s+\S/.test(line) && !/^[A-Za-z_]/.test(line)) continue; // sub-campo aninhado (drift_alerts) — ignora
    // Chave de topo: `chave: [valor]`
    const m = line.match(/^([A-Za-z_][A-Za-z0-9_]*):\s*(.*)$/);
    if (m) {
      key = m[1];
      const val = m[2].trim();
      if (val === '' ) {
        list = [];
        fields[key] = list;
      } else if (val === '[]') {
        list = null;
        fields[key] = [];
      } else {
        list = null;
        fields[key] = unquote(val);
      }
      continue;
    }
    // Linha de comentário de topo ou vazia entre chaves — encerra a lista corrente.
    if (/^\s*#/.test(line) || line.trim() === '') { /* mantém list p/ comentários intercalados */ }
  }
  return { fields, raw };
}

// ─────────────────────────────────────────────────────────────────────────────
// Extratores dos campos → tokens tipados (puros, testáveis).
// ─────────────────────────────────────────────────────────────────────────────

/** Nome do componente a partir de um item de `contains`: parte antes de ` — `/` – `/` - `/` (`. */
function componentNameFromContains(entry) {
  const s = String(entry).trim();
  const cut = s.search(/\s[—–-]\s|\s\(/);
  const name = (cut === -1 ? s : s.slice(0, cut)).trim();
  return name;
}

/** Descrição do componente (o resto depois do delimitador), ou '' se não houver. */
function componentDescFromContains(entry) {
  const s = String(entry).trim();
  const m = s.match(/\s[—–-]\s(.+)$/);
  return m ? m[1].trim() : '';
}

/**
 * Nomes de tabela "limpos" de um item de `db_tables_*`. Um item pode empacotar VÁRIAS tabelas
 * separadas por vírgula + anotação entre parênteses. Ex.:
 *   "mcp_cycles, mcp_tasks, mcp_decisions (lidos via procedure)" → ['mcp_cycles','mcp_tasks','mcp_decisions']
 *   "copiloto_metas (view)" → ['copiloto_metas']
 */
function tableNamesFrom(entry) {
  return String(entry)
    .split(',')
    .map((p) => (p.trim().match(/^[a-z_][a-z0-9_]*/) || [])[0])
    .filter(Boolean);
}

/** Prefixo de URL "limpo" de um item de `url_prefixes` (1º token, ex. `/jana/* (canônico)` → `/jana/*`). */
function apiPrefixFrom(entry) {
  const tok = String(entry).trim().split(/\s+/)[0];
  return tok.startsWith('/') ? tok : '';
}

/** Número de 4 dígitos de um slug/num de ADR (`0093-multi-tenant...` → `0093`; `0080` → `0080`). */
function adrNumFrom(v) {
  const m = String(v).match(/(\d{4})/);
  return m ? m[1] : '';
}

/** Todos os `Modules/X` referenciados num item de `not_contains` (ou string qualquer). */
function moduleRefsIn(entry) {
  const out = [];
  const re = /Modules\/([A-Z][A-Za-z0-9]+)/g;
  let m;
  while ((m = re.exec(String(entry)))) out.push(m[1]);
  return out;
}

/** Nota (o "porquê") de um item de not_contains: texto antes da seta `→`. */
function delegationNote(entry) {
  const s = String(entry);
  const i = s.indexOf('→');
  return (i === -1 ? s : s.slice(0, i)).trim();
}

/** Alvos `Modules/X` declarados em `drift_alerts[].pertence_a:` (via regex no raw do frontmatter). */
function migrateTargetsFromRaw(raw) {
  const out = [];
  const re = /pertence_a:\s*"?([^"\n]*)"?/g;
  let m;
  while ((m = re.exec(raw))) out.push(...moduleRefsIn(m[1]));
  return out;
}

// ─────────────────────────────────────────────────────────────────────────────
// Relações ADR→ADR (supersedes / supersedes_partially / superseded_by).
//
// POR QUE AQUI: a relação já é VALIDADA pelo dono (`adr-index-generate.mjs` pega
// supersessão dangling, alvo-não-marcado, órfã, double-supersede e "declarada só em
// prosa", dentro do required `Governance Gate`). O que faltava era ela ser PUBLICADA
// como dado — o grafo tinha 0 arestas adr→adr. Aqui só se DERIVA e se publica; a
// integridade continua sendo do dono, e este gerador NÃO re-declara aquelas regras.
//
// MEDIDO em 2026-08-11 sobre memory/decisions/ (380 arquivos):
//   supersedes 15 · supersedes_partially 48 · superseded_by 19 pares.
//   100% dos itens são declarados por SLUG INTEIRO (0 números crus) — por isso a
//   resolução aqui é por slug, não por número.
// FORA de propósito (medido, não presumido):
//   `related` 1602 pares → +192% de arestas num grafo de 834, semântica vaga e 11
//   alvos pendurados; inchava o grafo sem responder pergunta nova. `amends` 39 pares
//   NÃO está no schema de ADR (scripts/memory-schemas/adr.schema.json) — emitir seria
//   inventar tipo de aresta a partir de campo não-canônico.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Itens CRUS (slug inteiro) de uma lista do frontmatter de ADR. Aceita inline
 * (`supersedes: [a, b]`) e bloco (`- "a"  # nota`).
 *
 * ⚠️ ORDEM DA LIMPEZA: tira o comentário `#` ANTES das aspas. O `rawItemsFrom` do
 * `adr-index-generate.mjs` faz o INVERSO (aspas → comentário), então um item escrito
 * `- "0189-slug"   # nota` volta de lá como `0189-slug"` — com a aspa presa. Isso é
 * artefato do parser, NÃO dado: medido em 2 itens de `superseded_by` (0182), que
 * parecem slug pendurado e não são. Aqui a ordem correta evita reproduzir o defeito.
 * (O defeito no dono é latente — hoje só o 0358 usa aquele caminho e é inline, sem
 * comentário. Corrigi-lo é intent separado, no arquivo dele.)
 *
 * @param {string} fm  frontmatter cru
 * @param {string} key nome do campo
 * @returns {string[]}
 */
function adrRelationItems(fm, key) {
  const clean = (s) => s.split('#')[0].trim().replace(/^['"]|['"]$/g, '').trim();
  const inline = fm.match(new RegExp(`^${key}:\\s*\\[([^\\]]*)\\]`, 'mi'));
  if (inline) return inline[1].split(',').map(clean).filter(Boolean);
  const block = fm.match(new RegExp(`^${key}:\\s*\\n((?:\\s*-\\s*.+\\n?)+)`, 'mi'));
  if (block) return block[1].split('\n').map((l) => clean(l.replace(/^\s*-\s*/, ''))).filter(Boolean);
  return [];
}

/**
 * Resolve um slug declarado → identidade de nó, SEM colapsar coisas diferentes.
 *
 * A regra de identidade (o ponto delicado): o id `adr:NNNN` só é usado quando o NÚMERO
 * é inequívoco. 13 números do repo têm 2 ADRs distintas (baseline curado em
 * `governance/adr-collisions-baseline.json`; o detector é do `adr-index-generate.mjs`,
 * que segue o dono — aqui NÃO se re-detecta colisão). Medido: 6 arestas têm ponta em
 * número colidido, e 2 delas apontam pra ADRs DIFERENTES que cairiam no MESMO
 * `adr:0180`. Emitir por número publicaria fato falso. Nesses casos o id é
 * `adr:<slug>`, que é preciso por construção (ADR 0274: o slug é quem desambigua).
 *
 * TOMBSTONE (ADR 0316): slug fora do disco mas no ledger de esquecimento é morte
 * LEGÍTIMA, não aresta pendurada — espelha `adr-index-generate.mjs` ("supersede de ADR
 * esquecida ≠ dangling"). Caso vivo: `0358 supersedes 0101-tests-business-id-1-nunca-cliente`.
 * Por número isso resolveria pra `0101-sistema-charter-capterra-governanca-escopo`, que
 * é OUTRA ADR, viva — o fato falso que esta função existe pra impedir.
 *
 * @param {string} slug              slug declarado (ex. "0093-multi-tenant-isolation-tier-0")
 * @param {{ knownSlugs: Set<string>, collidedNums?: Set<string>, tombstonedSlugs?: Set<string> }} idx
 * @returns {{ id: string, num: string, slug: string, exists: boolean, tombstoned: boolean } | null}
 */
export function resolveAdrTarget(slug, idx) {
  const s = String(slug || '').trim();
  const num = adrNumFrom(s);
  if (!num) return null;
  const known = idx.knownSlugs?.has(s) ?? false;
  const tombstoned = !known && (idx.tombstonedSlugs?.has(s) ?? false);
  // Número ambíguo (colidido) OU slug que não é um arquivo vivo → id qualificado pelo slug.
  const ambiguo = idx.collidedNums?.has(num) ?? false;
  const id = known && !ambiguo ? `adr:${num}` : `adr:${s}`;
  return { id, num, slug: s, exists: known, tombstoned };
}

/** Garante array (o parser devolve string se a lista tinha 1 valor inline, ou [] se vazia). */
function asList(v) {
  if (Array.isArray(v)) return v;
  if (v === undefined || v === null || v === '') return [];
  return [String(v)];
}

// ─────────────────────────────────────────────────────────────────────────────
// Leitura dos SCOPE.md → registros estruturados.
// ─────────────────────────────────────────────────────────────────────────────

/** Lista os módulos com SCOPE.md (dirs em Modules/), ordenado. */
function listScopeModules() {
  const dir = join(ROOT, 'Modules');
  if (!existsSync(dir)) return [];
  return readdirSync(dir)
    .filter((m) => existsSync(join(ROOT, 'memory', 'requisitos', m, 'SCOPE.md')))
    .sort();
}

/** Classes B derivadas dos SUPERFICIE.md gerados (Produto/Sells, sem SCOPE em Modules/). */
function listCoreClassBRecords() {
  const dir = join(ROOT, 'memory', 'requisitos');
  if (!existsSync(dir)) return [];
  return readdirSync(dir).sort().flatMap((module) => {
    const rel = `memory/requisitos/${module}/SUPERFICIE.md`;
    if (!existsSync(join(ROOT, rel))) return [];
    const txt = readFileSync(join(ROOT, rel), 'utf8');
    if (!/CLASSE B/.test(txt)) return [];
    return [{ module, path: rel, purpose: 'Domínio core UltimatePOS (Classe B)', trust: '', owner: '', permission_prefix: '',
      charter_adr: '', related_adrs: [], url_prefixes: [], contains: [], not_contains: [], depends_on: [],
      db_tables_owned: [], db_tables_consumed: [], db_tables_legacy_views: [], migrate_targets: [], catalog_kind: 'core-class-b' }];
  });
}

/** Números de ADR que EXISTEM em memory/decisions/ (pra checar aresta ADR pendurada). */
function knownAdrNumbers() {
  const dir = join(ROOT, 'memory/decisions');
  if (!existsSync(dir)) return new Set();
  return new Set(
    readdirSync(dir)
      .map((f) => (f.match(/^(\d{4})-.*\.md$/) || [])[1])
      .filter(Boolean),
  );
}

/**
 * Lê `memory/decisions/NNNN-*.md` → índice de identidade + relações declaradas.
 * Um passo de IO só; toda a decisão fica nas funções puras acima.
 * @returns {{ records: {num:string,slug:string,supersedes:string[],supersedes_partially:string[],superseded_by:string[]}[], slugs: Set<string>, collidedNums: Set<string> }}
 */
function readAdrIndex() {
  const dir = join(ROOT, 'memory/decisions');
  if (!existsSync(dir)) return { records: [], slugs: new Set(), collidedNums: new Set() };
  const records = [];
  const slugs = new Set();
  /** @type {Map<string, number>} */
  const perNum = new Map();
  for (const file of readdirSync(dir).sort()) {
    const m = file.match(/^(\d{4})-(.+)\.md$/);
    if (!m) continue;
    const [num, slug] = [m[1], `${m[1]}-${m[2]}`];
    slugs.add(slug);
    perNum.set(num, (perNum.get(num) || 0) + 1);
    const txt = readFileSync(join(dir, file), 'utf8');
    // Só o frontmatter: prosa do corpo é narrativa, não declaração (mesma doutrina dos SCOPE.md).
    const end = txt.startsWith('---') ? txt.indexOf('\n---', 3) : -1;
    const fm = end === -1 ? (txt.startsWith('---') ? txt : '') : txt.slice(0, end);
    records.push({
      num, slug,
      supersedes: adrRelationItems(fm, 'supersedes'),
      supersedes_partially: adrRelationItems(fm, 'supersedes_partially'),
      superseded_by: adrRelationItems(fm, 'superseded_by'),
    });
  }
  const collidedNums = new Set([...perNum].filter(([, c]) => c > 1).map(([n]) => n));
  return { records, slugs, collidedNums };
}

/**
 * Slugs de ADR TOMBADA (ADR 0316) — `governance/adr-tombstones.json` é o dono de
 * "esse número morreu, quando e por qual ADR"; aqui só se LÊ (§5: aponta pro dono,
 * não restateia). Ledger ausente/ilegível → set vazio, e aí a supersessão de ADR
 * esquecida volta a aparecer como pendurada (fail-safe idêntico ao do dono).
 * @returns {Set<string>}
 */
function loadAdrTombstoneSlugs() {
  const abs = join(ROOT, 'governance/adr-tombstones.json');
  if (!existsSync(abs)) return new Set();
  try {
    const j = JSON.parse(readFileSync(abs, 'utf8'));
    return new Set((j.tombstones ?? []).map((t) => String(t.slug || '').trim()).filter(Boolean));
  } catch {
    return new Set();
  }
}

/** Lê 1 SCOPE.md → registro { module, purpose, ... , contains[], not_contains[], tables }. */
function readScope(mod) {
  const rel = `memory/requisitos/${mod}/SCOPE.md`;
  const { fields, raw } = parseFrontmatter(readFileSync(join(ROOT, rel), 'utf8'));
  return {
    module: typeof fields.module === 'string' ? fields.module : mod,
    path: rel,
    purpose: typeof fields.purpose === 'string' ? fields.purpose : '',
    trust: typeof fields.trust_required === 'string' ? fields.trust_required : '',
    owner: typeof fields.owner === 'string' ? fields.owner : '',
    permission_prefix: typeof fields.permission_prefix === 'string' ? fields.permission_prefix : '',
    charter_adr: typeof fields.charter_adr === 'string' ? fields.charter_adr : '',
    related_adrs: asList(fields.related_adrs),
    url_prefixes: asList(fields.url_prefixes),
    contains: asList(fields.contains),
    not_contains: asList(fields.not_contains),
    depends_on: asList(fields.depends_on),
    db_tables_owned: asList(fields.db_tables_owned),
    db_tables_consumed: asList(fields.db_tables_consumed),
    db_tables_legacy_views: asList(fields.db_tables_legacy_views),
    migrate_targets: migrateTargetsFromRaw(raw),
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Construção do grafo (pura — recebe registros + set de ADRs conhecidos).
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @param {ReturnType<typeof readScope>[]} records
 * @param {{ knownAdrs?: Set<string> }} [opts]
 */
function buildGraph(records, opts = {}) {
  const knownAdrs = opts.knownAdrs; // undefined = pula check de ADR pendurada
  const adrRel = opts.adrRelations; // undefined = nenhuma aresta adr→adr (comportamento antigo)
  const moduleSet = new Set(records.map((r) => r.module));

  /** @type {Map<string, any>} */
  const nodes = new Map();
  const edges = [];
  const ensure = (id, make) => { if (!nodes.has(id)) nodes.set(id, make()); return nodes.get(id); };
  const addEdge = (from, to, type, source, note) => {
    edges.push(note ? { from, to, type, source, note } : { from, to, type, source });
  };

  for (const r of records) {
    const mid = `module:${r.module}`;
    const moduleData = {
      id: mid, type: 'module', module: r.module, purpose: r.purpose,
      trust: r.trust, owner: r.owner, permission_prefix: r.permission_prefix,
      charter_adr: adrNumFrom(r.charter_adr), path: r.path,
      catalog_kind: r.catalog_kind || 'scope', catalog_status: 'catalogued',
    };
    Object.assign(ensure(mid, () => moduleData), moduleData);

    // tabelas próprias / views legadas / consumidas (1 item pode listar N tabelas)
    for (const t of r.db_tables_owned.flatMap(tableNamesFrom)) {
      const tid = `table:${t}`;
      const n = ensure(tid, () => ({ id: tid, type: 'table', name: t, owners: [], consumers: [], legacy_views: [] }));
      n.owners.push(r.module);
      addEdge(mid, tid, 'ownsTable', 'db_tables_owned');
    }
    for (const name of r.db_tables_legacy_views.flatMap(tableNamesFrom)) {
      const tid = `table:${name}`;
      const n = ensure(tid, () => ({ id: tid, type: 'table', name, owners: [], consumers: [], legacy_views: [] }));
      if (!n.legacy_views.includes(r.module)) n.legacy_views.push(r.module);
      addEdge(mid, tid, 'ownsLegacyView', 'db_tables_legacy_views');
    }
    for (const t of r.db_tables_consumed.flatMap(tableNamesFrom)) {
      const tid = `table:${t}`;
      const n = ensure(tid, () => ({ id: tid, type: 'table', name: t, owners: [], consumers: [], legacy_views: [] }));
      n.consumers.push(r.module);
      addEdge(mid, tid, 'consumesTable', 'db_tables_consumed');
    }

    // APIs (superfície de URL declarada)
    for (const p of r.url_prefixes) {
      const prefix = apiPrefixFrom(p);
      if (!prefix) continue;
      const aid = `api:${prefix}`;
      ensure(aid, () => ({ id: aid, type: 'api', prefix, providers: [] }));
      const an = nodes.get(aid);
      if (!an.providers.includes(r.module)) an.providers.push(r.module);
      addEdge(mid, aid, 'providesApi', 'url_prefixes');
    }

    // ADR do charter
    const charterNum = adrNumFrom(r.charter_adr);
    if (charterNum) {
      const nid = `adr:${charterNum}`;
      ensure(nid, () => ({ id: nid, type: 'adr', num: charterNum, slug: '', exists: knownAdrs ? knownAdrs.has(charterNum) : true }));
      addEdge(mid, nid, 'charteredByAdr', 'charter_adr');
    }
    // ADRs relacionadas (related_adrs) — guarda o slug completo no nó (1ª vez visto)
    for (const slug of r.related_adrs) {
      const num = adrNumFrom(slug);
      if (!num) continue;
      const nid = `adr:${num}`;
      const n = ensure(nid, () => ({ id: nid, type: 'adr', num, slug: '', exists: knownAdrs ? knownAdrs.has(num) : true }));
      // Só carimba o slug se ele for de fato um arquivo VIVO e o número for inequívoco.
      // Sem essa guarda, um SCOPE que cita ADR tombada/renomeada carimba o slug MORTO no
      // nó do número — que hoje pertence a OUTRA ADR viva. Era o caso real de `adr:0101`:
      // Fiscal/SCOPE.md cita `0101-tests-...` (tombada, ADR 0316) e o nó do 0101 vivo
      // (`0101-sistema-charter-...`) saía rotulado com o slug da morta. Sem índice de ADR
      // (testes sintéticos), mantém o comportamento antigo.
      const slugConfiavel = adrRel
        ? adrRel.slugs.has(String(slug).trim()) && !adrRel.collidedNums.has(num)
        : true;
      if (!n.slug && slugConfiavel && /\d{4}-[a-z0-9-]+/.test(String(slug))) n.slug = String(slug).trim();
      addEdge(mid, nid, 'governedByAdr', 'related_adrs');
    }
    // componentes (contains)
    for (const c of r.contains) {
      const name = componentNameFromContains(c);
      if (!name) continue;
      const cid = `component:${r.module}/${name}`;
      ensure(cid, () => ({ id: cid, type: 'component', module: r.module, name, desc: componentDescFromContains(c) }));
      addEdge(mid, cid, 'hasComponent', 'contains');
    }
    // fronteiras declaradas (not_contains → Modules/X)
    for (const nc of r.not_contains) {
      const note = delegationNote(nc);
      for (const target of moduleRefsIn(nc)) {
        if (target === r.module) continue; // self-ref não é aresta
        ensure(`module:${target}`, () => ({ id: `module:${target}`, type: 'module', module: target,
          purpose: '', trust: '', owner: '', permission_prefix: '', charter_adr: '', path: null,
          catalog_kind: 'referenced-only', catalog_status: 'referenced-only' }));
        addEdge(mid, `module:${target}`, 'delegatesTo', 'not_contains', note);
      }
    }
    // Dependência explícita. Aceita `Sells` ou `Modules/Sells`; diferente de
    // not_contains, declara consumo real sem fingir delegação de escopo.
    for (const declared of r.depends_on || []) {
      const target = String(declared).replace(/^Modules\//, '').trim();
      if (!target || target === r.module) continue;
      ensure(`module:${target}`, () => ({ id: `module:${target}`, type: 'module', module: target,
        purpose: '', trust: '', owner: '', permission_prefix: '', charter_adr: '', path: null,
        catalog_kind: 'referenced-only', catalog_status: 'referenced-only' }));
      addEdge(mid, `module:${target}`, 'dependsOn', 'depends_on');
    }
    // migrações planejadas (drift_alerts.pertence_a)
    for (const target of r.migrate_targets) {
      if (target === r.module) continue;
      ensure(`module:${target}`, () => ({ id: `module:${target}`, type: 'module', module: target,
        purpose: '', trust: '', owner: '', permission_prefix: '', charter_adr: '', path: null,
        catalog_kind: 'referenced-only', catalog_status: 'referenced-only' }));
      addEdge(mid, `module:${target}`, 'migratesTo', 'drift_alerts.pertence_a');
    }
  }

  // ── relações ADR→ADR (linhagem de decisão) ────────────────────────────────
  // Sem `adrRelations` o comportamento é idêntico ao de antes (nenhuma aresta adr→adr),
  // que é o que mantém os testes sintéticos de módulo válidos sem tocá-los.
  if (adrRel) {
    const idx = {
      knownSlugs: adrRel.slugs,
      collidedNums: adrRel.collidedNums,
      tombstonedSlugs: opts.tombstonedAdrSlugs || new Set(),
    };
    /** Cria/atualiza o nó de uma ponta. Só carimba `slug` quando o número é inequívoco. */
    const ensureAdrNode = (t) => {
      const n = ensure(t.id, () => {
        const base = { id: t.id, type: 'adr', num: t.num, slug: '', exists: t.exists };
        // `tombstoned` só aparece quando true: nó de ADR normal fica byte-idêntico ao de antes.
        if (t.tombstoned) base.tombstoned = true;
        return base;
      });
      if (!n.slug && t.exists && !idx.collidedNums.has(t.num)) n.slug = t.slug;
      return n;
    };
    // campo do frontmatter → tipo de aresta. `source` guarda o campo, igual às demais.
    const REL = [
      ['supersedes', 'supersedes'],
      ['supersedes_partially', 'supersedesPartially'],
      ['superseded_by', 'supersededBy'],
    ];
    for (const a of adrRel.records) {
      const from = resolveAdrTarget(a.slug, idx);
      if (!from) continue;
      for (const [field, edgeType] of REL) {
        for (const declared of a[field] || []) {
          const to = resolveAdrTarget(declared, idx);
          if (!to || to.id === from.id) continue; // self-ref não é aresta (medido: 0, guarda mesmo assim)
          ensureAdrNode(from);
          ensureAdrNode(to);
          addEdge(from.id, to.id, edgeType, field);
        }
      }
    }
  }

  // Relação estilo Backstage/Cortex: fronteira declarada e consumo de tabela viram
  // dependsOn explícito, preservando também a aresta-fonte auditável.
  for (const e of [...edges]) {
    if ((e.type === 'delegatesTo' || e.type === 'migratesTo') && e.from !== e.to) {
      addEdge(e.from, e.to, 'dependsOn', e.type, e.note || undefined);
    }
    if (e.type === 'consumesTable') {
      const table = nodes.get(e.to);
      for (const owner of table?.owners || []) {
        const target = `module:${owner}`;
        if (target !== e.from) addEdge(e.from, target, 'dependsOn', 'db_tables_consumed→db_tables_owned', table.name);
      }
    }
  }

  // ── diagnósticos (arestas penduradas + smells de tabela) ──────────────────
  for (const n of nodes.values()) {
    if (n.type === 'table') n.ownership_mode = n.owners.length > 1 ? 'shared-declared' : n.owners.length === 1 ? 'single' : 'unowned';
  }

  const diagnostics = {
    referenced_only_modules: [...nodes.values()]
      .filter((n) => n.type === 'module' && n.catalog_status === 'referenced-only')
      .map((n) => ({ module: n.module, reason: 'referenciado por SCOPE, sem descritor próprio' })),
    dangling_module_refs: [],
    // Com `adrRelations` a existência é decidida por SLUG (definitiva), então o
    // diagnóstico vale mesmo sem `knownAdrs` — que só cobre o eixo módulo→adr.
    dangling_adr_refs: (knownAdrs || adrRel)
      ? [...nodes.values()]
          // ADR TOMBADA (0316) não é pendurada: é morte legítima, com lápide curada em
          // governance/adr-tombstones.json. Mesma regra do adr-index-generate.mjs.
          .filter((n) => n.type === 'adr' && n.exists === false && !n.tombstoned)
          .flatMap((n) => edges.filter((e) => e.to === n.id).map((e) => ({ from: e.from, to: n.id, type: e.type, source: e.source })))
      : [],
    // Informativo (nunca fatal): quem sucede ADR já esquecida fisicamente.
    adr_supersession_of_tombstoned: [...nodes.values()]
      .filter((n) => n.type === 'adr' && n.tombstoned === true)
      .flatMap((n) => edges.filter((e) => e.to === n.id).map((e) => ({ from: e.from, to: n.id, type: e.type, source: e.source }))),
    consumed_tables_without_catalog_owner: [...nodes.values()]
      .filter((n) => n.type === 'table' && n.consumers.length > 0 && n.owners.length === 0 && n.legacy_views.length === 0)
      .map((n) => ({ table: n.name, consumers: [...n.consumers].sort() })),
    tables_owned_by_multiple: [...nodes.values()]
      .filter((n) => n.type === 'table' && n.owners.length > 1)
      .map((n) => ({ table: n.name, owners: [...n.owners].sort() })),
  };
  for (const k of Object.keys(diagnostics)) {
    diagnostics[k].sort((a, b) => JSON.stringify(a).localeCompare(JSON.stringify(b)));
  }

  return { nodes: [...nodes.values()], edges, diagnostics, moduleCount: moduleSet.size };
}

// ─────────────────────────────────────────────────────────────────────────────
// Serialização determinística.
// ─────────────────────────────────────────────────────────────────────────────

/** Ordena nós por id; arestas por (from, type, to, note). */
function sortGraph(graph) {
  const nodes = [...graph.nodes].sort((a, b) => a.id.localeCompare(b.id));
  // normaliza arrays internos dos nós (owners/consumers/providers/legacy_views) pra determinismo
  for (const n of nodes) {
    for (const f of ['owners', 'consumers', 'providers', 'legacy_views']) {
      if (Array.isArray(n[f])) n[f] = [...new Set(n[f])].sort();
    }
  }
  const edges = [...graph.edges].sort((a, b) =>
    (a.from + '|' + a.type + '|' + a.to + '|' + (a.note || '')).localeCompare(
      b.from + '|' + b.type + '|' + b.to + '|' + (b.note || ''),
    ),
  );
  return { nodes, edges };
}

/** Monta o objeto catalog (com stats) e serializa em JSON determinístico + newline final. */
function serialize(graph) {
  const { nodes, edges } = sortGraph(graph);
  const byEdgeType = {};
  for (const t of EDGE_TYPES) byEdgeType[t] = 0;
  for (const e of edges) byEdgeType[e.type] = (byEdgeType[e.type] || 0) + 1;
  const byNodeType = {};
  for (const t of NODE_TYPES) byNodeType[t] = 0;
  for (const n of nodes) byNodeType[n.type] = (byNodeType[n.type] || 0) + 1;
  const danglingTotal =
    graph.diagnostics.dangling_module_refs.length + graph.diagnostics.dangling_adr_refs.length;

  const catalog = {
    $generator: 'scripts/governance/catalog-graph.mjs',
    $doc: 'Grafo tipado DERIVADO dos memory/requisitos/*/SCOPE.md (ADR 0256). NÃO editar à mão — a próxima geração sobrescreve. Regenerar: node scripts/governance/catalog-graph.mjs --write',
    $enforcement: 'Quem é required é a branch protection — o dono é governance/required-checks-baseline.json. Nasceu advisory em 2026-07 (ADR 0314/0275) e foi promovido em 2026-08-05 (ADR 0370); este campo NÃO declara o estado atual, aponta pro dono (§5 2026-07-16).',
    node_types: NODE_TYPES,
    edge_types: EDGE_TYPES,
    stats: {
      modules: graph.moduleCount,
      nodes: nodes.length,
      edges: edges.length,
      by_node_type: byNodeType,
      by_edge_type: byEdgeType,
      dangling: danglingTotal,
    },
    nodes,
    edges,
    diagnostics: graph.diagnostics,
  };
  return JSON.stringify(catalog, null, 2) + '\n';
}

/** Consulta pequena e estável para IA/CLI: nó + relações de entrada/saída. */
function queryGraph(graph, term) {
  const q = String(term || '').toLowerCase();
  const node = graph.nodes.find((n) => n.id.toLowerCase() === q)
    || graph.nodes.find((n) => [n.module, n.name, n.prefix, n.num].filter(Boolean).some((v) => String(v).toLowerCase() === q));
  if (!node) return null;
  return {
    node,
    outgoing: graph.edges.filter((e) => e.from === node.id),
    incoming: graph.edges.filter((e) => e.to === node.id),
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Relatório de diagnósticos no console (dry/write/check).
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Tombstones de módulo curados em `governance/ghost-rename-map.json` (chave
 * `excluded`). Este arquivo é o DONO de "esse nome foi removido, quando e por
 * qual ADR" — aqui só se LÊ, nunca se re-declara (§5: aponta pro dono, não
 * restateia). Devolve `null` quando não deu pra ler, pra a mensagem poder dizer
 * que NÃO classificou em vez de chamar módulo morto de "fronteira futura".
 * @returns {Record<string, any> | null}
 */
function loadModuleTombstones() {
  const abs = join(ROOT, 'governance/ghost-rename-map.json');
  if (!existsSync(abs)) return null;
  try {
    const j = JSON.parse(readFileSync(abs, 'utf8'));
    const ex = j.excluded;
    return ex && typeof ex === 'object' && !Array.isArray(ex) ? ex : null;
  } catch {
    return null;
  }
}

/**
 * Separa os `referenced-only` em três baldes. O rótulo antigo dizia "fronteira
 * futura/legada" pros dois casos juntos — e a maioria são módulos MORTOS por ADR,
 * não fronteira. Puro de propósito: recebe a lista e o mapa, não lê disco.
 *
 * NÃO mexe em nó, aresta nem no catalog.json — é só como o diagnóstico se
 * apresenta. A classificação vem do tombstone curado, nunca de heurística de nome.
 *
 * @param {{module: string}[]} refOnly
 * @param {Record<string, any>} tombstones  `excluded` do ghost-rename-map
 */
export function classifyReferencedOnly(refOnly, tombstones) {
  const removidos = [], ambiguos = [], futuros = [];
  for (const r of refOnly ?? []) {
    const t = tombstones?.[r.module];
    if (!t) { futuros.push(r.module); continue; }
    // `removed_at` é o que QUALIFICA a morte (mesma régua do knowledge-drift):
    // tombstone sem data é fila humana, não fato consumado.
    if (t.removed_at) removidos.push({ module: r.module, adr: t.removed_by_adr || '', em: t.removed_at });
    else ambiguos.push(r.module);
  }
  return { removidos, ambiguos, futuros };
}

function reportDiagnostics(graph) {
  const d = graph.diagnostics;
  const dm = d.dangling_module_refs, da = d.dangling_adr_refs;
  const co = d.consumed_tables_without_catalog_owner, tm = d.tables_owned_by_multiple;
  const ro = d.referenced_only_modules || [];
  if (dm.length) {
    console.error(`🔴 ${dm.length} aresta(s) → módulo SEM SCOPE.md no catálogo (rename não-propagado, módulo CLASSE B do core, ou módulo futuro — ver a nota):`);
    for (const e of dm) console.error(`   ${e.from} --${e.type}--> ${e.to}  (${e.source}${e.note ? `: "${e.note}"` : ''})`);
  }
  if (da.length) {
    console.error(`🔴 ${da.length} aresta(s) → ADR INEXISTENTE em memory/decisions/:`);
    for (const e of da) console.error(`   ${e.from} --${e.type}--> ${e.to}  (${e.source})`);
  }
  if (tm.length) {
    console.error(`🟡 ${tm.length} tabela(s) com co-ownership declarado por 2+ módulos (claims preservados para revisão):`);
    for (const t of tm) console.error(`   ${t.table} → ${t.owners.join(', ')}`);
  }
  if (co.length) {
    console.log(`ℹ️  ${co.length} tabela(s) consumida(s) sem dono no catálogo (pode ser core UltimatePOS): ${co.map((t) => t.table).join(', ')}`);
  }
  const ts = d.adr_supersession_of_tombstoned || [];
  if (ts.length) {
    console.log(`ℹ️  ${ts.length} aresta(s) → ADR TOMBADA (ADR 0316 — morte legítima com lápide curada, NÃO é pendurada): ${ts.map((e) => `${e.from} --${e.type}--> ${e.to}`).join(' · ')}`);
  }
  if (ro.length) {
    const tomb = loadModuleTombstones();
    if (tomb === null) {
      console.log(`ℹ️  ${ro.length} módulo(s) referenced-only, NÃO classificados (ghost-rename-map.json ausente ou ilegível): ${ro.map((x) => x.module).join(', ')}`);
    } else {
      const { removidos, ambiguos, futuros } = classifyReferencedOnly(ro, tomb);
      if (removidos.length) console.log(`ℹ️  ${removidos.length} módulo(s) citado(s) mas REMOVIDO(s) — tombstone curado, não é fronteira: ${removidos.map((r) => `${r.module} (ADR ${r.adr || '—'}, ${r.em})`).join(' · ')}`);
      if (ambiguos.length) console.log(`ℹ️  ${ambiguos.length} módulo(s) citado(s) com tombstone SEM data (fila humana no ghost-rename-map): ${ambiguos.join(', ')}`);
      if (futuros.length) console.log(`ℹ️  ${futuros.length} módulo(s) referenced-only sem tombstone (fronteira futura, sem SCOPE próprio): ${futuros.join(', ')}`);
    }
  }
  if (!dm.length && !da.length && !tm.length) console.log('✅ integridade: nenhuma aresta pendurada nem conflito de ownership.');
  return dm.length + da.length; // "fatais" pro exit do --check
}

// ─────────────────────────────────────────────────────────────────────────────
// main
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Arestas que compõem o FLUXO entre módulos. Deliberadamente um subconjunto:
 * `hasComponent` (419) e `governedByAdr`/`charteredByAdr` (135) são verdadeiras mas
 * viram cabelo — um diagrama que mostra tudo não mostra nada.
 */
const MERMAID_FLOW_EDGES = new Set(['dependsOn', 'delegatesTo', 'migratesTo']);
const MERMAID_ARROW = { dependsOn: '-->', delegatesTo: '-.->', migratesTo: '==>' };

/**
 * VISTA (não fonte) — renderiza o grafo já derivado como mermaid.
 *
 * POR QUE EXISTE: o `catalog.json` responde "quem depende de quem" mas só por `jq` —
 * o dado existe e ninguém consegue OLHAR. Isto não cria fonte nova nem doc paralelo
 * (lápide §5 2026-07-23): é o MESMO grafo, mesma derivação, outra saída.
 *
 * `--focus=<Modulo>` recorta a vizinhança de 1 salto, que é a pergunta que se faz de
 * verdade ("o que quebra se eu mexer no Financeiro?"). Sem foco, o grafo inteiro.
 */
export function toMermaid(graph, { focus = null } = {}) {
  const isMod = (id) => String(id).startsWith('module:');
  const nm = (id) => String(id).replace(/^module:/, '');

  let edges = graph.edges.filter((e) => MERMAID_FLOW_EDGES.has(e.type) && isMod(e.from) && isMod(e.to));
  if (focus) {
    const f = `module:${focus}`;
    if (!graph.nodes.some((n) => n.id === f)) return { erro: `módulo não está no grafo: ${focus}` };
    edges = edges.filter((e) => e.from === f || e.to === f);
  }

  const usados = new Set();
  for (const e of edges) { usados.add(nm(e.from)); usados.add(nm(e.to)); }

  const linhas = ['graph LR'];
  for (const m of [...usados].sort()) linhas.push(`  ${m}["${m}"]`);
  const vistas = new Set();
  for (const e of edges.slice().sort((a, b) => (a.from + a.to + a.type).localeCompare(b.from + b.to + b.type))) {
    const l = `  ${nm(e.from)} ${MERMAID_ARROW[e.type]} ${nm(e.to)}`;
    if (vistas.has(l)) continue; // 2 SCOPE.md podem declarar a mesma aresta
    vistas.add(l);
    linhas.push(l);
  }

  // Módulos SEM nenhuma aresta de fluxo — o silêncio também é informação.
  // SÓ faz sentido na vista GLOBAL: sob --focus, "fora do recorte" ≠ "sem aresta"
  // (eles TÊM arestas, só não com o módulo focado). Reportar ali seria o instrumento
  // respondendo uma pergunta PARECIDA com a feita — a classe LC-08.
  const ilhas = focus
    ? null
    : graph.nodes.filter((n) => n.type === 'module').map((n) => nm(n.id)).filter((m) => !usados.has(m)).sort();

  return { mermaid: linhas.join('\n'), modulos: usados.size, arestas: vistas.size, ilhas };
}

// ── ACOPLAMENTO DERIVADO (advisory) ─────────────────────────────────────────
// O grafo acima mede a fronteira DECLARADA (frontmatter escrito à mão). Este bloco
// mede a REAL (import no código) e reporta o delta. Medição 2026-08-12: dos 57 pares
// vivos em produção, só 16 estavam declarados (28%) — logo `depends_on` sozinho NÃO
// serve de inventário de fronteira, e um mutirão de backfill à mão só reinicia o
// relógio do apodrecimento (§5 2026-07-12). Aqui o fato é DERIVADO; o SCOPE segue
// dono da NORMA (`not_contains` = delegação declarada), que é decisão humana.
//
// CRITÉRIO e seu FP (medido antes de instalar, §5): só `use Modules\X\…` em linha de
// código. Docblock/comentário/string NÃO entram — 0 linhas de comentário casaram o
// padrão no corpus (o grep solto contava 116 pares; por import real são 41). O preço
// é ser PISO, não teto: container/facade/string, query crua em tabela alheia e
// `resources/js` ficam invisíveis. Advisory por desenho — exit 0 SEMPRE, nunca
// bloqueia merge (promoção a required é flip [W] com mordida provada, ADR 0336/0275).
const CAMADA_DADO = new Set(['Entities', 'Models']);
const CAMADA_COMPORTAMENTO = new Set(['Concerns', 'Scopes', 'Traits', 'Utils']);
const CAMADA_CONTRATO = new Set(['Contracts', 'Contract', 'Dto', 'DTO', 'Events', 'Exceptions', 'Repositories']);
/** Quantos módulos-origem distintos tornam um símbolo "cross-cutting" (derivado, não lista à mão). */
const LIMIAR_CROSS_CUTTING = 5;

/** Classifica o peso do acoplamento pela camada importada (pior camada vence). */
function pesoDaCamada(camada) {
  if (CAMADA_DADO.has(camada)) return 'dado';
  if (CAMADA_COMPORTAMENTO.has(camada)) return 'comportamento';
  if (CAMADA_CONTRATO.has(camada)) return 'contrato';
  return 'servico';
}
const ORDEM_PESO = { dado: 3, comportamento: 2, servico: 1, contrato: 0 };

/**
 * Extrai arestas REAIS de linhas `path:código` do git grep.
 * @param {string[]} linhas
 * @param {{modulosVivos:Set<string>, incluirTestes?:boolean}} opts
 */
function parseImportsCruzados(linhas, { modulosVivos, incluirTestes = false }) {
  const out = [];
  for (const linha of linhas) {
    const corte = linha.indexOf(':');
    if (corte < 0) continue;
    const path = linha.slice(0, corte);
    const code = linha.slice(corte + 1);
    if (!incluirTestes && /\/Tests?\//i.test(path)) continue;
    const src = (path.match(/^Modules\/([A-Za-z]+)\//) || [])[1];
    const m = code.match(/^\s*use\s+Modules[\\/]([A-Z][A-Za-z]+)[\\/](.+?);/);
    if (!src || !m) continue;
    const dst = m[1];
    if (dst === src || !modulosVivos.has(dst) || !modulosVivos.has(src)) continue;
    const partes = m[2].split(/[\\/]/);
    out.push({ src, dst, camada: partes[0] || '?', simbolo: partes[partes.length - 1] });
  }
  return out;
}

/** Símbolos importados por ≥ limiar módulos distintos = primitiva cross-cutting (derivado). */
function simbolosCrossCutting(refs, limiar = LIMIAR_CROSS_CUTTING) {
  const porSimbolo = new Map();
  for (const r of refs) {
    if (!porSimbolo.has(r.simbolo)) porSimbolo.set(r.simbolo, new Set());
    porSimbolo.get(r.simbolo).add(r.src);
  }
  return new Set([...porSimbolo].filter(([, srcs]) => srcs.size >= limiar).map(([s]) => s));
}

/** Agrega refs em pares e confronta com as arestas declaradas do grafo. */
function compararFronteira(refs, graph, { limiar = LIMIAR_CROSS_CUTTING, modulosVivos = null } = {}) {
  const cross = simbolosCrossCutting(refs, limiar);
  const declaradas = new Map();
  for (const e of graph.edges) {
    if (e.type !== 'dependsOn' && e.type !== 'delegatesTo') continue;
    const f = e.from.replace(/^module:/, '');
    const t = e.to.replace(/^module:/, '');
    // Alvo REMOVIDO não é "fronteira sem import" — é tombstone, e já tem diagnóstica
    // curada própria (referenced-only). Contar aqui inflaria o número com ruído.
    if (modulosVivos && !modulosVivos.has(t)) continue;
    if (!declaradas.has(f)) declaradas.set(f, new Set());
    declaradas.get(f).add(t);
  }
  const pares = new Map();
  for (const r of refs) {
    const k = `${r.src}>${r.dst}`;
    let p = pares.get(k);
    if (!p) {
      p = { src: r.src, dst: r.dst, imports: 0, peso: 'contrato', simbolos: new Set(), soPrimitiva: true };
      pares.set(k, p);
    }
    p.imports++;
    p.simbolos.add(r.simbolo);
    if (!cross.has(r.simbolo)) p.soPrimitiva = false;
    const w = pesoDaCamada(r.camada);
    if (ORDEM_PESO[w] > ORDEM_PESO[p.peso]) p.peso = w;
  }
  const lista = [...pares.values()].map((p) => ({
    ...p,
    simbolos: [...p.simbolos].sort(),
    declarado: Boolean(declaradas.get(p.src)?.has(p.dst)),
  }));
  const naoDeclaradas = lista.filter((p) => !p.declarado);
  const reais = new Set(lista.map((p) => `${p.src}>${p.dst}`));
  const soDeclaradas = [];
  for (const [f, alvos] of declaradas) {
    for (const t of alvos) if (!reais.has(`${f}>${t}`)) soDeclaradas.push({ src: f, dst: t });
  }
  return {
    pares: lista.sort((a, b) => b.imports - a.imports || a.src.localeCompare(b.src)),
    confirmadas: lista.length - naoDeclaradas.length,
    naoDeclaradas,
    soDeclaradas,
    crossCutting: [...cross].sort(),
  };
}

/** Lê os imports da árvore viva. Distingue "sem match" (rc=1) de falha real (§5 2026-07-31). */
function lerImportsDaArvore() {
  try {
    const out = execFileSync(
      'git',
      ['grep', '-I', '-E', '^\\s*use\\s+Modules.[A-Z][A-Za-z]+', '--', 'Modules/*.php'],
      { cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 },
    );
    return out.split('\n').filter(Boolean);
  } catch (err) {
    if (err && err.status === 1) return []; // sem ocorrência: legítimo
    throw new Error(`git grep falhou (status=${err && err.status}): ${err && err.message}`);
  }
}

function reportAcoplamento(graph) {
  const modulosVivos = new Set(readdirSync(join(ROOT, 'Modules')));
  const refs = parseImportsCruzados(lerImportsDaArvore(), { modulosVivos });
  const r = compararFronteira(refs, graph, { modulosVivos });
  const cobertura = r.pares.length ? ((r.confirmadas / r.pares.length) * 100).toFixed(1) : '—';
  console.log(
    `[catalog-graph] acoplamento REAL: ${r.pares.length} pares em produção · ` +
    `${r.confirmadas} declarados (${cobertura}%) · ${r.naoDeclaradas.length} NÃO declarados · ` +
    `${r.soDeclaradas.length} declarados sem import`,
  );
  if (r.crossCutting.length) {
    console.log(
      `ℹ️  primitiva cross-cutting (≥${LIMIAR_CROSS_CUTTING} módulos importam — não é fronteira, é alojamento): ` +
      r.crossCutting.join(', '),
    );
  }
  const negocio = r.naoDeclaradas.filter((p) => !p.soPrimitiva);
  const primitiva = r.naoDeclaradas.filter((p) => p.soPrimitiva);
  if (primitiva.length) {
    console.log(`ℹ️  ${primitiva.length} par(es) não-declarados são SÓ primitiva cross-cutting — 1 decisão, não ${primitiva.length}.`);
  }
  for (const p of negocio) {
    const selo = p.peso === 'dado' ? '⚠️ ' : '  ';
    console.log(`${selo}${p.src} → ${p.dst}  (${p.imports} imports · ${p.peso}) ${p.simbolos.slice(0, 3).join(', ')}`);
  }
  console.log(
    `[catalog-graph] advisory — PISO, não teto: mede só \`use Modules\\X\` em Modules/**.php ` +
    `(container/facade/query crua/resources/js ficam de fora).`,
  );
  return r;
}

function main() {
  const mods = listScopeModules();
  if (!mods.length) {
    console.error('[catalog-graph] nenhum memory/requisitos/*/SCOPE.md encontrado — rode da raiz do repo.');
    process.exit(2);
  }
  const records = [...mods.map(readScope), ...listCoreClassBRecords()];
  const graph = buildGraph(records, {
    knownAdrs: knownAdrNumbers(),
    adrRelations: readAdrIndex(),
    tombstonedAdrSlugs: loadAdrTombstoneSlugs(),
  });
  const content = serialize(graph);
  const outAbs = join(ROOT, OUT_REL);

  if (PRINT_JSON) { process.stdout.write(content); return; }
  if (args.includes('--mermaid')) {
    const fi = args.findIndex((a) => a === '--focus' || a.startsWith('--focus='));
    const focus = fi < 0 ? null : (args[fi].includes('=') ? args[fi].split('=')[1] : args[fi + 1]);
    const v = toMermaid(graph, { focus: focus || null });
    if (v.erro) { console.error(`[catalog-graph] ${v.erro}`); process.exit(1); }
    process.stdout.write(v.mermaid + '\n');
    console.error(
      `[catalog-graph] vista mermaid: ${v.modulos} módulos · ${v.arestas} arestas de fluxo` +
      (focus ? ` (foco: ${focus}, 1 salto)` : '') +
      (v.ilhas && v.ilhas.length ? ` · ${v.ilhas.length} sem NENHUMA aresta de fluxo: ${v.ilhas.join(', ')}` : ''),
    );
    return;
  }
  const qi = args.indexOf('--query');
  if (qi >= 0) {
    const result = queryGraph(graph, args[qi + 1]);
    if (!result) { console.error(`[catalog-graph] nó não encontrado: ${args[qi + 1] || '(vazio)'}`); process.exit(1); }
    console.log(JSON.stringify(result, null, 2));
    return;
  }

  // Advisory: NÃO altera --check (required). Sai sempre 0, mesmo com fronteira não declarada.
  // Roda DENTRO de um job required, então não pode avermelhá-lo — mas também não pode
  // fingir verde quando não conseguiu medir: "não medi" ≠ "não há acoplamento" (§5 2026-07-29).
  if (args.includes('--acoplamento')) {
    try {
      reportAcoplamento(graph);
    } catch (err) {
      console.log(`⚠️  [catalog-graph] acoplamento NÃO MEDIDO (${err && err.message}) — nenhum veredito sobre fronteira.`);
    }
    return;
  }

  const s = graph.diagnostics;
  console.log(
    `[catalog-graph] ${graph.moduleCount} módulos · ${graph.nodes.length} nós · ${graph.edges.length} arestas ` +
    `(pendurados: ${s.dangling_module_refs.length} módulo + ${s.dangling_adr_refs.length} ADR)`,
  );

  if (MODE === 'write') {
    writeFileSync(outAbs, content, 'utf8');
    console.log(`[catalog-graph] gravado → ${OUT_REL}`);
    reportDiagnostics(graph);
    return;
  }
  if (MODE === 'check') {
    const committed = existsSync(outAbs) ? readFileSync(outAbs, 'utf8') : null;
    const drift = committed !== content;
    if (committed === null) console.error(`[catalog-graph] ${OUT_REL} não existe — rode --write.`);
    else if (drift) console.error(`[catalog-graph] DRIFT: ${OUT_REL} desatualizado vs os SCOPE.md. Rode: node scripts/governance/catalog-graph.mjs --write`);
    else console.log(`[catalog-graph] freshness: OK (committed == regerado).`);
    const fatal = reportDiagnostics(graph);
    // advisory de nascença: exit 1 (visível/vermelho) se drift OU aresta pendurada — nunca bloqueia (não é required).
    if (drift || fatal > 0) process.exit(1);
    return;
  }
  // dry
  reportDiagnostics(graph);
  console.log(`[catalog-graph] dry-run — use --write pra gravar ${OUT_REL}, --check pra CI, --json pra imprimir.`);
}

if (import.meta.url === pathToFileURL(process.argv[1] || '').href) main();

export {
  parseFrontmatter,
  componentNameFromContains,
  componentDescFromContains,
  tableNamesFrom,
  apiPrefixFrom,
  adrNumFrom,
  moduleRefsIn,
  delegationNote,
  migrateTargetsFromRaw,
  adrRelationItems,
  buildGraph,
  serialize,
  sortGraph,
  queryGraph,
  listCoreClassBRecords,
  parseImportsCruzados,
  simbolosCrossCutting,
  compararFronteira,
  pesoDaCamada,
  NODE_TYPES,
  EDGE_TYPES,
};
