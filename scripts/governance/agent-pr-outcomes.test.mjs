#!/usr/bin/env node
// @ts-check
// SELF-TEST de agent-pr-outcomes.mjs — prova que as métricas MORDEM (CFR conta o
// hotfix ≤48h que cita #N) e LIBERAM (hotfix tardio / tipo-errado / sem-#N NÃO conta).
// Hermético: fixture de PRs em memória, nowIso fixo — zero gh/rede/git.
// Rodar: node scripts/governance/agent-pr-outcomes.test.mjs — exit 0 = passa.

import {
  buildReport, isAgentPR, terminalState, referencesPR, median, percentile,
  timeToMerge, acceptReject, changeFailure, failedPRNumbers, DEFAULT_MARKER,
} from './agent-pr-outcomes.mjs';

let fails = 0;
const check = (name, cond) => { console.log(`${cond ? '[OK]  ' : '[FAIL]'} ${name}`); if (!cond) fails++; };

// ── helpers puros ──────────────────────────────────────────────────────────────
check('referencesPR("#100",100) casa', referencesPR('fix #100 aqui', 100) === true);
check('referencesPR fronteira: "#1000" NÃO casa 100', referencesPR('ref #1000', 100) === false);
check('median [2,3,4,4,4] = 4', median([2, 3, 4, 4, 4]) === 4);
check('median par [2,4] = 3', median([2, 4]) === 3);
check('percentile p90 de 5 itens = último quintil', percentile([2, 3, 4, 4, 9], 90) === 9);
check('isAgentPR por marcador [CC]', isAgentPR({ title: 'feat: x [CC]' }) === true);
check('isAgentPR por autor bot', isAgentPR({ title: 'x', author: { login: 'github-actions[bot]' } }) === true);
check('isAgentPR NÃO casa PR humano', isAgentPR({ title: 'feat: x', author: { login: 'wagnerra23' } }) === false);
check('terminalState merged/rejected/open',
  terminalState({ mergedAt: '2026-07-01T00:00:00Z' }) === 'merged' &&
  terminalState({ closedAt: '2026-07-01T00:00:00Z', state: 'CLOSED' }) === 'rejected' &&
  terminalState({ state: 'OPEN' }) === 'open');

// ── fixture de PRs (todos em julho; nowIso 2026-07-09, janela 30d cobre todos) ──
const iso = (s) => `2026-07-${s}Z`;
const PRS = [
  // #100 [CC] mergeado — vai ser consertado por #101 em 10h (CFR HIT)
  { number: 100, title: 'feat: base [CC]', body: '', author: { login: 'x' }, createdAt: iso('01T00:00:00'), mergedAt: iso('01T02:00:00'), closedAt: iso('01T02:00:00'), state: 'MERGED' },
  // #101 [CC] fix que cita #100, mergeado 10h depois (dentro de 48h, tipo fix) → é o hotfix
  { number: 101, title: 'fix(x): corrige #100 [CC]', body: 'conserta o merge anterior', author: { login: 'x' }, createdAt: iso('01T08:00:00'), mergedAt: iso('01T12:00:00'), closedAt: iso('01T12:00:00'), state: 'MERGED' },
  // #102 [CC] mergeado sem follow-up → sem falha
  { number: 102, title: 'feat: y [CC]', body: '', author: { login: 'x' }, createdAt: iso('02T00:00:00'), mergedAt: iso('02T04:00:00'), closedAt: iso('02T04:00:00'), state: 'MERGED' },
  // #103 [CC] fix que cita #100 mas TARDE (>48h) → NÃO deve contar como hotfix de #100
  { number: 103, title: 'fix(x): tardio #100 [CC]', body: '', author: { login: 'x' }, createdAt: iso('04T20:00:00'), mergedAt: iso('05T00:00:00'), closedAt: iso('05T00:00:00'), state: 'MERGED' },
  // #104 [CC] REJEITADO (fechado sem merge)
  { number: 104, title: 'feat: z [CC]', body: '', author: { login: 'x' }, createdAt: iso('03T00:00:00'), mergedAt: null, closedAt: iso('03T06:00:00'), state: 'CLOSED' },
  // #105 humano mergeado — fora das métricas do agente (mas no universo de hotfixes)
  { number: 105, title: 'feat: humano', body: '', author: { login: 'wagnerra23' }, createdAt: iso('02T00:00:00'), mergedAt: iso('02T05:00:00'), closedAt: iso('02T05:00:00'), state: 'MERGED' },
  // #106 [CC] cita #100 dentro de 48h MAS é feat (tipo errado) → NÃO é hotfix
  { number: 106, title: 'feat: melhora baseada em #100 [CC]', body: '', author: { login: 'x' }, createdAt: iso('01T20:00:00'), mergedAt: iso('01T23:00:00'), closedAt: iso('01T23:00:00'), state: 'MERGED' },
];

