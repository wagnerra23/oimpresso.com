#!/usr/bin/env node
// block-pr-without-approval.mjs — R10 enforcement POR MÁQUINA (cross-platform).
//
// PROPOSTA (2026-05-28) — ainda NÃO registrada em settings.json. Criar o arquivo
// não ativa nada; só o registro ativa. Demo do padrão "regra → enforcement por
// máquina" pedido por Wagner.
//
// Problema: R10 do PROTOCOLO-WAGNER-SEMPRE ("aprovação humana antes de
// commit/push/merge/PR") era só ORIENTAÇÃO (skill wagner-protocol-enforce).
// Orientação o modelo ignora em sessão longa — nesta sessão (2026-05-28) o
// Claude abriu PRs #1905/#1906 sem aprovação. Este hook mecaniza a regra:
// vira independente de skill (não depende do modelo "lembrar").
//
// Mecânica (padrão de post-merge-ui-smoke-required.ps1 + force-r12.mjs):
//   1. UserPromptSubmit: se a mensagem do Wagner contém sinal de aprovação de
//      PUBLICAÇÃO (pode fazer/pode pushar/merge/manda/aprovado...), grava flag
//      com timestamp em tmpdir. TTL 15min.
//   2. PreToolUse Bash: se o Claude tenta `gh pr create|merge` / `git push`,
//      exige flag válida. Sem flag (ou expirada) → BLOQUEIA (exit 2).
//      Com flag → permite e CONSOME (1 aprovação = 1 publicação).
//
// HONESTIDADE (limitações conhecidas):
//   - Detecção por palavra-chave é uma REDE, não prova de escopo. Garante "houve
//     sinal de aprovação recente", não "Wagner aprovou ESTE push específico".
//   - Conservador: na dúvida, NÃO aprova (falso-negativo < falso-positivo).
//   - Escape valve: OIMPRESSO_PR_APPROVAL_OVERRIDE=1 (justificar no chat).
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira razão pro Claude)

import { stdin } from 'node:process';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { existsSync, writeFileSync, readFileSync, unlinkSync } from 'node:fs';

const FLAG = join(tmpdir(), 'oimpresso-pr-approval.flag');
const TTL_MIN = 15;

// Sinais FORTES de aprovação de publicação (não qualquer "sim" solto).
const approvePatterns = [
  /\bpode\s+(fazer|commitar|comitar|pushar|push|subir|mergear|merge|criar|abrir|publicar|mandar)\b/i,
  /\b(faça|faz|abre|abra|cria|crie)\s+o\s+pr\b/i,
  // 'merge' SO com verbo de ordem — nao casa 'merge' incidental ("estrategia de merge", "merge conflict")
  /\b(pode\s+mergear|faz(?:\s+o)?\s+merge|mergeia\s+(?:o|os|esse|essa|a|isso|agora)|manda\s+(?:o\s+)?merge|aprovo\s+o\s+merge)\b/i,
  /\b(aprovado|autorizado|manda\s+ver)\b/i,
  // publicar/subir SO com objeto/ordem — nao 'publica/suba' solto incidental
  /\b(suba|sobe|publica|publique)\s+(?:o|os|essa|esse|isso|pra|agora|tudo)\b/i,
  /\bvai\s+em\s+frente\b/i,
  /^\s*(pode|sim,?\s*pode|ok,?\s*pode)\b/i,
];

// Negação que CANCELA o sinal (precedência sobre approve).
const denyPatterns = [
  /\bn[aã]o\s+(pode|fa[cç]a|comite|comita|push|pushe|suba|mergeie|crie)\b/i,
  /\b(pare|espera|aguarda|aguarde|nunca|cancela|cancele|n[aã]o\s+merge)\b/i,
];

function isApproval(text) {
  if (!text) return false;
  if (denyPatterns.some((r) => r.test(text))) return false;
  return approvePatterns.some((r) => r.test(text));
}

// Ancorados no inicio efetivo do comando (apos ; & |) com limite de palavra —
// evita falso-bloqueio quando a frase aparece dentro de string/comentario/arg de busca
// (ex: rg 'git push', history | grep 'git push').
const publishPatterns = [/(^|[;&|]\s*)gh\s+pr\s+(create|merge)(\s|$)/i, /(^|[;&|]\s*)git\s+push(\s|$)/i];
function isPublish(cmd) {
  return !!cmd && publishPatterns.some((r) => r.test(cmd));
}

async function readStdin() {
  const chunks = [];
  for await (const c of stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

(async () => {
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

  if (process.env.OIMPRESSO_PR_APPROVAL_OVERRIDE === '1') process.exit(0);

  const event = p.hook_event_name;

  // 1. UserPromptSubmit → grava flag de aprovação
  if (event === 'UserPromptSubmit') {
    if (isApproval(p.prompt || '')) {
      try {
        writeFileSync(FLAG, new Date().toISOString(), 'utf8');
      } catch {
        /* silent */
      }
    }
    process.exit(0);
  }

  // 2. PreToolUse Bash/PowerShell → exige aprovação válida pra publicar
  //    (PowerShell e o shell primario deste ambiente — sem isto o R10 era contornavel)
  if (event === 'PreToolUse' && (p.tool_name === 'Bash' || p.tool_name === 'PowerShell')) {
    const cmd = p.tool_input?.command || '';
    if (!isPublish(cmd)) process.exit(0);

    let approved = false;
    if (existsSync(FLAG)) {
      try {
        const ts = new Date(readFileSync(FLAG, 'utf8').trim());
        const ageMin = (Date.now() - ts.getTime()) / 60000;
        if (ageMin >= 0 && ageMin < TTL_MIN) approved = true;
      } catch {
        /* flag corrompida → trata como ausente */
      }
    }

    if (approved) {
      try {
        unlinkSync(FLAG);
      } catch {
        /* silent */
      }
      process.exit(0);
    }

    const msg = [
      '[BLOCKED: R10 — aprovação humana antes de publicar]',
      '',
      `Comando: ${cmd}`,
      '',
      'PROTOCOLO-WAGNER-SEMPRE R10 (memory/proibicoes.md): push/PR/merge exige',
      `aprovação humana EXPLÍCITA. Não detectei sinal de aprovação do Wagner nos`,
      `últimos ${TTL_MIN}min.`,
      '',
      'A FAZER: peça aprovação explícita ("pode fazer o PR?") e aguarde o',
      '"pode / sim / manda / merge" do Wagner ANTES de publicar.',
      '',
      'Escape (raro, justifique no chat): OIMPRESSO_PR_APPROVAL_OVERRIDE=1',
    ].join('\n');
    process.stderr.write(msg + '\n');
    process.exit(2);
  }

  process.exit(0);
})();
