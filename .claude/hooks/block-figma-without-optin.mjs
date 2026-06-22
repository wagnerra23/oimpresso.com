#!/usr/bin/env node
// block-figma-without-optin.mjs — Figma NÃO é fonte de design (block determinístico por tool_name).
//
// REGISTRADO em .claude/settings.json — em UserPromptSubmit (grava flag de opt-in) +
// PreToolUse com matcher que casa as tools do Figma. Criar o arquivo NÃO ativa nada;
// o REGISTRO é o que ativa — scripts/governance/settings-figma-registration.test.mjs
// guarda contra des-registro (mesmo padrão de block-pr-without-approval / R10).
//
// Documento-mãe: ADR 0298 (Figma não é fonte de design). Fonte de design canônica =
// protótipo Cowork (prototipo-ui/) + Design System (tokens) + charter — versão atual do DS e
// paths vivem só no INDEX-DESIGN-MEMORIAS.md §0 (este hook NÃO restata fato que apodrece).
//
// PROBLEMA (incidente 2026-06-22): o servidor MCP do Figma injeta, always-on, uma ORDEM
// imperativa no system prompt ("use este server SEMPRE que o usuário quiser criar/editar
// qualquer UI/tela/component — even if Figma isn't named"). Esse atrator semântico,
// persistente e NÃO-editável venceu o canon (Cowork = fonte), que vivia só em docs que o
// agente não consultou. Resultado: ao pedido "fazer uma tela / diff do design", o agente
// foi pro Figma. Texto-canon (nudge) NÃO vence ordem-de-system-prompt — só interceptar a
// AÇÃO (a tool call) vence. É o que este hook faz.
//
// CONFORMIDADE COM ADR 0224 (hooks block vs advisory): bloqueio legítimo = determinístico.
// Aqui o bloqueio é por `tool_name` (match de nome de tool — MESMA classe de block-automem
// que bloqueia por path), NÃO por regex semântica de prompt. A única parte semântica é a
// detecção de "figma" no prompt do Wagner, que apenas CONCEDE opt-in (direção fail-safe:
// errar pra menos só gera +1 round-trip, nunca vaza). Logo NÃO rebaixa o critério do 0224.
//
// MECÂNICA (padrão block-pr-without-approval.mjs):
//   1. UserPromptSubmit: se o prompt do Wagner contém "figma" (ou URL figma.com) e não é
//      negação → grava flag oimpresso-figma-allow.flag (TTL 15min). É o "Wagner disse figma
//      explícito" mecanizado. Não-consome (uma sessão Figma não re-pergunta a cada tool).
//   2. PreToolUse: se a tool é do Figma (ver classifyFigmaTool) e NÃO há opt-in válido →
//      BLOQUEIA (exit 2), stderr aponta pro INDEX + /design-diff. Com opt-in → permite.
//
// COBERTURA do Figma (denylist por capacidade + por nome-de-servidor + fingerprint):
//   - Casa por SUFIXO de capability (use_figma, get_design_context, get_figjam,
//     search_design_system, etc.) → sobrevive à troca de UUID por conta (Felipe ≠ Wagner).
//   - Casa por NOME de servidor contendo "figma" (cobre o sabor plugin
//     mcp__plugin_product-management_figma__*).
//   - FINGERPRINT: ao ver uma capability figma-única (STRONG), aprende o prefixo do servidor
//     e passa a gatear QUALQUER tool daquele servidor na sessão — fecha capabilities FUTURAS
//     do mesmo servidor (must-fix do red-team: não enumerar só o que existe hoje).
//
// HONESTIDADE (limitações conhecidas — ADR 0298 §Gaps):
//   - É denylist do atrator FIGMA. NÃO fecha a CLASSE inteira ("qualquer atrator não-canon
//     vira fonte"): Notion, screenshot de Chrome/Windows-MCP, link externo NÃO são gateados
//     aqui. Esses dependem do L0 (lista NÃO-fontes no INDEX, advisory). Fechar a classe com
//     block é trabalho futuro registrado no ADR.
//   - Uma capability NOVA, de um servidor Figma UUID-nomeado, cujo PRIMEIRO uso na sessão
//     seja ela mesma (sem STRONG antes pra fingerprint) e cujo nome não esteja na lista,
//     escapa até ser exercida uma capability STRONG. O eval L5 exercita os caminhos reais.
//   - Detecção de opt-in por palavra é REDE, não prova de escopo: garante "houve menção a
//     figma recente", não "Wagner aprovou ESTE uso". Conservador (fail-closed): na dúvida bloqueia.
//   - Escape valve: OIMPRESSO_FIGMA_OK=1 (env) ou arquivo .figma-allow na raiz (opt-in
//     persistente pra quem desenha em Figma de propósito).
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira razão pro Claude)

