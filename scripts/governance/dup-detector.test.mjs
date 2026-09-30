#!/usr/bin/env node
// @ts-check
/**
 * dup-detector.test.mjs — controle-negativo da L3 anti-duplicação (sem rede).
 * Fixtures-armadilha: overlap de arquivo exato vs mesma-pasta-arquivo-diferente,
 * excluído, ack presente/ausente, próprio PR. Roda no governance-script-tests.yml.
 */
import assert from 'node:assert/strict';
import { isHot, hasAck, hotOverlap, evaluate, pathProbe, normPath, FILES_CAP, LIST_LIMIT } from './dup-detector.mjs';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

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

// ── modo --path (§5 2026-09-30, LC-19) ──
// Path FICTÍCIO de propósito: um literal de arquivo real aqui vira "leitor" dele no
// MAQUINAS-INVENTARIO (o maquinas-inventario casa literal de string) e desloca o leitor
// verdadeiro da coluna — quebrou o maquinas-inventario.test em 2026-09-30.
const BASE = 'governance/exemplo-dup-detector-fixture.json';
const PRS = [
  { number: 8225, title: 'de passagem', files: ['a.md', BASE] },
  { number: 8226, title: 'dedicado', files: [BASE] },
  { number: 9000, title: 'outro', files: ['b.md'] },
];
t('pathProbe: lista TODOS os PRs que tocam o arquivo (o de passagem inclusive)', () => {
  const r = pathProbe([BASE], PRS);
  assert.equal(r.verdict, 'tocado');
  assert.deepEqual(r.hits[0].prs.map((p) => p.number), [8225, 8226]);
});
t('pathProbe: arquivo que ninguém toca → livre', () => assert.equal(pathProbe(['c.md'], PRS).verdict, 'livre'));
t('pathProbe: mesma PASTA, arquivo diferente → livre (casa arquivo, não pasta)', () =>
  assert.equal(pathProbe(['governance/outro.json'], PRS).verdict, 'livre'));
t('pathProbe: --self exclui o próprio PR', () =>
  assert.deepEqual(pathProbe(['b.md'], PRS, { self: 9000 }).verdict, 'livre'));
t('pathProbe: normaliza ./ e barra invertida', () => {
  assert.equal(normPath('./' + BASE), BASE);
  assert.equal(normPath(BASE.split('/').join(String.fromCharCode(92))), BASE);
  assert.equal(pathProbe(['./' + BASE], PRS).verdict, 'tocado');
});
t('pathProbe: lista no LIMITE e sem hit → nao-medi (pode estar cortada)', () =>
  assert.equal(pathProbe(['c.md'], PRS, { returned: LIST_LIMIT }).verdict, 'nao-medi'));
t('pathProbe: PR no teto de files e sem hit → nao-medi, nomeando o PR', () => {
  const big = { number: 7777, title: 'grande', files: Array.from({ length: FILES_CAP }, (_, i) => `f${i}.md`) };
  const r = pathProbe(['c.md'], [...PRS, big]);
  assert.equal(r.verdict, 'nao-medi'); assert.deepEqual(r.capped, [7777]);
});
t('pathProbe: hit é evidência positiva mesmo com lista cortada', () =>
  assert.equal(pathProbe([BASE], PRS, { returned: LIST_LIMIT }).verdict, 'tocado'));

// CLI de fora (o chokepoint, não só as funções — §5 2026-07-30)
const SCRIPT = join(dirname(fileURLToPath(import.meta.url)), 'dup-detector.mjs');
const dir = mkdtempSync(join(tmpdir(), 'dupdet-'));
const fx = join(dir, 'open.json');
writeFileSync(fx, JSON.stringify(PRS.map((p) => ({ number: p.number, title: p.title, files: p.files.map((path) => ({ path })) }))));
const cli = (...a) => spawnSync(process.execPath, [SCRIPT, ...a], { encoding: 'utf8' });
t('CLI --path: tocado → exit 1 e cita os dois PRs', () => {
  const r = cli(`--path=${BASE}`, `--fixture=${fx}`);
  assert.equal(r.status, 1); assert.match(r.stdout, /#8225/); assert.match(r.stdout, /#8226/);
});
t('CLI --path: livre → exit 0', () => assert.equal(cli('--path=c.md', `--fixture=${fx}`).status, 0));
t('CLI --path: falha ao listar → exit 2 NÃO MEDI (nunca "livre")', () => {
  const r = cli('--path=c.md', `--fixture=${join(dir, 'nao-existe.json')}`);
  assert.equal(r.status, 2); assert.match(r.stderr, /NÃO MEDI/);
});

let pass = 0, fail = 0;
for (const [n, f] of tests) { try { f(); pass++; } catch (e) { fail++; console.error(`✗ ${n}\n  ${e.message}`); } }
console.log(`${fail ? '✗' : '✓'} dup-detector.test.mjs — ${pass}/${tests.length}${fail ? `, ${fail} FALHARAM` : ''}`);
process.exit(fail ? 1 : 0);
