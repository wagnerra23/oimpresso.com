#!/usr/bin/env node
// Teste de regressão: os enforcement "claim sem evidência" (block-claim-without-evidence.mjs,
// advisory ADR 0224) e "smoke visual pós-merge UI / R1" (post-merge-ui-smoke-required.mjs)
// continuam REGISTRADOS em .claude/settings.json com o comando NODE (não o powershell -File
// legado), em TODOS os pontos que a mecânica exige:
//   - evidence:   PreToolUse matcher cobrindo Bash
//   - ui-smoke:   PreToolUse Bash (caso 3 claim) + PreToolUse browser-MCP (caso 2 limpa flag)
//                 + PostToolUse Bash (caso 1 marca flag) — perder QUALQUER um quebra o ciclo.
// "Correção ≠ invocação" (meta-padrão grade-das-réguas). Padrão: #4025.
//
// Contrato-âncora: memory/proibicoes.md §"Claim sem evidência" (+ bullet pós-merge UI / R1).
// Rodar: node scripts/governance/settings-evidence-smoke-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');

const EVIDENCE_CMD = 'node .claude/hooks/block-claim-without-evidence.mjs';
const SMOKE_CMD = 'node .claude/hooks/post-merge-ui-smoke-required.mjs';
const LEGACY = ['.claude/hooks/block-claim-without-evidence.ps1', '.claude/hooks/post-merge-ui-smoke-required.ps1'];

let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK] ' : '[FAIL] ') + name); if (!cond) fails++; };

let cfg = null;
try {
  cfg = JSON.parse(readFileSync(SETTINGS, 'utf8'));
} catch (e) {
  console.log('[FAIL] settings.json ilegivel/JSON invalido: ' + e.message);
  process.exit(1);
}

function matcherCobre(m, tool) {
  try { return new RegExp(`^(?:${m})$`).test(tool); }
  catch { return String(m).split('|').includes(tool); }
}

function registrado(event, cmd, tool) {
  for (const g of (cfg.hooks && cfg.hooks[event]) || []) {
    const cmds = (g.hooks || []).map((x) => String(x.command || ''));
    if (cmds.some((c) => c === cmd) && matcherCobre(String(g.matcher || ''), tool)) return true;
  }
  return false;
}

check('settings.json e JSON valido', !!cfg && typeof cfg === 'object');
check('block-claim-without-evidence (NODE) registrado no PreToolUse que casa Bash', registrado('PreToolUse', EVIDENCE_CMD, 'Bash'));
check('ui-smoke caso 3 (NODE) registrado no PreToolUse que casa Bash', registrado('PreToolUse', SMOKE_CMD, 'Bash'));
check('ui-smoke caso 2 (NODE) registrado no PreToolUse que casa browser MCP (nome real minusculo pos-F7)',
  registrado('PreToolUse', SMOKE_CMD, 'mcp__claude-in-chrome__navigate') || registrado('PreToolUse', SMOKE_CMD, 'mcp__computer-use__screenshot'));
check('ui-smoke caso 1 (NODE) registrado no PostToolUse que casa Bash', registrado('PostToolUse', SMOKE_CMD, 'Bash'));

const rawSettings = readFileSync(SETTINGS, 'utf8');
for (const legacy of LEGACY) {
  check(`registro NAO usa mais o powershell -File ${legacy.split('/').pop()} (porte completo)`, !rawSettings.includes(legacy));
}

console.log('');
if (fails === 0) {
  console.log('[PASS] evidence (advisory 0224) + ui-smoke R1 ativados via node (cross-plataforma) nos 4 pontos; .ps1 legados des-registrados.');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- enforcement R1/evidencia NAO esta registrado como .mjs; a regra ficou orfa ou meio-portada.`);
process.exit(1);