import { stdin } from 'node:process';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { existsSync, writeFileSync, readFileSync, unlinkSync, appendFileSync } from 'node:fs';

const FLAG = join(tmpdir(), 'oimpresso-figma-allow.flag');
const FINGERPRINT = join(tmpdir(), 'oimpresso-figma-servers.txt');
const TTL_MIN = 15;

// ── Capacidades do Figma ─────────────────────────────────────────────────────
// STRONG: nomes figma-únicos o bastante pra fingerprintar o servidor (e bloquear sempre).
const STRONG_FIGMA_CAPS = new Set([
  'use_figma',
  'get_design_context',
  'get_figjam',
  'get_variable_defs',
  'search_design_system',
  'get_libraries',
  'get_context_for_code_connect',
  'get_code_connect_map',
  'get_code_connect_suggestions',
  'add_code_connect_map',
  'send_code_connect_mappings',
]);
// GENERIC: capabilities cujo nome poderia (em tese) colidir com outro servidor — neste
// ambiente são figma, mas tratamos com cuidado: só contam DEPOIS que o servidor foi
// identificado como Figma (fingerprint), pra não falso-bloquear servidor não-figma homônimo.
const GENERIC_FIGMA_CAPS = new Set([
  'get_screenshot',
  'get_metadata',
  'download_assets',
  'upload_assets',
  'create_new_file',
  'generate_diagram',
]);
// BENIGN: identidade pura, sem conteúdo de design — nunca bloqueia.
const BENIGN_FIGMA_CAPS = new Set(['whoami']);

// ── Opt-in (concede; direção fail-safe) ──────────────────────────────────────
const figmaMention = /\bfigma\b/i;
const figmaUrl = /figma\.com/i;
// negação explícita CANCELA o opt-in (precedência) — "não é figma, é cowork".
// Janela de até 4 palavras após "não" (accent-safe: \b não casa após "é" em JS sem flag u).
const figmaDeny = /\bn[aã]o\s+(?:\S+\s+){0,4}figma/i;

export function isFigmaOptInPrompt(text) {
  if (!text) return false;
  if (figmaDeny.test(text)) return false;
  return figmaMention.test(text) || figmaUrl.test(text);
}

// ── Classificação da tool (lógica pura, importada pelo test + eval) ───────────
// Retorna: { isMcp, server, cap, isFigma, benign, reason }
export function classifyFigmaTool(toolName, learnedServers = readFingerprint()) {
  const m = /^mcp__([^_].*?)__(.+)$/.exec(toolName || '');
  if (!m) return { isMcp: false, isFigma: false, benign: false, reason: 'nao-mcp' };
  const server = m[1];
  const cap = m[2];

  const serverByName = /figma/i.test(server);
  // capability figma-única (lista) OU cujo próprio nome contém "figma" (ex generate_figma_design)
  const strong = STRONG_FIGMA_CAPS.has(cap) || /figma/i.test(cap);
  const generic = GENERIC_FIGMA_CAPS.has(cap);
  const learned = learnedServers.includes(server);

  // fingerprint: capability STRONG ou nome-figma identifica o servidor como Figma.
  const fingerprintHit = strong || serverByName;

  const benign = BENIGN_FIGMA_CAPS.has(cap);
  // É leitura-como-fonte do Figma se: nome do servidor é figma, OU capability figma-ÚNICA
  // (STRONG), OU qualquer capability de um servidor já fingerprintado como Figma (learned).
  // Capability GENÉRICA sozinha NÃO marca (evita falso-positivo em servidor não-figma que
  // compartilhe um nome genérico) — só conta depois que o servidor foi identificado (learned).
  const isFigma = serverByName || strong || learned;

  let reason = 'nao-figma';
  if (isFigma) {
    if (serverByName) reason = 'servidor-nome-figma';
    else if (strong) reason = 'capability-figma-unica';
    else if (learned) reason = generic ? 'capability-generica-servidor-fingerprintado' : 'servidor-fingerprintado';
  }
  return { isMcp: true, server, cap, isFigma, benign, fingerprintHit, reason };
}

