#!/usr/bin/env node
// Teste do PORTE cross-plataforma nudge-test-contract-anchor.mjs (ex-.ps1). Deriva do CONTRATO
// (proibicoes §"Ideias descartadas" 2026-06-05: teste ancorado em contrato, não no código), NÃO do .ps1.
// Advisory: SEMPRE exit 0 — prova o CLASSIFICADOR isTestFile + E2E.
//
// Rodar: node .claude/hooks/nudge-test-contract-anchor.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { isTestFile, NUDGE_LINES } from './nudge-test-contract-anchor.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'nudge-test-contract-anchor.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── isTestFile (puro) ────────────────────────────────────────────────────────────
check('isTestFile: *Test.php', isTestFile('tests/Feature/Jana/FooTest.php') === true);
check('isTestFile: backslash Windows', isTestFile('tests\\Feature\\BarTest.php') === true);
check('isTestFile: .php normal NÃO', isTestFile('app/Services/Foo.php') === false);
check('isTestFile: .test.mjs NÃO (é JS, não PHP)', isTestFile('scripts/x.test.mjs') === false);
check('isTestFile: vazio NÃO', isTestFile('') === false);
check('NUDGE_LINES cita ADR e "tautologico"', NUDGE_LINES.join(' ').includes('ADR-XXXX') && NUDGE_LINES.join(' ').includes('tautologico'));

// ── E2E: advisory SEMPRE exit 0 (nudge no stdout só em *Test.php) ────────────────
function runHook(input) { return spawnSync(process.execPath, [HOOK], { input: JSON.stringify(input), encoding: 'utf8' }); }
const w = (tool, file_path) => ({ tool_name: tool, tool_input: { file_path } });
const fired = runHook(w('Write', 'tests/Feature/NovoTest.php'));
check('E2E: Write *Test.php → exit 0 + nudge stdout', fired.status === 0 && fired.stdout.includes('ANCORA DE CONTRATO'));
check('E2E: Edit *Test.php também avisa', runHook(w('Edit', 'tests/Unit/XTest.php')).stdout.includes('ANCORA DE CONTRATO'));
check('E2E: arquivo não-teste → exit 0 silencioso', (() => { const r = runHook(w('Write', 'app/Foo.php')); return r.status === 0 && !r.stdout.trim(); })());
check('E2E: stdin vazio → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '', encoding: 'utf8' }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '{lixo', encoding: 'utf8' }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs avisa âncora-de-contrato em *Test.php, silencia no resto, NUNCA bloqueia; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
