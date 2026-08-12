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
import { readFileSync } from 'node:fs';
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
  adrRelationItems,
  resolveAdrTarget,
  buildGraph,
  serialize,
  queryGraph,
  classifyReferencedOnly,
  parseImportsCruzados,
  simbolosCrossCutting,
  compararFronteira,
  pesoDaCamada,
  derivarDonoDeTabela,
  parseQueriesCruas,
  agruparFronteiraDeTabela,
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
depends_on:
  - Beta
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
      path: `memory/requisitos/${mod}/SCOPE.md`,
      purpose: typeof fields.purpose === 'string' ? fields.purpose : '',
      trust: '', owner: '', permission_prefix: '',
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
test('diagnostics: módulo sem SCOPE vira nó referenced-only, não aresta estruturalmente pendurada', () => {
  const g = buildGraph(recordsFromSynthetic());
  assert.equal(g.diagnostics.dangling_module_refs.length, 0);
  assert.ok(g.diagnostics.referenced_only_modules.some((e) => e.module === 'Fantasma'));
  assert.equal(g.nodes.find((n) => n.id === 'module:Fantasma').catalog_status, 'referenced-only');
});

test('dependsOn é derivado da fronteira declarada e do consumo de tabela com dono', () => {
  const g = buildGraph(recordsFromSynthetic());
  const deps = g.edges.filter((e) => e.type === 'dependsOn' && e.from === 'module:Alpha');
  assert.ok(deps.some((e) => e.to === 'module:Beta' && e.source === 'depends_on'));
  assert.ok(deps.some((e) => e.to === 'module:Beta' && e.source === 'delegatesTo'));
  assert.ok(deps.some((e) => e.to === 'module:Beta' && e.source === 'db_tables_consumed→db_tables_owned'));
});

