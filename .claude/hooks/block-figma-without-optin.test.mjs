#!/usr/bin/env node
// Teste da LÓGICA de block-figma-without-optin (ADR 0299). Importa as funções puras e
// prova a classificação — incluindo os caminhos de fuga que o red-team adversarial achou
// (search_design_system, get_figjam), UUID diferente (Felipe ≠ Wagner) e o sabor plugin.
// Complementa settings-figma-registration.test.mjs (que prova que o hook está REGISTRADO).
//
// Rodar: node .claude/hooks/block-figma-without-optin.test.mjs   (exit 0 = passa)

import { classifyFigmaTool, isFigmaOptInPrompt, hasValidOptIn } from './block-figma-without-optin.mjs';

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK]   ' : '[FAIL] ') + name);
  if (!cond) fails++;
}

const WAGNER = '3c977f85-5f27-407f-8a6e-cbefde76e83d';
const FELIPE = 'deadbeef-0000-1111-2222-333344445555'; // outro UUID (mesma capability)

// ── Bloqueia o atrator principal ─────────────────────────────────────────────
check('get_design_context é Figma (não-benign)', (() => {
  const c = classifyFigmaTool(`mcp__${WAGNER}__get_design_context`);
  return c.isFigma && !c.benign;
})());
check('use_figma é Figma', classifyFigmaTool(`mcp__${WAGNER}__use_figma`).isFigma);

// ── Caminhos de FUGA que o refutador apontou (devem ser pegos) ────────────────
check('search_design_system é Figma (escape #1 do red-team)', classifyFigmaTool(`mcp__${WAGNER}__search_design_system`).isFigma);
check('get_figjam é Figma (escape #2 do red-team)', classifyFigmaTool(`mcp__${WAGNER}__get_figjam`).isFigma);
check('get_libraries é Figma', classifyFigmaTool(`mcp__${WAGNER}__get_libraries`).isFigma);

// ── Independente de UUID (Felipe em outra conta) ──────────────────────────────
check('get_design_context do Felipe (UUID diferente) também é Figma', classifyFigmaTool(`mcp__${FELIPE}__get_design_context`).isFigma);

// ── Sabor plugin (nome do servidor contém "figma") ───────────────────────────
check('plugin figma (nome) é Figma', classifyFigmaTool('mcp__plugin_product-management_figma__authenticate').isFigma);

// ── Benign: whoami de servidor figma conhecido passa (benign), e de desconhecido passa ──
check('whoami de servidor figma fingerprintado é benign (passa)', (() => {
  const c = classifyFigmaTool(`mcp__${WAGNER}__whoami`, [WAGNER]);
  return c.isFigma && c.benign;
})());

// ── Fingerprint: capability genérica de servidor já aprendido é pega ──────────
check('get_screenshot de servidor fingerprintado é Figma', classifyFigmaTool(`mcp__${WAGNER}__get_screenshot`, [WAGNER]).isFigma);
check('get_screenshot de servidor NÃO-figma desconhecido NÃO é Figma', !classifyFigmaTool('mcp__algum-outro-uuid__get_screenshot', []).isFigma);

// ── NÃO bloqueia tools legítimas (falso-positivo = veneno) ────────────────────
check('oimpresso brief-fetch NÃO é Figma', !classifyFigmaTool('mcp__Oimpresso_MCP___Wagner__brief-fetch').isFigma);
check('computer-use screenshot NÃO é Figma (cap=screenshot, não get_screenshot)', !classifyFigmaTool('mcp__computer-use__screenshot').isFigma);
check('file-MCP create_file NÃO é Figma (cap≠create_new_file)', !classifyFigmaTool('mcp__f79c55da-6380__create_file').isFigma);
check('tool não-MCP (Read) → isMcp false', !classifyFigmaTool('Read').isMcp);

// ── Opt-in por prompt ─────────────────────────────────────────────────────────
check('prompt de HOJE não dá opt-in (sem "figma")', !isFigmaOptInPrompt('agora quero fazer uma tela, e pegar a diff do desing para o code'));
check('"usa o figma desse link" dá opt-in', isFigmaOptInPrompt('usa o figma desse link aí'));
check('URL figma.com dá opt-in', isFigmaOptInPrompt('https://www.figma.com/file/abc/Tela'));
check('"não é figma, é cowork" NÃO dá opt-in (negação)', !isFigmaOptInPrompt('não é figma, é cowork mesmo'));
check('"a tela ta lenta" NÃO dá opt-in', !isFigmaOptInPrompt('a tela ta lenta, roda o smoke'));
check('"design da arquitetura de filas" NÃO dá opt-in', !isFigmaOptInPrompt('me explica o design da arquitetura de filas'));

// ── Opt-in válido via env (escape valve) ──────────────────────────────────────
const savedEnv = process.env.OIMPRESSO_FIGMA_OK;
process.env.OIMPRESSO_FIGMA_OK = '1';
check('OIMPRESSO_FIGMA_OK=1 concede opt-in', hasValidOptIn());
if (savedEnv === undefined) delete process.env.OIMPRESSO_FIGMA_OK; else process.env.OIMPRESSO_FIGMA_OK = savedEnv;

console.log('');
if (fails === 0) {
  console.log('[PASS] block-figma: classificação + opt-in corretos (atrator bloqueado, legítimas livres).');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s).`);
process.exit(1);
