#!/usr/bin/env node
// Teste de regressão: o enforcement "testes/PHPStan só no CT 100" (block-test-fora-ct100.mjs)
// continua REGISTRADO em .claude/settings.json no PreToolUse que casa Bash (e PowerShell), com
// o comando NODE (não o powershell -File legado). Complementa o selftest da LÓGICA (embutido no
// hook via --selftest) — "correção ≠ invocação": consertar/portar o hook sem registrar não
// enforça nada (o meta-padrão do dossiê grade-das-réguas). Mesmo padrão de
// settings-brl-values-registration.test.mjs.
//
// Contrato-âncora: memory/proibicoes.md §"Testes NUNCA rodam local" (ADR 0062, Tier 0).
// Rodar: node scripts/governance/settings-test-fora-ct100-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');
const HOOK_CMD = 'node .claude/hooks/block-test-fora-ct100.mjs';
const LEGACY_PS1 = '.claude/hooks/block-test-fora-ct100.ps1';

let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK] ' : '[FAIL] ') + name); if (!cond) fails++; };

let cfg = null;
try {
  cfg = JSON.parse(readFileSync(SETTINGS, 'utf8'));
} catch (e) {
  console.log('[FAIL] settings.json ilegivel/JSON invalido: ' + e.message);
  process.exit(1);
}
const groups = (cfg.hooks && cfg.hooks.PreToolUse) || [];

function matcherCobre(m, tool) {
  try { return new RegExp(`^(?:${m})$`).test(tool); }
  catch { return String(m).split('|').includes(tool); }
}

let registradoBash = false;
let aindaTemPs1 = false;
for (const g of groups) {
  const matcher = String(g.matcher || '');
  const cmds = (g.hooks || []).map((h) => String(h.command || ''));
  if (cmds.some((c) => c === HOOK_CMD) && matcherCobre(matcher, 'Bash')) registradoBash = true;
  if (cmds.some((c) => c.includes(LEGACY_PS1))) aindaTemPs1 = true;
}

check('settings.json e JSON valido', !!cfg && typeof cfg === 'object');
check('block-test-fora-ct100 (NODE .mjs) registrado no PreToolUse que casa Bash', registradoBash);
check('registro NÃO usa mais o powershell -File .ps1 legado (porte completo)', !aindaTemPs1);

console.log('');
if (fails === 0) {
  console.log('[PASS] block-test-fora-ct100 ativado via node (cross-plataforma) em Bash; .ps1 legado des-registrado.');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- enforcement CT100 NAO esta registrado como .mjs; a regra ficou orfa ou meio-portada.`);
process.exit(1);
