#!/usr/bin/env node
// Teste de regressão: os 3 hooks do lote 5 do porte .ps1→.mjs (US-GOV-052) continuam
// REGISTRADOS em .claude/settings.json — como `node` (não `powershell`), no SessionStart.
//
// POR QUE (correção-do-mecanismo ≠ invocação · proibicoes.md §5 2026-07-09):
//   Portar o .ps1 pra .mjs não ativa nada sozinho: se a wiring não trocar junto, o Claude Code
//   segue chamando `powershell ...`. Em Mac/Linux (time MCP) o `powershell` nem existe → o hook
//   evapora EM SILÊNCIO, e o time abre sessão SEM brief/handoff/banner. A troca pra `node` é o porte.
//
// NOTA (ressalva do lote): este PR NÃO deleta os .ps1 do disco (deleção em PR separado).
//   A asserção é sobre a WIRING (string de comando), não sobre o arquivo no disco.
//
// Contrato-âncora (nenhuma asserção deriva do código dos hooks):
//   · brief-fetch-curl → skill brief-first (Tier A) + ADR 0091 (Daily Brief)
//   · tier-a-banner    → ADR 0094/0095/0225 (Constituição v2 + skills tiers)
//   · handoff-inline   → ADR 0130 (handoff append-only) + ADR 0070 (tasks via MCP).
//     Era comando PowerShell INLINE (não arquivo .ps1) — o sinal do porte é o inline sumir.
//
// Mesmo padrão de settings-portlote3-sessionstart-registration.test.mjs.
// Rodar: node scripts/governance/settings-portlote5-sessionstart-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');

const PORTADOS = [
  { nome: 'brief-fetch-curl', cmd: 'node .claude/hooks/brief-fetch-curl.mjs', evento: 'SessionStart', tools: ['*'] },
  { nome: 'tier-a-banner', cmd: 'node .claude/hooks/tier-a-banner.mjs', evento: 'SessionStart', tools: ['*'] },
  { nome: 'handoff-inline', cmd: 'node .claude/hooks/handoff-inline.mjs', evento: 'SessionStart', tools: ['*'] },
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
}

// ── sinais do porte: nada de .ps1 na wiring de hooks ──
// (escopo = comandos de hook; NUNCA o raw inteiro — a seção permissions cita 08-handoff.md
//  legitimamente `Edit(memory/08-handoff.md)`, e um critério amplo pegaria o legítimo · §5)
const allCmds = Object.values((cfg.hooks && typeof cfg.hooks === 'object') ? cfg.hooks : {})
  .flatMap((grupos) => (Array.isArray(grupos) ? grupos : []).flatMap((g) => (g.hooks || []).map((x) => String(x.command || ''))));
check('ZERO wiring .ps1 do brief-fetch-curl', !allCmds.some((c) => c.includes('brief-fetch-curl.ps1')));
check('ZERO wiring .ps1 do tier-a-banner', !allCmds.some((c) => c.includes('tier-a-banner.ps1')));

// ── handoff-inline: o comando inline PowerShell SUMIU do SessionStart ──
const ssCmds = ((cfg.hooks && cfg.hooks.SessionStart) || []).flatMap((g) => (g.hooks || []).map((x) => String(x.command || '')));
check('handoff-inline: nenhum comando SessionStart cita 08-handoff (inline PowerShell removido)', ssCmds.every((c) => !c.includes('08-handoff')));
check('handoff-inline: nenhum comando SessionStart usa Get-Content (inline PowerShell removido)', ssCmds.every((c) => !/Get-Content/i.test(c)));

// ── nenhum comando do SessionStart usa powershell (os 3 eram os ultimos) ──
check('SessionStart tem ZERO comando powershell (todos os 3 portados)', ssCmds.every((c) => !/powershell/i.test(c)));

console.log('');
if (fails === 0) {
  console.log('[PASS] lote 5: brief-fetch + tier-a-banner + handoff-inline ativados como node no SessionStart (porte invocado, nao so escrito).');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- porte escrito mas NAO ativado (ou wiring ainda em powershell).`);
process.exit(1);
