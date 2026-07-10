#!/usr/bin/env node
// Teste de regressão: os enforcement "zero auto-mem privada" (block-automem.mjs, ADR 0061+0131)
// e "MWART único caminho" (block-mwart-violation.mjs, ADR 0104) continuam REGISTRADOS em
// .claude/settings.json no PreToolUse que casa Write/Edit/MultiEdit, com o comando NODE (não o
// powershell -File legado). "Correção ≠ invocação" (meta-padrão do dossiê grade-das-réguas):
// portar o hook sem registrar não enforça nada. Mesmo padrão de
// settings-test-fora-ct100-registration.test.mjs (#4025).
//
// Contrato-âncora: memory/proibicoes.md §Memória (ADR 0061/0131) + §MWART (ADR 0104) — desde
// a ADR 0271 onda 2 o hook mwart é o ÚNICO enforcement de RUNBOOK (CI gate foi deletado).
// Rodar: node scripts/governance/settings-automem-mwart-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');

const HOOKS = [
  { cmd: 'node .claude/hooks/block-automem.mjs', legacy: '.claude/hooks/block-automem.ps1', nome: 'block-automem' },
  { cmd: 'node .claude/hooks/block-mwart-violation.mjs', legacy: '.claude/hooks/block-mwart-violation.ps1', nome: 'block-mwart-violation' },
];

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

check('settings.json e JSON valido', !!cfg && typeof cfg === 'object');

for (const h of HOOKS) {
  let registrado = false;
  let aindaTemPs1 = false;
  for (const g of groups) {
    const matcher = String(g.matcher || '');
    const cmds = (g.hooks || []).map((x) => String(x.command || ''));
    if (cmds.some((c) => c === h.cmd) && matcherCobre(matcher, 'Write') && matcherCobre(matcher, 'Edit')) registrado = true;
    if (cmds.some((c) => c.includes(h.legacy))) aindaTemPs1 = true;
  }
  check(`${h.nome} (NODE .mjs) registrado no PreToolUse que casa Write+Edit`, registrado);
  check(`${h.nome}: registro NAO usa mais o powershell -File .ps1 legado (porte completo)`, !aindaTemPs1);
}

console.log('');
if (fails === 0) {
  console.log('[PASS] block-automem + block-mwart-violation ativados via node (cross-plataforma); .ps1 legados des-registrados.');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- blocker Tier-0 NAO esta registrado como .mjs; a regra ficou orfa ou meio-portada.`);
process.exit(1);
