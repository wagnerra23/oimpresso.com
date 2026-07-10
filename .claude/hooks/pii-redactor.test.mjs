#!/usr/bin/env node
// Teste do PORTE cross-plataforma pii-redactor.mjs (ex-.ps1). Cada caso deriva do
// CONTRATO canônico (US-COPI-086 + LGPD Art. 7º + opção B commit-only 2026-06-13 +
// whitelist de fixtures), NÃO do output do .ps1 legado.
// Roda em Linux/CI (o test-pii-redactor.ps1 nunca rodou no gate-selftest).
// PIIs abaixo são NÚMEROS DE FORMATO, não dados reais (fixtures de teste).
//
// Rodar: node .claude/hooks/pii-redactor.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';
import { findPii, isGitCommit, hasBypass, blockMessage } from './pii-redactor.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'pii-redactor.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── findPii: detecta PII real, ignora fixtures (whitelist do contrato) ──────────
check('detecta CPF formato real não-whitelisted', findPii('cliente CPF 987.654.321-00').length === 1);
check('detecta CNPJ formato real', findPii('CNPJ 12.345.678/0001-95').length === 1);
check('detecta cartão 16 dígitos', findPii('cartao 5105 1051 0510 5100').length === 1);
check('tipo correto reportado', findPii('987.654.321-00')[0].tipo === 'cpf');
check('whitelist: CPF fixture 123.456.789-09 passa', findPii('teste com 123.456.789-09').length === 0);
check('whitelist: CPF fixture 111.111.111-11 passa', findPii('111.111.111-11').length === 0);
check('whitelist: CNPJ fixture 11.222.333/0001-81 passa', findPii('11.222.333/0001-81').length === 0);
check('whitelist: Visa test 4111 1111 1111 1111 passa', findPii('4111 1111 1111 1111').length === 0);
check('whitelist: Mastercard test 5555-5555-5555-4444 passa', findPii('5555-5555-5555-4444').length === 0);
check('texto limpo → nada', findPii('feat(jana): PII redactor BR [F]').length === 0);
check('múltiplas PIIs contadas', findPii('987.654.321-00 e 12.345.678/0001-95').length === 2);

// ── escopo opção B: SÓ git commit ───────────────────────────────────────────────
check('isGitCommit: git commit -m', isGitCommit('git commit -m "x"') === true);
check('isGitCommit: com espaços à esquerda', isGitCommit('  git commit --amend') === true);
check('isGitCommit: mysql com CPF NÃO é escopo (debug legítimo ERP)', isGitCommit('mysql -e "SELECT * FROM contacts WHERE cpf=\'987.654.321-00\'"') === false);
check('isGitCommit: grep de log NÃO é escopo', isGitCommit('grep 987.654.321-00 storage/logs/laravel.log') === false);
check('isGitCommit: git push não é commit', isGitCommit('git push origin main') === false);
check('hasBypass: --allow-pii', hasBypass('git commit -m "x" --allow-pii') === true);

// ── mensagem: instrui remediação + bypass (contrato) ────────────────────────────
const msg = blockMessage(findPii('987.654.321-00'));
check('mensagem cita LGPD + restore --staged + --allow-pii', /LGPD/.test(msg) && /restore --staged/.test(msg) && /--allow-pii/.test(msg));
check('mensagem trunca o valor (não re-vaza a PII inteira)', msg.includes('987.65...') && !msg.includes('987.654.321-00'));

// ── E2E: stdin JSON → exit code. cwd=tmpdir (fora de repo git → escaneia só a msg,
//    determinístico: staged diff da máquina de quem roda o teste NUNCA interfere) ──
function runHook(stdin) {
  return spawnSync(process.execPath, [HOOK], { input: stdin, encoding: 'utf8', cwd: tmpdir() }).status;
}
const j = (cmd) => JSON.stringify({ tool_name: 'Bash', tool_input: { command: cmd }, cwd: tmpdir() });
check('E2E: commit com CPF real na mensagem → exit 2 (BLOQUEIA)', runHook(j('git commit -m "fix cliente 987.654.321-00"')) === 2);
check('E2E: commit com fixture whitelisted → exit 0', runHook(j('git commit -m "test com 123.456.789-09"')) === 0);
check('E2E: commit limpo → exit 0', runHook(j('git commit -m "feat(jana): recall flow"')) === 0);
check('E2E: bypass --allow-pii → exit 0', runHook(j('git commit -m "987.654.321-00" --allow-pii')) === 0);
check('E2E: comando não-commit com CPF → exit 0 (opção B)', runHook(j('grep 987.654.321-00 laravel.log')) === 0);
check('E2E: tool não-Bash → exit 0', runHook(JSON.stringify({ tool_name: 'Write', tool_input: { file_path: 'x' } })) === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('') === 0);
check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava sessão)', runHook('{lixo') === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs bloqueia PII real em git commit (msg+staged) em Win/Mac/Linux, whitelist de fixtures preservada, opção B commit-only respeitada; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
