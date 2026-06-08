#!/usr/bin/env node
// Hook design-handoff-reprocess — detecta o bloco `## new_design_memories` num
// handoff do Claude Design e FORÇA a skill `design-memoria-reprocess` (gatilho G2
// RECONCILIAR). "Lembrete é esquecível; hook é garantido" — ADR 0224 / R12.
//
// **Cross-platform** (Node.js — Windows/macOS/Linux). Lição R12 2026-05-28
// aplicada: NUNCA .ps1 (Windows-only). Funciona pra todo dev do time MCP
// (Wagner/Felipe/Maiara/Eliana/Luiz, qualquer OS). Node já é dependência (Vite/npm).
//
// **Wiring que faltava do ADR 0236** (governança de evolução da doc de design).
// O contrato do handoff (ADR 0236 + SKILL.md design-memoria-reprocess §"Contrato")
// diz que TODO handoff do Claude Design carrega:
//
//   ## new_design_memories
//   - tipo: golden | conflito | anti-padrao | token | doc-novo
//   - ref: <path ou ADR>
//   - resumo: <1 linha>
//
// O handoff DECLARA; este hook EXECUTA (dispara G2). Sem este wiring, o bloco era
// só "pedido educado" — e pedido educado falha (ADR 0236 linha 61).
//
// Como funciona:
//   1. Recebe JSON via stdin. Dispara em DOIS vetores de chegada do handoff:
//        - UserPromptSubmit: handoff relayado como prompt do user  → payload.prompt
//        - PostToolUse(Write|Edit): handoff gravado em arquivo .md  → tool_input.content / .new_string
//   2. Procura o header `## new_design_memories` no texto relevante (regex multiline).
//   3. Match → emite markdown em stdout (vira <system-reminder>) mandando rodar a
//      skill `design-memoria-reprocess` (G2). Idempotente: stateless, 2× = mesmo efeito.
//   4. Sem match → exit 0 silencioso (zero overhead em 99% dos eventos).
//
// Idempotência (invariante A do ADR 0230/0236): o hook é puro — não escreve arquivo,
// não cria task, não muta estado. Rodar 2× produz o mesmo system-reminder. A própria
// skill G2 é append-only + idempotente, então re-disparar não duplica nada no índice.
//
// Não-destrutivo: nunca bloqueia (sempre exit 0). É um nudge, não um gate.
//
// Refs: ADR 0236 (governanca-evolucao-doc-design) · skill design-memoria-reprocess
//   (gatilho G2) · ADR 0224 (hook>lembrete) · R12 · INDEX-DESIGN-MEMORIAS.md

import { stdin } from 'node:process';

/** Lê stdin completo (assíncrono). */
async function readStdin() {
  const chunks = [];
  for await (const chunk of stdin) chunks.push(chunk);
  return Buffer.concat(chunks).toString('utf8');
}

// Header do bloco-contrato. Tolerante a:
//   - espaços antes/depois do `##`
//   - 2+ hashes (`##`, `###`)
//   - ancorado a início de linha (multiline) pra não casar menção inline em prosa
const BLOCK_HEADER = /^[ \t]*#{2,}[ \t]+new_design_memories\b/im;

/** Extrai o texto candidato conforme o evento de hook. */
function extractText(payload) {
  const event = payload.hook_event_name;

  // Vetor 1 — handoff relayado como prompt (igual force-r12-closing-signal.mjs).
  if (event === 'UserPromptSubmit') {
    return typeof payload.prompt === 'string' ? payload.prompt : '';
  }

  // Vetor 2 — handoff gravado em arquivo (igual audit-creates-tasks.mjs).
  // Write usa .content; Edit usa .new_string.
  if (event === 'PostToolUse' || event === 'PreToolUse') {
    const tool = payload.tool_name;
    if (tool !== 'Write' && tool !== 'Edit' && tool !== 'MultiEdit') return '';
    const ti = payload.tool_input ?? {};
    // MultiEdit: junta os new_string de todos os edits.
    if (Array.isArray(ti.edits)) {
      return ti.edits.map((e) => e?.new_string ?? '').join('\n');
    }
    return ti.content ?? ti.new_string ?? '';
  }

  // Evento desconhecido — tenta os campos mais prováveis sem assumir.
  if (typeof payload.prompt === 'string') return payload.prompt;
  return payload.tool_input?.content ?? payload.tool_input?.new_string ?? '';
}

(async () => {
  try {
    const raw = await readStdin();
    if (!raw) process.exit(0);

    let payload;
    try {
      payload = JSON.parse(raw);
    } catch {
      process.exit(0);
    }

    const text = extractText(payload);
    if (!text || typeof text !== 'string') process.exit(0);

    if (!BLOCK_HEADER.test(text)) process.exit(0);

    // Bloco detectado → força a skill design-memoria-reprocess (G2).
    const reminder = `🔁 **ADR 0236 — bloco \`## new_design_memories\` detectado num handoff do Claude Design** (hook \`design-handoff-reprocess.mjs\`)

Isto é o gatilho **G2 RECONCILIAR** do modelo de reprocesso event-driven. O handoff **declarou** memórias de design novas; o hook **garante** que elas sejam reconciliadas (lembrete é esquecível; hook é garantido — ADR 0224 + R12).

**RODE AGORA a skill \`design-memoria-reprocess\` (modo G2):**

1. **LER estado:** abrir [\`INDEX-DESIGN-MEMORIAS.md\`](../../memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md) + \`git diff\` desde \`last_reprocess\` (ponteiro no rodapé do índice).
2. **CLASSIFICAR** cada item do bloco \`new_design_memories\` por \`tipo\` (golden | conflito | anti-padrao | token | doc-novo) + \`ref\` + \`resumo\`.
3. **APLICAR a regra de ouro** (§0 do índice) mecanicamente: ADR recente c/ supersedes vence · DS v4 > v3 · código real > doc · UI-0013 hierarquia · data recente.
4. **ATUALIZAR só as linhas afetadas** — §2 (positivo) / §3 (negativo) / §4 (conflito: append linha nova + move o vencido p/ "aposentado") / §6 (stale). **Nunca reescreve o índice inteiro** (append-only + ratchet).
5. **VALIDAR:** índice não cita ADR \`superseded\` como canon vigente; toda linha tem fonte real (RTM, ADR 0230 Inv. B); ratchet OK (nada rebaixado/deletado).
6. **REGISTRAR:** bump \`last_reprocess: <data>\` no rodapé + 1 linha no changelog do índice.

**Invariantes (ADR 0230/0236):** idempotente (rodar 2× = mesmo resultado) · rastreável (toda linha cita a fonte). **NUNCA commit/push automático sem aprovação humana (R10).**

Se este bloco já foi reconciliado nesta sessão (índice já reflete os itens), reporte "G2 já aplicado — idempotente, sem mudança" e siga — não duplique entradas.

Pareado com [skill design-memoria-reprocess](../../.claude/skills/design-memoria-reprocess/SKILL.md) (gatilho G2) · [ADR 0236](../../memory/decisions/0236-governanca-evolucao-doc-design.md).`;

    process.stdout.write(reminder);
    process.exit(0);
  } catch {
    // Falha silenciosa — nunca bloqueia prompt nem tool.
    process.exit(0);
  }
})();
