#!/usr/bin/env node
// Hook UserPromptSubmit — FORÇA R12 PROTOCOLO ao detectar sinal de fechamento.
//
// **Cross-platform** (Node.js — Windows / macOS / Linux).
// Funciona pra Wagner + Felipe + Maiara + Eliana + Luiz (qualquer OS).
// Node.js já é dependência do projeto (Vite + ESLint + npm).
//
// Camada 2 de ativação R12 (defesa em depth).
// Camada 1: skill `encerrar-sessao` Tier B description-match.
//
// Origem: Wagner 2026-05-28
//   "Isso tem que funcionar no MCP em todos os computadores"
//
// Diagnóstico: hook PowerShell anterior (`force-r12-closing-signal.ps1`) era
// Windows-only. Em Mac/Linux falhava silenciosamente (`powershell` não no PATH).
// Esta versão Node.js é universal.
//
// Como funciona:
//   1. UserPromptSubmit recebe JSON via stdin com `prompt` do user
//   2. Regex case-insensitive contra patterns de fechamento
//   3. Match → emite markdown em stdout (vira <system-reminder> no contexto Claude)
//   4. Sem match → exit 0 silencioso (zero overhead em 99% prompts)
//
// Patterns disparadores (regex case-insensitive, anchored):
//   - Explícito: encerrar/encerre/encerra, fim de sessão, vamos parar, continua depois,
//     outra sessão, próxima sessão, salvar tudo, salve as memórias, salve no protocolo,
//     salve na memória, vai pra MCP
//   - Cortesia: tchau, obrigado/obrigada, valeu (anchored ao início do prompt)
//   - Adiamento: depois eu vejo, fica pra depois, baixa prioridade
//
// Anti-pattern: "ok" / "fim" sozinhos = muitos false-positives. Exige forma específica.
//
// Refs: PROTOCOLO-WAGNER-SEMPRE.md R12 · skill encerrar-sessao · ADR 0130

import { stdin } from 'node:process';

/** Lê stdin completo (síncrono). */
async function readStdin() {
  const chunks = [];
  for await (const chunk of stdin) chunks.push(chunk);
  return Buffer.concat(chunks).toString('utf8');
}

const closingPatterns = [
  /\bencerrar?\b/i,
  /fim\s+(de|da)\s+sess[aã]o/i,
  /finalizar?\s+(a\s+)?sess[aã]o/i,
  /vamos\s+parar/i,
  /para\s+aqui/i,
  /continua?\s+depois/i,
  /continua?\s+(em\s+)?outra\s+sess[aã]o/i,
  /pr[oó]xima\s+sess[aã]o/i,
  /salvar?\s+tudo/i,
  /salve?\s+(as\s+)?mem[oó]rias?/i,
  /salve?\s+(no\s+)?protocolo/i,
  /salve?\s+(na\s+)?mem[oó]ria/i,
  /vai\s+pra?\s+mcp/i,
  /^tchau\b/i,
  /^obrigad[oa]\b/i,
  /^valeu\b/i,
  /depois\s+eu\s+vejo/i,
  /fica\s+pra?\s+depois/i,
  /baixa\s+prioridade/i,
];

(async () => {
  try {
    const raw = await readStdin();
    if (!raw) {
      process.exit(0);
    }

    let payload;
    try {
      payload = JSON.parse(raw);
    } catch {
      process.exit(0);
    }

    const prompt = payload.prompt;
    if (!prompt || typeof prompt !== 'string') {
      process.exit(0);
    }

    // Match contra patterns
    let matched = null;
    for (const pattern of closingPatterns) {
      const m = prompt.match(pattern);
      if (m) {
        matched = m[0];
        break;
      }
    }

    if (!matched) {
      process.exit(0);
    }

    // Emite system-reminder
    const reminder = `🔔 **R12 PROTOCOLO-WAGNER-SEMPRE — sinal de fechamento detectado** (hook \`force-r12-closing-signal.mjs\` cross-platform)

Pattern detectado: \`${matched}\`

**EXECUTE AGORA os 5 passos do R12** (carregue conteúdo via skill \`encerrar-sessao\` OU leia \`memory/reference/PROTOCOLO-WAGNER-SEMPRE.md\` §R12):

1. **MCP-first checklist**: \`cycles-active\` + \`my-work\` + \`Glob memory/handoffs/2026-MM-*.md\` + \`decisions-search\`
2. **Handoff append-only**: \`memory/handoffs/YYYY-MM-DD-HHMM-<slug>.md\` (~30-80 linhas, frontmatter completo)
3. **Atualizar índice**: linha NO TOPO de \`memory/08-handoff.md\` \`## Últimos handoffs\`
4. **Commit + push**: handoff + índice + tudo canon do trabalho
5. **Reportar ≤8 linhas**: tabela passos ✅/❌ + caveats + próxima ação

**CITE EXPLÍCITO** no report: \`"Cumprindo R12 PROTOCOLO via skill encerrar-sessao (ativação lazy via hook UserPromptSubmit)"\` — auditoria do mecanismo.

**Caso especial sessão curta (<2h, 0-1 PRs sem mudança canon)**: pular passo 1-2 OK, reportar "sessão curta — sem handoff" explícito.

Pareada com [R12](../../memory/reference/PROTOCOLO-WAGNER-SEMPRE.md) · [skill encerrar-sessao](../../.claude/skills/encerrar-sessao/SKILL.md) · [ADR 0130](../../memory/decisions/0130-handoff-append-only-mcp-first.md).`;

    process.stdout.write(reminder);
    process.exit(0);
  } catch (err) {
    // Falha silenciosa — nunca bloqueia user prompt
    process.exit(0);
  }
})();
