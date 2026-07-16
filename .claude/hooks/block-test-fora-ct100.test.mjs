#!/usr/bin/env node
// Teste do PORTE cross-plataforma block-test-fora-ct100.mjs (ex-.ps1). Prova a LÓGICA
// (funções puras) + o E2E (stdin JSON → exit code), cobrindo os MESMOS casos do
// block-test-fora-ct100.test.ps1 legado. Roda em Linux/CI (o .ps1 não rodava).
// Complementa settings-test-fora-ct100-registration.test.mjs (prova que está REGISTRADO).
//
// Rodar: node .claude/hooks/block-test-fora-ct100.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { shouldBlock, isRunner, isCt100, hasOverride } from './block-test-fora-ct100.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-test-fora-ct100.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── LÓGICA pura: os casos que devem BLOQUEAR (runner local) ─────────────────────
const BLOCK = [
  ['pest local', 'vendor/bin/pest --filter=Foo'],
  ['phpstan local', 'vendor/bin/phpstan analyse Modules/Financeiro'],
  ['artisan test local', 'php artisan test'],
  ['phpunit local', 'vendor/bin/phpunit'],
  ['composer phpstan local', 'composer phpstan'],
  ['pest com cd local', 'cd D:/oimpresso.com && php artisan test --filter=X'],
  ['hostinger ssh test', 'ssh u906587222@148.135.133.115 "php artisan test"'],
];
for (const [nome, cmd] of BLOCK) check(`BLOCK: ${nome}`, shouldBlock(cmd) === true);

// ── LÓGICA pura: os casos que devem LIBERAR (CT 100 / não-runner / override) ─────
const ALLOW = [
  ['ct100 docker exec', 'tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test"'],
  ['ct100 phpstan', 'tailscale ssh root@ct100-mcp "docker exec oimpresso-staging vendor/bin/phpstan analyse"'],
  ['le baseline (cat)', 'cat phpstan-baseline.neon'],
  ['grep phpstan log', 'gh run view 123 --log | grep -iE "phpstan|larastan"'],
  ['git normal', 'git status'],
  ['npm test (frontend)', 'npm test'],
  ['override explicito', 'vendor/bin/pest # test-local-override'],
];
for (const [nome, cmd] of ALLOW) check(`ALLOW: ${nome}`, shouldBlock(cmd) === false);

// ── discriminadores unitários (redundância de defesa) ───────────────────────────
check('isRunner pega "vendor/bin/pest", solta "npm test"', isRunner('vendor/bin/pest') && !isRunner('npm test'));
check('isCt100 pega tailscale+ct100-mcp, solta local', isCt100('tailscale ssh root@ct100-mcp x') && !isCt100('vendor/bin/pest'));
check('hasOverride pega o sentinela', hasOverride('vendor/bin/pest # test-local-override') && !hasOverride('vendor/bin/pest'));
check('cmd vazio não bloqueia (fail-open)', shouldBlock('') === false);

// ── E2E: stdin JSON → exit code (prova o wrapper + fail-open de parse) ───────────
function runHook(stdin) {
  return spawnSync(process.execPath, [HOOK], { input: stdin, encoding: 'utf8' }).status;
}
const j = (cmd) => JSON.stringify({ tool_input: { command: cmd } });
check('E2E: pest local → exit 2 (BLOQUEIA)', runHook(j('vendor/bin/pest --filter=X')) === 2);
check('E2E: ct100 → exit 0 (LIBERA)', runHook(j('tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test"')) === 0);
check('E2E: git status → exit 0 (não-runner)', runHook(j('git status')) === 0);
check('E2E: override → exit 0', runHook(j('php artisan test # test-local-override')) === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('') === 0);
check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava sessão)', runHook('{lixo não-json') === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs bloqueia runner local, libera CT100/override/não-runner; fail-open provado (E2E). Roda em Linux (o .ps1 não rodava).');
process.exit(fails ? 1 : 0);
