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
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import {
  classifyDesignSync,
  isDesignSyncOptInPrompt,
  hasValidOptIn,
  READ_METHODS,
  OPT_IN_EXEMPLOS,
  denyMessage,
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

// ── 5. Opt-in por prompt — exige INTENÇÃO de publicar, não menção ─────────────
// Positivos: comando /design-sync OU nome-da-feature + verbo de publicar.
check('"/design-sync e sobe" dá opt-in (comando)', isDesignSyncOptInPrompt('roda o /design-sync e sobe o botão'));
check('"manda pro claude.ai/design" dá opt-in (alvo+verbo)', isDesignSyncOptInPrompt('manda o componente pro claude.ai/design'));
check('"sobe os componentes pro design-sync" dá opt-in', isDesignSyncOptInPrompt('sobe os componentes pro design-sync'));
check('"publica no design sync" dá opt-in', isDesignSyncOptInPrompt('publica isso no design sync'));
// ── REGRESSÃO do red-team 2026-06-30 (furo #2 fail-open): DISCUTIR não arma ───
check('FURO#2: "como funciona o design sync?" NÃO arma escrita', !isDesignSyncOptInPrompt('como funciona o design sync?'));
check('FURO#2: "o que é design-sync?" NÃO arma escrita', !isDesignSyncOptInPrompt('o que é design-sync?'));
check('FURO#2: "explica o claude.ai/design" NÃO arma', !isDesignSyncOptInPrompt('explica o claude.ai/design pra mim'));
check('FURO#2: "nunca usa design sync" NÃO arma (deny nunca/jamais)', !isDesignSyncOptInPrompt('nunca usa design sync aqui'));
check('"não é design-sync, é cowork" NÃO dá opt-in (negação)', !isDesignSyncOptInPrompt('não é design-sync, é cowork'));
check('"melhora o design da tela" NÃO dá opt-in (design ≠ design-sync)', !isDesignSyncOptInPrompt('melhora o design da tela de venda'));
check('"design system em git" NÃO dá opt-in (sem verbo de publicar)', !isDesignSyncOptInPrompt('o design system em git é a fonte'));
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

// ── RETORNO ao Cowork: a isenção da ADR 0412 SAIU (ADR 0438, 2026-10-23) ────────
// Prova invertida, e precisa ser DISCRIMINANTE: o cenário abaixo é exatamente o que a isenção
// deixava passar (canon mergeado em cowork-inbox/, localDir no espelho, blob igual a origin/main,
// plano medido antes do write_files). Com a isenção de volta, os dois casos saem exit 0; sem ela,
// exit 2. Um cenário que já falharia por outro motivo (sem localDir, por exemplo) não provaria nada.
const P = '019dcfd3-6ef2-7ee6-8512-b1b0e5544e58'; // projeto de TELAS (era RETORNO_PROJECT_ID)
const rec = 'cowork-inbox/placar/playbook/_saida-01.md';
function g(cwd, ...args) {
  const r = spawnSync('git', args, { cwd, encoding: 'utf8' });
  if (r.status !== 0) throw new Error(`git ${args.join(' ')}: ${r.stderr}`);
  return r.stdout.trim();
}
function runHookIn(cwd, tool_input) {
  const r = spawnSync(process.execPath, [HOOK], {
    cwd,
    input: JSON.stringify({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input }),
    encoding: 'utf8',
    env: { ...process.env, OIMPRESSO_DESIGN_SYNC_OK: '' },
  });
  return { code: r.status, stderr: r.stderr || '' };
}
const SB = mkdtempSync(join(tmpdir(), 'ds-retorno-'));
try {
  g(SB, 'init', '-q');
  g(SB, 'config', 'user.email', 't@t'); g(SB, 'config', 'user.name', 't'); g(SB, 'config', 'core.autocrlf', 'false');
  const ESP = join(SB, 'prototipo-ui', 'cowork', 'Wagner');
  const inbox = join(ESP, 'cowork-inbox', 'placar', 'playbook');
  mkdirSync(inbox, { recursive: true });
  writeFileSync(join(inbox, '_saida-01.md'), 'recibo mergeado' + String.fromCharCode(10));
  g(SB, 'add', '-A'); g(SB, 'commit', '-q', '-m', 'canon');
  g(SB, 'update-ref', 'refs/remotes/origin/main', 'HEAD');
  const fin = runHookIn(SB, { method: 'finalize_plan', projectId: P, localDir: ESP, writes: [rec], deletes: [] });
  check('FECHADO: finalize_plan de canon mergeado em cowork-inbox/ sem opt-in → exit 2', fin.code === 2);
  check('FECHADO: a mensagem diz que não há canal isento (ADR 0438)', /0438/.test(fin.stderr) && /não há canal isento/.test(fin.stderr));
  const wf = runHookIn(SB, { method: 'write_files', projectId: P, planId: 'x', files: [{ path: rec, localPath: rec }] });
  check('FECHADO: write_files por localPath em cowork-inbox/ sem opt-in → exit 2', wf.code === 2);
} finally {
  rmSync(SB, { recursive: true, force: true });
}

// ── CONTRATO do texto anunciado (LC-15): toda frase que a mensagem anuncia arma o opt-in ──
const msg = denyMessage('finalize_plan', 'x');
const anunciadas = [...msg.matchAll(/«([^»]+)»/g)].map((m) => m[1]);
check('CONTRATO: a mensagem anuncia ≥1 frase de opt-in', anunciadas.length >= 1);
check('CONTRATO: a mensagem anuncia exatamente OPT_IN_EXEMPLOS', JSON.stringify(anunciadas) === JSON.stringify(OPT_IN_EXEMPLOS));
for (const a of anunciadas) check(`CONTRATO: frase anunciada «${a}» passa no predicado`, isDesignSyncOptInPrompt(a));
check('CONTRATO: a promessa antiga (\'diga "design-sync" explícito\') sumiu', !/diga "design-sync" explícito/.test(msg));
check('CONTRATO: o nome sozinho segue NÃO armando (a mensagem diz isso)', !isDesignSyncOptInPrompt('design-sync') && /SOZINHO não arma/.test(msg));
check('E2E: tela sem opt-in → exit 2 (segue bloqueada)', runHook({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input: { method: 'finalize_plan', projectId: P, writes: ['clientes-page.jsx'], deletes: [] } }).code === 2);

console.log('');
if (fails === 0) {
  console.log('[PASS] block-design-sync: leitura livre, escrita gateada, default-deny, opt-in correto, E2E exit codes provados.');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s).`);
process.exit(1);
