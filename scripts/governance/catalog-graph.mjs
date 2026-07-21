#!/usr/bin/env node
// @ts-check
/**
 * catalog-graph.mjs — GERADOR determinístico do GRAFO TIPADO de módulos.
 *
 * A DOR (grade catálogo/IDP 7,0, memory/sessions/2026-07-21-grade-catalogo-aprendizado-vs-mercado.md,
 * chip #2): o `Modules/<X>/SCOPE.md` é o descritor por módulo (estilo Backstage `catalog-info.yaml`),
 * mas hoje é markdown lido por humano/IA — NÃO um grafo tipado consultável. Backstage/Cortex/Port
 * são graph-native (`dependsOn`/`providesApi`/`partOf`), o que deixa perguntar "que módulo quebra se
 * a tabela X (ou o módulo Y) mudar". Este gerador DERIVA as arestas dos SCOPE.md e emite um
 * `catalog.json` consultável (nós + arestas tipadas).
 *
 * DOUTRINA (ADR 0256): derivado sobrevive; escrito+lembrado apodrece. O grafo é 100% recalculado
 * dos SCOPE.md — nada à mão. NÃO INVENTA relação que o SCOPE não declara: as arestas saem SÓ dos
 * campos estruturados do frontmatter (`db_tables_owned`/`db_tables_consumed`/`db_tables_legacy_views`,
 * `related_adrs`/`charter_adr`, `url_prefixes`, `contains`, e os cross-refs `→ Modules/X` que vivem
 * DENTRO de `not_contains` + `drift_alerts.pertence_a`). Prosa do corpo markdown é ignorada de
 * propósito (não é declaração — é narrativa, e não-uniforme).
 *
 * SEM data volátil no corpo (igual `module-surface.mjs`): o frescor é provado por `--check`
 * (committed == regerado), não por timestamp que apodrece (§5 2026-07-17 — recibo é query
 * re-rodável, não afirmação atemporal). Logo o JSON é byte-determinístico.
 *
 * ADVISORY DE NASCENÇA (ADR 0314/0275): required = só Tier-0. Este é um catálogo de conveniência —
 * `--check` pode ficar VERMELHO (drift OU aresta pendurada) sem bloquear merge; é sinal, não catraca.
 *
 * O que ele NÃO faz (delega): superfície de código por papel é do `module-surface.mjs`; cobertura/nota
 * de tela é do `screen-coverage`/`casos-gate`. Aqui é só o GRAFO de fronteiras entre módulos.
 *
 * Uso:
 *   node scripts/governance/catalog-graph.mjs            (dry-run: resumo + diagnósticos, exit 0)
 *   node scripts/governance/catalog-graph.mjs --write    (grava memory/governance/catalog.json)
 *   node scripts/governance/catalog-graph.mjs --check     (CI advisory: exit 1 se DRIFT ou aresta pendurada)
 *   node scripts/governance/catalog-graph.mjs --json      (imprime o catalog.json no stdout, não grava)
 *
 * Refs: ADR 0256 (survival, fonte única gerada) · ADR 0314/0275 (advisory-primeiro) ·
 *       grade 2026-07-21 (chip #2 "arestas tipadas no catálogo") · irmão `module-surface.mjs`.
 */
import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

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
  'delegatesTo',     // module → module       (not_contains "→ Modules/X" — fronteira declarada)
  'migratesTo',      // module → module       (drift_alerts.pertence_a "Modules/X")
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
    .filter((m) => existsSync(join(dir, m, 'SCOPE.md')))
    .sort();
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

