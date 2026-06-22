#!/usr/bin/env node
// @ts-check
/**
 * outcome-metrics.test.mjs — controle-negativo do medidor de aceitação (sem git/rede).
 *
 * Fixtures-ARMADILHA provando que o RETRABALHO é contado certo:
 *   - entrega sem retrabalho → first-pass (não conta rework).
 *   - retrabalho ANTES da entrega → NÃO conta (cronologia).
 *   - retrabalho DEPOIS da entrega → conta 1.
 *   - revert/fix no git → revert-rate; 2º commit "feat" → rework fraco, não revert.
 *   - shallow clone → marca confiavel:false e número vira PISO (não mente 0%).
 *   - linha não-evento (cabeçalho/tabela) → ignorada.
 *   - mesma linha entrega+RE-LAND → conta entrega E retrabalho.
 *
 * Roda no governance-script-tests.yml. Node puro. Exit 1 em qualquer falha.
 */
import assert from 'node:assert/strict';
import {
  parseSyncLine, parseSyncLog, screensInText, classifySyncEvents,
  syncMetrics, gitMetrics, pct,
} from './outcome-metrics.mjs';

const tests = [];
const t = (name, fn) => tests.push([name, fn]);

// telas sintéticas controladas (não dependem da lista canon real) ───────────────
const SCREENS = [
  { id: 'tela-a', label: 'A', tokens: [/\btela-a\b/i] },
  { id: 'tela-b', label: 'B', tokens: [/\btela-b\b/i] },
];

// ── pct ──
t('pct denominador zero → null (não NaN, não 0)', () => {
  assert.equal(pct(3, 0), null);
});
t('pct arredonda a 1 casa', () => {
  assert.equal(pct(1, 3), 33.3);
  assert.equal(pct(2, 3), 66.7);
});

// ── parseSyncLine ──
t('parseSyncLine evento com ~hora', () => {
  const e = parseSyncLine('2026-05-31 ~00:30 [CL] algo aconteceu');
  assert.deepEqual(e, { date: '2026-05-31', sigla: 'CL', text: 'algo aconteceu' });
});
t('parseSyncLine evento com hora cheia', () => {
  const e = parseSyncLine('2026-05-09 14:00 [W] add request');
  assert.equal(e.date, '2026-05-09');
  assert.equal(e.sigla, 'W');
});
t('parseSyncLine sigla composta [W2]/[W/CL]', () => {
  assert.equal(parseSyncLine('2026-06-01 ~01:33 [W2] merged').sigla, 'W2');
  assert.equal(parseSyncLine('2026-05-31 ~00:45 [W/CL] shift').sigla, 'W/CL');
});
t('parseSyncLine ARMADILHA: linha de tabela markdown → null', () => {
  assert.equal(parseSyncLine('| Evento | Sigla | Linha esperada |'), null);
});
t('parseSyncLine ARMADILHA: cabeçalho/comentário → null', () => {
  assert.equal(parseSyncLine('> Cada linha = 1 evento.'), null);
  assert.equal(parseSyncLine('## Eventos a registrar'), null);
});

// ── parseSyncLog (descarta não-eventos) ──
t('parseSyncLog só pega linhas-evento', () => {
  const txt = [
    '# SYNC_LOG.md',
    '> imutável',
    '2026-05-09 14:00 [CL] criou',
    '| tabela |',
    '2026-05-10 10:00 [W] aprovou',
  ].join('\n');
  const evs = parseSyncLog(txt);
  assert.equal(evs.length, 2);
});

// ── screensInText ──
t('screensInText casa token', () => {
  assert.deepEqual(screensInText('mexi na tela-a hoje', SCREENS), ['tela-a']);
});
t('screensInText sem match → []', () => {
  assert.deepEqual(screensInText('nada relevante', SCREENS), []);
});

// ── classifySyncEvents: o CORAÇÃO (rework contado certo) ──
t('ARMADILHA: retrabalho ANTES da entrega NÃO conta', () => {
  const evs = [
    { date: '2026-05-01', sigla: 'CL', text: 'tela-a tem bug regress (ainda em F1)' },
    { date: '2026-05-05', sigla: 'CL', text: 'tela-a MERGED entregue' },
  ];
  const cls = classifySyncEvents(evs, SCREENS);
  const a = cls.get('tela-a');
  assert.equal(a.delivered, true);
  assert.equal(a.deliveryDate, '2026-05-05');
  assert.equal(a.reworkEvents.length, 0, 'rework pré-entrega não pode contar');
});
t('retrabalho DEPOIS da entrega conta', () => {
  const evs = [
    { date: '2026-05-05', sigla: 'CL', text: 'tela-a MERGED entregue' },
    { date: '2026-05-08', sigla: 'CL', text: 'tela-a INCIDENTE 500 hotfix' },
  ];
  const a = classifySyncEvents(evs, SCREENS).get('tela-a');
  assert.equal(a.reworkEvents.length, 1);
});
t('entrega sem retrabalho → first-pass (0 rework)', () => {
  const evs = [{ date: '2026-05-05', sigla: 'CL', text: 'tela-b MERGED entregue limpo' }];
  const b = classifySyncEvents(evs, SCREENS).get('tela-b');
  assert.equal(b.delivered, true);
  assert.equal(b.reworkEvents.length, 0);
});
t('ARMADILHA: mesma linha entrega + RE-LAND conta entrega E rework', () => {
  const evs = [{ date: '2026-05-05', sigla: 'CL', text: 'tela-a RE-LAND MERGED após recovery' }];
  const a = classifySyncEvents(evs, SCREENS).get('tela-a');
  assert.equal(a.delivered, true);
  assert.equal(a.reworkEvents.length, 1, 'RE-LAND na entrega = retrabalho embutido');
});
t('ARMADILHA: 2 retrabalhos depois da entrega contam 2', () => {
  const evs = [
    { date: '2026-05-05', sigla: 'CL', text: 'tela-a MERGED' },
    { date: '2026-05-06', sigla: 'CL', text: 'tela-a pass-2 conserto' },
    { date: '2026-05-07', sigla: 'CL', text: 'tela-a regress no slice' },
  ];
  const a = classifySyncEvents(evs, SCREENS).get('tela-a');
  assert.equal(a.reworkEvents.length, 2);
});
t('ARMADILHA: evento de retrabalho de OUTRA tela não vaza', () => {
  const evs = [
    { date: '2026-05-05', sigla: 'CL', text: 'tela-a MERGED' },
    { date: '2026-05-06', sigla: 'CL', text: 'tela-b hotfix incidente' },
  ];
  const cls = classifySyncEvents(evs, SCREENS);
  assert.equal(cls.get('tela-a').reworkEvents.length, 0);
});

