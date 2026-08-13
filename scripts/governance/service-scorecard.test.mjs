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
import { graphSignals, buildDoc, gitLastDate } from './service-scorecard.mjs';

// ── fixtures sintéticas ────────────────────────────────────────────────────────
const catalog = {
  stats: { modules: 4 },
  nodes: [
    { id: 'module:Alpha', type: 'module', module: 'Alpha', trust: 'L1', owner: 'wagner', charter_adr: '0080', path: 'memory/requisitos/Alpha/SCOPE.md', purpose: 'Alpha.' },
    { id: 'module:Beta', type: 'module', module: 'Beta', charter_adr: '0081', path: 'memory/requisitos/Beta/SCOPE.md', purpose: 'Beta backend-only.' },
    { id: 'module:TeamX', type: 'module', module: 'TeamX', charter_adr: null, path: 'memory/requisitos/TeamX/SCOPE.md', purpose: 'sem PAGES_NS → cai na normalização.' },
    { id: 'module:TeamY', type: 'module', module: 'TeamY', charter_adr: '0083', path: 'memory/requisitos/TeamY/SCOPE.md', purpose: 'com PAGES_NS → match direto.' },
    { id: 'module:Solo', type: 'module', module: 'Solo', charter_adr: '0082', path: 'memory/requisitos/Solo/SCOPE.md', purpose: 'sem grade, sem aresta.' },
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

// ── BITE do guard anti-fabricação de data (clone raso) ──────────────────────
// Num checkout raso o `git log -1 -- <path>` datava TODO arquivo com o dia da run,
// e o número saía plausível — foi assim que `last_commit: 2026-08-12` em bloco
// passou meses sem ninguém notar. O guard tem que devolver `null` ("não medido"),
// nunca uma data inventada. Sem as DUAS pernas isto é carimbo, não teste.
test('BITE raso: em history truncada NÃO inventa data — devolve null', () => {
  assert.equal(gitLastDate('memory/requisitos/Jana/BRIEFING.md', { raso: true }), null);
});

test('CONTROLE completo: com history NÃO truncada mede de verdade (ISO curta)', () => {
  const d = gitLastDate('memory/requisitos/Jana/BRIEFING.md', { raso: false });
  assert.match(String(d), /^\d{4}-\d{2}-\d{2}$/);
});

test('CONTROLE raso: path inexistente devolve null nos dois modos (vazio ≠ fabricação)', () => {
  assert.equal(gitLastDate('memory/requisitos/__nao_existe__/BRIEFING.md', { raso: false }), null);
  assert.equal(gitLastDate('memory/requisitos/__nao_existe__/BRIEFING.md', { raso: true }), null);
});

// ── PAGES_NS 1:N (um módulo, vários namespaces) ────────────────────────────────
// Desde que as Pages passaram a morar no módulo dono (PR #5686), PAGES_NS aceita ARRAY:
// Whatsapp → ['Whatsapp','Atendimento']. Este bloco é auto-contido de propósito — não
// mexe nas fixtures compartilhadas acima, para não deslocar as contagens dos testes de stats.
//
// Sem este teste a regressão passava CALADA: os 8 casos acima usam pagesNs só com string,
// então nenhum deles exercitava o array. Em produção o cron `mv-metabolismo` quebrou com
// `ERR_INVALID_ARG_TYPE` em join(), e o defeito silencioso era pior que o barulhento —
// `vitalByNs.get(<array>)` devolve undefined sem erro, classificando o módulo como
// backend-only. Cobre os dois: o crash E o silêncio.
const catalog1N = {
  stats: { modules: 2 },
  nodes: [
    { id: 'module:Multi', type: 'module', module: 'Multi', charter_adr: '0090', path: 'memory/requisitos/Multi/SCOPE.md', purpose: 'namespace primário sem telas; as telas vivem no secundário.' },
    { id: 'module:Unico', type: 'module', module: 'Unico', charter_adr: '0091', path: 'memory/requisitos/Unico/SCOPE.md', purpose: 'string simples — a forma legada segue valendo.' },
  ],
  edges: [],
};
const vital1N = {
  generated_at: '2026-08-13',
  modulos: [
    // repare: NÃO existe linha 'Multi'. A única linha é a do namespace SECUNDÁRIO.
    { mod: 'Secundario', telas: 7, com_scorecard: 7, nota_media: 82, nota_min: 75, pior_tela: 'Secundario/A', charter_pct: 100, casos_pct: 50, stale: false, idade_max_dias: 2 },
    { mod: 'Unico', telas: 1, com_scorecard: 1, nota_media: 90, nota_min: 90, pior_tela: 'Unico/A', charter_pct: 100, casos_pct: 100, stale: false, idade_max_dias: 1 },
  ],
};
const deps1N = {
  pagesNs: { Multi: ['Multi', 'Secundario'], Unico: 'Unico' },
  // recebe a LISTA inteira e casa se QUALQUER namespace tiver dir — como as duas raízes reais
  hasPagesDir: (nss) => (Array.isArray(nss) ? nss : [nss]).some((n) => n === 'Secundario' || n === 'Unico'),
  scopeExists: () => true,
  briefingInfo: () => ({ present: true, last_commit: '2026-08-01' }),
};
const doc1N = buildDoc({ catalog: catalog1N, gradesDoc: { baseline_version: 'vTEST', rubric_adr: '0155', modules: { Multi: 80, Unico: 80 } }, vitalDoc: vital1N }, deps1N);
const by1N = Object.fromEntries(doc1N.services.map((s) => [s.id, s]));

test('1:N — módulo com PAGES_NS array acha o vital-signs pelo namespace SECUNDÁRIO', () => {
  const sc = by1N.Multi.signals.screens;
  assert.equal(sc.matched, true, 'sem o fallback pela lista, Multi cairia em backend_only');
  assert.equal(sc.ns, 'Secundario');
  assert.equal(sc.telas, 7);
});

test('1:N — o ns publicado é STRING, nunca o array cru (não vaza pro JSON nem pro template)', () => {
  for (const s of doc1N.services) {
    assert.equal(Array.isArray(s.signals.screens.ns), false, `${s.id} publicou ns como array`);
    assert.equal(typeof s.signals.screens.ns, 'string');
  }
});

// ── 1:N com AMBOS os namespaces presentes — o caso que o `break` subcontava ──────
// A 1ª versão parava no primeiro hit: `Whatsapp` casava com `Whatsapp` (3) e `Atendimento` (8)
// virava "namespace órfão", publicando 3 de 11. Aqui os DOIS têm linha, então o teste morde
// exatamente esse `break`.
const vitalAmbos = {
  generated_at: '2026-08-13',
  modulos: [
    { mod: 'Multi', telas: 3, com_scorecard: 3, nota_media: 90, nota_min: 88, pior_tela: 'Multi/A', charter_pct: 100, casos_pct: 100, stale: false, idade_max_dias: 1 },
    { mod: 'Secundario', telas: 7, com_scorecard: 5, nota_media: 60, nota_min: 40, pior_tela: 'Secundario/Z', charter_pct: 50, casos_pct: 0, stale: true, idade_max_dias: 30 },
  ],
};
const docAmbos = buildDoc(
  { catalog: catalog1N, gradesDoc: { baseline_version: 'vTEST', rubric_adr: '0155', modules: { Multi: 80, Unico: 80 } }, vitalDoc: vitalAmbos },
  { ...deps1N, pagesNs: { Multi: ['Multi', 'Secundario'], Unico: 'Unico' } },
);
const multiAmbos = docAmbos.services.find((s) => s.id === 'Multi').signals.screens;

test('1:N — com AMBOS presentes SOMA as telas (não para no primeiro)', () => {
  assert.equal(multiAmbos.telas, 10, '3 + 7 — o `break` publicava 3');
  assert.equal(multiAmbos.com_scorecard, 8);
});

test('1:N — nenhum dos namespaces agregados vira órfão falso', () => {
  assert.equal(docAmbos.stats.orphan_screen_ns.includes('Secundario'), false);
  assert.equal(docAmbos.stats.orphan_screen_ns.includes('Multi'), false);
});

test('1:N — agrega por NATUREZA: mín/stale/idade pelo pior, nota e % ponderados por telas', () => {
  assert.equal(multiAmbos.nota_min, 40, 'o mínimo é do pior namespace');
  assert.equal(multiAmbos.pior_tela, 'Secundario/Z', 'pior_tela acompanha o mínimo');
  assert.equal(multiAmbos.stale, true, 'stale é OR — um namespace velho contamina o módulo');
  assert.equal(multiAmbos.idade_max_dias, 30, 'idade é o máximo');
  // ponderado por telas (3×90 + 7×60)/10 = 69 — média simples daria 75, e esconderia o pior
  assert.equal(multiAmbos.nota_media, 69);
  assert.equal(multiAmbos.casos_pct, 30); // (3×100 + 7×0)/10
});

test('1:N — a forma STRING legada continua funcionando (não regredir quem não é 1:N)', () => {
  const sc = by1N.Unico.signals.screens;
  assert.equal(sc.matched, true);
  assert.equal(sc.ns, 'Unico');
  assert.equal(sc.telas, 1);
});
