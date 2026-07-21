// @ts-check
/**
 * tema-owner.test.mjs — self-test do detector de DONO-DE-TEMA.
 * Roda: node scripts/governance/tema-owner.test.mjs  (ou `node --test`).
 *
 * Dois níveis:
 *  (1) LÓGICA PURA com inputs SINTÉTICOS (não apodrece com o repo) — extração, normalização, match.
 *  (2) BITE-TEST + PROVA REAL — o detector DEVE morder quando há sobreposição de entidade, DEVE
 *      passar limpo quando é tema novo, e NÃO pode reagir a semelhança de NOME. Se a lógica for
 *      afrouxada (comparar por nome de arquivo, parar de extrair tabela, ignorar self-exclude), um
 *      destes testes fica VERMELHO — é o que impede o gate de virar teatro.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import {
  frontmatterBlock,
  scalarField,
  anchorsFromFrontmatter,
  entitiesFromBody,
  entityKey,
  signalsFromDoc,
  indexCatalog,
  indexTopicos,
  findOwners,
  renderAdvisory,
  loadCorpus,
} from './tema-owner.mjs';

const REPO_ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

// ── fixtures sintéticas ───────────────────────────────────────────────────────
const DOC_CALCULO = `---
id: calculo-total-fatura
module: Produto
title: "Cálculo do total da fatura"
kind: regra-negocio
status: contestado
updated_at: "2026-07-21"
anchors:
  screens: []
  functions:
    - app/Utils/ProductUtil.php::calculateInvoiceTotal
  models:
    - app/TaxRate.php
  tables:
    - tax_rates
  adrs:
    - 0093-multi-tenant-isolation-tier-0
---
# Cálculo do total da fatura
Fala de tax_rates e calculateInvoiceTotal.
`;

// nome de arquivo TOTALMENTE diferente, MESMA entidade (tax_rates) → DEVE colidir
const DOC_IMPOSTO = `---
id: regra-de-imposto-na-nota
module: Fiscal
title: "Regra de imposto na nota"
kind: regra-negocio
status: rascunho
updated_at: "2026-07-21"
anchors:
  tables:
    - tax_rates
---
# outra nota sobre a mesma tabela
`;

// nome de arquivo PARECIDO com o calculo, entidades DIFERENTES → NÃO pode colidir
const DOC_CALCULO_PARECIDO = `---
id: calculo-total-frete
module: Logistica
title: "Cálculo do total de frete"
kind: regra-negocio
status: rascunho
updated_at: "2026-07-21"
anchors:
  tables:
    - shipping_rates
---
# nome parece com calculo-total-fatura mas entidade é outra
`;

// só compartilha ADR transversal (0093) — NÃO pode colidir (ADR não é sinal de tema)
const DOC_SO_ADR = `---
id: outro-tema-qualquer
module: Jana
title: "Tema com o mesmo ADR transversal"
kind: capacidade
status: rascunho
updated_at: "2026-07-21"
anchors:
  tables:
    - jana_messages
  adrs:
    - 0093-multi-tenant-isolation-tier-0
---
`;

const FAKE_CATALOG = {
  nodes: [
    { type: 'table', name: 'tax_rates', owners: ['Produto'], legacy_views: [], consumers: [] },
    { type: 'table', name: 'shipping_rates', owners: ['Logistica'], legacy_views: [], consumers: [] },
    { type: 'table', name: 'jana_messages', owners: ['Jana'], legacy_views: [], consumers: [] },
    { type: 'api', prefix: '/produto/*', providers: ['Produto'] },
  ],
};

function buildCorpus(docsByPath, catalog = FAKE_CATALOG) {
  const catalogIndex = indexCatalog(catalog);
  const docs = Object.entries(docsByPath).map(([path, txt]) => ({ path, txt }));
  const topicos = indexTopicos(docs, catalogIndex.knownTables);
  return { catalogIndex, topicos };
}

// ── (1) extração e normalização ───────────────────────────────────────────────
test('frontmatterBlock isola o bloco YAML', () => {
  assert.ok(frontmatterBlock(DOC_CALCULO).includes('module: Produto'));
  assert.equal(frontmatterBlock('sem frontmatter'), '');
});

test('scalarField lê module sem aspas', () => {
  assert.equal(scalarField(frontmatterBlock(DOC_CALCULO), 'module'), 'Produto');
});

test('anchorsFromFrontmatter pega tables/functions/models e ignora adrs/review', () => {
  const a = anchorsFromFrontmatter(frontmatterBlock(DOC_CALCULO));
  assert.deepEqual(a.tables, ['tax_rates']);
  assert.deepEqual(a.functions, ['app/Utils/ProductUtil.php::calculateInvoiceTotal']);
  assert.deepEqual(a.models, ['app/TaxRate.php']);
  assert.equal(a.screens.length, 0);
});

test('entityKey normaliza: função por basename+símbolo, path por basename, tabela lower', () => {
  assert.equal(entityKey('functions', 'app/Utils/ProductUtil.php::calculateInvoiceTotal'), 'functions:productutil.php::calculateinvoicetotal');
  assert.equal(entityKey('models', 'app/TaxRate.php'), 'models:taxrate.php');
  assert.equal(entityKey('tables', 'Tax_Rates'), 'tables:tax_rates');
});

test('entitiesFromBody só conta tabela que EXISTE no catálogo (anti-ruído)', () => {
  const known = new Set(['tax_rates']);
  const e = entitiesFromBody('a venda usa tax_rates mas também a palavra rates e total genéricos', known);
  assert.deepEqual(e.tables, ['tax_rates']); // "rates"/"total" NÃO entram
});

test('signalsFromDoc NÃO gera chave de ADR (transversal não é tema)', () => {
  const s = signalsFromDoc(DOC_SO_ADR, new Set(['jana_messages']));
  assert.ok([...s.keys].every((k) => !k.startsWith('adr')), 'nenhuma key adr:*');
  assert.ok(s.keys.has('tables:jana_messages'));
});

// ── (2) MATCHING / MORDIDA ────────────────────────────────────────────────────
test('BITE: tema com tabela tax_rates MORDE o tópico que a declara (nome de arquivo IRRELEVANTE)', () => {
  const corpus = buildCorpus({ 'produto/topicos/calculo-total-fatura.md': DOC_CALCULO });
  const signals = signalsFromDoc(DOC_IMPOSTO, corpus.catalogIndex.knownTables); // arquivo "regra-de-imposto..."
  const r = findOwners(signals, corpus, 'fiscal/topicos/regra-de-imposto-na-nota.md');
  assert.equal(r.topicoOverlaps.length, 1, 'deve apontar 1 tópico dono');
  assert.equal(r.topicoOverlaps[0].path, 'produto/topicos/calculo-total-fatura.md');
  assert.ok(r.topicoOverlaps[0].shared.includes('tables:tax_rates'));
});

test('ANTI-SINTÁTICO: nome de arquivo PARECIDO + entidade diferente NÃO colide', () => {
  const corpus = buildCorpus({ 'produto/topicos/calculo-total-fatura.md': DOC_CALCULO });
  const signals = signalsFromDoc(DOC_CALCULO_PARECIDO, corpus.catalogIndex.knownTables); // "calculo-total-frete"
  const r = findOwners(signals, corpus, 'logistica/topicos/calculo-total-frete.md');
  assert.equal(r.topicoOverlaps.length, 0, 'nomes parecidos NÃO podem casar — mede entidade, não nome');
});

test('ANTI-ADR: dois docs que só compartilham 0093 NÃO colidem', () => {
  const corpus = buildCorpus({ 'produto/topicos/calculo-total-fatura.md': DOC_CALCULO });
  const signals = signalsFromDoc(DOC_SO_ADR, corpus.catalogIndex.knownTables); // compartilha adr 0093, tabela diferente
  const r = findOwners(signals, corpus, 'jana/topicos/outro-tema-qualquer.md');
  assert.equal(r.topicoOverlaps.length, 0, 'ADR transversal não pode ser sinal de tema');
});

test('SELF-EXCLUDE: o próprio doc não se reporta como dono de si mesmo', () => {
  const corpus = buildCorpus({ 'produto/topicos/calculo-total-fatura.md': DOC_CALCULO });
  const signals = signalsFromDoc(DOC_CALCULO, corpus.catalogIndex.knownTables);
  const r = findOwners(signals, corpus, 'produto/topicos/calculo-total-fatura.md');
  assert.equal(r.topicoOverlaps.length, 0, 'não pode casar consigo mesmo');
});

test('PASSA-LIMPO: tema com entidade inexistente → 0 donos (não inventa)', () => {
  const corpus = buildCorpus({ 'produto/topicos/calculo-total-fatura.md': DOC_CALCULO });
  const novo = `---
id: coisa-nova
module: NadaAVer
title: "Assunto genuinamente novo"
kind: capacidade
status: rascunho
updated_at: "2026-07-21"
anchors:
  tables:
    - zzz_tabela_que_nao_existe
---`;
  const signals = signalsFromDoc(novo, corpus.catalogIndex.knownTables);
  const r = findOwners(signals, corpus, 'nadaaver/topicos/coisa-nova.md');
  assert.equal(r.topicoOverlaps.length, 0);
  assert.equal(r.catalogOwners.length, 0);
});

test('CATALOG-OWNER: tabela com dono no catálogo aponta o módulo', () => {
  const corpus = buildCorpus({});
  const signals = signalsFromDoc(DOC_IMPOSTO, corpus.catalogIndex.knownTables); // tax_rates
  const r = findOwners(signals, corpus, null);
  assert.equal(r.catalogOwners.length, 1);
  assert.equal(r.catalogOwners[0].owner, 'Produto');
  assert.ok(r.catalogOwners[0].shared.includes('tables:tax_rates'));
});

test('renderAdvisory: overlap gera aviso; tema novo gera ✅', () => {
  const corpus = buildCorpus({ 'produto/topicos/calculo-total-fatura.md': DOC_CALCULO });
  const rOverlap = findOwners(signalsFromDoc(DOC_IMPOSTO, corpus.catalogIndex.knownTables), corpus, 'x.md');
  assert.match(renderAdvisory(rOverlap), /SOBREPÕE/);
  const rNovo = findOwners(signalsFromDoc('---\nmodule: X\ntitle: t\nanchors:\n  tables:\n    - zzz_novo\n---', new Set()), corpus, 'y.md');
  assert.match(renderAdvisory(rNovo), /tema NOVO/);
});

test('robustez: doc vazio / sem anchors não quebra', () => {
  const corpus = buildCorpus({});
  const s = signalsFromDoc('', new Set());
  const r = findOwners(s, corpus, null);
  assert.equal(r.hasSignals, false);
  assert.match(renderAdvisory(r), /não declara entidade/);
});

// ── PROVA CONTRA O CORPUS REAL (DoD: 2 casos reais) ───────────────────────────
test('REAL-A (tem dono): {tables:[tax_rates]} aponta o tópico calculo-total-fatura real', () => {
  const corpus = loadCorpus(REPO_ROOT); // catalog.json + topicos reais do repo
  const signals = { module: 'Fiscal', keys: new Set([entityKey('tables', 'tax_rates')]) };
  const r = findOwners(signals, corpus, null);
  const hit = r.topicoOverlaps.find((t) => /calculo-total-fatura\.md$/.test(t.path));
  assert.ok(hit, `esperava apontar calculo-total-fatura.md real; overlaps=${JSON.stringify(r.topicoOverlaps.map((x) => x.path))}`);
});

test('REAL-B (tema novo): entidade inexistente passa limpo contra o corpus real', () => {
  const corpus = loadCorpus(REPO_ROOT);
  const signals = { module: 'InexistenteXYZ', keys: new Set([entityKey('tables', 'zzz_tabela_fantasma_9999')]) };
  const r = findOwners(signals, corpus, null);
  assert.equal(r.topicoOverlaps.length, 0);
  assert.equal(r.catalogOwners.length, 0);
});
