#!/usr/bin/env node
// Teste de regressão: os 2 blockers do lote B (block-merge-markers + block-routes-string-legacy)
// continuam REGISTRADOS em .claude/settings.json — e registrados como `node` (não `powershell`).
//
// POR QUE (correção-do-mecanismo ≠ invocação · proibicoes.md §5 2026-07-09):
//   Portar o .ps1 pra .mjz não ativa nada: se a linha do settings.json não trocar junto, o Claude
//   Code segue chamando `powershell -File ...block-merge-markers.ps1` — um arquivo que o PR
//   DELETOU. Em Windows isso é erro; em Mac/Linux o `powershell` nem existe → exit 127 → o Claude
//   Code trata exit≠2 como não-bloqueante e o blocker evapora EM SILÊNCIO. Este teste é a trava.
//   A asserção `node` (e NÃO powershell) é o coração do porte: era exatamente o vetor que fazia o
//   time MCP (Felipe/Maiara/Eliana/Luiz, Mac/Linux) rodar sem defesa.
//
// Contrato-âncora (nenhuma asserção aqui deriva do código dos hooks):
//   · block-merge-markers      → memory/reference/post-mortem-v4-go-live.md §anti-pattern A (#1000/#1001)
//   · block-routes-string-legacy → .claude/rules/routes.md §"FQCN obrigatório" (incidente #843)
//   · triagem que mandou portar → memory/sessions/2026-07-09-triagem-hooks-ps1-subtracao.md (lote B, itens #6 e #7)
//
// Mesmo padrão de settings-automem-mwart-registration.test.mjs.
// Rodar: node scripts/governance/settings-merge-routes-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');

const PORTADOS = [
  { nome: 'block-merge-markers', cmd: 'node .claude/hooks/block-merge-markers.mjs' },
  { nome: 'block-routes-string-legacy', cmd: 'node .claude/hooks/block-routes-string-legacy.mjs' },
];
const TOOLS = ['Write', 'Edit', 'MultiEdit'];

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK]   ' : '[FAIL] ') + name);
  if (!cond) fails++;
}

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
  for (const tool of TOOLS) {
    const ok = groups.some((g) => (g.hooks || []).some((x) => x.command === h.cmd) && matcherCobre(String(g.matcher || ''), tool));
    check(`${h.nome} registrado como node no PreToolUse que casa ${tool}`, ok);
  }
  // O .ps1 foi deletado no porte — nenhuma linha pode continuar apontando pra ele.
  check(`${h.nome}: ZERO referencia ao .ps1 morto no settings.json`, !raw.includes(`${h.nome}.ps1`));
}

console.log('');
if (fails === 0) {
  console.log('[PASS] lote B: os 2 blockers ativados como node em Write/Edit/MultiEdit (porte invocado, nao so escrito).');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- porte escrito mas NAO ativado (ou apontando pro .ps1 deletado).`);
process.exit(1);
