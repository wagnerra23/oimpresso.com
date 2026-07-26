#!/usr/bin/env node
// Teste de regressão: o guard de schema de memory/ (memory-schema-guard.mjs) continua
// REGISTRADO em .claude/settings.json, no PreToolUse com matcher que casa Write E Edit.
// Criar o hook sem registrar não enforça NADA — o registro é o que liga a regra.
// Mesmo padrão de settings-porta-viva-registration / settings-ancora-registration.
//
// Por que o matcher é conferido nome-a-nome: em 2026-07-08 um matcher capitalizado errado
// (`Claude_in_Chrome`) deixou um hook de smoke MORTO por 0 disparos — parecia ativo, nunca
// casava. Aqui as tools que importam são exigidas: Write (arquivo nasce inteiro) e Edit
// (frontmatter reescrito).
//
// Origem: incidente 2026-07-26 (#4798) — session log + handoff com frontmatter fora do
// schema mergearam no main. A skill `memory-schema-preflight` não disparou, o validador
// nem existia como comando, e os jobs dessas 2 famílias são advisory (auto-merge não
// espera advisory). Este hook é o backstop determinístico da camada de prevenção.
// Rodar: node scripts/governance/settings-memory-schema-registration.test.mjs  (exit 0 = passa)

import { existsSync, readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..', '..');
const SETTINGS = join(ROOT, '.claude', 'settings.json');
const HOOK_CMD = 'node .claude/hooks/memory-schema-guard.mjs';

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
check('memory-schema-guard registrado em PreToolUse', hasCmd('PreToolUse', () => true));
check('matcher casa Write (arquivo nasce inteiro — o caso do #4798)', hasCmd('PreToolUse', (m) => /\bWrite\b/.test(m)));
check('matcher casa Edit (frontmatter reescrito in-place)', hasCmd('PreToolUse', (m) => /\bEdit\b/.test(m)));

// O hook DELEGA o veredito ao validate.mjs — se o dono da regra sumir, o hook vira no-op
// silencioso (fail-open por design). Conferir que o arquivo existe fecha esse buraco.
check('o dono da regra existe (scripts/memory-schemas/validate.mjs)',
  existsSync(join(ROOT, 'scripts', 'memory-schemas', 'validate.mjs')));

console.log('');
if (fails === 0) { console.log('[PASS] memory-schema-guard ativo no PreToolUse/Write|Edit (registro persistido).'); process.exit(0); }
console.log(`[FAIL] ${fails} caso(s) — o guard de schema NÃO está registrado; a regra ficou órfã.`);
process.exit(1);
