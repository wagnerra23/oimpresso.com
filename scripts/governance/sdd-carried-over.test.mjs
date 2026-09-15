#!/usr/bin/env node
// Meta-teste de preservarSeNaoLido — "não consegui medir" NÃO é estado do objeto medido
// (§5 proibicoes 2026-07-29). Importa de sdd-scorecard.mjs (guard isMain garante que o
// import NÃO dispara o scorecard inteiro) e prova os 4 lados + o contrato com o ratchet:
//   A) leitura OK                      → devolve a medição de agora (não preserva nada)
//   B) leitura falha + fato anterior    → carried_over, value/detail PRESERVADOS
//   C) leitura falha + anterior sem fato → notYet; anterior carried_over → RE-carrega
//   D) leitura falha + sem anterior      → devolve o notYet (1º run, comportamento inalterado)
//   E) CONTRATO: carried_over !== 'measured' — é disto que depende o P14 fail-closed do
//      ratchet(); se alguém "melhorar" pra 'measured', a métrica ARMADA cuja fonte sumiu
//      volta a passar em silêncio e o conserto vira um fail-open novo.
// Uso: node scripts/governance/sdd-carried-over.test.mjs
import { preservarSeNaoLido } from './sdd-scorecard.mjs';

let fails = 0;
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { console.error(`  ✗ ${msg}`); fails++; } };

const medido = {
  status: 'measured', value: 291, unit: 'arquivos-que-falham', direction: 'down', target: 0,
  source: 'governance/nightly-floor.json (transporte CT100→scorecard)',
  detail: { floor_files_hash: '9bddd90ce4ce66a2', runs: [{ sha: 'a1' }, { sha: 'b2' }], intersection_of: 2 },
  stream: 'FV',
};
const naoLido = {
  status: 'not_yet_measured', value: null, direction: 'down', target: 0,
  source: 'governance/nightly-floor.json ainda não publicado pelo write-side CT100 — falta o transporte.',
  baseline_rule: '1ª medição real da fonte, nunca do plano (anti-stale)',
};

// ── A: leu → passa a medição de agora ───────────────────────────────────────
const a = preservarSeNaoLido({ ...medido, value: 305 }, medido);
ok(a.status === 'measured' && a.value === 305, 'leitura OK → devolve a medição de AGORA (305), não o fato velho');

// ── B: não leu + fato anterior → preserva ───────────────────────────────────
const b = preservarSeNaoLido(naoLido, medido);
ok(b.status === 'carried_over', 'não leu + anterior medido → status carried_over');
ok(b.value === 291, 'value preservado (291) — o fato não é destruído');
ok(b.detail?.floor_files_hash === '9bddd90ce4ce66a2', 'detail.floor_files_hash preservado');
ok(b.detail?.runs?.length === 2, 'detail.runs preservado (2 runs)');
ok(b.source === medido.source, 'source preservado — NÃO vira a frase falsa "falta o transporte"');
ok(typeof b.read_failure === 'string' && b.read_failure.includes('nightly-floor'),
  'read_failure declara POR QUE esta execução não leu');
ok(!('stream' in b), 'stream não é carregado do anterior (é recarimbado pelo buildScorecard)');

// ── C: não leu + anterior também não-medido → nada a preservar ──────────────
ok(preservarSeNaoLido(naoLido, naoLido).status === 'not_yet_measured',
  'anterior não-medido → devolve o notYet (não inventa fato)');
const cc = preservarSeNaoLido(naoLido, { status: 'carried_over', value: 9, carried_since: 'origem-X' });
ok(cc.status === 'carried_over' && cc.value === 9,
  'anterior JÁ carried_over → RE-carrega (senão a 2ª rodada local destrói o que a 1ª salvou)');
ok(cc.carried_since === 'origem-X', 'carried_since preserva a origem do fato ao re-carregar');
ok(preservarSeNaoLido(naoLido, { status: 'measured', value: null }).status === 'not_yet_measured',
  'anterior measured mas sem value numérico → notYet (não carrega null como se fosse fato)');

// ── D: sem anterior (1º run) → comportamento de hoje, inalterado ────────────
ok(preservarSeNaoLido(naoLido, undefined).status === 'not_yet_measured', 'sem anterior → notYet (1º run)');
ok(preservarSeNaoLido(naoLido, {}).status === 'not_yet_measured', 'anterior vazio → notYet');

// ── E: contrato com ratchet() — P14 fail-closed depende disto ───────────────
ok(b.status !== 'measured',
  'CONTRATO P14: carried_over !== "measured" → ratchet segue tratando como não-medida (ARMADA = RED)');

console.log(fails === 0 ? '\n  carried_over (§5 2026-07-29 — não-medição não é estado): OK\n'
                        : `\n  carried_over: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
