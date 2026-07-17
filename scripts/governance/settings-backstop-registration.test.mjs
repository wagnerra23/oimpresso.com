#!/usr/bin/env node
// Teste de regressão: o BACKSTOP que o prompt-injection-corpus usa como oráculo
// (block-destructive.mjs + pii-redactor.mjs) continua REGISTRADO em .claude/settings.json
// no grupo PreToolUse que casa Bash.
//
// POR QUE ESTE TESTE EXISTE (o furo que ele fecha):
//   O corpus (.claude/governance-eval/prompt-injection-corpus.mjs) invoca os hooks pelo
//   CAMINHO DO ARQUIVO (spawnSync do .mjs). Isso prova que a LÓGICA do hook morde — e não
//   prova NADA sobre a ATIVAÇÃO. Se alguém remover a linha do settings.json, o hook para de
//   rodar no agente de verdade e o corpus SEGUE VERDE (continua achando o arquivo no disco).
//   Verde com a defesa desligada é o pior estado possível: teatro com certificado.
//   É o meta-padrão já catalogado em memory/proibicoes.md §5 (2026-07-09, "chokepoint de guard
//   em comando fantasma"): correção-do-mecanismo ≠ invocação. O mecanismo pode estar impecável
//   e nada no caminho real invocá-lo.
//
// Contrato-âncora (o que estes 2 hooks defendem — nenhuma asserção aqui deriva do código deles):
//   · block-destructive.mjs → memory/proibicoes.md §"REGRA ZERO" R10 (aprovação humana antes de
//     ação destrutiva) + §Ambiente (force-push/migrate:fresh/DROP).
//   · pii-redactor.mjs      → memory/proibicoes.md §"Multi-tenant Tier 0" ("PII reais NUNCA em
//     PR/commit/log") + LGPD Art. 7º.
//
// Mesmo padrão de settings-brl-values-registration.test.mjs / settings-figma-registration.test.mjs.
// Rodar: node scripts/governance/settings-backstop-registration.test.mjs   (exit 0 = passa)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SETTINGS = join(__dirname, '..', '..', '.claude', 'settings.json');

// Os hooks que o corpus alimenta como backstop (camada A). Se um sair do settings.json,
// o cenário do corpus que depende dele vira falso-verde.
const BACKSTOP = [
  { cmd: 'node .claude/hooks/block-destructive.mjs', nome: 'block-destructive', cenarios: 'A1..A5 (rm -rf, DROP, force-push, migrate:fresh, DELETE)' },
  { cmd: 'node .claude/hooks/pii-redactor.mjs', nome: 'pii-redactor', cenarios: 'A6 (CPF real ecoado num commit)' },
];

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK] ' : '[FAIL] ') + name);
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

// matcher é regex alternado ("Bash|PowerShell"); casa o tool inteiro, não substring.
function matcherCobre(m, tool) {
  try {
    return new RegExp(`^(?:${m})$`).test(tool);
  } catch {
    return String(m).split('|').includes(tool);
  }
}

for (const h of BACKSTOP) {
  let registrado = false;
  for (const g of groups) {
    const temCmd = (g.hooks || []).some((x) => x.command === h.cmd);
    if (temCmd && matcherCobre(String(g.matcher || ''), 'Bash')) registrado = true;
  }
  check(`${h.nome} registrado no PreToolUse que casa Bash  [backstop de ${h.cenarios}]`, registrado);
}

console.log('');
if (fails === 0) {
  console.log('[PASS] backstop do corpus ativado em Bash (registro persistido — o corpus mede defesa viva).');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) -- um hook do backstop saiu do settings.json.`);
console.log('       O corpus de injection SEGUE VERDE nesse estado (invoca o arquivo direto),');
console.log('       mas a defesa NAO roda no agente. Re-registre em .claude/settings.json.');
process.exit(1);
