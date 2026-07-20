#!/usr/bin/env node
// Teste do PORTE cross-plataforma block-serving-branch-switch.mjs (ex-.ps1). Cada caso
// deriva do CONTRATO (R8 PROTOCOLO-WAGNER + ADR 0233: só worktree isolado troca branch;
// o checkout MAIN serve o Herd), NÃO do output do .ps1 legado. Roda em Linux/CI.
//
// Rodar: node .claude/hooks/block-serving-branch-switch.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { hasOverride, isBranchSwitch, effectivePath, isLinkedWorktree, isServingCheckout, shouldBlock } from './block-serving-branch-switch.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-serving-branch-switch.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

const MAIN = 'D:/oimpresso.com';
const WT = 'D:/oimpresso.com/.claude/worktrees/blissful-cori-9e0703';

// ── BLOCK: troca de branch no checkout MAIN que serve o Herd ─────────────────────
check('BLOCK: git switch <branch> no main', shouldBlock('git switch feat/x', MAIN) === true);
check('BLOCK: git switch -c no main', shouldBlock('git switch -c feat/x', MAIN) === true);
check('BLOCK: git checkout -b no main', shouldBlock('git checkout -b feat/x', MAIN) === true);
check('BLOCK: git checkout <ref> no main', shouldBlock('git checkout main', MAIN) === true);
check('BLOCK: cd main + switch (path do comando vence cwd)', shouldBlock('cd "D:/oimpresso.com" && git switch dev', WT) === true);

// ── ALLOW: worktree isolado, restaurar arquivo, comando não-switch, override ─────
check('ALLOW: switch dentro de worktree linkado', shouldBlock('git switch feat/x', WT) === false);
check('ALLOW: git checkout -- <path> (restaurar arquivo)', shouldBlock('git checkout -- app/A.php', MAIN) === false);
check('ALLOW: git status não é switch', shouldBlock('git status', MAIN) === false);
check('ALLOW: git commit não é switch', shouldBlock('git commit -m x', MAIN) === false);
check('ALLOW: override explícito', shouldBlock('git switch dev # serving-branch-override urgente', MAIN) === false);
check('ALLOW: comando vazio', shouldBlock('', MAIN) === false);
check('ALLOW: sem path (cwd vazio, sem cd)', shouldBlock('git switch dev', '') === false);

// ── unitários (redundância de defesa) ────────────────────────────────────────────
check('hasOverride', hasOverride('x serving-branch-override') === true && hasOverride('git switch dev') === false);
check('isBranchSwitch pega switch/checkout, solta checkout --', isBranchSwitch('git switch a') && isBranchSwitch('git checkout a') && !isBranchSwitch('git checkout -- a') && !isBranchSwitch('git status'));
check('effectivePath prefere cd do comando', effectivePath('cd "D:/oimpresso.com" && git switch x', WT) === MAIN);
check('effectivePath cai no cwd sem cd', effectivePath('git switch x', WT) === WT);
check('isLinkedWorktree', isLinkedWorktree(WT) === true && isLinkedWorktree(MAIN) === false);
check('isServingCheckout', isServingCheckout(MAIN) === true && isServingCheckout(WT) === false);

// ── E2E: stdin JSON → exit code (prova a MORDIDA + fail-open) ────────────────────
function runHook(stdin) {
  return spawnSync(process.execPath, [HOOK], { input: stdin, encoding: 'utf8' });
}
const j = (command, cwd) => JSON.stringify({ tool_name: 'Bash', tool_input: { command }, cwd });

const blocked = runHook(j('git switch feat/x', MAIN));
check('E2E MORDIDA: switch no main → exit 2', blocked.status === 2);
check('E2E MORDIDA: razão cita R8/worktree no stderr', /R8|worktree/i.test(blocked.stderr));
check('E2E: switch no worktree → exit 0', runHook(j('git switch feat/x', WT)).status === 0);
check('E2E: git status → exit 0', runHook(j('git status', MAIN)).status === 0);
check('E2E: override → exit 0', runHook(j('git switch dev serving-branch-override', MAIN)).status === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('').status === 0);
check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava sessão)', runHook('{lixo').status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs bloqueia switch no checkout serving, libera worktree/restore/override; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
