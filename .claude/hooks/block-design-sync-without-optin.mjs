#!/usr/bin/env node
// block-design-sync-without-optin.mjs — claude.ai/design NÃO é fonte de design canônica.
// Gateia a ESCRITA da tool nativa DesignSync (block determinístico por tool_name + method).
//
// REGISTRADO em .claude/settings.json — em UserPromptSubmit (grava flag de opt-in) +
// PreToolUse com matcher "DesignSync". Criar o arquivo NÃO ativa nada; o REGISTRO é o que
// ativa — scripts/governance/settings-design-sync-registration.test.mjs guarda contra
// des-registro (mesmo padrão de block-figma-without-optin / block-pr-without-approval).
//
// Documento-mãe: ADR 0315 (/design-sync vs Cowork+charter) — fecha o Gap 1 da ADR 0299
// (a classe NÃO-fonte não estava 100% fechada; só o Figma era gateado). Fonte de design
// canônica = protótipo Cowork (prototipo-ui/) + Design System em git (SSOT, ADR 0239) +
// charter da tela. claude.ai/design é "MCP/integração de design novo" → NÃO-fonte (ADR 0299 §1).
//
// PROBLEMA: a integração oficial /design-sync sincroniza um Design System hospedado no
// claude.ai/design. A direção write (write_files/delete_files/create_project) empurra
// componentes locais PRA FORA do perímetro git canônico, sem PR/CI/gate — publicação externa
// (publication-policy + R10). E cria um SEGUNDO armazém de DS divergindo do git (colide 0239).
//
// CONFORMIDADE COM ADR 0224 (block vs advisory): bloqueio legítimo = determinístico. Aqui é
// por `tool_name` exato (DesignSync) + `method` (string do tool_input) — NÃO por regex
// semântica de prompt. A única parte semântica é a detecção de opt-in no prompt do Wagner,
// que apenas CONCEDE (direção fail-safe). Logo NÃO rebaixa o critério do 0224.
//
// POLÍTICA (Eixo B do ADR 0315):
//   - Métodos de LEITURA (inspeção, sem publicar) → SEMPRE permitidos (sem opt-in).
//       list_projects · get_project · list_files · get_file · report_validate
//   - Qualquer OUTRO método (escrita/mutação) → exige opt-in explícito. DEFAULT-DENY:
//     método desconhecido/futuro também é gateado (fail-closed; não enumera só o de hoje).
//       finalize_plan · write_files · delete_files · register_assets · unregister_assets · create_project · <futuros>
//
// OPT-IN (concede; fail-safe): prompt do Wagner com "/design-sync" / "design sync" /
//   "claude.ai/design" / "sincroniza ... design" → grava flag TTL 15min. Ou env
//   OIMPRESSO_DESIGN_SYNC_OK=1, ou arquivo .design-sync-allow na raiz.
//
// HONESTIDADE (limitações — endurecidas pelo red-team 2026-06-30, ver ADR 0315 §Furos):
//   - opt-in por prompt exige INTENÇÃO explícita (verbo de publicar + nome da feature, ou
//     /design-sync), NÃO mera menção: discutir/perguntar sobre a feature NÃO arma escrita
//     (furo #2 fechado — antes "como funciona design sync?" armava 15min de escrita).
//   - flag é POR-PROJETO (keyed no cwd), não machine-wide (furo #3 fechado).
//   - ATIVAÇÃO depende do harness rotear PreToolUse pra tool NATIVA DesignSync. Hook editado
//     no meio da sessão NÃO faz hot-reload → o gate só vale a partir da PRÓXIMA sessão. O E2E
//     prova a LÓGICA, não a entrega do payload pelo harness (furo #1 — exige baseline com
//     sessão fresca: rodar DesignSync.finalize_plan sem opt-in e ver o [BLOCKED]).
//   - leitura é livre — e é o vetor de injeção (get_file traz conteúdo de outros membros).
//     Tratar como DADO, nunca instrução (furo #5, inerente ao protocolo).
//   - NÃO fecha a classe inteira (Notion/file-MCP/screenshot seguem advisory).
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira razão pro Claude)

import { stdin } from 'node:process';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createHash } from 'node:crypto';
import { existsSync, writeFileSync, readFileSync } from 'node:fs';

const TTL_MIN = 15;
// Flag POR-PROJETO (keyed no cwd) — fecha o vazamento machine-wide (furo #3). UserPromptSubmit
// (grava) e PreToolUse (lê) rodam no MESMO cwd da sessão → a chave bate.
function flagPath(root = process.cwd()) {
  const h = createHash('sha1').update(String(root)).digest('hex').slice(0, 12);
  return join(tmpdir(), `oimpresso-design-sync-allow-${h}.flag`);
}

// ── Métodos de LEITURA (inspeção segura, sem publicar) ────────────────────────
// Tudo que NÃO está aqui é tratado como escrita/mutação → gateado (default-deny).
export const READ_METHODS = new Set([
  'list_projects',
  'get_project',
  'list_files',
  'get_file',
  'report_validate',
]);