const r = buildReport({ prs: PRS, nowIso: '2026-07-09', days: 30, marker: DEFAULT_MARKER });

// ── contagens de agente ──────────────────────────────────────────────────────
check('6 PRs terminais do agente (#105 humano excluído)', r.agent.total_terminais === 6);
check('5 mergeados do agente', r.agent.mergeados === 5);

// ── accept-rate ──────────────────────────────────────────────────────────────
check('accept: 5 merged / 1 rejected', r.metrics.accept.merged === 5 && r.metrics.accept.rejected === 1);
check('accept_rate = 83.3%', r.metrics.accept.accept_rate === 83.3);

// ── time-to-merge (horas: #100=2 #101=4 #102=4 #103=4 #106=3 → mediana 4) ─────
check('time-to-merge n=5', r.metrics.time_to_merge.count === 5);
check('time-to-merge mediana = 4h', r.metrics.time_to_merge.median_h === 4);

// ── change-failure: só #100 tem hotfix válido (#101) ──────────────────────────
const cf = r.metrics.change_failure;
check('CFR: 1 falha de 5 mergeados = 20%', cf.failures === 1 && cf.merged_count === 5 && cf.cfr === 20);
check('CFR hit = #100 consertado por #101 em 10h', cf.hits.length === 1 && cf.hits[0].pr === 100 && cf.hits[0].hotfix === 101 && cf.hits[0].horas === 10);

// ── LIBERA: remover o hotfix válido #101 → CFR cai a 0 (prova que #103 tardio e ──
//    #106 tipo-errado NÃO contam) ───────────────────────────────────────────────
const semHotfix = PRS.filter((p) => p.number !== 101);
const r2 = buildReport({ prs: semHotfix, nowIso: '2026-07-09', days: 30 });
check('sem #101: CFR = 0% (tardio #103 e feat #106 não mordem)', r2.metrics.change_failure.cfr === 0);

// ── janela: days=1 desde 2026-07-09 exclui tudo de julho-início → 0 terminais ──
const r3 = buildReport({ prs: PRS, nowIso: '2026-07-09', days: 1 });
check('janela 1d exclui PRs antigos (0 terminais)', r3.agent.total_terminais === 0);

// ── funções unitárias diretas (redundância de defesa) ─────────────────────────
check('acceptReject direto', acceptReject([{ mergedAt: '2026-01-01T00:00:00Z' }, { state: 'CLOSED', closedAt: '2026-01-01T00:00:00Z' }]).accept_rate === 50);
check('timeToMerge ignora mergedAt<createdAt (dado torto)',
  timeToMerge([{ createdAt: '2026-01-02T00:00:00Z', mergedAt: '2026-01-01T00:00:00Z' }]).count === 0);
check('changeFailure vazio → cfr null', changeFailure([], []).cfr === null);

// ── failedPRNumbers: a definição de "não sobreviveu" que o custo (agent-cost-per-pr) consome ──
check('failedPRNumbers extrai os #N dos hits do CFR real (#100)', (() => { const s = failedPRNumbers(cf); return s.has(100) && !s.has(102) && s.size === 1; })());
check('failedPRNumbers aceita o array .hits direto', failedPRNumbers([{ pr: 7 }, { pr: 9 }]).has(9) === true);
check('failedPRNumbers de vazio/null → Set vazio (não quebra)', failedPRNumbers(null).size === 0 && failedPRNumbers({ hits: [] }).size === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — CFR morde (hotfix ≤48h + tipo + #N) e libera (tardio/feat/sem-#N); accept-rate + time-to-merge conferem; failedPRNumbers dá o conjunto "não sobreviveu" pro custo.');
process.exit(fails ? 1 : 0);
