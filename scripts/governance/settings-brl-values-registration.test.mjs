#!/usr/bin/env node
// Teste de regressão: o enforcement "valor BRL não vai pra memory/" (block-brl-values-in-memory.mjs)
// continua REGISTRADO em .claude/settings.json no grupo PreToolUse Write|Edit|MultiEdit.
// Complementa o selftest da LÓGICA (embutido no hook via --selftest) garantindo que a
// ATIVAÇÃO não seja removida — criar/consertar o hook sem registrar não enforça nada.
// Mesmo padrão de settings-figma-registration.test.mjs / settings-ancora-registration.test.mjs.
//
// Contrato-âncora: memory/proibicoes.md §"NUNCA commitar valores BRL" (Tier 0 dinheiro).
// Rodar: node scripts/governance/settings-brl-values-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');
const HOOK_CMD = 'node .claude/hooks/block-brl-values-in-memory.mjs';

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK] ' : '[FAIL] ') + name);
  if (!cond) fails++;
}

let cfg = null;
try {
  cfg = JSON.parse(readFileSync(SETTINGS, 'utf8'));
} catch (e) {
  console.log('[FAIL] settings.json ilegivel/JSON invalido: ' + e.message);
  process.exit(1);
}
const groups = (cfg.hooks && cfg.hooks.PreToolUse) || [];

// Acha o grupo cujo matcher casa Write/Edit/MultiEdit e que contém o comando do hook.
function matcherCobre(m, tool) {
  // matcher é um regex alternado tipo "Write|Edit|MultiEdit"
  try {
    return new RegExp(`^(?:${m})$`).test(tool);
  } catch {
    return String(m).split('|').includes(tool);
  }
}
let registradoWrite = false;
let registradoEdit = false;
let registradoMultiEdit = false;
for (const g of groups) {
  const matcher = String(g.matcher || '');
  const temCmd = (g.hooks || []).some((h) => h.command === HOOK_CMD);
  if (!temCmd) continue;
  if (matcherCobre(matcher, 'Write')) registradoWrite = true;
  if (matcherCobre(matcher, 'Edit')) registradoEdit = true;
  if (matcherCobre(matcher, 'MultiEdit')) registradoMultiEdit = true;
}

check('settings.json e JSON valido', !!cfg && typeof cfg === 'object');
check('block-brl-values registrado no PreToolUse que casa Write', registradoWrite);
check('block-brl-values registrado no PreToolUse que casa Edit', registradoEdit);
check('block-brl-values registrado no PreToolUse que casa MultiEdit', registradoMultiEdit);

console.log('');
if (fails === 0) {
  console.log('[PASS] block-brl-values ativado em Write/Edit/MultiEdit (registro persistido).');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- enforcement BRL NAO esta registrado; a regra ficou orfa.`);
process.exit(1);
