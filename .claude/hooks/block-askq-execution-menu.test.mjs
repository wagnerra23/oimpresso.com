#!/usr/bin/env node
// Teste do block-askq-execution-menu.mjs — roda: node block-askq-execution-menu.test.mjs
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-askq-execution-menu.mjs');

// Roda o hook com um payload e devolve o exit code (0 = passou, 2 = bloqueou).
function run(payload, env = {}) {
  try {
    execFileSync('node', [HOOK], {
      input: JSON.stringify(payload),
      env: { ...process.env, ...env },
      stdio: ['pipe', 'pipe', 'pipe'],
    });
    return 0;
  } catch (e) {
    return e.status ?? -1;
  }
}

function askq(questions) {
  return { hook_event_name: 'PreToolUse', tool_name: 'AskUserQuestion', tool_input: { questions } };
}

let pass = 0;
let fail = 0;
function check(name, got, want) {
  if (got === want) {
    pass++;
    console.log(`  ok   ${name}`);
  } else {
    fail++;
    console.log(`  FAIL ${name} — esperado exit ${want}, veio ${got}`);
  }
}

// === DEVE BLOQUEAR (exit 2): menus de execução/fato ===
check('menu fecho-ou-investigo',
  run(askq([{ question: 'O que faço?', options: [
    { label: 'Fecho os 10 verdes', description: 'marco como done' },
    { label: 'Investigo os 6 amarelos', description: 'apuro primeiro' }] }])), 2);

check('menu cria task A ou B',
  run(askq([{ question: 'Como seguir?', options: [
    { label: 'Crio a task agora', description: 'abro a tarefa no MCP' },
    { label: 'Deixo pra depois', description: 'não cria task' }] }])), 2);

check('menu qual próximo passo',
  run(askq([{ question: 'Qual o próximo passo?', options: [
    { label: 'Deleto as branches', description: 'limpeza' },
    { label: 'Rodo o smoke', description: 'executar' }] }])), 2);

check('menu fato está feito',
  run(askq([{ question: 'A US-FIN-053 está feita?', options: [
    { label: 'Sim, está done', description: 'marcar done' },
    { label: 'Não', description: 'reabrir' }] }])), 2);

check('menu qual dos dois detalhar',
  run(askq([{ question: 'Qual dos dois detalhar?', options: [
    { label: 'Disparar a auditoria', description: 'rodar agora' },
    { label: 'Só explicar', description: 'não roda' }] }])), 2);

// === DEVE PASSAR (exit 0): decisões que só o Wagner sabe ===
check('escopo qual módulo',
  run(askq([{ question: 'Qual módulo audito?', options: [
    { label: 'Financeiro', description: 'o módulo Financeiro' },
    { label: 'OficinaAuto', description: 'o módulo de oficina' }] }])), 0);

check('UX persona',
  run(askq([{ question: 'Qual persona?', options: [
    { label: 'Larissa', description: 'vestuário biz=4' },
    { label: 'Eliana', description: 'outra persona' }] }])), 0);

check('produto preço',
  run(askq([{ question: 'Qual preço do plano?', options: [
    { label: 'R$ 99', description: 'plano básico' },
    { label: 'R$ 199', description: 'plano pro' }] }])), 0);

check('visual screenshot',
  run(askq([{ question: 'Qual screenshot vira produção?', options: [
    { label: 'Layout A', description: 'o mockup roxo' },
    { label: 'Layout B', description: 'o mockup claro' }] }])), 0);

// === Edge: execução MAS com sinal de escopo → permite (allow vence) ===
check('execucao+escopo deixa passar',
  run(askq([{ question: 'Crio a tela em qual módulo?', options: [
    { label: 'Financeiro', description: 'criar a tela no Financeiro' },
    { label: 'Compras', description: 'criar no módulo Compras' }] }])), 0);

// === Override desliga o bloqueio ===
check('override permite',
  run(askq([{ question: 'O que faço?', options: [
    { label: 'Fecho tudo', description: 'marco done' },
    { label: 'Investigo', description: 'apuro' }] }]), { OIMPRESSO_ASKQ_OVERRIDE: '1' }), 0);

// === Fail-open: payload de outra tool não bloqueia ===
check('outra tool ignora',
  run({ hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: 'ls' } }), 0);

console.log(`\n${pass} ok, ${fail} fail`);
process.exit(fail === 0 ? 0 : 1);
