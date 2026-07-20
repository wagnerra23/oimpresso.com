#!/usr/bin/env node
// Teste de regressão: os 5 hooks do lote de porte .ps1→.mjs (US-GOV-052) continuam
// REGISTRADOS em .claude/settings.json — e registrados como `node` (não `powershell -File`),
// cada um no matcher correto.
//
// POR QUE (correção-do-mecanismo ≠ invocação · proibicoes.md §5 2026-07-09):
//   Portar o .ps1 pra .mjs não ativa nada sozinho: se a linha do settings.json não trocar
//   junto, o Claude Code segue chamando `powershell -File ...X.ps1`. Em Mac/Linux (time MCP
//   Felipe/Maiara/Luiz) o `powershell` nem existe → exit 127 → o Claude Code trata exit≠2 como
//   não-bloqueante e o hook evapora EM SILÊNCIO. A asserção `node` (e a ausência do comando
//   .ps1 na wiring) é o coração do porte.
//
// NOTA (ressalva do lote): este PR NÃO deleta o .ps1 do disco (port + wiring primeiro,
//   deleção em PR separado após validação — rollback barato). A asserção aqui é sobre a
//   WIRING (string de comando no settings.json), não sobre o arquivo no disco: a wiring foi
//   trocada pra `node .mjs`, então nenhum COMANDO `powershell -File ...X.ps1` pode sobrar.
//
// Contrato-âncora (nenhuma asserção deriva do código dos hooks):
//   · block-bom-encoding        → proibicoes.md §Ambiente (BOM PS 5.1 · post-mortem v4 · #984)
//   · block-serving-branch-switch → R8 (PROTOCOLO-WAGNER) + ADR 0233 (checkout serving)
//   · block-test-without-red / warn-red-first → SDD FV-T0 (plano 2026-06-12) + proibicoes §"Ideias descartadas"
//   · commit-discipline-check   → skill commit-discipline + ADR 0094 §5 + regras-time (PII)
//
// Mesmo padrão de settings-merge-routes-registration.test.mjs / settings-automem-mwart.
// Rodar: node scripts/governance/settings-portlote-ps1-mjs-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');

// cada hook + o(s) tool(s) que seu matcher DEVE cobrir (matcher difere por evento).
const PORTADOS = [
  { nome: 'block-bom-encoding', cmd: 'node .claude/hooks/block-bom-encoding.mjs', tools: ['Write', 'Edit', 'MultiEdit'] },
  { nome: 'block-test-without-red', cmd: 'node .claude/hooks/block-test-without-red.mjs', tools: ['Write', 'Edit', 'MultiEdit'] },
  { nome: 'warn-red-first', cmd: 'node .claude/hooks/warn-red-first.mjs', tools: ['Write', 'Edit', 'MultiEdit'] },
  { nome: 'block-serving-branch-switch', cmd: 'node .claude/hooks/block-serving-branch-switch.mjs', tools: ['Bash'] },
  { nome: 'commit-discipline-check', cmd: 'node .claude/hooks/commit-discipline-check.mjs', tools: ['Bash'] },
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
  // A wiring foi trocada — nenhum COMANDO pode continuar chamando o .ps1 (o arquivo no disco
  // ainda existe de propósito; aqui checamos a string de comando, que NÃO pode citar o .ps1).
  check(`${h.nome}: ZERO comando powershell -File apontando pro .ps1 na wiring`, !raw.includes(`${h.nome}.ps1`));
}

console.log('');
if (fails === 0) {
  console.log('[PASS] lote porte .ps1->.mjs: os 5 hooks ativados como node no matcher certo (porte invocado, nao so escrito).');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- porte escrito mas NAO ativado (ou wiring ainda apontando pro .ps1).`);
process.exit(1);
