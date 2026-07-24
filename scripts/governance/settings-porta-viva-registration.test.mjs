#!/usr/bin/env node
// Teste de regressão: o enforcement "instrumento errado quando existe porta viva"
// (block-instrumento-sem-porta-viva.mjs) continua REGISTRADO em .claude/settings.json,
// no PreToolUse com matcher que casa Glob E Grep. Criar o hook sem registrar não enforça
// NADA — o registro é o que liga a regra. Mesmo padrão de settings-ancora-registration.
//
// Por que o matcher é conferido nome-a-nome: em 2026-07-08 um matcher capitalizado errado
// (`Claude_in_Chrome`) deixou um hook de smoke MORTO por 0 disparos — parecia ativo, nunca
// casava. Aqui as DUAS tools do LC-08 (Glob do incidente 07-22, Grep do 07-17) são exigidas.
//
// Origem: LC-08 (7 ocorrências) — memory/LICOES_CODE.md + memory/proibicoes.md §5.
// Rodar: node scripts/governance/settings-porta-viva-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');
const HOOK_CMD = 'node .claude/hooks/block-instrumento-sem-porta-viva.mjs';

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
check('block-instrumento-sem-porta-viva registrado em PreToolUse', hasCmd('PreToolUse', () => true));
check('matcher casa Glob (tool do incidente 2026-07-22 — mapa de tela)', hasCmd('PreToolUse', (m) => /\bGlob\b/.test(m)));
check('matcher casa Grep (tool do incidente 2026-07-17 — parse de Kernel)', hasCmd('PreToolUse', (m) => /\bGrep\b/.test(m)));

console.log('');
if (fails === 0) { console.log('[PASS] block-instrumento-sem-porta-viva ativo no PreToolUse/Glob|Grep (registro persistido).'); process.exit(0); }
console.log(`[FAIL] ${fails} caso(s) — enforcement de porta-viva NÃO está registrado; a regra ficou órfã.`);
process.exit(1);
