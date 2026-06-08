#!/usr/bin/env node
// TESTE DE REGRESSÃO DE GOVERNANÇA — ADR 0236 (G2 reconciliar via hook).
//
// Pergunta que este teste responde (espelho de block-pr-without-approval.test.mjs):
//   "O wiring continua disparando G2 mesmo SEM a skill ativa?"
//
// Prova que o gatilho G2 é enforçado pela MÁQUINA (hook design-handoff-reprocess.mjs),
// NÃO pela boa-vontade do handoff. Se o bloco `## new_design_memories` aparece, o hook
// emite o nudge pra rodar `design-memoria-reprocess`. Se o hook quebrar, este teste
// FALHA → você sabe que o wiring do ADR 0236 ficou órfão.
//
// Rodar: node .claude/hooks/design-handoff-reprocess.test.mjs
// Exit 0 = todos passam. Exit 1 = regressão.

import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const HOOK = join(__dirname, 'design-handoff-reprocess.mjs');

function runHook(payload) {
  const r = spawnSync('node', [HOOK], {
    input: JSON.stringify(payload),
    encoding: 'utf8',
  });
  return { code: r.status, stdout: r.stdout || '' };
}

// Emite o nudge se stdout cita a skill G2.
function fired(res) {
  return res.code === 0 && /design-memoria-reprocess/.test(res.stdout);
}

const BLOCK = `## new_design_memories
- tipo: golden
- ref: prototipo-ui/golden/cockpit-v4.html
- resumo: novo golden do cockpit DS v4`;

const HANDOFF_PROMPT = `Handoff Claude Design — tela Financeiro

Resumo das mudanças visuais aplicadas.

${BLOCK}

Fim do handoff.`;

let fails = 0;
function check(name, cond) {
  if (cond) {
    console.log(`[OK] ${name}`);
  } else {
    console.log(`[FAIL] ${name}`);
    fails++;
  }
}

// 1. Bloco via UserPromptSubmit (handoff relayado como prompt) → FORÇA G2.
check(
  'bloco em UserPromptSubmit → força G2',
  fired(runHook({ hook_event_name: 'UserPromptSubmit', prompt: HANDOFF_PROMPT })),
);

// 2. Bloco via PostToolUse(Write) (handoff gravado em .md) → FORÇA G2.
check(
  'bloco em PostToolUse(Write) → força G2',
  fired(
    runHook({
      hook_event_name: 'PostToolUse',
      tool_name: 'Write',
      tool_input: { file_path: 'memory/handoffs/2026-05-30-design.md', content: HANDOFF_PROMPT },
    }),
  ),
);

// 3. Bloco via Edit (.new_string) → FORÇA G2.
check(
  'bloco em PostToolUse(Edit) → força G2',
  fired(
    runHook({
      hook_event_name: 'PostToolUse',
      tool_name: 'Edit',
      tool_input: { file_path: 'x.md', new_string: HANDOFF_PROMPT },
    }),
  ),
);

// 4. Prompt SEM o bloco → silêncio (exit 0, stdout vazio). Não-destrutivo.
const noBlock = runHook({ hook_event_name: 'UserPromptSubmit', prompt: 'só um oi qualquer, sem handoff' });
check('sem bloco → silêncio (exit 0, sem nudge)', noBlock.code === 0 && noBlock.stdout === '');

// 5. Idempotência: rodar 2× = mesmo stdout (stateless, sem duplicar estado).
const r1 = runHook({ hook_event_name: 'UserPromptSubmit', prompt: HANDOFF_PROMPT });
const r2 = runHook({ hook_event_name: 'UserPromptSubmit', prompt: HANDOFF_PROMPT });
check('idempotente: 2× = mesmo output', r1.stdout === r2.stdout && r1.stdout.length > 0);

// 6. Menção inline em prosa (NÃO header) → NÃO dispara (evita false-positive).
check(
  'menção inline "new_design_memories" sem header → NÃO dispara',
  !fired(runHook({ hook_event_name: 'UserPromptSubmit', prompt: 'falando sobre new_design_memories no meio da frase' })),
);

// 7. Header com ### e espaços extras → dispara (tolerância de formatação).
check(
  'header ###  com espaços → dispara',
  fired(runHook({ hook_event_name: 'UserPromptSubmit', prompt: '###   new_design_memories\n- tipo: token' })),
);

// 8. stdin vazio / JSON inválido → exit 0 silencioso (robustez).
const emptyIn = spawnSync('node', [HOOK], { input: '', encoding: 'utf8' });
const badJson = spawnSync('node', [HOOK], { input: 'not json{', encoding: 'utf8' });
check('stdin vazio/inválido → exit 0 sem crash', emptyIn.status === 0 && badJson.status === 0);

console.log('');
if (fails === 0) {
  console.log('[PASS] G2 enforçado pela MÁQUINA — wiring ADR 0236 vivo. (8/8)');
  process.exit(0);
} else {
  console.log(`[FAIL] ${fails} caso(s) — wiring G2 NÃO garantido. Conserte o hook ANTES de mexer na skill.`);
  process.exit(1);
}
