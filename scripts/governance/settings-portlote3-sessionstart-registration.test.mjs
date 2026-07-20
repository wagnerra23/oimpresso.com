#!/usr/bin/env node
// Teste de regressão: os 4 hooks do lote 3 do porte .ps1→.mjs (US-GOV-052) continuam
// REGISTRADOS em .claude/settings.json — como `node` (não `powershell -File`), no evento/matcher certo.
//
// POR QUE (correção-do-mecanismo ≠ invocação · proibicoes.md §5 2026-07-09):
//   Portar o .ps1 pra .mjs não ativa nada sozinho: se a wiring não trocar junto, o Claude
//   Code segue chamando `powershell -File ...X.ps1`. Em Mac/Linux (time MCP) o `powershell`
//   nem existe → o hook evapora EM SILÊNCIO. A asserção `node` é o coração do porte.
//
// NOTA (ressalva do lote): este PR NÃO deleta o .ps1 do disco (deleção em PR separado).
//   A asserção é sobre a WIRING (string de comando), não sobre o arquivo no disco.
//
// Contrato-âncora (nenhuma asserção deriva do código dos hooks):
//   · check-skills-fresh        → skill sync-skills (skill nova entre sessões · ADR 0070)
//   · loop-fechar-check         → AUDIT IA-OS 2026-05-29 (rotina idempotente do brief)
//   · licoes-code-two-strikes   → memory/LICOES_CODE.md + ADR 0256 (loop de aprendizado)
//   · modulo-preflight-warning  → FASE 1 PRÉ-FLIGHT Tier 0 (proibicoes.md · skill preflight-modulo)
//
// Mesmo padrão de settings-portlote2-nudges-registration.test.mjs.
// Rodar: node scripts/governance/settings-portlote3-sessionstart-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');

const PORTADOS = [
  { nome: 'check-skills-fresh', cmd: 'node .claude/hooks/check-skills-fresh.mjs', evento: 'SessionStart', tools: ['*'] },
  { nome: 'loop-fechar-check', cmd: 'node .claude/hooks/loop-fechar-check.mjs', evento: 'SessionStart', tools: ['*'] },
  { nome: 'licoes-code-two-strikes', cmd: 'node .claude/hooks/licoes-code-two-strikes.mjs', evento: 'SessionStart', tools: ['*'] },
  { nome: 'modulo-preflight-warning', cmd: 'node .claude/hooks/modulo-preflight-warning.mjs', evento: 'PreToolUse', tools: ['Write', 'Edit', 'MultiEdit'] },
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

const matcherCobre = (m, tool) => {
  if (tool === '*') return String(m) === '*' || String(m) === '';
  try { return new RegExp(`^(?:${m})$`).test(tool); } catch { return String(m).split('|').includes(tool); }
};

const raw = JSON.stringify(cfg);
for (const h of PORTADOS) {
  const grupos = (cfg.hooks && cfg.hooks[h.evento]) || [];
  for (const tool of h.tools) {
    const ok = grupos.some((g) => (g.hooks || []).some((x) => x.command === h.cmd) && matcherCobre(String(g.matcher ?? '*'), tool));
    check(`${h.nome} registrado como node no ${h.evento} que casa ${tool}`, ok);
  }
  check(`${h.nome}: ZERO comando powershell -File apontando pro .ps1 na wiring`, !raw.includes(`${h.nome}.ps1`));
}

console.log('');
if (fails === 0) {
  console.log('[PASS] lote 3: os 4 hooks ativados como node no evento/matcher certo (porte invocado, nao so escrito).');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- porte escrito mas NAO ativado (ou wiring ainda apontando pro .ps1).`);
process.exit(1);
