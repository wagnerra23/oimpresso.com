// @ts-check
/**
 * catalog-graph.test.mjs — self-test do gerador do grafo tipado de módulos.
 * Roda: node --test scripts/governance/catalog-graph.test.mjs
 * Testa a LÓGICA PURA (parse de frontmatter + extratores + buildGraph + serialização
 * determinística + detecção de aresta pendurada) com inputs SINTÉTICOS — não a árvore
 * viva (que muda), então o teste não apodrece junto com o repo.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
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
  EDGE_TYPES,
} from './catalog-graph.mjs';

// ── SCOPE.md sintéticos (o mínimo pra exercitar cada campo) ──────────────────
const SCOPE_ALPHA = `---
module: Alpha
purpose: "Módulo alpha de teste."
contains:
  # comentário no bloco de lista (deve ser pulado)
  - "FooController — faz foo"
  - "Bar/BazController (com paren)"
not_contains:
  - "Coisa fiscal → Modules/Beta (lê via Service)"
  - "Outra coisa → Modules/Fantasma ou Modules/Beta"
db_tables_owned:
  - alpha_things (tabela principal)
  - alpha_a, alpha_b (duas de uma vez)
db_tables_consumed:
  - beta_shared (consumida do Beta)
charter_adr: 0080
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 9999-adr-que-nao-existe
url_prefixes:
  - /alpha/* (canônico)
drift_alerts:
  - controller: "LegadoController"
    pertence_a: "Modules/Beta (futuro)"
    motivo: "consolidação"
---

# Modules/Alpha
Corpo com menção a Modules/Gamma que NÃO deve virar aresta (prosa, não declaração).
`;

const SCOPE_BETA = `---
module: Beta
purpose: "Módulo beta."
contains:
  - "QuuxController"
not_contains: []
db_tables_owned:
  - beta_shared
charter_adr: 0093
url_prefixes:
  - /beta/*
---

# Modules/Beta
`;

function recordsFromSynthetic() {
  // reusa o parser real, mas monta os registros à mão (como readScope faria).
  const parse = (txt, mod) => {
    const { fields, raw } = parseFrontmatter(txt);
    const asList = (v) => (Array.isArray(v) ? v : v ? [String(v)] : []);
    return {
      module: typeof fields.module === 'string' ? fields.module : mod,
      path: `Modules/${mod}/SCOPE.md`,
      purpose: typeof fields.purpose === 'string' ? fields.purpose : '',
      trust: '', owner: '', permission_prefix: '',
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
  };
  return [parse(SCOPE_ALPHA, 'Alpha'), parse(SCOPE_BETA, 'Beta')];
}

// ── extratores puros ─────────────────────────────────────────────────────────
test('parseFrontmatter: escalares, listas, comentários e [] vazio', () => {
  const { fields } = parseFrontmatter(SCOPE_ALPHA);
  assert.equal(fields.module, 'Alpha');
  assert.equal(fields.purpose, 'Módulo alpha de teste.');
  assert.deepEqual(fields.contains, ['FooController — faz foo', 'Bar/BazController (com paren)']); // comentário pulado
  assert.equal(fields.charter_adr, '0080');
  const { fields: fb } = parseFrontmatter(SCOPE_BETA);
  assert.deepEqual(fb.not_contains, []); // `[]` vira lista vazia
});

test('componentNameFromContains: separa nome antes de —/(', () => {
  assert.equal(componentNameFromContains('FooController — faz foo'), 'FooController');
  assert.equal(componentNameFromContains('Bar/BazController (com paren)'), 'Bar/BazController');
  assert.equal(componentNameFromContains('SoNome'), 'SoNome');
  assert.equal(componentDescFromContains('FooController — faz foo'), 'faz foo');
});

test('tableNamesFrom: split por vírgula + strip de anotação', () => {
  assert.deepEqual(tableNamesFrom('alpha_things (tabela principal)'), ['alpha_things']);
  assert.deepEqual(tableNamesFrom('alpha_a, alpha_b (duas de uma vez)'), ['alpha_a', 'alpha_b']);
  assert.deepEqual(tableNamesFrom('copiloto_metas (view)'), ['copiloto_metas']);
});

test('apiPrefixFrom / adrNumFrom / moduleRefsIn / delegationNote', () => {
  assert.equal(apiPrefixFrom('/alpha/* (canônico)'), '/alpha/*');
  assert.equal(apiPrefixFrom('sem-barra'), '');
  assert.equal(adrNumFrom('0093-multi-tenant-isolation-tier-0'), '0093');
  assert.equal(adrNumFrom('0080'), '0080');
  assert.deepEqual(moduleRefsIn('x → Modules/Fantasma ou Modules/Beta'), ['Fantasma', 'Beta']);
  assert.equal(delegationNote('Coisa fiscal → Modules/Beta (lê via Service)'), 'Coisa fiscal');
});

test('migrateTargetsFromRaw: pega pertence_a do drift_alerts', () => {
  const { raw } = parseFrontmatter(SCOPE_ALPHA);
  assert.deepEqual(migrateTargetsFromRaw(raw), ['Beta']);
});

// ── buildGraph: nós e arestas tipadas ────────────────────────────────────────
test('buildGraph: cria nó module por SCOPE + tabelas/adr/component/api', () => {
  const g = buildGraph(recordsFromSynthetic());
  const ids = new Set(g.nodes.map((n) => n.id));
  assert.ok(ids.has('module:Alpha'));
  assert.ok(ids.has('module:Beta'));
  assert.ok(ids.has('table:alpha_things'));
  assert.ok(ids.has('table:alpha_a') && ids.has('table:alpha_b')); // split por vírgula funcionou
  assert.ok(ids.has('table:beta_shared'));
  assert.ok(ids.has('adr:0080') && ids.has('adr:0093'));
  assert.ok(ids.has('component:Alpha/FooController'));
  assert.ok(ids.has('api:/alpha/*'));
});

test('buildGraph: ownsTable/consumesTable ligam nas tabelas certas', () => {
  const g = buildGraph(recordsFromSynthetic());
  const has = (from, to, type) => g.edges.some((e) => e.from === from && e.to === to && e.type === type);
  assert.ok(has('module:Alpha', 'table:alpha_things', 'ownsTable'));
  assert.ok(has('module:Alpha', 'table:beta_shared', 'consumesTable'));
  assert.ok(has('module:Beta', 'table:beta_shared', 'ownsTable'));
  // a tabela compartilhada tem 1 dono (Beta) e 1 consumidor (Alpha)
  const shared = g.nodes.find((n) => n.id === 'table:beta_shared');
  assert.deepEqual(shared.owners, ['Beta']);
  assert.deepEqual(shared.consumers, ['Alpha']);
});

test('buildGraph: delegatesTo vem de not_contains (com nota), NÃO da prosa do corpo', () => {
  const g = buildGraph(recordsFromSynthetic());
  const del = g.edges.filter((e) => e.type === 'delegatesTo' && e.from === 'module:Alpha');
  const targets = del.map((e) => e.to).sort();
  assert.deepEqual(targets, ['module:Beta', 'module:Beta', 'module:Fantasma']);
  // a nota (o "porquê") é preservada
  assert.ok(del.some((e) => e.to === 'module:Beta' && e.note === 'Coisa fiscal'));
  // Gamma (mencionado só no corpo markdown) NÃO vira aresta
  assert.ok(!g.edges.some((e) => e.to === 'module:Gamma'));
});

test('buildGraph: migratesTo vem do drift_alerts.pertence_a', () => {
  const g = buildGraph(recordsFromSynthetic());
  assert.ok(g.edges.some((e) => e.from === 'module:Alpha' && e.to === 'module:Beta' && e.type === 'migratesTo'));
});

test('buildGraph: não cria self-ref delegatesTo', () => {
  const recs = recordsFromSynthetic();
  recs[0].not_contains.push('algo → Modules/Alpha'); // Alpha delega pra si mesmo
  const g = buildGraph(recs);
  assert.ok(!g.edges.some((e) => e.from === 'module:Alpha' && e.to === 'module:Alpha'));
});

// ── diagnósticos: arestas penduradas ─────────────────────────────────────────
test('diagnostics: aresta → módulo sem SCOPE é pendurada (Fantasma), Beta não', () => {
  const g = buildGraph(recordsFromSynthetic());
  const dm = g.diagnostics.dangling_module_refs;
  assert.ok(dm.some((e) => e.to === 'module:Fantasma'));
  assert.ok(!dm.some((e) => e.to === 'module:Beta')); // Beta existe → não pendura
});

test('diagnostics: aresta → ADR inexistente pendura só quando knownAdrs é passado', () => {
  const recs = recordsFromSynthetic();
  // sem knownAdrs: não checa (todas exist=true)
  const g0 = buildGraph(recs);
  assert.equal(g0.diagnostics.dangling_adr_refs.length, 0);
  // com knownAdrs: 0093 e 0080 existem, 9999 não
  const g1 = buildGraph(recs, { knownAdrs: new Set(['0080', '0093']) });
  assert.ok(g1.diagnostics.dangling_adr_refs.some((e) => e.to === 'adr:9999'));
  assert.ok(!g1.diagnostics.dangling_adr_refs.some((e) => e.to === 'adr:0093'));
});

test('diagnostics: tabela owned por 2+ módulos vira smell', () => {
  const recs = recordsFromSynthetic();
  recs[1].db_tables_owned.push('alpha_things'); // Beta também declara owner de alpha_things
  const g = buildGraph(recs);
  const tm = g.diagnostics.tables_owned_by_multiple;
  assert.ok(tm.some((t) => t.table === 'alpha_things' && t.owners.length === 2));
});

// ── serialização determinística ──────────────────────────────────────────────
test('serialize: determinístico (mesmo input → bytes idênticos) + newline final', () => {
  const recs = recordsFromSynthetic();
  const a = serialize(buildGraph(recs));
  const b = serialize(buildGraph(recs));
  assert.equal(a, b);
  assert.ok(a.endsWith('\n'));
});

test('serialize: JSON válido com header, stats e tipos declarados', () => {
  const cat = JSON.parse(serialize(buildGraph(recordsFromSynthetic())));
  assert.equal(cat.$generator, 'scripts/governance/catalog-graph.mjs');
  assert.equal(cat.stats.modules, 2);
  assert.equal(cat.stats.nodes, cat.nodes.length);
  assert.equal(cat.stats.edges, cat.edges.length);
  assert.deepEqual(Object.keys(cat.stats.by_edge_type).sort(), [...EDGE_TYPES].sort());
  // ordem estável: ids de nós ordenados
  const ids = cat.nodes.map((n) => n.id);
  assert.deepEqual(ids, [...ids].sort((x, y) => x.localeCompare(y)));
});

test('serialize: ordem de entrada dos registros NÃO muda o output (independe de ordem)', () => {
  const recs = recordsFromSynthetic();
  const a = serialize(buildGraph(recs));
  const b = serialize(buildGraph([...recs].reverse()));
  assert.equal(a, b);
});
