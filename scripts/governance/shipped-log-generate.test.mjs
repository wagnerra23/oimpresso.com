#!/usr/bin/env node
// @ts-check
/**
 * shipped-log-generate.test.mjs — controle-negativo do gerador (sem rede).
 * Roda no governance-script-tests.yml. Fixtures-armadilha cobrem os gaps que
 * reprovaram a v1: título não-convencional, acento/alias de scope, revert,
 * borda BRT×UTC, e truncação (cross-check). Exit 1 em qualquer falha.
 */
import assert from 'node:assert/strict';
import {
  parseTitle, normScope, isDS, reconcileReverts, groupByArea,
  crossCheck, dayList, inBrtRange, markDeployed,
} from './shipped-log-generate.mjs';

const tests = [];
const t = (name, fn) => tests.push([name, fn]);

// ── markDeployed (G8 — merge ≠ deploy) ──
t('markDeployed: PR antes do deploy → no ar; depois → aguardando', () => {
  const prs = [
    { number: 1, mergedAt: '2026-06-20T10:00:00Z' },
    { number: 2, mergedAt: '2026-06-22T10:00:00Z' },
  ];
  const { onAir, waiting } = markDeployed(prs, '2026-06-21T00:00:00Z');
  assert.equal(prs[0]._deployed, true);
  assert.equal(prs[1]._deployed, false);
  assert.equal(onAir, 1); assert.equal(waiting, 1);
});
t('markDeployed: sem deploy_at → _deployed null, contagem null (degrada)', () => {
  const prs = [{ number: 1, mergedAt: '2026-06-20T10:00:00Z' }];
  const { onAir, waiting } = markDeployed(prs, null);
  assert.equal(prs[0]._deployed, null);
  assert.equal(onAir, null); assert.equal(waiting, null);
});
t('markDeployed: groupByArea propaga deployed pro row', () => {
  const prs = [{ number: 9, title: 'feat(x): y', mergedAt: '2026-06-20T10:00:00Z' }];
  markDeployed(prs, '2026-06-21T00:00:00Z');
  const { sorted } = groupByArea(prs);
  assert.equal(sorted.find(([a]) => a === 'x')[1].meaningful[0].deployed, true);
});

// ── parseTitle / normScope ──
t('parseTitle conventional simples', () => {
  assert.deepEqual(parseTitle('feat(financeiro): nova cobrança'), { type: 'feat', scope: 'financeiro', subject: 'nova cobrança' });
});
t('parseTitle título NÃO-convencional vira outros (não some)', () => {
  const r = parseTitle('PR Onda B: faxina geral');
  assert.equal(r.type, 'outros');
  assert.equal(r.scope, '');
});
t('normScope tira acento (governança → governance via alias)', () => {
  assert.equal(normScope('governança'), 'governance');
});
t('normScope alias caixa-unif → caixa-unificada', () => {
  assert.equal(normScope('caixa-unif'), 'caixa-unificada');
  assert.equal(normScope('caixa'), 'caixa-unificada');
});
t('parseTitle com bang (feat!: breaking)', () => {
  assert.equal(parseTitle('feat(api)!: quebra contrato').type, 'feat');
});

// ── isDS (inclui o falso-positivo conhecido G9) ──
t('isDS por scope', () => assert.equal(isDS('feat(ui): x', 'ui'), true));
t('isDS por título (redesign)', () => assert.equal(isDS('feat(x): redesign fiel ao protótipo', 'x'), true));
t('isDS falso-NEGATIVO esperado: financeiro puro não é DS', () => {
  assert.equal(isDS('feat(financeiro): baixa título', 'financeiro'), false);
});

// ── reconcileReverts ──
t('reconcileReverts casa par #revertido', () => {
  const m = reconcileReverts([
    { number: 2107, title: 'revert: PR2 endereço (#2104) — regressão' },
    { number: 2104, title: 'feat(cliente): endereço na venda' },
  ]);
  assert.equal(m.get(2104), 2107);
  assert.equal(m.size, 1);
});
t('reconcileReverts ignora revert sem #ref', () => {
  assert.equal(reconcileReverts([{ number: 9, title: 'revert: algo sem ref' }]).size, 0);
});

// ── groupByArea ──
t('groupByArea separa produto de ruído e marca DS', () => {
  const prs = [
    { number: 1, title: 'feat(ui): botão', mergedAt: '2026-06-01T10:00:00Z' },
    { number: 2, title: 'docs(x): readme', mergedAt: '2026-06-01T11:00:00Z' },
    { number: 3, title: 'feat(financeiro): baixa', mergedAt: '2026-06-01T12:00:00Z' },
  ];
  const { sorted, dsAll, totalMean, totalNoise } = groupByArea(prs);
  assert.equal(totalMean, 2);
  assert.equal(totalNoise, 1);
  assert.equal(dsAll.length, 1); // só o ui
  const ui = sorted.find(([a]) => a === 'ui');
  assert.equal(ui[1].meaningful.length, 1);
});
t('groupByArea anota revert na linha', () => {
  const { sorted } = groupByArea([{ number: 2104, title: 'feat(cliente): x', mergedAt: '2026-06-02T10:00:00Z' }], new Map([[2104, 2107]]));
  const row = sorted.find(([a]) => a === 'cliente')[1].meaningful[0];
  assert.equal(row.rev, 2107);
});

// ── crossCheck (anti-truncação) ──
t('crossCheck ok quando bate', () => assert.equal(crossCheck(50, 50, false).ok, true));
t('crossCheck FALHA quando diverge', () => assert.equal(crossCheck(49, 50, false).ok, false));
t('crossCheck FALHA quando sub-janela bateu no teto', () => assert.equal(crossCheck(1000, 1000, true).ok, false));
t('crossCheck ok (pulado) quando não há total independente', () => assert.equal(crossCheck(50, null, false).ok, true));

// ── borda BRT × UTC ──
t('inBrtRange inclui noite BRT do último dia (29/jun 01:00 UTC = 28/jun 22:00 BRT)', () => {
  assert.equal(inBrtRange('2026-06-29T01:00:00Z', '2026-05-31', '2026-06-28'), true);
});
t('inBrtRange exclui já-29/jun BRT (29/jun 05:00 UTC = 29/jun 02:00 BRT)', () => {
  assert.equal(inBrtRange('2026-06-29T05:00:00Z', '2026-05-31', '2026-06-28'), false);
});
t('dayList cobre margem ±1 dia', () => {
  const d = dayList('2026-06-01', '2026-06-02');
  assert.deepEqual(d, ['2026-05-31', '2026-06-01', '2026-06-02', '2026-06-03']);
});

// ── runner ──
let pass = 0, fail = 0;
for (const [name, fn] of tests) {
  try { fn(); pass++; } catch (e) { fail++; console.error(`✗ ${name}\n  ${e.message}`); }
}
console.log(`${fail ? '✗' : '✓'} shipped-log-generate.test.mjs — ${pass}/${tests.length} passaram${fail ? `, ${fail} FALHARAM` : ''}`);
process.exit(fail ? 1 : 0);
