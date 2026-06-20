#!/usr/bin/env node
// Teste de regressao: o enforcement R10 (block-pr-without-approval.mjs) continua
// REGISTRADO em .claude/settings.json (Onda 1, gap #3, PR #3058). Complementa
// .claude/hooks/block-pr-without-approval.test.mjs (que testa a LOGICA do hook)
// garantindo que a ATIVACAO nao seja removida -- registrar o arquivo e' o que liga
// a regra (criar o hook sem registrar nao enforca nada -- foi exatamente o gap #3).
//
// Rodar: node scripts/governance/settings-r10-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');
const HOOK_CMD = 'node .claude/hooks/block-pr-without-approval.mjs';

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

function commandsFor(event, matcherIncludes) {
  const cmds = [];
  for (const g of hooks[event] || []) {
    if (matcherIncludes && !String(g.matcher || '').includes(matcherIncludes)) continue;
    for (const h of g.hooks || []) if (h.command) cmds.push(h.command);
  }
  return cmds;
}

check('settings.json e JSON valido', !!cfg && typeof cfg === 'object');
check('R10 registrado em UserPromptSubmit (grava flag de aprovacao)', commandsFor('UserPromptSubmit', '').includes(HOOK_CMD));
check('R10 registrado em PreToolUse/Bash (bloqueia push/PR sem aprovacao)', commandsFor('PreToolUse', 'Bash').includes(HOOK_CMD));

console.log('');
if (fails === 0) {
  console.log('[PASS] R10 ativado nos 2 eventos (registro persistido).');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- R10 NAO esta registrado; a regra ficou orfa.`);
process.exit(1);
