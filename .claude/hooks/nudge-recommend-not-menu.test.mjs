#!/usr/bin/env node
// Teste do PORTE cross-plataforma nudge-recommend-not-menu.mjs (ex-.ps1). Deriva do CONTRATO
// (R13 feedback-recomendar-nao-menu: cálculo técnico CRAVA recomendação), NÃO do output do .ps1.
// Advisory: SEMPRE exit 0 — o teste prova o CLASSIFICADOR shouldNudge + o leitor de transcript.
//
// Rodar: node .claude/hooks/nudge-recommend-not-menu.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { shouldNudge, lastAssistantText, NUDGE } from './nudge-recommend-not-menu.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'nudge-recommend-not-menu.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── shouldNudge (puro) ───────────────────────────────────────────────────────────
// DISPARA: menu numerado + pergunta de escolha, SEM recomendação.
check('NUDGE: menu + "qual prefere?" sem recomendação', shouldNudge('Opções:\n1. Fazer X\n2. Fazer Y\nQual você prefere?') === true);
check('NUDGE: bullets + "qual é melhor" sem recomendação', shouldNudge('- caminho A\n- caminho B\nQual é a melhor opção?') === true);
// NÃO dispara: tem recomendação cravada.
check('SILÊNCIO: menu mas com "recomendo"', shouldNudge('1. A\n2. B\nQual prefere?\nRecomendo a A porque é mais barata.') === false);
check('SILÊNCIO: menu mas "minha recomendação"', shouldNudge('1. A\n2. B\nQual deles?\nMinha recomendação: B.') === false);
// NÃO dispara: falta menu OU falta pergunta de escolha.
check('SILÊNCIO: pergunta de escolha sem menu', shouldNudge('Qual você prefere?') === false);
check('SILÊNCIO: menu sem pergunta de escolha', shouldNudge('Fiz assim:\n1. passo um\n2. passo dois\nPronto.') === false);
check('SILÊNCIO: texto vazio', shouldNudge('') === false);

// ── lastAssistantText (transcript JSONL) ─────────────────────────────────────────
const tmp = mkdtempSync(join(tmpdir(), 'recnudge-'));
const tp = join(tmp, 'transcript.jsonl');
writeFileSync(tp, [
  JSON.stringify({ type: 'user', message: { content: [{ type: 'text', text: 'oi' }] } }),
  JSON.stringify({ type: 'assistant', message: { content: [{ type: 'text', text: 'PRIMEIRA resposta' }] } }),
  JSON.stringify({ type: 'assistant', message: { content: [{ type: 'text', text: 'ULTIMA resposta do assistant' }] } }),
  JSON.stringify({ type: 'user', message: { content: [{ type: 'text', text: 'depois' }] } }),
].join('\n'));
check('lastAssistantText: pega a ÚLTIMA msg assistant (ignora user posterior)', lastAssistantText(tp) === 'ULTIMA resposta do assistant');
check('lastAssistantText: arquivo inexistente → ""', lastAssistantText(join(tmp, 'nao-existe.jsonl')) === '');
check('lastAssistantText: path vazio → ""', lastAssistantText('') === '');

// ── E2E: advisory SEMPRE exit 0 (nudge no stdout quando classifica) ──────────────
function runHook(input) {
  return spawnSync(process.execPath, [HOOK], { input: JSON.stringify(input), encoding: 'utf8' });
}
const tpNudge = join(tmp, 'nudge.jsonl');
writeFileSync(tpNudge, JSON.stringify({ type: 'assistant', message: { content: [{ type: 'text', text: 'Opções:\n1. A\n2. B\nQual você prefere?' }] } }));
const fired = runHook({ transcript_path: tpNudge });
check('E2E: menu-sem-recomendação → exit 0 + nudge no stdout', fired.status === 0 && fired.stdout.includes('[R13]'));
check('E2E: NUDGE const bate com o output', NUDGE.startsWith('[R13]'));
const tpQuiet = join(tmp, 'quiet.jsonl');
writeFileSync(tpQuiet, JSON.stringify({ type: 'assistant', message: { content: [{ type: 'text', text: 'Recomendo A. Fiz e testei.' }] } }));
check('E2E: com recomendação → exit 0 silencioso', (() => { const r = runHook({ transcript_path: tpQuiet }); return r.status === 0 && !/\[R13\]/.test(r.stdout); })());
check('E2E: transcript ausente → exit 0 silencioso', runHook({ transcript_path: join(tmp, 'x.jsonl') }).status === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '', encoding: 'utf8' }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '{lixo', encoding: 'utf8' }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs classifica menu-sem-recomendação, avisa no stdout, NUNCA bloqueia; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
