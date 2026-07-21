// @ts-check
/**
 * service-scorecard.test.mjs — self-test do agregador de sinais-vivos por serviço.
 * Roda: node --test scripts/governance/service-scorecard.test.mjs
 *
 * Testa a LÓGICA PURA (graphSignals + buildDoc) com inputs SINTÉTICOS — não a árvore
 * viva (que muda todo dia), então o teste não apodrece junto com o repo. Cobre os
 * pontos que quebram calado: join grade exato, join tela PAGES_NS + normalização EXATA
 * (TeamMcp↔team-mcp) sem casar por similaridade, backend-only ≠ falha, aresta pendurada,
 * maturidade só sobre checks aplicáveis, e órfão = ns não consumido.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { graphSignals, buildDoc } from './service-scorecard.mjs';

// ── fixtures sintéticas ────────────────────────────────────────────────────────
const catalog = {
  stats: { modules: 4 },
  nodes: [
    { id: 'module:Alpha', type: 'module', module: 'Alpha', trust: 'L1', owner: 'wagner', charter_adr: '0080', path: 'Modules/Alpha/SCOPE.md', purpose: 'Alpha.' },
    { id: 'module:Beta', type: 'module', module: 'Beta', charter_adr: '0081', path: 'Modules/Beta/SCOPE.md', purpose: 'Beta backend-only.' },
    { id: 'module:TeamX', type: 'module', module: 'TeamX', charter_adr: null, path: 'Modules/TeamX/SCOPE.md', purpose: 'sem PAGES_NS → cai na normalização.' },
    { id: 'module:TeamY', type: 'module', module: 'TeamY', charter_adr: '0083', path: 'Modules/TeamY/SCOPE.md', purpose: 'com PAGES_NS → match direto.' },
    { id: 'module:Solo', type: 'module', module: 'Solo', charter_adr: '0082', path: 'Modules/Solo/SCOPE.md', purpose: 'sem grade, sem aresta.' },
    { id: 'table:alpha_t', type: 'table' },
    { id: 'api:/alpha/*', type: 'api' },
  ],
  edges: [
    { from: 'module:Alpha', to: 'table:alpha_t', type: 'ownsTable' },
    { from: 'module:Alpha', to: 'api:/alpha/*', type: 'providesApi' },
    { from: 'module:Alpha', to: 'module:Beta', type: 'delegatesTo' },
    { from: 'module:Beta', to: 'module:Alpha', type: 'delegatesTo' },
    { from: 'module:TeamX', to: 'module:Fantasma', type: 'delegatesTo' }, // aresta pendurada
  ],
};
const gradesDoc = {
  baseline_version: 'vTEST', rubric_adr: '0155',
  modules: { Alpha: 90, Beta: 60, TeamX: 75, TeamY: 70 }, // Solo sem grade de propósito
};
const vitalDoc = {
  generated_at: '2026-07-06',
  modulos: [
    { mod: 'Alpha', telas: 3, com_scorecard: 3, nota_media: 80, nota_min: 70, pior_tela: 'Alpha/X', charter_pct: 100, casos_pct: 0, stale: false, idade_max_dias: 1 },
    { mod: 'team-x', telas: 2, com_scorecard: 2, nota_media: 72, nota_min: 60, pior_tela: 'team-x/Y', charter_pct: 50, casos_pct: 0, stale: true, idade_max_dias: 9 },
    { mod: 'team-y', telas: 4, com_scorecard: 4, nota_media: 84, nota_min: 78, pior_tela: 'team-y/W', charter_pct: 100, casos_pct: 0, stale: false, idade_max_dias: 3 },
    { mod: 'Sells', telas: 5, com_scorecard: 5, nota_media: 88, nota_min: 80, pior_tela: 'Sells/Z', charter_pct: 100, casos_pct: 20, stale: false, idade_max_dias: 2 }, // órfão core-app
  ],
};
// deps injetadas: só o TeamY tem PAGES_NS (→ match direto); TeamX cai na normalização.
const deps = {
  pagesNs: { TeamY: 'team-y' }, // namespace Inertia difere do nome do módulo
  hasPagesDir: (ns) => ns === 'Alpha' || ns === 'team-y', // TeamX (ns 'TeamX') não tem dir
  scopeExists: () => true,
  briefingInfo: (mod) => ({ present: mod !== 'Solo', last_commit: mod !== 'Solo' ? '2026-07-01' : null }),
};

const doc = buildDoc({ catalog, gradesDoc, vitalDoc }, deps);
const byId = Object.fromEntries(doc.services.map((s) => [s.id, s]));

test('graphSignals conta arestas por tipo e detecta pendurada + connected', () => {
  const g = graphSignals('module:Alpha', catalog.edges, new Set(catalog.nodes.map((n) => n.id)));
  assert.equal(g.owns_tables, 1);
  assert.equal(g.provides_api, 1);
  assert.equal(g.depends_on, 1);   // Alpha → Beta
  assert.equal(g.dependents, 1);   // Beta → Alpha
  assert.equal(g.dangling_edges, 0);
  assert.equal(g.connected, true);
  const t = graphSignals('module:TeamX', catalog.edges, new Set(catalog.nodes.map((n) => n.id)));
  assert.equal(t.dangling_edges, 1); // → module:Fantasma (inexistente)
});

test('grade casa por nome exato; ausente vira null (Solo)', () => {
  assert.equal(byId.Alpha.signals.grade.value, 90);
  assert.equal(byId.Alpha.signals.grade.baseline, 'vTEST');
  assert.equal(byId.Solo.signals.grade, null);
});

test('tela: match direto por PAGES_NS (TeamY→team-y) e fallback normalização EXATA (TeamX↔team-x)', () => {
  assert.equal(byId.Alpha.signals.screens.matched, true);
  assert.equal(byId.Alpha.signals.screens.via, 'direto');
  assert.equal(byId.TeamY.signals.screens.matched, true);   // PAGES_NS resolveu
  assert.equal(byId.TeamY.signals.screens.via, 'direto');
  assert.equal(byId.TeamX.signals.screens.matched, true);   // sem PAGES_NS → normalização
  assert.equal(byId.TeamX.signals.screens.via, 'normalizado');
  assert.equal(byId.TeamX.signals.screens.telas, 2);
});

test('backend-only NÃO é falha: Beta sem dir de Pages → matched:false backend_only', () => {
  assert.equal(byId.Beta.signals.screens.matched, false);
  assert.equal(byId.Beta.signals.screens.backend_only, true);
  // o check screens_matched fica n/a (não aplicável) → não pesa contra Beta
  const chk = byId.Beta.service?.checks ?? byId.Beta.checks;
  assert.equal(chk.find((c) => c.key === 'screens_matched').na, true);
});

test('similaridade NÃO casa (Sells fica órfão, não vira tela de nenhum serviço)', () => {
  assert.ok(doc.stats.orphan_screen_ns.includes('Sells'));
  // nenhum serviço puxou os números de Sells
  for (const s of doc.services) {
    if (s.signals.screens.matched) assert.notEqual(s.signals.screens.ns, 'Sells');
  }
});

test('maturidade só conta checks aplicáveis; TeamX cai por stale', () => {
  // TeamX: tem tela (stale=true, charter 50%) → no_stale_screens e charter_full falham
  const m = byId.TeamX.maturity;
  assert.ok(m.applicable >= 1);
  assert.ok(m.passed < m.applicable); // não é ouro
  assert.ok(['prata', 'bronze'].includes(m.level));
});

test('stats: serviços, com_grade e níveis somam certo', () => {
  assert.equal(doc.stats.services, 5);
  assert.equal(doc.stats.with_grade, 4); // Solo sem grade
  const lv = doc.stats.maturity_levels;
  assert.equal(lv.ouro + lv.prata + lv.bronze, 5);
});

test('determinismo: buildDoc duas vezes → JSON idêntico', () => {
  const a = JSON.stringify(buildDoc({ catalog, gradesDoc, vitalDoc }, deps));
  const b = JSON.stringify(buildDoc({ catalog, gradesDoc, vitalDoc }, deps));
  assert.equal(a, b);
});