// ── Opt-in válido? (flag TTL | env | arquivo) ─────────────────────────────────
export function hasValidOptIn(now = Date.now(), root = process.cwd()) {
  if (process.env.OIMPRESSO_FIGMA_OK === '1') return true;
  if (existsSync(join(root, '.figma-allow'))) return true;
  if (existsSync(FLAG)) {
    try {
      const ts = new Date(readFileSync(FLAG, 'utf8').trim());
      const ageMin = (now - ts.getTime()) / 60000;
      if (ageMin >= 0 && ageMin < TTL_MIN) return true;
    } catch {
      /* flag corrompida → trata como ausente */
    }
  }
  return false;
}

// ── Fingerprint store (servidores Figma vistos nesta sessão) ──────────────────
function readFingerprint() {
  try {
    return readFileSync(FINGERPRINT, 'utf8').split('\n').map((s) => s.trim()).filter(Boolean);
  } catch {
    return [];
  }
}
function learnServer(server) {
  try {
    if (!readFingerprint().includes(server)) appendFileSync(FINGERPRINT, server + '\n', 'utf8');
  } catch {
    /* silent */
  }
}

function denyMessage(server, cap) {
  return [
    '[BLOCKED: Figma não é fonte de design no oimpresso (ADR 0298)]',
    '',
    `Tool: mcp__${server}__${cap}`,
    '',
    'A FONTE DE DESIGN canônica é o protótipo Cowork (prototipo-ui/) + o Design System (tokens)',
    '+ charter da tela — NÃO o Figma. Resolva a fonte (versão atual do DS + paths) em:',
    '  memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md (§0 · Fontes e NÃO-fontes)',
    '',
    'Pra a DIFF design→code: a skill mwart-comparative gera o comparativo a partir do',
    'protótipo Cowork (um /design-diff determinístico está previsto no ADR 0298, ainda não existe).',
    '',
    'Se você REALMENTE quer usar o Figma de propósito: diga "figma" explícito no chat',
    '(ou OIMPRESSO_FIGMA_OK=1, ou crie .figma-allow na raiz) e tente de novo.',
  ].join('\n');
}

async function readStdin() {
  const chunks = [];
  for await (const c of stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  let raw;
  try {
    raw = await readStdin();
  } catch {
    process.exit(0);
  }
  if (!raw) process.exit(0);

  let p;
  try {
    p = JSON.parse(raw);
  } catch {
    process.exit(0);
  }

  const event = p.hook_event_name;

  // 1. UserPromptSubmit → grava flag de opt-in se o Wagner mencionou figma
  if (event === 'UserPromptSubmit') {
    if (isFigmaOptInPrompt(p.prompt || '')) {
      try {
        writeFileSync(FLAG, new Date().toISOString(), 'utf8');
      } catch {
        /* silent */
      }
    }
    process.exit(0);
  }

  // 2. PreToolUse → bloqueia tool do Figma sem opt-in
  if (event === 'PreToolUse') {
    const toolName = p.tool_name || '';
    const c = classifyFigmaTool(toolName);
    if (!c.isMcp || !c.isFigma) process.exit(0); // não é tool do Figma → segue

    // aprende o servidor (fecha capabilities futuras dele na sessão)
    if (c.fingerprintHit) learnServer(c.server);

    if (c.benign) process.exit(0); // whoami etc — identidade, sem design

    if (hasValidOptIn()) process.exit(0); // Wagner autorizou (flag/env/arquivo)

    process.stderr.write(denyMessage(c.server, c.cap) + '\n');
    process.exit(2);
  }

  process.exit(0);
}

// Só executa quando rodado como script (permite import puro no test/eval).
const invokedDirectly = process.argv[1] && process.argv[1].replace(/\\/g, '/').endsWith('block-figma-without-optin.mjs');
if (invokedDirectly) main();
