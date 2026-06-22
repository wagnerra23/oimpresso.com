#!/usr/bin/env node
// @ts-check
/**
 * dup-detector.test.mjs — controle-negativo da L3 anti-duplicação (sem rede).
 * Fixtures-armadilha: overlap de arquivo exato vs mesma-pasta-arquivo-diferente,
 * excluído, ack presente/ausente, próprio PR. Roda no governance-script-tests.yml.
 */
import assert from 'node:assert/strict';
import { isHot, hasAck, hotOverlap, evaluate } from './dup-detector.mjs';

const HOT = ['scripts/governance/', '.github/workflows/'];
const EXC = ['scripts/governance/gates-registry.json'];
const tests = [];
const t = (n, f) => tests.push([n, f]);

t('isHot: sob prefixo', () => assert.equal(isHot('scripts/governance/x.mjs', HOT, EXC), true));
t('isHot: fora de hot', () => assert.equal(isHot('resources/js/App.tsx', HOT, EXC), false));
t('isHot: excluído não conta', () => assert.equal(isHot('scripts/governance/gates-registry.json', HOT, EXC), false));

t('hasAck: presente', () => assert.equal(hasAck('blah\nDedup-ack: #123 não é dup\nfoo'), true));
t('hasAck: ausente', () => assert.equal(hasAck('corpo sem marcador'), false));
t('hasAck: marcador vazio não vale', () => assert.equal(hasAck('Dedup-ack:'), false));

t('hotOverlap: MESMO arquivo hot → colide', () =>
  assert.deepEqual(hotOverlap(['scripts/governance/a.mjs', 'b.tsx'], ['scripts/governance/a.mjs'], HOT, EXC), ['scripts/governance/a.mjs']));
t('hotOverlap: arquivos DIFERENTES mesma pasta → vazio (não é dup)', () =>
  assert.deepEqual(hotOverlap(['scripts/governance/a.mjs'], ['scripts/governance/b.mjs'], HOT, EXC), []));
t('hotOverlap: arquivo excluído não conta', () =>
  assert.deepEqual(hotOverlap(['scripts/governance/gates-registry.json'], ['scripts/governance/gates-registry.json'], HOT, EXC), []));
t('hotOverlap: overlap fora de hot-path não conta', () =>
  assert.deepEqual(hotOverlap(['resources/js/App.tsx'], ['resources/js/App.tsx'], HOT, EXC), []));

t('evaluate: colisão SEM ack → blocked', () => {
  const r = evaluate({ number: 1, body: '', files: ['scripts/governance/a.mjs'] }, [{ number: 2, title: 'outro', files: ['scripts/governance/a.mjs'] }], HOT, EXC);
  assert.equal(r.collisions.length, 1); assert.equal(r.blocked, true);
});
t('evaluate: colisão COM ack → NÃO blocked', () => {
  const r = evaluate({ number: 1, body: 'Dedup-ack: #2 é o canônico', files: ['scripts/governance/a.mjs'] }, [{ number: 2, title: 'o', files: ['scripts/governance/a.mjs'] }], HOT, EXC);
  assert.equal(r.blocked, false);
});
t('evaluate: sem overlap → NÃO blocked', () => {
  const r = evaluate({ number: 1, body: '', files: ['scripts/governance/a.mjs'] }, [{ number: 2, title: 'o', files: ['scripts/governance/b.mjs'] }], HOT, EXC);
  assert.equal(r.collisions.length, 0); assert.equal(r.blocked, false);
});
t('evaluate: ignora o PRÓPRIO PR (mesmo number)', () => {
  const r = evaluate({ number: 1, body: '', files: ['scripts/governance/a.mjs'] }, [{ number: 1, title: 'self', files: ['scripts/governance/a.mjs'] }], HOT, EXC);
  assert.equal(r.collisions.length, 0);
});

let pass = 0, fail = 0;
for (const [n, f] of tests) { try { f(); pass++; } catch (e) { fail++; console.error(`✗ ${n}\n  ${e.message}`); } }
console.log(`${fail ? '✗' : '✓'} dup-detector.test.mjs — ${pass}/${tests.length}${fail ? `, ${fail} FALHARAM` : ''}`);
process.exit(fail ? 1 : 0);
