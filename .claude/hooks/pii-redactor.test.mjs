#!/usr/bin/env node
// Teste do PORTE cross-plataforma pii-redactor.mjs (ex-.ps1). Cada caso deriva do
// CONTRATO canônico (US-COPI-086 + LGPD Art. 7º + opção B commit-only 2026-06-13 +
// whitelist de fixtures), NÃO do output do .ps1 legado.
// Roda em Linux/CI (o test-pii-redactor.ps1 nunca rodou no gate-selftest).
//
// Rodar: node .claude/hooks/pii-redactor.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';
import { findPii, isGitCommit, hasBypass, blockMessage } from './pii-redactor.mjs';

// Fixtures 100% SINTÉTICAS (formato BR válido, números fake — jamais dados reais):
const CPF_FAKE = '987.654.321-00';        // pii-allowlist (fixture sintética do teste — não é CPF real)
const CNPJ_FAKE = '12.345.678/0001-95';   // pii-allowlist (fixture sintética do teste — não é CNPJ real)
const CPF_WL1 = '123.456.789-09';         // pii-allowlist (whitelist canônica do contrato)
const CPF_WL2 = '111.111.111-11';         // pii-allowlist (whitelist canônica do contrato)
const CNPJ_WL = '11.222.333/0001-81';     // pii-allowlist (whitelist canônica do contrato)
const CARD_FAKE = '5105 1051 0510 5100';  // cartão de teste clássico (não emitido)
const VISA_WL = '4111 1111 1111 1111';    // Visa test (whitelist do contrato)
const MC_WL = '5555-5555-5555-4444';      // Mastercard test (whitelist do contrato)

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'pii-redactor.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── findPii: detecta PII real, ignora fixtures (whitelist do contrato) ──────────
check('detecta CPF formato real não-whitelisted', findPii(`cliente CPF ${CPF_FAKE}`).length === 1);
check('detecta CNPJ formato real', findPii(`CNPJ ${CNPJ_FAKE}`).length === 1);
check('detecta cartão 16 dígitos', findPii(`cartao ${CARD_FAKE}`).length === 1);
check('tipo correto reportado', findPii(CPF_FAKE)[0].tipo === 'cpf');
check('whitelist: CPF fixture canônica passa', findPii(`teste com ${CPF_WL1}`).length === 0);
check('whitelist: CPF fixture repetida passa', findPii(CPF_WL2).length === 0);
check('whitelist: CNPJ fixture passa', findPii(CNPJ_WL).length === 0);
check('whitelist: Visa test passa', findPii(VISA_WL).length === 0);
check('whitelist: Mastercard test passa', findPii(MC_WL).length === 0);
check('texto limpo → nada', findPii('feat(jana): PII redactor BR [F]').length === 0);
check('múltiplas PIIs contadas', findPii(`${CPF_FAKE} e ${CNPJ_FAKE}`).length === 2);

// ── escopo opção B: SÓ git commit ───────────────────────────────────────────────
check('isGitCommit: git commit -m', isGitCommit('git commit -m "x"') === true);
check('isGitCommit: com espaços à esquerda', isGitCommit('  git commit --amend') === true);
check('isGitCommit: mysql com CPF NÃO é escopo (debug legítimo ERP)', isGitCommit(`mysql -e "SELECT * FROM contacts WHERE cpf='${CPF_FAKE}'"`) === false);
check('isGitCommit: grep de log NÃO é escopo', isGitCommit(`grep ${CPF_FAKE} storage/logs/laravel.log`) === false);
check('isGitCommit: git push não é commit', isGitCommit('git push origin main') === false);
check('hasBypass: --allow-pii', hasBypass('git commit -m "x" --allow-pii') === true);

// ── mensagem: instrui remediação + bypass (contrato) ────────────────────────────
const msg = blockMessage(findPii(CPF_FAKE));
check('mensagem cita LGPD + restore --staged + --allow-pii', /LGPD/.test(msg) && /restore --staged/.test(msg) && /--allow-pii/.test(msg));
check('mensagem trunca o valor (não re-vaza a PII inteira)', msg.includes('987.65...') && !msg.includes(CPF_FAKE));

// ── E2E: stdin JSON → exit code. cwd=tmpdir (fora de repo git → escaneia só a msg,
//    determinístico: staged diff da máquina de quem roda o teste NUNCA interfere) ──
function runHook(stdin) {
  return spawnSync(process.execPath, [HOOK], { input: stdin, encoding: 'utf8', cwd: tmpdir() }).status;
}
const j = (cmd) => JSON.stringify({ tool_name: 'Bash', tool_input: { command: cmd }, cwd: tmpdir() });
check('E2E: commit com CPF real na mensagem → exit 2 (BLOQUEIA)', runHook(j(`git commit -m "fix cliente ${CPF_FAKE}"`)) === 2);
check('E2E: commit com fixture whitelisted → exit 0', runHook(j(`git commit -m "test com ${CPF_WL1}"`)) === 0);
check('E2E: commit limpo → exit 0', runHook(j('git commit -m "feat(jana): recall flow"')) === 0);
check('E2E: bypass --allow-pii → exit 0', runHook(j(`git commit -m "${CPF_FAKE}" --allow-pii`)) === 0);
check('E2E: comando não-commit com CPF → exit 0 (opção B)', runHook(j(`grep ${CPF_FAKE} laravel.log`)) === 0);
check('E2E: tool não-Bash → exit 0', runHook(JSON.stringify({ tool_name: 'Write', tool_input: { file_path: 'x' } })) === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('') === 0);
check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava sessão)', runHook('{lixo') === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs bloqueia PII real em git commit (msg+staged) em Win/Mac/Linux, whitelist de fixtures preservada, opção B commit-only respeitada; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
