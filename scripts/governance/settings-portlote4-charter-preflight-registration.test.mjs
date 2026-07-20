#!/usr/bin/env node
// Teste de regressão: os 2 hooks do lote 4 do porte .ps1→.mjs (US-GOV-052) continuam
// REGISTRADOS em .claude/settings.json — como `node` (não `powershell -File`), no matcher certo.
//
// POR QUE (correção-do-mecanismo ≠ invocação · proibicoes.md §5 2026-07-09):
//   se a wiring não trocar junto, o Claude Code segue chamando `powershell -File ...X.ps1`;
//   em Mac/Linux (time MCP) o `powershell` nem existe → o hook evapora EM SILÊNCIO.
//
// NOTA: estes 2 foram PORTADOS (não aposentados) — o adversário 2026-07-20 REFUTOU a
//   aposentadoria do triagem (a "cobertura" alegada cobria vetor DIFERENTE). Ver session
//   log 2026-07-20. Os .ps1 NÃO são deletados neste PR (deleção em PR separado).
//
// Contrato-âncora:
//   · charter-validate          → Constituição V2 #3 Charter > Spec (ADR 0094/0101)
//   · preflight-new-capability  → anti-reinvenção de framework (lição 2026-05-29, ADR 0216)
//
// Rodar: node scripts/governance/settings-portlote4-charter-preflight-registration.test.mjs

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');

const PORTADOS = [
  { nome: 'charter-validate', cmd: 'node .claude/hooks/charter-validate.mjs', tools: ['Write', 'Edit', 'MultiEdit'] },
  { nome: 'preflight-new-capability', cmd: 'node .claude/hooks/preflight-new-capability.mjs', tools: ['Write', 'Edit', 'MultiEdit'] },
];

let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

let cfg = null;
try {
  cfg = JSON.parse(readFileSync(SETTINGS, 'utf8'));
} catch (e) {
  console.log('[FAIL] settings.json ilegivel/JSON invalido: ' + e.message);
  process.exit(1);
}
check('settings.json e JSON valido', !!cfg && typeof cfg === 'object');

const groups = (cfg.hooks && cfg.hooks.PreToolUse) || [];
const matcherCobre = (m, tool) => {
  try { return new RegExp(`^(?:${m})$`).test(tool); } catch { return String(m).split('|').includes(tool); }
};

const raw = JSON.stringify(cfg);
for (const h of PORTADOS) {
  for (const tool of h.tools) {
    const ok = groups.some((g) => (g.hooks || []).some((x) => x.command === h.cmd) && matcherCobre(String(g.matcher || ''), tool));
    check(`${h.nome} registrado como node no PreToolUse que casa ${tool}`, ok);
  }
  check(`${h.nome}: ZERO comando powershell -File apontando pro .ps1 na wiring`, !raw.includes(`${h.nome}.ps1`));
}

console.log('');
if (fails === 0) {
  console.log('[PASS] lote 4: os 2 hooks (charter-validate + preflight-new-capability) ativados como node.');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- porte escrito mas NAO ativado (ou wiring ainda apontando pro .ps1).`);
process.exit(1);
