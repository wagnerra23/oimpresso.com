#!/usr/bin/env node
// Teste de regressão: o enforcement "Figma não é fonte" (block-figma-without-optin.mjs)
// continua REGISTRADO em .claude/settings.json. Complementa
// .claude/hooks/block-figma-without-optin.test.mjs (que testa a LÓGICA) garantindo que a
// ATIVAÇÃO não seja removida — registrar o arquivo é o que liga a regra (criar o hook sem
// registrar não enforça nada). Mesmo padrão de settings-r10-registration.test.mjs.
//
// Rodar: node scripts/governance/settings-figma-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');
const HOOK_CMD = 'node .claude/hooks/block-figma-without-optin.mjs';

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
const hooks = cfg.hooks || {};

function groupsFor(event) {
  return hooks[event] || [];
}
function hasCmd(event, predicate) {
  for (const g of groupsFor(event)) {
    const matcher = String(g.matcher || '');
    for (const h of g.hooks || []) {
      if (h.command === HOOK_CMD && predicate(matcher)) return true;
    }
  }
  return false;
}

check('settings.json e JSON valido', !!cfg && typeof cfg === 'object');
// 1. UserPromptSubmit — grava a flag de opt-in quando Wagner diz "figma"
check('block-figma registrado em UserPromptSubmit (grava opt-in)', hasCmd('UserPromptSubmit', () => true));
// 2. PreToolUse — bloqueia a tool do Figma; matcher PRECISA casar as capabilities figma
check('block-figma registrado em PreToolUse', hasCmd('PreToolUse', () => true));
check('matcher do PreToolUse casa get_design_context (atrator principal)', hasCmd('PreToolUse', (m) => /get_design_context/.test(m)));
check('matcher casa use_figma', hasCmd('PreToolUse', (m) => /use_figma/.test(m)));
check('matcher casa search_design_system (escape do red-team)', hasCmd('PreToolUse', (m) => /search_design_system/.test(m)));
check('matcher casa nome-de-servidor figma (sabor plugin)', hasCmd('PreToolUse', (m) => /figma/.test(m)));

// ── L4 anti-restating: enforcement não pode hardcodar FATO VOLÁTIL ────────────
// (versão do DS que apodrece — "DS v6"→v7 — ou o path COMPARISON.md que foi alucinado).
// Texto agente-facing deve APONTAR pro INDEX, nunca restatar o fato que drifta. O INDEX
// é a exceção (lá o COMPARISON.md aparece só na negação que o enterra).
function readRoot(rel) {
  try { return readFileSync(join(__dirname, '..', '..', rel), 'utf8'); } catch { return ''; }
}
const volatile = /DS v\d|COMPARISON\.md/;
check('hook NÃO hardcoda versão do DS nem COMPARISON.md (aponta pro INDEX)', !volatile.test(readRoot('.claude/hooks/block-figma-without-optin.mjs')));
check('rule pages.md NÃO hardcoda versão do DS nem COMPARISON.md', !volatile.test(readRoot('.claude/rules/pages.md')));

console.log('');
if (fails === 0) {
  console.log('[PASS] block-figma ativado nos 2 eventos + matcher cobre o atrator (registro persistido).');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- enforcement Figma NAO esta registrado; a regra ficou orfa.`);
process.exit(1);
