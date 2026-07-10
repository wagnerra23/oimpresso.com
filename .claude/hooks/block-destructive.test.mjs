#!/usr/bin/env node
// Teste do PORTE cross-plataforma block-destructive.mjs (ex-.ps1). Cada caso deriva
// do CONTRATO canônico (US-COPI-085 8 categorias + ADR 0063 composer.lock +
// whitelist rm de caches reconstruíveis), NÃO do output do .ps1 legado.
// Roda em Linux/CI (o test-block-destructive.ps1 nunca rodou no gate-selftest).
//
// Rodar: node .claude/hooks/block-destructive.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { matchDestructive, normalizeCmd, blockMessage } from './block-destructive.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-destructive.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── BLOCK: as 8 categorias do contrato ──────────────────────────────────────────
const BLOCK = [
  ['rm -rf em path de trabalho', 'rm -rf src/'],
  ['rm -rf no meio de pipeline (;)', 'cd x; rm -rf Modules/'],
  ['RM -RF maiúsculo (PS -match era case-insensitive)', 'RM -RF app/'],
  ['git push --force', 'git push --force origin main'],
  ['git push -f', 'git push -f'],
  ['git push --force-with-lease (exige confirmação Wagner)', 'git push origin main --force-with-lease'],
  ['git reset --hard origin/', 'git reset --hard origin/main'],
  ['DROP TABLE', 'mysql -e "DROP TABLE transactions"'],
  ['drop database minúsculo', 'mysql -e "drop database oimpresso"'],
  ['DELETE FROM sem WHERE', 'mysql -e "DELETE FROM contacts"'],
  ['DELETE WHERE 1=1 (wipe disfarçado)', 'mysql -e "DELETE FROM contacts WHERE 1=1"'],
  ['TRUNCATE TABLE', 'mysql -e "TRUNCATE TABLE sale_stage_history"'],
  ['composer update sem --lock (ADR 0063)', 'composer update'],
  ['composer update pacote sem --lock', 'composer update laravel/framework'],
  ['migrate:fresh', 'php artisan migrate:fresh'],
  ['migrate:reset', 'php artisan migrate:reset --force'],
  ['migrate:rollback --step grande (2+ dígitos)', 'php artisan migrate:rollback --step=10'],
];
for (const [nome, cmd] of BLOCK) check(`BLOCK: ${nome}`, matchDestructive(cmd) !== null);

// ── ALLOW: whitelist rm (caches reconstruíveis) + operações normais ─────────────
const ALLOW = [
  ['rm -rf /tmp/ (whitelist)', 'rm -rf /tmp/scratch'],
  ['rm -rf node_modules (whitelist)', 'rm -rf node_modules'],
  ['rm -rf vendor (whitelist)', 'rm -rf vendor'],
  ['rm -rf storage/framework/views (whitelist)', 'rm -rf storage/framework/views/'],
  ['rm -rf public/build (whitelist)', 'rm -rf public/build-inertia'],
  ['rm sem -rf de arquivo comum', 'rm /tmp/scratch.md'],
  ['git push normal', 'git push origin claude/feature'],
  ['git reset --hard local (sem origin/)', 'git reset --hard HEAD~1'],
  ['DELETE FROM com WHERE real (fix do backtracking do .ps1)', 'mysql -e "DELETE FROM contacts WHERE id = 42"'],
  ['composer update --lock (ADR 0063 caminho certo)', 'composer update --lock'],
  ['composer require', 'composer require laravel/ai:^0.6'],
  ['migrate normal', 'php artisan migrate'],
  ['migrate:rollback --step=1', 'php artisan migrate:rollback --step=1'],
  ['comando inocente', 'echo test'],
  ['comando vazio (fail-open)', ''],
];
for (const [nome, cmd] of ALLOW) check(`ALLOW: ${nome}`, matchDestructive(cmd) === null);

// ── unitários (redundância de defesa) ───────────────────────────────────────────
check('normalizeCmd colapsa espaços', normalizeCmd('  rm   -rf    src/ ') === 'rm -rf src/');
check('primeiro match determina a mensagem (ordem determinística)', matchDestructive('rm -rf src/').key === 'rm-rf-perigoso');
check('mensagem cita a chave e a sugestão', /rm-rf-perigoso/.test(blockMessage(matchDestructive('rm -rf src/'))) && /Sugestão/.test(blockMessage(matchDestructive('rm -rf src/'))));

// ── E2E: stdin JSON → exit code (prova o wrapper + fail-open) ────────────────────
function runHook(stdin) {
  return spawnSync(process.execPath, [HOOK], { input: stdin, encoding: 'utf8' }).status;
}
const j = (cmd) => JSON.stringify({ tool_name: 'Bash', tool_input: { command: cmd } });
check('E2E: rm -rf perigoso → exit 2 (BLOQUEIA)', runHook(j('rm -rf Modules/')) === 2);
check('E2E: rm -rf /tmp/ whitelisted → exit 0', runHook(j('rm -rf /tmp/x')) === 0);
check('E2E: tool não-Bash → exit 0', runHook(JSON.stringify({ tool_name: 'Write', tool_input: { file_path: 'x' } })) === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('') === 0);
check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava sessão)', runHook('{lixo') === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs bloqueia as 8 categorias destrutivas em Win/Mac/Linux, whitelist rm preservada, DELETE-com-WHERE liberado (regra como escrita); fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
