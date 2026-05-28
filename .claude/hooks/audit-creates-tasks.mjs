#!/usr/bin/env node
// Hook PostToolUse(Write) — detecta tasks órfãs em audit doc + propõe tasks-create MCP.
//
// **Cross-platform** (Node.js — Windows/macOS/Linux). Lição R12 2026-05-28
// aplicada: NUNCA .ps1 (Windows-only). Funciona pra todo dev do time MCP.
//
// **Mecanismo 2 do ADR 0213** (audit-to-backlog loop fechado).
//
// Origem: R10 sessão Larissa 2026-05-28 — audit catalogou 11 gaps, 6 viraram
// órfãos no doc (fora do MCP backlog). 24h depois diagnóstico leu snapshot
// estático e listou 2 gaps JÁ fechados. Hook fecha o loop.
//
// Como funciona:
//   1. PostToolUse(Write) recebe JSON via stdin com tool_input.file_path + tool_input.content
//   2. Se path matcha `memory/sessions/*-audit-*.md` OU `memory/requisitos/*/AUDIT-*.md`
//   3. Parse linhas `- [ ] TASK[<owner>](P\d): <desc>` SEM `<!-- TASK_CREATED -->` ao lado
//   4. Se achou N tasks órfãs → emite system-reminder pro Claude propor tasks-create batch
//   5. Sem match → exit 0 silencioso (zero overhead em 99% dos Writes)
//
// NÃO cria task automaticamente — só LEMBRA Claude de propor (Wagner confirma 1×).
// Respeita ADR 0105: gap dormente usa `<!-- TASK_IGNORED: razão -->` e não dispara.
//
// Refs: ADR 0213 Mecanismo 2 · skill audit-to-backlog · template _TEMPLATE-audit.md

import { stdin } from 'node:process';

async function readStdin() {
  const chunks = [];
  for await (const chunk of stdin) chunks.push(chunk);
  return Buffer.concat(chunks).toString('utf8');
}

/** Path matcha um audit doc? */
function isAuditPath(filePath) {
  if (!filePath) return false;
  const p = filePath.replace(/\\/g, '/');
  // memory/sessions/*-audit-*.md  OU  memory/requisitos/<X>/AUDIT-*.md
  return (
    /memory\/sessions\/\d{4}-\d{2}-\d{2}-audit-.+\.md$/i.test(p) ||
    /memory\/requisitos\/[^/]+\/AUDIT-.+\.md$/i.test(p)
  );
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

    // PostToolUse payload: tool_name + tool_input
    const toolName = payload.tool_name;
    if (toolName !== 'Write' && toolName !== 'Edit') process.exit(0);

    const filePath = payload.tool_input?.file_path;
    if (!isAuditPath(filePath)) process.exit(0);

    // Conteúdo escrito: Write usa .content, Edit usa .new_string
    const content = payload.tool_input?.content ?? payload.tool_input?.new_string ?? '';
    if (!content) process.exit(0);

    // Parse linhas TASK[owner](Px): desc
    // Captura tasks SEM TASK_CREATED na mesma linha OU linha seguinte
    const lines = content.split('\n');
    const taskPattern = /^\s*-\s*\[ \]\s*TASK\[([a-z]+)\]\((P[0-3])\):\s*(.+)$/i;
    const orphanTasks = [];

    for (let i = 0; i < lines.length; i++) {
      const m = lines[i].match(taskPattern);
      if (!m) continue;

      // Já tem TASK_CREATED nesta linha ou nas próximas 4 (bullets Onde/Esforço/Impact)?
      const window = lines.slice(i, Math.min(i + 5, lines.length)).join('\n');
      if (window.includes('TASK_CREATED') || window.includes('TASK_IGNORED')) {
        continue; // já linkada OR ignorada conscientemente
      }

      orphanTasks.push({
        owner: m[1],
        priority: m[2],
        desc: m[3].trim().slice(0, 100),
      });
    }

    if (orphanTasks.length === 0) process.exit(0);

    // Emite system-reminder
    const taskList = orphanTasks
      .map((t, i) => `  ${i + 1}. [${t.owner}](${t.priority}) ${t.desc}`)
      .join('\n');

    const reminder = `📋 **ADR 0213 — Audit-to-backlog: ${orphanTasks.length} task(s) órfã(s) detectada(s)** (hook \`audit-creates-tasks.mjs\`)

Path: \`${filePath.replace(/\\/g, '/')}\`

Tasks no audit SEM \`<!-- TASK_CREATED -->\` correspondente:

${taskList}

**AÇÃO (ADR 0213 Mecanismo 2):**
1. Propor ao Wagner criar estas ${orphanTasks.length} task(s) via \`tasks-create\` MCP em batch (NÃO criar sem confirmação 1×)
2. Após Wagner confirmar + criar: escrever \`<!-- TASK_CREATED: US-MOD-NNN -->\` ao lado de cada item no audit doc
3. Gap dormente consciente (ADR 0105 cliente-como-sinal): marcar \`<!-- TASK_IGNORED: razão -->\` em vez de criar

Skill \`audit-to-backlog\` carrega o fluxo completo. NÃO deixar gap órfão — foi o R10 raiz da sessão Larissa.`;

    process.stdout.write(reminder);
    process.exit(0);
  } catch {
    process.exit(0);
  }
})();
