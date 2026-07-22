#!/usr/bin/env node
// Teste de regressão: o enforcement "não editar arquivo gerado à mão"
// (block-edit-authority-generated.mjs, ADR 0256) continua REGISTRADO em
// .claude/settings.json no PreToolUse que casa Write/Edit/MultiEdit.
// "Correção ≠ invocação" (meta-padrão do §5 grade-das-réguas 2026-07-09): escrever
// o hook sem registrar não enforça NADA — foi exatamente o buraco que a grade de
// guardrails 2026-07-22 mediu (gerador certo, zero trava no vetor runtime).
// Mesmo padrão de settings-automem-mwart-registration.test.mjs.
//
// Contrato-âncora: ADR 0256 (derivado+enforçado sobrevive) + grade guardrails
// 2026-07-22 (classe "editar gerado à mão" = 5/10, o_que_falta = deny no runtime).
// Rodar: node scripts/governance/settings-block-generated-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');
const CMD = 'node .claude/hooks/block-edit-authority-generated.mjs';

let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK] ' : '[FAIL] ') + name); if (!cond) fails++; };

let cfg = null;
try {
  cfg = JSON.parse(readFileSync(SETTINGS, 'utf8'));
} catch (e) {
  console.log('[FAIL] settings.json ilegivel/JSON invalido: ' + e.message);
  process.exit(1);
}
check('settings.json e JSON valido', !!cfg && typeof cfg === 'object');

const groups = (cfg.hooks && cfg.hooks.PreToolUse) || [];
function matcherCobre(m, tool) {
  try { return new RegExp(`^(?:${m})$`).test(tool); }
  catch { return String(m).split('|').includes(tool); }
}

let registrado = false;
for (const g of groups) {
  const matcher = String(g.matcher || '');
  const cmds = (g.hooks || []).map((x) => String(x.command || ''));
  if (cmds.some((c) => c === CMD) && matcherCobre(matcher, 'Write') && matcherCobre(matcher, 'Edit') && matcherCobre(matcher, 'MultiEdit')) {
    registrado = true;
  }
}
check('block-edit-authority-generated registrado no PreToolUse que casa Write+Edit+MultiEdit', registrado);

console.log('');
if (fails === 0) {
  console.log('[PASS] hook ativado via node — bloqueia edicao-a-mao de authority:generated no runtime (ADR 0256).');
  process.exit(0);
}
console.log(`[FAIL] ${fails} — hook escrito mas NAO invocado (o buraco que a grade mediu). Registre em settings.json.`);
process.exit(1);