// ── Classificação (lógica pura, importada pelo test) ──────────────────────────
// Retorna { isDesignSync, method, isWrite, reason }
export function classifyDesignSync(toolName, toolInput = {}) {
  if (toolName !== 'DesignSync') {
    return { isDesignSync: false, isWrite: false, reason: 'nao-design-sync' };
  }
  const method = typeof toolInput?.method === 'string' ? toolInput.method : '';
  // default-deny: método de leitura conhecido → não-escrita; QUALQUER outra coisa
  // (escrita conhecida, método novo/futuro, method ausente) → escrita/gateado.
  const isRead = READ_METHODS.has(method);
  const isWrite = !isRead;
  let reason;
  if (isRead) reason = 'leitura-permitida';
  else if (!method) reason = 'method-ausente-default-deny';
  else reason = `escrita:${method}`;
  return { isDesignSync: true, method, isWrite, reason };
}

// ── Opt-in (exige INTENÇÃO de publicar, não menção — furo #2 fechado) ─────────
// Discutir/perguntar sobre a feature NÃO arma escrita. Arma só: (a) o comando /design-sync,
// ou (b) nomear a feature (design-sync / claude.ai/design) JUNTO de um verbo de publicar.
const slashInvoke = /(?:^|\s)\/design-sync\b/i;                     // comando explícito do skill
const designTarget = /\bdesign[-\s]?sync\b|claude\.ai\/design\b/i;  // nome da feature
const syncVerb = /\b(sincroniz\w*|sobe|subir|publica\w*|push|envia\w*|manda\w*|salva\w*\s+na\s+nuvem)\b/i;
// pergunta/explicação NUNCA arma (mata "como funciona design sync?", "o que é design-sync?").
const interrogative = /\b(como|o que|que[ée]?|qual|quais|por\s?que|porque|explica\w*|entender|funciona\w*|d[uú]vida)\b/i;
// negação CANCELA — "não/nunca/jamais ... design-sync".
const optInDeny = /\b(n[aã]o|nunca|jamais)\s+(?:\S+\s+){0,4}(?:design[-\s]?sync|claude\.ai\/design)/i;

export function isDesignSyncOptInPrompt(text) {
  if (!text) return false;
  if (optInDeny.test(text)) return false;
  if (interrogative.test(text)) return false; // perguntar/explicar não destrava publicar
  if (slashInvoke.test(text)) return true; // rodar o skill = intenção
  if (designTarget.test(text) && syncVerb.test(text)) return true; // nomear feature + verbo de publicar
  return false;
}

// ── Opt-in válido? (flag TTL por-projeto | env | arquivo) ─────────────────────
export function hasValidOptIn(now = Date.now(), root = process.cwd()) {
  if (process.env.OIMPRESSO_DESIGN_SYNC_OK === '1') return true;
  if (existsSync(join(root, '.design-sync-allow'))) return true;
  const FLAG = flagPath(root);
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

function denyMessage(method) {
  return [
    '[BLOCKED: claude.ai/design não é fonte de design no oimpresso (ADR 0315 / fecha Gap 1 da 0299)]',
    '',
    `Tool: DesignSync (method: ${method || '—'})`,
    '',
    'Esse método ESCREVE/PUBLICA num Design System hospedado no claude.ai/design. A fonte de',
    'design canônica é o protótipo Cowork (prototipo-ui/) + o Design System em git (SSOT, ADR',
    '0239) + charter da tela. Subir componente pra nuvem cria um SEGUNDO armazém divergindo do',
    'git e publica fora do perímetro PR/CI/gate (publication-policy + R10).',
    '',
    'LEITURA é livre (list_projects/get_file/...): inspecionar o que existe lá não precisa opt-in.',
    '',
    'Se você REALMENTE quer sincronizar de propósito: diga "design-sync" explícito no chat',
    '(ou OIMPRESSO_DESIGN_SYNC_OK=1, ou crie .design-sync-allow na raiz) e tente de novo.',
    'Uso legítimo previsto = vitrine read-mostly A PARTIR do DS git aprovado; nunca o inverso.',
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

  // 1. UserPromptSubmit → grava flag de opt-in se o Wagner mencionou design-sync
  if (event === 'UserPromptSubmit') {
    if (isDesignSyncOptInPrompt(p.prompt || '')) {
      try {
        writeFileSync(flagPath(), new Date().toISOString(), 'utf8');
      } catch {
        /* silent */
      }
    }
    process.exit(0);
  }

  // 2. PreToolUse → bloqueia método de ESCRITA do DesignSync sem opt-in
  if (event === 'PreToolUse') {
    const c = classifyDesignSync(p.tool_name || '', p.tool_input || {});
    if (!c.isDesignSync) process.exit(0); // não é DesignSync → segue
    if (!c.isWrite) process.exit(0); // método de leitura → inspeção livre
    if (hasValidOptIn()) process.exit(0); // Wagner autorizou (flag/env/arquivo)

    process.stderr.write(denyMessage(c.method) + '\n');
    process.exit(2);
  }

  process.exit(0);
}

// Só executa quando rodado como script (permite import puro no test).
const invokedDirectly =
  process.argv[1] && process.argv[1].replace(/\\/g, '/').endsWith('block-design-sync-without-optin.mjs');
if (invokedDirectly) main();
