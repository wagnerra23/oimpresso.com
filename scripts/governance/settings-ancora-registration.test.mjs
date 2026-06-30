#!/usr/bin/env node
// Teste de regressão: o enforcement "âncora não no olho" (block-ancora-no-olho.mjs) continua
// REGISTRADO em .claude/settings.json, no PreToolUse com matcher que casa Read. Criar o hook
// sem registrar não enforça NADA — o registro é o que liga a regra. Mesmo padrão de
// settings-figma-registration.test.mjs / settings-r10-registration.test.mjs.
//
// Origem: incidente #7 (2026-06-30) — print de auditoria apresentado como "o design".
// Rodar: node scripts/governance/settings-ancora-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');
const HOOK_CMD = 'node .claude/hooks/block-ancora-no-olho.mjs';

let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK] ' : '[FAIL] ') + name); if (!cond) fails++; };

let cfg = null;
try { cfg = JSON.parse(readFileSync(SETTINGS, 'utf8')); }
catch (e) { console.log('[FAIL] settings.json ilegível/JSON inválido: ' + e.message); process.exit(1); }

const groupsFor = (event) => (cfg.hooks || {})[event] || [];
function hasCmd(event, predicate) {
  for (const g of groupsFor(event)) {
    const matcher = String(g.matcher || '');
    for (const h of g.hooks || []) if (h.command === HOOK_CMD && predicate(matcher)) return true;
  }
  return false;
}

check('settings.json é JSON válido', !!cfg && typeof cfg === 'object');
check('block-ancora-no-olho registrado em PreToolUse', hasCmd('PreToolUse', () => true));
check('matcher do PreToolUse casa Read (a tool do incidente #7)', hasCmd('PreToolUse', (m) => /\bRead\b/.test(m)));

console.log('');
if (fails === 0) { console.log('[PASS] block-ancora-no-olho ativo no PreToolUse/Read (registro persistido).'); process.exit(0); }
console.log(`[FAIL] ${fails} caso(s) — enforcement de âncora NÃO está registrado; a regra ficou órfã.`);
process.exit(1);
