#!/usr/bin/env node
// Teste do PORTE cross-plataforma nudge-diagnosis-without-evidence.mjs (ex-.ps1). Deriva do
// CONTRATO (R1 / claim sem evidência: mostre grep/log/SQL antes de cravar causa), NÃO do .ps1.
// Advisory: SEMPRE exit 0 — prova o CLASSIFICADOR shouldNudge + leitor de transcript.
//
// Rodar: node .claude/hooks/nudge-diagnosis-without-evidence.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { shouldNudge, lastAssistantText, NUDGE } from './nudge-diagnosis-without-evidence.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'nudge-diagnosis-without-evidence.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── shouldNudge (puro) ───────────────────────────────────────────────────────────
// DISPARA: afirma causa/diagnóstico SEM marcador de evidência.
check('NUDGE: "a causa é X" sem evidência', shouldNudge('Investiguei e a causa é o cache stale do autoload.') === true);
check('NUDGE: "o problema foi Y" sem evidência', shouldNudge('O problema foi a migration não rodada.') === true);
check('NUDGE: "root cause" sem evidência', shouldNudge('The root cause is a race condition no observer.') === true);
// NÃO dispara: afirma causa MAS mostra evidência.
check('SILÊNCIO: causa + grep', shouldNudge('A causa é o BOM; confirmei com grep no arquivo.') === false);
check('SILÊNCIO: causa + laravel.log', shouldNudge('O problema foi o classmap; laravel.log mostra o erro.') === false);
check('SILÊNCIO: causa + HTTP 500 + curl', shouldNudge('A causa foi o 500; curl retornou HTTP 500 no /login.') === false);
check('SILÊNCIO: causa + "verifiquei"', shouldNudge('A causa é a FK; verifiquei no schema.') === false);
// NÃO dispara: não afirma causa.
check('SILÊNCIO: sem afirmação de causa', shouldNudge('Vou investigar o erro agora.') === false);
check('SILÊNCIO: texto vazio', shouldNudge('') === false);

// ── lastAssistantText ────────────────────────────────────────────────────────────
const tmp = mkdtempSync(join(tmpdir(), 'diagnudge-'));
const tp = join(tmp, 't.jsonl');
writeFileSync(tp, [
  JSON.stringify({ type: 'assistant', message: { content: [{ type: 'text', text: 'antiga' }] } }),
  JSON.stringify({ type: 'assistant', message: { content: [{ type: 'text', text: 'a causa é o cache' }] } }),
].join('\n'));
check('lastAssistantText: última assistant', lastAssistantText(tp) === 'a causa é o cache');
check('lastAssistantText: inexistente → ""', lastAssistantText(join(tmp, 'no.jsonl')) === '');

// ── E2E: advisory SEMPRE exit 0 ──────────────────────────────────────────────────
function runHook(input) { return spawnSync(process.execPath, [HOOK], { input: JSON.stringify(input), encoding: 'utf8' }); }
const tpFire = join(tmp, 'fire.jsonl');
writeFileSync(tpFire, JSON.stringify({ type: 'assistant', message: { content: [{ type: 'text', text: 'A causa é a config errada.' }] } }));
const fired = runHook({ transcript_path: tpFire });
check('E2E: causa-sem-evidência → exit 0 + nudge stdout', fired.status === 0 && fired.stdout.includes('[R1+'));
check('E2E: NUDGE const bate', NUDGE.includes('EVIDENCIA'));
const tpQuiet = join(tmp, 'quiet.jsonl');
writeFileSync(tpQuiet, JSON.stringify({ type: 'assistant', message: { content: [{ type: 'text', text: 'A causa é o BOM; confirmei via grep.' }] } }));
check('E2E: causa + evidência → exit 0 silencioso', (() => { const r = runHook({ transcript_path: tpQuiet }); return r.status === 0 && !/\[R1\+/.test(r.stdout); })());
check('E2E: stdin vazio → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '', encoding: 'utf8' }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '{lixo', encoding: 'utf8' }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs classifica causa-sem-evidência, avisa no stdout, NUNCA bloqueia; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
