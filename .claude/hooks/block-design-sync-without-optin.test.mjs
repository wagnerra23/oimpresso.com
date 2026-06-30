#!/usr/bin/env node
// Teste da LÓGICA de block-design-sync-without-optin (ADR 0315 / fecha Gap 1 da 0299).
// Importa as funções puras e prova a classificação read-vs-write (incl. default-deny de
// método futuro/ausente), o opt-in por prompt, e o end-to-end real (spawn do hook → exit code).
// Complementa settings-design-sync-registration.test.mjs (que prova que o hook está REGISTRADO).
//
// Rodar: node .claude/hooks/block-design-sync-without-optin.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import {
  classifyDesignSync,
  isDesignSyncOptInPrompt,
  hasValidOptIn,
  READ_METHODS,
} from './block-design-sync-without-optin.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const HOOK = join(HERE, 'block-design-sync-without-optin.mjs');

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK]   ' : '[FAIL] ') + name);
  if (!cond) fails++;
}

// ── 1. Leitura é sempre permitida (não-escrita, sem opt-in) ───────────────────
for (const m of ['list_projects', 'get_project', 'list_files', 'get_file', 'report_validate']) {
  check(`leitura "${m}" NÃO é escrita (livre)`, classifyDesignSync('DesignSync', { method: m }).isWrite === false);
}

// ── 2. Escrita conhecida é gateada ────────────────────────────────────────────
for (const m of ['finalize_plan', 'write_files', 'delete_files', 'register_assets', 'unregister_assets', 'create_project']) {
  check(`escrita "${m}" é gateada (isWrite)`, classifyDesignSync('DesignSync', { method: m }).isWrite === true);
}

// ── 3. DEFAULT-DENY: método futuro/desconhecido e method ausente → gateado ─────
check('método FUTURO desconhecido → gateado (default-deny)', classifyDesignSync('DesignSync', { method: 'publish_to_org_xpto' }).isWrite === true);
check('method AUSENTE → gateado (default-deny, fail-closed)', classifyDesignSync('DesignSync', {}).isWrite === true);
check('method vazio "" → gateado', classifyDesignSync('DesignSync', { method: '' }).isWrite === true);

// ── 4. Não-DesignSync passa reto (falso-positivo = veneno) ────────────────────
check('Write (tool nativa) NÃO é DesignSync', classifyDesignSync('Write', { method: 'write_files' }).isDesignSync === false);
check('tool MCP qualquer NÃO é DesignSync', classifyDesignSync('mcp__Oimpresso_MCP___Wagner__brief-fetch', {}).isDesignSync === false);
check('READ_METHODS cobre exatamente os 5 de leitura', READ_METHODS.size === 5);

// ── 5. Opt-in por prompt ──────────────────────────────────────────────────────
check('"/design-sync" dá opt-in', isDesignSyncOptInPrompt('roda o /design-sync e sobe o botão'));
check('"design sync" (espaço) dá opt-in', isDesignSyncOptInPrompt('quero usar o design sync agora'));
check('"claude.ai/design" dá opt-in', isDesignSyncOptInPrompt('manda pro claude.ai/design'));
check('"sincronizar o design" dá opt-in', isDesignSyncOptInPrompt('pode sincronizar o design system pra nuvem'));
check('"não é design-sync, é cowork" NÃO dá opt-in (negação)', !isDesignSyncOptInPrompt('não é design-sync, é cowork'));
check('"melhora o design da tela" NÃO dá opt-in', !isDesignSyncOptInPrompt('melhora o design da tela de venda'));
check('prompt vazio NÃO dá opt-in', !isDesignSyncOptInPrompt(''));

// ── 6. Opt-in válido via env (escape valve) ───────────────────────────────────
const savedEnv = process.env.OIMPRESSO_DESIGN_SYNC_OK;
process.env.OIMPRESSO_DESIGN_SYNC_OK = '1';
check('OIMPRESSO_DESIGN_SYNC_OK=1 concede opt-in', hasValidOptIn());
if (savedEnv === undefined) delete process.env.OIMPRESSO_DESIGN_SYNC_OK; else process.env.OIMPRESSO_DESIGN_SYNC_OK = savedEnv;

// ── 7. END-TO-END: spawn do hook, prova exit code real ────────────────────────
function runHook(payload, env = {}) {
  const r = spawnSync(process.execPath, [HOOK], {
    input: JSON.stringify(payload),
    encoding: 'utf8',
    env: { ...process.env, OIMPRESSO_DESIGN_SYNC_OK: '', ...env },
  });
  return { code: r.status, stderr: r.stderr || '' };
}

// write_files SEM opt-in → exit 2 (BLOQUEIA) — este é o Gap que se fecha
const e2eBlock = runHook({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input: { method: 'write_files' } });
check('E2E: write_files sem opt-in → exit 2 (BLOQUEIA)', e2eBlock.code === 2);
check('E2E: stderr cita ADR 0315', /0315/.test(e2eBlock.stderr));

// create_project SEM opt-in → exit 2
check('E2E: create_project sem opt-in → exit 2', runHook({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input: { method: 'create_project' } }).code === 2);

// list_projects (leitura) SEM opt-in → exit 0 (inspeção livre)
check('E2E: list_projects sem opt-in → exit 0 (livre)', runHook({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input: { method: 'list_projects' } }).code === 0);

// write_files COM opt-in (env) → exit 0
check('E2E: write_files COM opt-in (env) → exit 0', runHook({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input: { method: 'write_files' } }, { OIMPRESSO_DESIGN_SYNC_OK: '1' }).code === 0);

// tool não-DesignSync → exit 0
check('E2E: tool Write → exit 0 (não é DesignSync)', runHook({ hook_event_name: 'PreToolUse', tool_name: 'Write', tool_input: {} }).code === 0);

// método futuro desconhecido SEM opt-in → exit 2 (default-deny end-to-end)
check('E2E: método futuro sem opt-in → exit 2 (default-deny)', runHook({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input: { method: 'sync_everything_v2' } }).code === 2);

console.log('');
if (fails === 0) {
  console.log('[PASS] block-design-sync: leitura livre, escrita gateada, default-deny, opt-in correto, E2E exit codes provados.');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s).`);
process.exit(1);