// ── syncMetrics agregado ──
t('syncMetrics calcula rework_rate e first_pass_rate', () => {
  const evs = [
    { date: '2026-05-05', sigla: 'CL', text: 'tela-a MERGED' },
    { date: '2026-05-08', sigla: 'CL', text: 'tela-a hotfix incidente' },
    { date: '2026-05-09', sigla: 'CL', text: 'tela-b MERGED limpo' },
  ];
  const m = syncMetrics(evs, SCREENS);
  assert.equal(m.telas_entregues, 2);
  assert.equal(m.telas_retrabalhadas, 1);
  assert.equal(m.rework_rate, 50);
  assert.equal(m.first_pass_rate, 50);
  assert.equal(m.confianca, 'proxy');
});
t('syncMetrics zero entregas → rates null (honesto, não 0)', () => {
  const m = syncMetrics([{ date: '2026-05-05', sigla: 'CL', text: 'nada casado' }], SCREENS);
  assert.equal(m.telas_entregues, 0);
  assert.equal(m.rework_rate, null);
  assert.equal(m.first_pass_rate, null);
});

// ── gitMetrics ──
t('gitMetrics 1 commit = first-pass (sem rework)', () => {
  const recs = [{ file: 'X.tsx', commits: [{ sha: 'a', subject: 'feat: X', date: '2026-05-01' }] }];
  const m = gitMetrics(recs);
  assert.equal(m.telas_entregues, 1);
  assert.equal(m.telas_retrabalhadas, 0);
  assert.equal(m.first_pass_rate, 100);
});
t('gitMetrics commit fix subsequente → revert-rate conta', () => {
  const recs = [{ file: 'X.tsx', commits: [
    { sha: 'a', subject: 'feat: X', date: '2026-05-01' },
    { sha: 'b', subject: 'fix(X): corrige regressão', date: '2026-05-03' },
  ] }];
  const m = gitMetrics(recs);
  assert.equal(m.telas_com_fix_revert, 1);
  assert.equal(m.revert_rate, 100);
  assert.equal(m.rework_rate, 100);
});
t('ARMADILHA: 2º commit feat (evolução, não fix) → rework fraco mas NÃO revert', () => {
  const recs = [{ file: 'X.tsx', commits: [
    { sha: 'a', subject: 'feat: X v1', date: '2026-05-01' },
    { sha: 'b', subject: 'feat: X adiciona aba', date: '2026-05-10' },
  ] }];
  const m = gitMetrics(recs);
  assert.equal(m.telas_com_fix_revert, 0, '2º feat não é fix/revert');
  assert.equal(m.revert_rate, 0);
  assert.equal(m.rework_rate, 100, '2º commit ainda conta rework fraco');
});
t('gitMetrics shallow → confiavel:false + aviso (não mente número)', () => {
  const recs = [{ file: 'X.tsx', commits: [{ sha: 'a', subject: 'feat: X', date: '2026-05-01' }] }];
  const m = gitMetrics(recs, { shallow: true });
  assert.equal(m.shallow, true);
  assert.equal(m.confiavel, false);
  assert.match(m.aviso_shallow, /RASO|shallow/i);
});
t('gitMetrics records vazios → rates null (não 0)', () => {
  const m = gitMetrics([]);
  assert.equal(m.telas_entregues, 0);
  assert.equal(m.rework_rate, null);
});
t('gitMetrics ignora records sem commits', () => {
  const m = gitMetrics([{ file: 'X.tsx', commits: [] }]);
  assert.equal(m.telas_entregues, 0);
});

// ── runner ──
let pass = 0, fail = 0;
for (const [name, fn] of tests) {
  try { fn(); pass++; } catch (e) { fail++; console.error(`✗ ${name}\n  ${e.message}`); }
}
console.log(`${fail ? '✗' : '✓'} outcome-metrics.test.mjs — ${pass}/${tests.length} passaram${fail ? `, ${fail} FALHARAM` : ''}`);
process.exit(fail ? 1 : 0);
