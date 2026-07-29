#!/usr/bin/env node
// Teste de regressão: o aviso "doc nasce fora do índice do RAG" (doc-fora-do-rag.mjs)
// continua REGISTRADO em .claude/settings.json, no PreToolUse com matcher que casa Write.
// Criar o hook sem registrar não avisa NADA — o registro é o que liga a regra.
//
// Por que o matcher é conferido nome-a-nome: em 2026-07-08 um matcher capitalizado errado
// (`Claude_in_Chrome`) deixou um hook de smoke MORTO por 0 disparos — parecia ativo, nunca
// casava. Aqui a tool exigida é `Write`, e SÓ ela: o hook é forward-only por desenho
// (arquivo que já existe é legado grandfatherado — lápide §5 2026-07-12), então casar
// `Edit`/`MultiEdit` seria acoplar no lugar errado.
//
// Dono da regra: memory/reference/como-escrever-doc-para-o-rag.md (Regra 2).
// Rodar: node scripts/governance/settings-doc-fora-do-rag-registration.test.mjs   (exit 0 = passa)

import { existsSync, readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..', '..');
const SETTINGS = join(ROOT, '.claude', 'settings.json');
const HOOK_REL = '.claude/hooks/doc-fora-do-rag.mjs';
const HOOK_CMD = `node ${HOOK_REL}`;

let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK] ' : '[FAIL] ') + name); if (!cond) fails++; };

let cfg = null;
try { cfg = JSON.parse(readFileSync(SETTINGS, 'utf8')); }
catch (e) { console.log('[FAIL] settings.json ilegível/JSON inválido: ' + e.message); process.exit(1); }

const grupos = (evento) => (cfg.hooks || {})[evento] || [];
function temCmd(evento, predicado) {
  for (const g of grupos(evento)) {
    const matcher = String(g.matcher || '');
    for (const h of g.hooks || []) if (h.command === HOOK_CMD && predicado(matcher)) return true;
  }
  return false;
}
/** o matcher (regex de tool) casa esta tool? */
const casa = (matcher, tool) => {
  try { return new RegExp(`^(?:${matcher})$`).test(tool); }
  catch { return String(matcher).split('|').includes(tool); }
};
const casaTool = (tool) => temCmd('PreToolUse', (m) => casa(m, tool));

check('settings.json é JSON válido', !!cfg && typeof cfg === 'object');
check('arquivo do hook existe no disco (wiring sem arquivo = fantasma)', existsSync(join(ROOT, HOOK_REL)));
check('doc-fora-do-rag registrado em PreToolUse', temCmd('PreToolUse', () => true));
check('matcher casa Write (o chokepoint onde o arquivo NASCE)', casaTool('Write'));
check('registrado como `node <path>` (porte .mjs — não `pwsh`/`powershell`)', /^node /.test(HOOK_CMD) && temCmd('PreToolUse', () => true));

console.log('');
if (fails === 0) { console.log('[PASS] doc-fora-do-rag ativo no PreToolUse/Write (registro persistido).'); process.exit(0); }
console.log(`[FAIL] ${fails} caso(s) — o aviso de doc fora do RAG NÃO está registrado; a regra ficou órfã.`);
process.exit(1);
