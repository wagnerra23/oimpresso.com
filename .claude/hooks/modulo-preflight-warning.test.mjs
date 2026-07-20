#!/usr/bin/env node
// Teste do PORTE modulo-preflight-warning.mjs (ex-.ps1). Deriva do CONTRATO (FASE 1
// PRÉ-FLIGHT Tier 0: leu o briefing do módulo antes de Editar?), NÃO do .ps1. Advisory: exit 0.
// Rodar: node .claude/hooks/modulo-preflight-warning.test.mjs

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { toFwd, extractModule, buildReadPatterns, hasReadEvidence, projectKey, warningMessage } from './modulo-preflight-warning.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'modulo-preflight-warning.mjs');
const BS = String.fromCharCode(92);
let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };

// ── toFwd + extractModule (backslash-safe) ───────────────────────────────────────
check('toFwd normaliza backslash', toFwd('Modules' + BS + 'Vestuario' + BS + 'x.php') === 'Modules/Vestuario/x.php');
check('extractModule: forward slash', extractModule('Modules/Jana/Services/Foo.php') === 'Jana');
check('extractModule: backslash Windows', extractModule('Modules' + BS + 'Vestuario' + BS + 'x.php') === 'Vestuario');
check('extractModule: fora de Modules → null', extractModule('app/Foo.php') === null);
check('extractModule: vazio → null', extractModule('') === null);

// ── patterns + evidência (puros) ─────────────────────────────────────────────────
check('buildReadPatterns cita requisitos/<Mod>', buildReadPatterns('Jana').some((p) => p.includes('memory/requisitos/Jana/')));
check('hasReadEvidence: leu SPEC do modulo → true', hasReadEvidence('...Read memory/requisitos/Jana/SPEC.md...', 'Jana') === true);
check('hasReadEvidence: leu charter → true', hasReadEvidence('abri o jana charter da tela', 'Jana') === true);
check('hasReadEvidence: nada do modulo → false', hasReadEvidence('mexi em app/Foo.php sem ler nada', 'Jana') === false);
check('hasReadEvidence: content vazio → false', hasReadEvidence('', 'Jana') === false);

// ── projectKey (backslash + ':' → '-') ───────────────────────────────────────────
check('projectKey sanitiza \\ : . / (chave real do Claude Code)', projectKey('D:' + BS + 'oimpresso.com') === 'D--oimpresso-com');

check('warningMessage cita PRÉ-FLIGHT + o modulo', /PRÉ-FLIGHT MISSING/.test(warningMessage('Jana')) && /Modules\/Jana\//.test(warningMessage('Jana')));

// ── E2E: advisory exit 0 (avisa quando sem evidência) ────────────────────────────
const tmp = mkdtempSync(join(tmpdir(), 'mpw-'));
const tpVazio = join(tmp, 'vazio.jsonl');
writeFileSync(tpVazio, JSON.stringify({ type: 'user', message: { content: [{ type: 'text', text: 'oi' }] } }));
function run(input) { return spawnSync(process.execPath, [HOOK], { input: JSON.stringify(input), encoding: 'utf8' }); }
const w = (file_path, transcript_path) => ({ tool_name: 'Write', tool_input: { file_path }, transcript_path });

const warned = run(w('Modules/Jana/Services/Foo.php', tpVazio));
check('E2E: Edit em Modules sem evidência → exit 0 + aviso stderr', warned.status === 0 && /PRÉ-FLIGHT MISSING/.test(warned.stderr));
const tpLeu = join(tmp, 'leu.jsonl');
writeFileSync(tpLeu, JSON.stringify({ type: 'assistant', message: { content: [{ type: 'text', text: 'li memory/requisitos/Jana/SPEC.md' }] } }));
check('E2E: leu o briefing → exit 0 silencioso', (() => { const r = run(w('Modules/Jana/Services/Foo.php', tpLeu)); return r.status === 0 && !/PRÉ-FLIGHT MISSING/.test(r.stderr); })());
check('E2E: arquivo fora de Modules → exit 0 silencioso', (() => { const r = run(w('app/Foo.php', tpVazio)); return r.status === 0 && !r.stderr.trim(); })());
check('E2E: Read (não Write) → exit 0', run({ tool_name: 'Read', tool_input: { file_path: 'Modules/Jana/x.php' }, transcript_path: tpVazio }).status === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '', encoding: 'utf8' }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '{lixo', encoding: 'utf8' }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs detecta Edit em Modules sem briefing, avisa, advisory exit 0; fail-open provado.');
process.exit(fails ? 1 : 0);
