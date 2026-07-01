#!/usr/bin/env node
// block-skill-design-sync-without-optin.mjs — gateia a INVOCAÇÃO da skill /design-sync
// (a porta OFICIAL do fluxo claude.ai/design) sem opt-in explícito do Wagner.
//
// POR QUE ESTE HOOK EXISTE (companheiro do block-design-sync-without-optin.mjs):
//   O gate irmão casa matcher "DesignSync" (a tool nativa). Diagnóstico de runtime
//   (ADR 0315 §Furos #1 + §Decisão adversarial, sessões wizardly-elion / amazing-tereshkova
//   2026-06-30/07-01) PROVOU que o harness NÃO roteia PreToolUse pra tool nativa
//   `DesignSync` — ela é *surfaced-as-skill* (fora da lista canônica de built-in tools),
//   então o hook por "DesignSync" é INERTE em runtime. A entrada REAL no namespace de
//   hooks é a tool `Skill` (built-in de verdade — hooks mordem). `/design-sync` roda
//   PELA tool `Skill`. Logo gatear `Skill` fecha a porta OFICIAL do fluxo de sincronização.
//
// O QUE FAZ:
//   PreToolUse matcher "Skill" → se a skill invocada for `design-sync` (ou variante
//   plugin-namespaced `plugin:design-sync`) E não houver opt-in → BLOQUEIA (exit 2).
//   Qualquer OUTRA skill → segue (exit 0 imediato; custo trivial).
//
// CONFORMIDADE ADR 0224 (block vs advisory): bloqueio determinístico — casa a tool
//   `Skill` + o NOME da skill (string exata), NÃO regex semântica. A única parte
//   semântica é a detecção de opt-in no prompt (só CONCEDE; direção fail-safe).
//
// OPT-IN (compartilhado com o gate irmão — MESMA flag/env/arquivo, reusa hasValidOptIn):
//   - env OIMPRESSO_DESIGN_SYNC_OK=1, ou arquivo .design-sync-allow na raiz, ou
//   - flag TTL 15min gravada pelo block-design-sync-without-optin.mjs no UserPromptSubmit
//     quando o Wagner expressa INTENÇÃO de publicar (verbo + nome, ou /design-sync).
//   Este hook NÃO grava flag (não precisa de UserPromptSubmit próprio) — só LÊ o opt-in.
//
// LIMITAÇÕES HONESTAS (ADR 0315):
//   - Fecha a entrada OFICIAL (a skill). NÃO pega chamada DIRETA à tool `DesignSync` sem
//     passar pela skill — mas essa é justamente a que o harness não roteia (inerte) e que,
//     em headless, já falha na auth do /design-login. O risco real (publicação) vive em
//     claude.ai/code logado no claude.ai/design; lá estes hooks locais podem nem rodar.
//   - Coarse: bloqueia a skill inteira (não distingue leitura/escrita DENTRO da skill).
//     Aceitável — opt-in é barato e o fluxo oficial começa na invocação.
//   - RUNTIME-BITE da tool `Skill` é built-in-por-doc (alta confiança), mas a prova
//     literal (o [BLOCKED] aparecendo ao rodar /design-sync sem opt-in) só é obtível
//     numa sessão bootada COM este hook registrado — i.e. DEPOIS deste PR mergear no main
//     (todo worktree fresco é cortado do main). Ver ADR 0315 §Regra de fechamento.
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira razão pro Claude)

import { stdin } from 'node:process';
import { hasValidOptIn } from './block-design-sync-without-optin.mjs';

// ── Nome(s) da skill-alvo ──────────────────────────────────────────────────────
// A tool `Skill` recebe o nome resolvido em `skill` (ex.: "design-sync" ou, se vier
// de plugin, "algum-plugin:design-sync"). Casa o segmento final == design-sync.
const DESIGN_SYNC_SKILL = /(?:^|:)design-sync$/i;

// ── Classificação (lógica pura, importada pelo test) ──────────────────────────
// Retorna { isSkill, isDesignSyncSkill, skillName }
export function classifySkillCall(toolName, toolInput = {}) {
  if (toolName !== 'Skill') {
    return { isSkill: false, isDesignSyncSkill: false, skillName: '' };
  }
  const skillName = typeof toolInput?.skill === 'string' ? toolInput.skill : '';
  const isDesignSyncSkill = DESIGN_SYNC_SKILL.test(skillName.trim());
  return { isSkill: true, isDesignSyncSkill, skillName };
}

function denyMessage(skillName) {
  return [
    '[BLOCKED: /design-sync (claude.ai/design) não é fonte de design no oimpresso (ADR 0315 / fecha Gap 1 da 0299)]',
    '',
    `Skill: ${skillName || '—'}`,
    '',
    'A skill /design-sync sincroniza um Design System hospedado no claude.ai/design — a direção',
    'write empurra componentes locais PRA FORA do perímetro git canônico (publicação externa,',
    'publication-policy + R10) e cria um SEGUNDO armazém de DS divergindo do git (colide ADR 0239).',
    'A fonte de design canônica é o protótipo Cowork (prototipo-ui/) + Design System em git (SSOT)',
    '+ charter da tela.',
    '',
    'Se você REALMENTE quer sincronizar de propósito: diga "design-sync" explícito no chat',
    '(ou OIMPRESSO_DESIGN_SYNC_OK=1, ou crie .design-sync-allow na raiz) e invoque de novo.',
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

  if (p.hook_event_name !== 'PreToolUse') process.exit(0);

  const c = classifySkillCall(p.tool_name || '', p.tool_input || {});
  if (!c.isSkill) process.exit(0); // não é Skill → segue
  if (!c.isDesignSyncSkill) process.exit(0); // outra skill qualquer → segue
  if (hasValidOptIn()) process.exit(0); // Wagner autorizou (flag/env/arquivo)

  process.stderr.write(denyMessage(c.skillName) + '\n');
  process.exit(2);
}

// Só executa quando rodado como script (permite import puro no test).
const invokedDirectly =
  process.argv[1] && process.argv[1].replace(/\\/g, '/').endsWith('block-skill-design-sync-without-optin.mjs');
if (invokedDirectly) main();
