#!/usr/bin/env node
// Teste de regressão: o enforcement "claude.ai/design não é fonte" (block-design-sync-
// without-optin.mjs) continua REGISTRADO em .claude/settings.json. Complementa
// .claude/hooks/block-design-sync-without-optin.test.mjs (que testa a LÓGICA) garantindo que
// a ATIVAÇÃO não seja removida — registrar o arquivo é o que liga a regra (criar o hook sem
// registrar não enforça nada). Mesmo padrão de settings-figma-registration.test.mjs.
//
// Rodar: node scripts/governance/settings-design-sync-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');
const HOOK_CMD = 'node .claude/hooks/block-design-sync-without-optin.mjs';
const SKILL_HOOK_CMD = 'node .claude/hooks/block-skill-design-sync-without-optin.mjs';

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
function hasCmd(event, cmd, predicate) {
  for (const g of groupsFor(event)) {
    const matcher = String(g.matcher || '');
    for (const h of g.hooks || []) {
      if (h.command === cmd && predicate(matcher)) return true;
    }
  }
  return false;
}

check('settings.json e JSON valido', !!cfg && typeof cfg === 'object');
// 1. UserPromptSubmit — grava a flag de opt-in quando Wagner diz "design-sync"
check('block-design-sync registrado em UserPromptSubmit (grava opt-in)', hasCmd('UserPromptSubmit', HOOK_CMD, () => true));
// 2. PreToolUse — gateia a tool nativa DesignSync; matcher PRECISA casar "DesignSync"
check('block-design-sync registrado em PreToolUse', hasCmd('PreToolUse', HOOK_CMD, () => true));
check('matcher do PreToolUse casa DesignSync (tool nativa)', hasCmd('PreToolUse', HOOK_CMD, (m) => /\bDesignSync\b/.test(m)));
// 3. Gate-skill (ADR 0315 Eixo B via matcher Skill — o ponto que REALMENTE morde em runtime,
//    diferente do matcher DesignSync que o harness não roteia). PreToolUse matcher "Skill".
check('block-skill-design-sync registrado em PreToolUse', hasCmd('PreToolUse', SKILL_HOOK_CMD, () => true));
check('matcher do gate-skill casa Skill (tool built-in)', hasCmd('PreToolUse', SKILL_HOOK_CMD, (m) => /\bSkill\b/.test(m)));

console.log('');
if (fails === 0) {
  console.log('[PASS] block-design-sync (DesignSync + Skill) registrado (registro persistido).');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- enforcement design-sync NAO esta registrado; a regra ficou orfa.`);
process.exit(1);
