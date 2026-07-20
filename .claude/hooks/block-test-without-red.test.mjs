#!/usr/bin/env node
// Teste do PORTE cross-plataforma block-test-without-red.mjs (ex-.ps1). Cada caso deriva
// do CONTRATO (SDD FV-T0 + proibicoes.md §"Ideias descartadas" 2026-06-05: teste sem red
// é tautológico), NÃO do output do .ps1 legado. Roda em Linux/CI.
//
// Rodar: node .claude/hooks/block-test-without-red.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import {
  getMode, isTestFile, relPath, findOverride, hasRedHeader, hasFreshEvidence,
  isTracked, repoRoot,
} from './block-test-without-red.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-test-without-red.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── unitários dos classificadores puros ──────────────────────────────────────────
check('getMode default warn', getMode({}) === 'warn' && getMode({ OIMPRESSO_REDFIRST_BLOCK_MODE: 'block' }) === 'block' && getMode({ OIMPRESSO_REDFIRST_BLOCK_MODE: 'off' }) === 'off');
check('isTestFile pega *Test.php', isTestFile('tests/Feature/FooTest.php') && isTestFile('a\\b\\BarTest.php') && !isTestFile('app/Foo.php') && !isTestFile('x.test.mjs'));
check('relPath tira raiz absoluta', relPath('/repo/tests/ATest.php', '/repo') === 'tests/ATest.php' && relPath('tests/ATest.php', '/repo') === 'tests/ATest.php');
check('findOverride extrai razão', findOverride('// red-first-override: characterization legado') === 'characterization legado' && findOverride('sem nada') === null);
check('hasRedHeader', hasRedHeader('// red-first: rodei pest, FALHOU') === true && hasRedHeader('nada') === false);

// ── git real: isTracked contra arquivo committed vs inexistente ──────────────────
// prova que a integração com git funciona (arquivo committed em origin/main é tracked).
const ROOT = repoRoot();
check('isTracked: arquivo committed → true', isTracked('.claude/settings.json', ROOT) === true);
check('isTracked: caminho inexistente → false', isTracked('tests/__nao_existe__/ZzzTest.php', ROOT) === false);

// ── hasFreshEvidence: repo temporário isolado ────────────────────────────────────
const tmp = mkdtempSync(join(tmpdir(), 'redfirst-'));
mkdirSync(join(tmp, '.claude', 'run'), { recursive: true });
check('hasFreshEvidence: dir sem evidência → false', hasFreshEvidence(tmp, 60) === false);
writeFileSync(join(tmp, '.claude', 'run', 'red-evidence-abc.txt'), 'FAIL: expected 200 got 500');
check('hasFreshEvidence: evidência fresca → true', hasFreshEvidence(tmp, 60) === true);
check('hasFreshEvidence: janela 0min → false (não é recente)', hasFreshEvidence(tmp, 0) === false);

// ── E2E: repo git temporário — prova MORDIDA (block) + destravas + fail-open ──────
const repo = mkdtempSync(join(tmpdir(), 'redfirst-repo-'));
const git = (...a) => spawnSync('git', ['-C', repo, ...a], { encoding: 'utf8' });
git('init', '-q');
git('config', 'user.email', 't@t');
git('config', 'user.name', 't');
mkdirSync(join(repo, 'tests', 'Feature'), { recursive: true });
writeFileSync(join(repo, 'tests', 'Feature', 'TrackedTest.php'), '<?php // já commitado');
git('add', '.');
git('commit', '-qm', 'seed');

function runHook(input, mode = 'block') {
  return spawnSync(process.execPath, [HOOK], {
    input: JSON.stringify(input), encoding: 'utf8',
    env: { ...process.env, OIMPRESSO_REDFIRST_BLOCK_MODE: mode, OIMPRESSO_REDFIRST_REPO_ROOT: repo },
  });
}
const w = (rel, content) => ({ tool_name: 'Write', tool_input: { file_path: rel, content } });

const blocked = runHook(w('tests/Feature/NovoTest.php', '<?php class NovoTest {}'));
check('E2E MORDIDA: teste NOVO sem red em block → exit 2', blocked.status === 2);
check('E2E MORDIDA: razão cita RED-FIRST no stderr', /RED-FIRST/.test(blocked.stderr));
check('E2E destrava: header red-first → exit 0', runHook(w('tests/Feature/NovoTest.php', '// red-first: rodei pest, FALHOU\n<?php')).status === 0);
check('E2E destrava: override → exit 0', runHook(w('tests/Feature/NovoTest.php', '// red-first-override: golden snapshot\n<?php')).status === 0);
check('E2E: teste JÁ tracked (overwrite) → exit 0', runHook(w('tests/Feature/TrackedTest.php', '<?php // muda')).status === 0);
check('E2E: arquivo não-teste → exit 0', runHook(w('app/Foo.php', '<?php')).status === 0);
check('E2E: Edit (não Write) → exit 0', runHook({ tool_name: 'Edit', tool_input: { file_path: 'tests/Feature/NovoTest.php', new_string: 'x' } }).status === 0);
check('E2E: modo warn (default off da flag) → exit 0 mesmo sem red', runHook(w('tests/Feature/NovoTest.php', '<?php'), 'warn').status === 0);
check('E2E: modo off → exit 0', runHook(w('tests/Feature/NovoTest.php', '<?php'), 'off').status === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '', encoding: 'utf8', env: { ...process.env, OIMPRESSO_REDFIRST_BLOCK_MODE: 'block' } }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '{lixo', encoding: 'utf8', env: { ...process.env, OIMPRESSO_REDFIRST_BLOCK_MODE: 'block' } }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs morde teste novo sem red (block), destrava por header/override/tracked, advisory em warn; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
