#!/usr/bin/env node
// Teste de regressão: os 5 nudges advisory do lote 2 de porte .ps1→.mjs (US-GOV-052)
// continuam REGISTRADOS em .claude/settings.json — e registrados como `node` (não
// `powershell -File`), cada um no matcher correto.
//
// POR QUE (correção-do-mecanismo ≠ invocação · proibicoes.md §5 2026-07-09):
//   Portar o .ps1 pra .mjs não ativa nada sozinho: se a linha do settings.json não trocar
//   junto, o Claude Code segue chamando `powershell -File ...X.ps1`. Em Mac/Linux (time MCP)
//   o `powershell` nem existe → o nudge evapora EM SILÊNCIO. A asserção `node` (e a ausência
//   do comando .ps1 na wiring) é o coração do porte.
//
// NOTA (ressalva do lote): este PR NÃO deleta o .ps1 do disco (port + wiring primeiro,
//   deleção em PR separado — rollback barato). A asserção é sobre a WIRING (string de
//   comando), não sobre o arquivo no disco.
//
// Contrato-âncora (nenhuma asserção deriva do código dos hooks):
//   · memory-pending                    → skill memory-sync + how-trabalhar §fechamento (ADR 0070)
//   · nudge-recommend-not-menu          → R13 memory/reference/feedback-recomendar-nao-menu.md
//   · nudge-diagnosis-without-evidence  → R1 proibicoes §"Claim sem evidência" (sessão 2026-05-29)
//   · mcp-first-warning                 → skill mcp-first + how-trabalhar §tools MCP primeiro
//   · nudge-test-contract-anchor        → proibicoes §"Ideias descartadas" 2026-06-05 (Check 9)
//
// Mesmo padrão de settings-portlote-ps1-mjs-registration.test.mjs (lote 1).
// Rodar: node scripts/governance/settings-portlote2-nudges-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');

// cada hook + o(s) tool(s) que seu matcher DEVE cobrir (matcher difere por evento).
const PORTADOS = [
  { nome: 'memory-pending', cmd: 'node .claude/hooks/memory-pending.mjs', evento: 'Stop', tools: ['*'] },
  { nome: 'nudge-recommend-not-menu', cmd: 'node .claude/hooks/nudge-recommend-not-menu.mjs', evento: 'Stop', tools: ['*'] },
  { nome: 'nudge-diagnosis-without-evidence', cmd: 'node .claude/hooks/nudge-diagnosis-without-evidence.mjs', evento: 'Stop', tools: ['*'] },
  { nome: 'mcp-first-warning', cmd: 'node .claude/hooks/mcp-first-warning.mjs', evento: 'PreToolUse', tools: ['Read', 'Glob', 'Grep'] },
  { nome: 'nudge-test-contract-anchor', cmd: 'node .claude/hooks/nudge-test-contract-anchor.mjs', evento: 'PreToolUse', tools: ['Write', 'Edit', 'MultiEdit'] },
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
  // A wiring foi trocada — nenhum COMANDO pode continuar chamando o .ps1 (o arquivo no disco
  // ainda existe de propósito; aqui checamos a string de comando).
  check(`${h.nome}: ZERO comando powershell -File apontando pro .ps1 na wiring`, !raw.includes(`${h.nome}.ps1`));
}

console.log('');
if (fails === 0) {
  console.log('[PASS] lote 2 nudges: os 5 advisory ativados como node no evento/matcher certo (porte invocado, nao so escrito).');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- porte escrito mas NAO ativado (ou wiring ainda apontando pro .ps1).`);
process.exit(1);