test('queryGraph devolve nó + relações incoming/outgoing', () => {
  const g = buildGraph(recordsFromSynthetic());
  const result = queryGraph(g, 'Alpha');
  assert.equal(result.node.id, 'module:Alpha');
  assert.ok(result.outgoing.some((e) => e.type === 'dependsOn'));
  assert.ok(Array.isArray(result.incoming));
  assert.equal(queryGraph(g, 'nao-existe'), null);
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
  assert.equal(g.nodes.find((n) => n.id === 'table:alpha_things').ownership_mode, 'shared-declared');
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

// ── classifyReferencedOnly: módulo MORTO não é "fronteira" ───────────────────
// Contexto: a linha ℹ️ chamava de "fronteira futura/legada" tanto o módulo que
// ainda não existe quanto o que foi REMOVIDO por ADR. A verdade já é curada em
// governance/ghost-rename-map.json (`excluded`); aqui só se lê.

const TOMB = {
  SRS:        { class: 'C', removed_by_adr: '0357', removed_at: '2026-07-29' },
  Admin:      { class: 'C', removed_by_adr: '0360', removed_at: '2026-07-29' },
  Accounting: { class: 'C', removed_by_adr: '0172', removed_at: '2026-06-05' },
  Project:    { class: 'AMBIGUO', reason: 'fila humana' }, // sem removed_at
};
const refOnly = (...ms) => ms.map((m) => ({ module: m }));

test('BITE: removido por ADR sai de "fronteira" e carrega ADR + data', () => {
  const r = classifyReferencedOnly(refOnly('SRS', 'Admin', 'Accounting'), TOMB);
  assert.deepEqual(r.futuros, [], 'nenhum morto pode cair no balde de fronteira');
  assert.deepEqual(r.removidos.map((x) => x.module), ['SRS', 'Admin', 'Accounting']);
  assert.deepEqual(r.removidos[0], { module: 'SRS', adr: '0357', em: '2026-07-29' });
});

test('BITE: tombstone SEM removed_at é fila humana, não fato consumado', () => {
  const r = classifyReferencedOnly(refOnly('Project'), TOMB);
  assert.deepEqual(r.ambiguos, ['Project']);
  assert.deepEqual(r.removidos, [], 'AMBIGUO não pode ser afirmado como removido');
  assert.deepEqual(r.futuros, [], 'AMBIGUO também não é fronteira futura');
});

test('CN: módulo sem tombstone continua fronteira futura (não vira morto)', () => {
  const r = classifyReferencedOnly(refOnly('Notas'), TOMB);
  assert.deepEqual(r.futuros, ['Notas']);
  assert.deepEqual(r.removidos, []);
  assert.deepEqual(r.ambiguos, []);
});

test('CN: mapa vazio → tudo fronteira futura (degrada sem inventar morte)', () => {
  const r = classifyReferencedOnly(refOnly('SRS', 'Notas'), {});
  assert.deepEqual(r.futuros, ['SRS', 'Notas']);
  assert.deepEqual(r.removidos, []);
});

test('CN: lista vazia/ausente não quebra', () => {
  assert.deepEqual(classifyReferencedOnly([], TOMB), { removidos: [], ambiguos: [], futuros: [] });
  assert.deepEqual(classifyReferencedOnly(undefined, TOMB), { removidos: [], ambiguos: [], futuros: [] });
});

// ── relações ADR→ADR ────────────────────────────────────────────────────────
// O grafo tinha 0 aresta adr→adr: a linhagem de decisão existia no frontmatter e era
// VALIDADA (adr-index-generate.mjs), mas nunca PUBLICADA como dado. O risco todo está
// na IDENTIDADE do alvo — 13 números do repo têm 2 ADRs distintas e 1 slug está tombado,
// então resolver por número publica fato falso. É isso que estes testes prendem.

test('adrRelationItems: inline, bloco e lista ausente', () => {
  assert.deepEqual(adrRelationItems('supersedes: [0101-a, 0102-b]', 'supersedes'), ['0101-a', '0102-b']);
  assert.deepEqual(adrRelationItems('supersedes:\n  - 0101-a\n  - "0102-b"\n', 'supersedes'), ['0101-a', '0102-b']);
  assert.deepEqual(adrRelationItems('outro: x', 'supersedes'), []);
  assert.deepEqual(adrRelationItems('supersedes: []', 'supersedes'), []);
});

test('BITE: item citado com aspas + comentário não vaza a aspa de fechamento', () => {
  // O rawItemsFrom do adr-index-generate.mjs tira as aspas ANTES do `#` e devolve
  // `0189-slug"` — artefato que faz um slug VIVO parecer pendurado (visto de verdade
  // no superseded_by da 0182). Aqui a ordem é comentário→aspas.
  const fm = 'superseded_by:\n  - "0189-pageheader-canon-v3-1"   # superseded parcial 2026-05-24\n';
  assert.deepEqual(adrRelationItems(fm, 'superseded_by'), ['0189-pageheader-canon-v3-1']);
});

const ADR_IDX = {
  knownSlugs: new Set(['0093-multi-tenant', '0180-drift-numero', '0180-sidebar-v3-5', '0358-doutrina-teste']),
  collidedNums: new Set(['0180']),
  tombstonedSlugs: new Set(['0101-tests-business-id-1-nunca-cliente']),
};

test('CN: slug vivo com número único resolve pro id por número (liga com o nó do SCOPE)', () => {
  const t = resolveAdrTarget('0093-multi-tenant', ADR_IDX);
  assert.deepEqual(t, { id: 'adr:0093', num: '0093', slug: '0093-multi-tenant', exists: true, tombstoned: false });
});

test('BITE: número COLIDIDO nunca colapsa 2 ADRs distintas no mesmo nó', () => {
  const a = resolveAdrTarget('0180-drift-numero', ADR_IDX);
  const b = resolveAdrTarget('0180-sidebar-v3-5', ADR_IDX);
  assert.notEqual(a.id, b.id, 'duas ADRs diferentes não podem virar o mesmo nó');
  assert.equal(a.id, 'adr:0180-drift-numero');
  assert.equal(b.id, 'adr:0180-sidebar-v3-5');
  assert.ok(a.exists && b.exists, 'ambas são arquivos vivos — ambíguo é o NÚMERO, não a existência');
});

test('BITE: slug TOMBADO não vira o nó do número (que pertence a outra ADR viva)', () => {
  const t = resolveAdrTarget('0101-tests-business-id-1-nunca-cliente', ADR_IDX);
  assert.equal(t.id, 'adr:0101-tests-business-id-1-nunca-cliente');
  assert.notEqual(t.id, 'adr:0101', 'adr:0101 é a ADR VIVA de outro assunto — apontar pra lá é fato falso');
  assert.equal(t.exists, false);
  assert.equal(t.tombstoned, true);
});

test('CN: slug desconhecido e NÃO tombado continua pendurado (não é perdoado)', () => {
  const t = resolveAdrTarget('0999-nao-existe', ADR_IDX);
  assert.equal(t.exists, false);
  assert.equal(t.tombstoned, false, 'só o ledger de tombstone perdoa — ausência não se auto-perdoa');
});

test('CN: sem ledger de tombstone, a supersessão de ADR esquecida volta a pendurar (fail-safe)', () => {
  const t = resolveAdrTarget('0101-tests-business-id-1-nunca-cliente', { ...ADR_IDX, tombstonedSlugs: new Set() });
  assert.equal(t.tombstoned, false);
});

/** Índice de ADR sintético no formato que readAdrIndex() devolve. */
const adrRelations = {
  slugs: ADR_IDX.knownSlugs,
  collidedNums: ADR_IDX.collidedNums,
  records: [
    { num: '0358', slug: '0358-doutrina-teste', supersedes: ['0101-tests-business-id-1-nunca-cliente'], supersedes_partially: [], superseded_by: [] },
    { num: '0093', slug: '0093-multi-tenant', supersedes: [], supersedes_partially: ['0180-drift-numero'], superseded_by: [] },
    { num: '0180', slug: '0180-sidebar-v3-5', supersedes: [], supersedes_partially: [], superseded_by: ['0093-multi-tenant'] },
  ],
};
const buildWithAdrs = (extra = {}) =>
  buildGraph(recordsFromSynthetic(), {
    adrRelations,
    tombstonedAdrSlugs: ADR_IDX.tombstonedSlugs,
    ...extra,
  });

test('buildGraph: emite os 3 tipos adr→adr com o campo do frontmatter como source', () => {
  const g = buildWithAdrs();
  const e = (from, to, type) => g.edges.some((x) => x.from === from && x.to === to && x.type === type);
  assert.ok(e('adr:0358', 'adr:0101-tests-business-id-1-nunca-cliente', 'supersedes'));
  assert.ok(e('adr:0093', 'adr:0180-drift-numero', 'supersedesPartially'));
  assert.ok(e('adr:0180-sidebar-v3-5', 'adr:0093', 'supersededBy'));
  assert.equal(g.edges.find((x) => x.type === 'supersedes').source, 'supersedes');
  assert.equal(g.edges.find((x) => x.type === 'supersedesPartially').source, 'supersedes_partially');
});

test('CN: sem adrRelations, nenhuma aresta adr→adr (compatível com o grafo de antes)', () => {
  const g = buildGraph(recordsFromSynthetic());
  const isAdr = (id) => String(id).startsWith('adr:');
  assert.equal(g.edges.filter((x) => isAdr(x.from) && isAdr(x.to)).length, 0);
});

test('BITE: aresta → ADR INEXISTENTE é acusada pendurada (não entra como válida)', () => {
  const rel = { ...adrRelations, records: [{ num: '0093', slug: '0093-multi-tenant', supersedes: ['0999-fantasma'], supersedes_partially: [], superseded_by: [] }] };
  const g = buildGraph(recordsFromSynthetic(), { adrRelations: rel, tombstonedAdrSlugs: ADR_IDX.tombstonedSlugs });
  assert.ok(
    g.diagnostics.dangling_adr_refs.some((d) => d.to === 'adr:0999-fantasma' && d.type === 'supersedes'),
    'slug fantasma tem que aparecer em dangling_adr_refs — senão o grafo publica aresta pra ADR que não existe',
  );
});

test('BITE: supersessão de ADR TOMBADA não conta como pendurada, mas fica visível', () => {
  const g = buildWithAdrs();
  assert.equal(g.diagnostics.dangling_adr_refs.length, 0, 'tombada é morte legítima (ADR 0316), não aresta quebrada');
  assert.ok(
    g.diagnostics.adr_supersession_of_tombstoned.some((d) => d.from === 'adr:0358'),
    'perdoar não é esconder — a supersessão de ADR esquecida continua reportada',
  );
});

test('BITE: sem o ledger de tombstone, a MESMA aresta vira pendurada (a guarda é o ledger)', () => {
  const g = buildGraph(recordsFromSynthetic(), { adrRelations, tombstonedAdrSlugs: new Set() });
  assert.ok(g.diagnostics.dangling_adr_refs.some((d) => d.from === 'adr:0358'));
  assert.equal(g.diagnostics.adr_supersession_of_tombstoned.length, 0);
});

test('CN: self-ref por slug não vira aresta', () => {
  const rel = { ...adrRelations, records: [{ num: '0093', slug: '0093-multi-tenant', supersedes: ['0093-multi-tenant'], supersedes_partially: [], superseded_by: [] }] };
  const g = buildGraph(recordsFromSynthetic(), { adrRelations: rel });
  assert.ok(!g.edges.some((x) => x.from === 'adr:0093' && x.to === 'adr:0093'));
});

test('BITE: SCOPE que cita ADR tombada NÃO carimba o slug morto no nó do número vivo', () => {
  // Caso real: Fiscal/SCOPE.md cita `0101-tests-...` (tombada) e o nó adr:0101 — que é a
  // ADR VIVA `0101-sistema-charter-...` — saía rotulado com o slug da morta.
  const recs = recordsFromSynthetic();
  recs[0].related_adrs = ['0101-tests-business-id-1-nunca-cliente', '0093-multi-tenant'];
  const g = buildGraph(recs, { adrRelations, tombstonedAdrSlugs: ADR_IDX.tombstonedSlugs });
  assert.equal(g.nodes.find((n) => n.id === 'adr:0101').slug, '', 'slug morto não pode rotular ADR viva');
  assert.equal(g.nodes.find((n) => n.id === 'adr:0093').slug, '0093-multi-tenant', 'slug legítimo continua carimbado');
});

// ── acoplamento derivado (advisory) ─────────────────────────────────────────
const VIVOS = new Set(['Alpha', 'Beta', 'Gama']);
const L = (path, code) => `${path}:${code}`;

test('parseImportsCruzados: extrai src/dst/camada/símbolo e ignora self-ref', () => {
  const refs = parseImportsCruzados([
    L('Modules/Alpha/Services/X.php', 'use Modules\\Beta\\Entities\\Nota;'),
    L('Modules/Alpha/Services/X.php', 'use Modules\\Alpha\\Services\\Proprio;'),
  ], { modulosVivos: VIVOS });
  assert.equal(refs.length, 1, 'self-ref não é fronteira');
  assert.deepEqual(refs[0], {
    src: 'Alpha', dst: 'Beta', camada: 'Entities', simbolo: 'Nota',
    fqcn: 'Modules\\Beta\\Entities\\Nota',
  });
});

test('FP: comentário/docblock que MENCIONA o import NÃO conta (o erro que inflou 41→116)', () => {
  const refs = parseImportsCruzados([
    L('Modules/Alpha/S.php', ' * use Modules\\Beta\\Entities\\Nota; (morava aqui até 2026-07)'),
    L('Modules/Alpha/S.php', '// use Modules\\Beta\\Services\\Velho;'),
    L('Modules/Alpha/S.php', '# use Modules\\Beta\\Models\\Y;'),
  ], { modulosVivos: VIVOS });
  assert.deepEqual(refs, [], 'prosa não é acoplamento');
});

test('módulo REMOVIDO citado em import não vira fronteira (tombstone ≠ aresta)', () => {
  const refs = parseImportsCruzados([
    L('Modules/Alpha/S.php', 'use Modules\\Fantasma\\Entities\\Z;'),
  ], { modulosVivos: VIVOS });
  assert.deepEqual(refs, []);
});

test('Tests/ fica fora por padrão e entra com incluirTestes', () => {
  const linhas = [L('Modules/Alpha/Tests/Feature/AT.php', 'use Modules\\Beta\\Services\\S;')];
  assert.equal(parseImportsCruzados(linhas, { modulosVivos: VIVOS }).length, 0);
  assert.equal(parseImportsCruzados(linhas, { modulosVivos: VIVOS, incluirTestes: true }).length, 1);
});

test('simbolosCrossCutting: primitiva é DERIVADA do nº de módulos, não de lista à mão', () => {
  const refs = ['Alpha', 'Beta', 'Gama'].map((src) =>
    ({ src, dst: 'Jana', camada: 'Scopes', simbolo: 'ScopeByBusiness' }));
  refs.push({ src: 'Alpha', dst: 'Beta', camada: 'Entities', simbolo: 'Nota' });
  // sem `fqcn` nos refs sintéticos, a identidade cai no fallback `Modules\<dst>\<simbolo>`
  assert.deepEqual([...simbolosCrossCutting(refs, 3)], ['Modules\\Jana\\ScopeByBusiness']);
  assert.deepEqual([...simbolosCrossCutting(refs, 4)], [], 'limiar acima do uso real não elege ninguém');
});

test('BITE: classes HOMÔNIMAS de módulos diferentes NÃO somam no limiar cross-cutting', () => {
  // Defeito real, achado pelo especialista revisando esta medição: `Subscription` existe em
  // Superadmin\Entities (4 importadores) E em RecurringBilling\Models (1). Agrupado por nome
  // CURTO somavam 5, cruzavam o corte e eram eleitos "primitiva cross-cutting" — sem nenhuma
  // qualificar sozinha. A identidade do símbolo é o FQCN, não o basename.
  const refs = [
    ...['Connector', 'Officeimpresso', 'PaymentGateway', 'VozDoCliente'].map((src) => ({
      src, dst: 'Superadmin', camada: 'Entities', simbolo: 'Subscription',
      fqcn: 'Modules\\Superadmin\\Entities\\Subscription',
    })),
    {
      src: 'Financeiro', dst: 'RecurringBilling', camada: 'Models', simbolo: 'Subscription',
      fqcn: 'Modules\\RecurringBilling\\Models\\Subscription',
    },
  ];
  assert.equal(refs.length, 5, 'somados por nome curto dariam exatamente o limiar');
  assert.deepEqual([...simbolosCrossCutting(refs, 5)], [], 'nenhuma qualifica: 4 e 1, não 5');
});

test('CN: símbolo REALMENTE cross-cutting segue eleito (o fix não cega o detector)', () => {
  const refs = ['A', 'B', 'C', 'D', 'E'].map((src) => ({
    src, dst: 'Jana', camada: 'Scopes', simbolo: 'ScopeByBusiness',
    fqcn: 'Modules\\Jana\\Scopes\\ScopeByBusiness',
  }));
  assert.deepEqual([...simbolosCrossCutting(refs, 5)], ['Modules\\Jana\\Scopes\\ScopeByBusiness']);
});

test('parseImportsCruzados carimba fqcn (a identidade que o limiar usa)', () => {
  const refs = parseImportsCruzados([
    L('Modules/Alpha/S.php', 'use Modules\\Beta\\Entities\\Nota as N;'),
  ], { modulosVivos: VIVOS });
  assert.equal(refs[0].fqcn, 'Modules\\Beta\\Entities\\Nota', 'alias não entra no fqcn');
});

test('pesoDaCamada: model/entity é o pior; contrato é o mais fraco', () => {
  assert.equal(pesoDaCamada('Entities'), 'dado');
  assert.equal(pesoDaCamada('Models'), 'dado');
  assert.equal(pesoDaCamada('Scopes'), 'comportamento');
  assert.equal(pesoDaCamada('Events'), 'contrato');
  assert.equal(pesoDaCamada('Services'), 'servico');
});

test('BITE: fronteira REAL não declarada no SCOPE é detectada (e a declarada não vira ruído)', () => {
  // Alpha declara depender de Beta (SCOPE_ALPHA: not_contains → Modules/Beta).
  const g = buildGraph(recordsFromSynthetic());
  const refs = parseImportsCruzados([
    L('Modules/Alpha/S.php', 'use Modules\\Beta\\Services\\Legit;'),  // declarada
    L('Modules/Alpha/S.php', 'use Modules\\Gama\\Entities\\Escondida;'), // NÃO declarada
  ], { modulosVivos: VIVOS });
  const r = compararFronteira(refs, g);
  const nd = r.naoDeclaradas.map((p) => `${p.src}>${p.dst}`);
  assert.ok(nd.includes('Alpha>Gama'), 'MORDE: import sem declaração tem que aparecer');
  assert.ok(!nd.includes('Alpha>Beta'), 'CONTROLE NEGATIVO: declarada não pode virar achado');
  assert.equal(r.naoDeclaradas.find((p) => p.dst === 'Gama').peso, 'dado');
});

test('par cujo acoplamento é SÓ primitiva é marcado soPrimitiva (1 decisão, não N)', () => {
  const g = buildGraph(recordsFromSynthetic());
  const refs = ['Alpha', 'Beta', 'Gama'].map((src) =>
    ({ src, dst: 'Jana', camada: 'Services', simbolo: 'PiiRedactor' }));
  const r = compararFronteira(refs, g, { limiar: 3 });
  assert.ok(r.naoDeclaradas.every((p) => p.soPrimitiva));
});

test('soDeclaradas: aresta no SCOPE sem NENHUM import é reportada (boilerplate)', () => {
  const g = buildGraph(recordsFromSynthetic());
  const r = compararFronteira([], g);
  assert.ok(r.soDeclaradas.some((p) => p.src === 'Alpha' && p.dst === 'Beta'));
});

test('soDeclaradas NÃO conta alvo REMOVIDO (tombstone já tem diagnóstica própria)', () => {
  const g = buildGraph(recordsFromSynthetic());
  const semFiltro = compararFronteira([], g);
  const comFiltro = compararFronteira([], g, { modulosVivos: VIVOS });
  assert.ok(semFiltro.soDeclaradas.some((p) => !VIVOS.has(p.dst)), 'fixture tem alvo fora dos vivos');
  assert.ok(comFiltro.soDeclaradas.every((p) => VIVOS.has(p.dst)), 'com modulosVivos, só vivo entra');
});

// ── fronteira por TABELA (query crua) ───────────────────────────────────────
const MIG = (mod, tabela) => `Modules/${mod}/Database/Migrations/2026_01_01_x.php:        Schema::create('${tabela}', function (Blueprint $t) {`;
const QRY = (mod, code) => `Modules/${mod}/Services/S.php:        ${code}`;

test('derivarDonoDeTabela: dono é quem CRIA na migration; core não sobrescreve módulo', () => {
  const { dono } = derivarDonoDeTabela(
    [MIG('Alpha', 'alpha_notas'), MIG('Beta', 'beta_itens')],
    ["database/migrations/x.php:        Schema::create('contacts', function (Blueprint $t) {"],
  );
  assert.equal(dono.get('alpha_notas'), 'Alpha');
  assert.equal(dono.get('beta_itens'), 'Beta');
  assert.equal(dono.get('contacts'), '(core)');
});

test('REVISÃO: 2 módulos criando a MESMA tabela é conflito REPORTADO, não first-wins calado', () => {
  // Caso real medido: nfe_certificados e nfse_emissoes (NFSe vs NfeBrasil). A diagnóstica
  // do grafo DECLARADO diz "0 conflito de ownership" — ela olha db_tables_owned, não a árvore.
  const { dono, conflitos } = derivarDonoDeTabela([MIG('Beta', 'disputada'), MIG('Alpha', 'disputada')]);
  assert.deepEqual(conflitos, [{ tabela: 'disputada', modulos: ['Alpha', 'Beta'] }]);
  assert.equal(dono.get('disputada'), 'Alpha', 'atribuição determinística (ordem alfabética), não ordem de leitura');
});

test('CN: tabela criada por 1 módulo só NÃO entra em conflitos', () => {
  const { conflitos } = derivarDonoDeTabela([MIG('Alpha', 'so_dela'), MIG('Alpha', 'so_dela')]);
  assert.deepEqual(conflitos, [], 'mesmo módulo 2x não é disputa');
});

test('REVISÃO: `use X as Alias` não polui o símbolo (senão o limiar cross-cutting conta errado)', () => {
  const refs = parseImportsCruzados([
    L('Modules/Alpha/S.php', 'use Modules\\Beta\\Http\\Controllers\\DataController as BetaData;'),
    L('Modules/Gama/S.php', 'use Modules\\Beta\\Http\\Controllers\\DataController;'),
  ], { modulosVivos: VIVOS });
  assert.deepEqual(refs.map((r) => r.simbolo), ['DataController', 'DataController'],
    'o mesmo símbolo com 2 apelidos tem que contar como 1');
});

test('parseQueriesCruas: tabela alheia vira ref; própria e core NÃO', () => {
  const { dono } = derivarDonoDeTabela([MIG('Alpha', 'alpha_notas'), MIG('Beta', 'beta_itens')],
    ["database/migrations/x.php:Schema::create('contacts', function (Blueprint $t) {"]);
  const { refs } = parseQueriesCruas([
    QRY('Alpha', "DB::table('beta_itens')->get();"),   // alheia → conta
    QRY('Alpha', "DB::table('alpha_notas')->get();"),  // própria → não
    QRY('Alpha', "DB::table('contacts')->get();"),     // core → não
  ], { donoDe: dono, modulosVivos: VIVOS });
  assert.deepEqual(refs, [{ src: 'Alpha', dono: 'Beta', tabela: 'beta_itens' }]);
});

test('DB::table($var) dinâmico é NÃO RESOLVIDO — nunca some como zero (§5 2026-07-29)', () => {
  const { refs, dinamico } = parseQueriesCruas([
    QRY('Alpha', 'DB::table($tabela)->get();'),
    QRY('Alpha', 'DB::table( $this->tbl )->get();'),
  ], { donoDe: new Map(), modulosVivos: VIVOS });
  assert.equal(refs.length, 0);
  assert.equal(dinamico, 2, 'o que não deu pra resolver tem que ser CONTADO, não engolido');
});

test('tabela sem migration localizada conta em semDono, não vira fronteira inventada', () => {
  const { refs, semDono } = parseQueriesCruas(
    [QRY('Alpha', "DB::table('tabela_fantasma')->get();")],
    { donoDe: new Map(), modulosVivos: VIVOS },
  );
  assert.equal(refs.length, 0);
  assert.equal(semDono, 1);
});

test('infra compartilhada é DERIVADA por limiar (o caso failed_jobs), não por lista à mão', () => {
  const refs = ['Alpha', 'Beta', 'Gama'].map((src) => ({ src, dono: 'Delta', tabela: 'failed_jobs' }));
  refs.push({ src: 'Alpha', dono: 'Beta', tabela: 'beta_itens' });
  const r = agruparFronteiraDeTabela(refs, { limiar: 3 });
  assert.deepEqual(r.infra, ['failed_jobs']);
  assert.deepEqual(r.pares.map((p) => `${p.src}>${p.dono}`), ['Alpha>Beta'], 'infra sai; fronteira real fica');
});

test('CN: abaixo do limiar a tabela NÃO é rebaixada a infra (limiar não é lista disfarçada)', () => {
  const refs = ['Alpha', 'Beta'].map((src) => ({ src, dono: 'Delta', tabela: 'failed_jobs' }));
  const r = agruparFronteiraDeTabela(refs, { limiar: 3 });
  assert.deepEqual(r.infra, []);
  assert.equal(r.pares.length, 2);
});

test('Tests/ não conta como acoplamento de produção também no eixo tabela', () => {
  const { dono } = derivarDonoDeTabela([MIG('Beta', 'beta_itens')]);
  const { refs } = parseQueriesCruas(
    ["Modules/Alpha/Tests/Feature/T.php:        DB::table('beta_itens')->get();"],
    { donoDe: dono, modulosVivos: VIVOS },
  );
  assert.deepEqual(refs, []);
});

// ── INVOCAÇÃO: a máquina roda no MOMENTO CERTO? ─────────────────────────────
// Estes não testam o cálculo — testam que alguém CHAMA o cálculo. Sem eles, uma edição
// futura pode deixar o medidor perfeito e órfão ("máquina que ninguém invoca é bug",
// proibicoes.md §Sempre fazer; meta-padrão 'correção-do-mecanismo ≠ invocação', §5 2026-07-09).
const WORKFLOW = readFileSync(new URL('../../.github/workflows/catalog-graph.yml', import.meta.url), 'utf8');

test('INVOCAÇÃO: o workflow chama --acoplamento (senão o medidor nasce órfão)', () => {
  assert.match(WORKFLOW, /catalog-graph\.mjs --acoplamento/, 'nenhum step invoca o medidor');
});

test('INVOCAÇÃO: roda em TODO PR — `on: pull_request` SEM `paths:`', () => {
  // O momento certo é "todo PR", não "PR que toca Modules/". Acoplamento entra por PR que
  // nem encosta em SCOPE.md; com path-filter o medidor ficaria mudo justamente aí.
  const on = WORKFLOW.slice(WORKFLOW.indexOf('\non:'), WORKFLOW.indexOf('\npermissions:'));
  assert.match(on, /pull_request/, 'tem que disparar em pull_request');
  assert.doesNotMatch(on, /paths:/, 'path-filter faria o medidor calar no PR que mais importa');
});

test('INVOCAÇÃO: o step do medidor NÃO tem continue-on-error nem `|| true`', () => {
  // Ele já sai 0 por desenho; um `continue-on-error` aqui seria teatro (§5 2026-07-09)
  // e mascararia uma falha REAL de execução (ex.: o script quebrar ao ser editado).
  const linha = WORKFLOW.split('\n').find((l) => l.includes('--acoplamento'));
  assert.ok(linha, 'step não encontrado');
  assert.doesNotMatch(linha, /\|\| true/);
  assert.doesNotMatch(WORKFLOW, /--acoplamento[\s\S]{0,120}continue-on-error/);
});

test('serialize: os 3 tipos novos entram no by_edge_type e o JSON segue determinístico', () => {
  const cat = JSON.parse(serialize(buildWithAdrs()));
  assert.equal(cat.stats.by_edge_type.supersedes, 1);
  assert.equal(cat.stats.by_edge_type.supersedesPartially, 1);
  assert.equal(cat.stats.by_edge_type.supersededBy, 1);
  assert.deepEqual(Object.keys(cat.stats.by_edge_type).sort(), [...EDGE_TYPES].sort());
  assert.equal(serialize(buildWithAdrs()), serialize(buildWithAdrs()));
});
