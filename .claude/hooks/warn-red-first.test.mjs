#!/usr/bin/env node
// Teste do PORTE cross-plataforma warn-red-first.mjs (ex-.ps1). Cada caso deriva do
// CONTRATO (SDD FV-T0 advisory, lado da PRODUÇÃO), NÃO do output do .ps1 legado. Roda em Linux/CI.
// Advisory nasce SEMPRE exit 0 — o teste prova o CLASSIFICADOR (quem dispararia o aviso).
//
// Rodar: node .claude/hooks/warn-red-first.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { relPath, isProdFile, windowHours, testTouchedRecently, warnLines } from './warn-red-first.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'warn-red-first.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── unitários do classificador de PRODUÇÃO ───────────────────────────────────────
check('isProdFile: app/**.php', isProdFile('app/Services/Foo.php') === true);
check('isProdFile: Modules/<Mod>/Services', isProdFile('Modules/Jana/Services/Bar.php') === true);
check('isProdFile: Modules/<Mod>/Entities', isProdFile('Modules/Crm/Entities/Lead.php') === true);
check('isProdFile: Modules/<Mod>/Http', isProdFile('Modules/Repair/Http/Controllers/X.php') === true);
check('isProdFile: teste NÃO conta', isProdFile('app/Services/FooTest.php') === false);
check('isProdFile: markdown NÃO conta', isProdFile('app/x.md') === false);
check('isProdFile: Modules fora de Services/Entities/Http', isProdFile('Modules/Jana/Database/Migrations/x.php') === false);
check('isProdFile: fora de app/Modules', isProdFile('resources/js/x.tsx') === false);
check('relPath tira raiz', relPath('/r/app/A.php', '/r') === 'app/A.php');
check('windowHours default 4', windowHours({}) === 4 && windowHours({ OIMPRESSO_REDFIRST_WINDOW_HOURS: '8' }) === 8);
check('warnLines cita o arquivo e o teste esperado', warnLines('app/Services/Foo.php').join(' ').includes('FooTest.php'));

// ── git real: testTouchedRecently em repo temporário ─────────────────────────────
const repo = mkdtempSync(join(tmpdir(), 'warnred-'));
const git = (...a) => spawnSync('git', ['-C', repo, ...a], { encoding: 'utf8' });
git('init', '-q'); git('config', 'user.email', 't@t'); git('config', 'user.name', 't');
mkdirSync(join(repo, 'tests'), { recursive: true });
mkdirSync(join(repo, 'app'), { recursive: true });
writeFileSync(join(repo, 'app', 'seed.php'), '<?php'); git('add', '.'); git('commit', '-qm', 'seed');
check('testTouchedRecently: nenhum teste tocado → false', testTouchedRecently(repo, 4) === false);
writeFileSync(join(repo, 'tests', 'NovoTest.php'), '<?php // untracked');
check('testTouchedRecently: *Test.php untracked → true', testTouchedRecently(repo, 4) === true);

// ── E2E: advisory SEMPRE exit 0, aviso no stdout só quando classifica ────────────
function runHook(input, root) {
  return spawnSync(process.execPath, [HOOK], {
    input: JSON.stringify(input), encoding: 'utf8',
    env: { ...process.env, OIMPRESSO_REDFIRST_REPO_ROOT: root, OIMPRESSO_REDFIRST_MODE: 'warn' },
  });
}
const w = (rel) => ({ tool_name: 'Edit', tool_input: { file_path: rel } });

// repo limpo (nenhum teste tocado) → prod file dispara o AVISO, mas exit 0
const clean = mkdtempSync(join(tmpdir(), 'warnred-clean-'));
const gitc = (...a) => spawnSync('git', ['-C', clean, ...a], { encoding: 'utf8' });
gitc('init', '-q'); gitc('config', 'user.email', 't@t'); gitc('config', 'user.name', 't');
mkdirSync(join(clean, 'app', 'Services'), { recursive: true });
writeFileSync(join(clean, 'seed.txt'), 'x'); gitc('add', '.'); gitc('commit', '-qm', 's');

const warned = runHook(w('app/Services/Foo.php'), clean);
check('E2E: prod sem teste → exit 0 (advisory NUNCA bloqueia)', warned.status === 0);
check('E2E: aviso RED-FIRST no stdout', /RED-FIRST/.test(warned.stdout));
check('E2E: arquivo não-prod → exit 0 sem aviso', (() => { const r = runHook(w('resources/js/x.tsx'), clean); return r.status === 0 && !/RED-FIRST/.test(r.stdout); })());
check('E2E: OIMPRESSO_REDFIRST_MODE=off → exit 0 sem aviso', (() => { const r = spawnSync(process.execPath, [HOOK], { input: JSON.stringify(w('app/Services/Foo.php')), encoding: 'utf8', env: { ...process.env, OIMPRESSO_REDFIRST_REPO_ROOT: clean, OIMPRESSO_REDFIRST_MODE: 'off' } }); return r.status === 0 && !/RED-FIRST/.test(r.stdout); })());
check('E2E: stdin vazio → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '', encoding: 'utf8' }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '{lixo', encoding: 'utf8' }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs classifica prod-sem-teste, avisa no stdout, NUNCA bloqueia (advisory); fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
