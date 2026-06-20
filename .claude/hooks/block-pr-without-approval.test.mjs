#!/usr/bin/env node
// TESTE DE REGRESSÃO DE GOVERNANÇA — R10 ("aprovação humana antes de publicar").
//
// Pergunta que este teste responde (Wagner 2026-05-28):
//   "A regra continua funcionando mesmo SEM a skill ativa?"
//
// Prova que R10 é enforçada pela MÁQUINA (hook block-pr-without-approval.mjs),
// NÃO pela orientação (skill wagner-protocol-enforce). Se alguém rebaixar/remover
// a skill, este teste AINDA passa → R10 sobrevive. Se o hook quebrar, este teste
// FALHA → você sabe que R10 ficou órfã ANTES de reformular qualquer skill.
//
// Rodar: node .claude/hooks/block-pr-without-approval.test.mjs
// Exit 0 = todos passam. Exit 1 = alguma regressão (caso explicitado).

import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { existsSync, writeFileSync, unlinkSync } from 'node:fs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const HOOK = join(__dirname, 'block-pr-without-approval.mjs');
const FLAG = join(tmpdir(), 'oimpresso-pr-approval.flag');

function clearFlag() {
  if (existsSync(FLAG)) unlinkSync(FLAG);
}

function runHook(payload, env = {}) {
  const r = spawnSync('node', [HOOK], {
    input: JSON.stringify(payload),
    env: { ...process.env, ...env },
    encoding: 'utf8',
  });
  return { code: r.status, stderr: r.stderr || '' };
}

const PUSH = { hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: 'git push -u origin HEAD' } };
const PR = { hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: 'gh pr create --base main' } };
const STATUS = { hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: 'git status --short' } };
const prompt = (t) => ({ hook_event_name: 'UserPromptSubmit', prompt: t });

let fails = 0;
function check(name, cond) {
  if (cond) {
    console.log(`[OK] ${name}`);
  } else {
    console.log(`[FAIL] ${name}`);
    fails++;
  }
}

// 1. push SEM aprovação → BLOQUEIA (exit 2). (Este é o caso que falhou na sessão real.)
clearFlag();
check('push sem aprovação → BLOCK', runHook(PUSH).code === 2);

// 2. Wagner aprova ("pode fazer o PR") → flag criada (exit 0).
clearFlag();
const approve = runHook(prompt('pode fazer o PR'));
check('aprovação grava flag (exit 0)', approve.code === 0 && existsSync(FLAG));

// 3. Com flag → PR PASSA (exit 0) e CONSOME a flag.
check('PR com aprovação → ALLOW', runHook(PR).code === 0);
check('flag consumida após publicar', !existsSync(FLAG));

// 4. Segundo push (flag já consumida) → BLOQUEIA. (1 aprovação = 1 publicação.)
check('2º push sem nova aprovação → BLOCK', runHook(PUSH).code === 2);

// 5. Negação ("não pode pushar") → NÃO grava flag → push BLOQUEIA.
clearFlag();
runHook(prompt('não pode pushar ainda'));
check('negação não cria flag → BLOCK', !existsSync(FLAG) && runHook(PUSH).code === 2);

// 6. Comando inócuo (git status) → ALLOW (não é publicação).
clearFlag();
check('git status → ALLOW (não-publicação)', runHook(STATUS).code === 0);

// 7. Flag EXPIRADA (timestamp velho) → BLOQUEIA.
clearFlag();
writeFileSync(FLAG, new Date(Date.now() - 30 * 60000).toISOString(), 'utf8'); // 30min atrás
check('flag expirada → BLOCK', runHook(PUSH).code === 2);

// 8. Escape valve env → ALLOW.
clearFlag();
check('override env → ALLOW', runHook(PUSH, { OIMPRESSO_PR_APPROVAL_OVERRIDE: '1' }).code === 0);

// 9. "merge" do Wagner aprova → gh pr merge passa.
clearFlag();
runHook(prompt('pode mergear'));
check('"merge" aprova → gh pr merge ALLOW', runHook({ hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: 'gh pr merge 1908 --admin' } }).code === 0);

// 10. FALSO-POSITIVO corrigido: 'merge' incidental em conversa NAO aprova publicacao.
clearFlag();
runHook(prompt('qual a estrategia de merge antes?'));
check('"estrategia de merge" NAO aprova (falso-positivo) -> BLOCK', !existsSync(FLAG) && runHook(PUSH).code === 2);

// 11. Gap PowerShell fechado: push via tool PowerShell sem aprovacao -> BLOCK.
clearFlag();
check('PowerShell push sem aprovacao -> BLOCK', runHook({ hook_event_name: 'PreToolUse', tool_name: 'PowerShell', tool_input: { command: 'git push' } }).code === 2);

// 12. PowerShell push COM aprovacao -> ALLOW.
clearFlag();
runHook(prompt('pode pushar'));
check('PowerShell push com aprovacao -> ALLOW', runHook({ hook_event_name: 'PreToolUse', tool_name: 'PowerShell', tool_input: { command: 'git push -u origin HEAD' } }).code === 0);

// 13. publishPatterns ancorado: 'git push' embebido em comando de busca NAO bloqueia.
clearFlag();
check('busca com "git push" embebido (rg) -> ALLOW (nao e publicacao)', runHook({ hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: "rg 'git push' .claude/hooks" } }).code === 0);

clearFlag();
console.log('');
if (fails === 0) {
  console.log('[PASS] R10 enforçada pela MÁQUINA — sobrevive sem a skill. (13/13)');
  process.exit(0);
} else {
  console.log(`[FAIL] ${fails} caso(s) — R10 NÃO está garantida pela máquina. NÃO rebaixar a skill.`);
  process.exit(1);
}