/** Lê 1 SCOPE.md → registro { module, purpose, ... , contains[], not_contains[], tables }. */
function readScope(mod) {
  const rel = `Modules/${mod}/SCOPE.md`;
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
    ensure(mid, () => ({
      id: mid, type: 'module', module: r.module, purpose: r.purpose,
      trust: r.trust, owner: r.owner, permission_prefix: r.permission_prefix,
      charter_adr: adrNumFrom(r.charter_adr), path: r.path,
    }));

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
      if (!n.slug && /\d{4}-[a-z0-9-]+/.test(String(slug))) n.slug = String(slug).trim();
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
        addEdge(mid, `module:${target}`, 'delegatesTo', 'not_contains', note);
      }
    }
    // migrações planejadas (drift_alerts.pertence_a)
    for (const target of r.migrate_targets) {
      if (target === r.module) continue;
      addEdge(mid, `module:${target}`, 'migratesTo', 'drift_alerts.pertence_a');
    }
  }

  // ── diagnósticos (arestas penduradas + smells de tabela) ──────────────────
  const diagnostics = {
    dangling_module_refs: edges
      .filter((e) => (e.type === 'delegatesTo' || e.type === 'migratesTo') && !moduleSet.has(e.to.replace(/^module:/, '')))
      .map((e) => ({ from: e.from, to: e.to, type: e.type, source: e.source, note: e.note || '' })),
    dangling_adr_refs: knownAdrs
      ? [...nodes.values()]
          .filter((n) => n.type === 'adr' && n.exists === false)
          .flatMap((n) => edges.filter((e) => e.to === n.id).map((e) => ({ from: e.from, to: n.id, type: e.type, source: e.source })))
      : [],
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
    $doc: 'Grafo tipado DERIVADO dos Modules/*/SCOPE.md (ADR 0256). NÃO editar à mão — a próxima geração sobrescreve. Regenerar: node scripts/governance/catalog-graph.mjs --write',
    $advisory: 'Advisory de nascença (ADR 0314/0275) — não é gate required.',
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

// ─────────────────────────────────────────────────────────────────────────────
// Relatório de diagnósticos no console (dry/write/check).
// ─────────────────────────────────────────────────────────────────────────────

function reportDiagnostics(graph) {
  const d = graph.diagnostics;
  const dm = d.dangling_module_refs, da = d.dangling_adr_refs;
  const co = d.consumed_tables_without_catalog_owner, tm = d.tables_owned_by_multiple;
  if (dm.length) {
    console.error(`🔴 ${dm.length} aresta(s) → módulo SEM SCOPE.md no catálogo (rename não-propagado, módulo CLASSE B do core, ou módulo futuro — ver a nota):`);
    for (const e of dm) console.error(`   ${e.from} --${e.type}--> ${e.to}  (${e.source}${e.note ? `: "${e.note}"` : ''})`);
  }
  if (da.length) {
    console.error(`🔴 ${da.length} aresta(s) → ADR INEXISTENTE em memory/decisions/:`);
    for (const e of da) console.error(`   ${e.from} --${e.type}--> ${e.to}  (${e.source})`);
  }
  if (tm.length) {
    console.error(`🟡 ${tm.length} tabela(s) declarada(s) como OWNED por 2+ módulos (conflito de ownership):`);
    for (const t of tm) console.error(`   ${t.table} → ${t.owners.join(', ')}`);
  }
  if (co.length) {
    console.log(`ℹ️  ${co.length} tabela(s) consumida(s) sem dono no catálogo (pode ser core UltimatePOS): ${co.map((t) => t.table).join(', ')}`);
  }
  if (!dm.length && !da.length && !tm.length) console.log('✅ integridade: nenhuma aresta pendurada nem conflito de ownership.');
  return dm.length + da.length; // "fatais" pro exit do --check
}

// ─────────────────────────────────────────────────────────────────────────────
// main
// ─────────────────────────────────────────────────────────────────────────────

function main() {
  const mods = listScopeModules();
  if (!mods.length) {
    console.error('[catalog-graph] nenhum Modules/*/SCOPE.md encontrado — rode da raiz do repo.');
    process.exit(2);
  }
  const records = mods.map(readScope);
  const graph = buildGraph(records, { knownAdrs: knownAdrNumbers() });
  const content = serialize(graph);
  const outAbs = join(ROOT, OUT_REL);

  if (PRINT_JSON) { process.stdout.write(content); return; }

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
  buildGraph,
  serialize,
  sortGraph,
  NODE_TYPES,
  EDGE_TYPES,
};
