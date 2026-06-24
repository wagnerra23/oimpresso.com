#!/usr/bin/env node
// block-pr-without-approval.mjs — R10 enforcement POR MÁQUINA (cross-platform).
//
// REGISTRADO em .claude/settings.json (PR #3058; endurecido #3065 + review adversarial
// 2026-06-20) — em UserPromptSubmit + PreToolUse(Bash|PowerShell). Criar o arquivo não
// ativa nada; o REGISTRO é o que ativa — scripts/governance/settings-r10-registration.test.mjs
// guarda contra des-registro. Padrão "regra → enforcement por máquina" pedido por Wagner.
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
//   1b. Afirmativo CURTO ("ok", "aprovo", "blz", "isso", "fechou"...) é ambíguo
//      isolado, então só conta como aprovação quando o ÚLTIMO turno do assistente
//      (lido do `transcript_path` do payload) perguntou/ofereceu PUBLICAR ("abro o
//      PR?", "posso mergear?", "quer que eu commite + pushe?"). Casa a intenção real
//      do Wagner (responder "ok" a uma pergunta de publicação É aprovação genuína)
//      SEM afrouxar: "ok" solto no meio de outra conversa NÃO cria flag. Incidente
//      origem: PR #3358 (Wagner respondeu "ok"/"aprovo" a "commito + abro o PR?" e
//      o hook não casou — só passou via OIMPRESSO_PR_APPROVAL_OVERRIDE=1).
//   2. PreToolUse Bash/PowerShell: se o Claude tenta PUBLICAR — `gh pr create|merge`,
//      `gh api` escrevendo em /pulls (criar PR) ou /pulls/N/merge, ou `git push`
//      (inclusive `ENV=val git push` e `git -c k=v push`) — exige flag válida.
//      Sem flag (ou expirada) → BLOQUEIA (exit 2). Com flag → permite e CONSOME.
//
// HONESTIDADE (limitações conhecidas):
//   - Detecção por palavra-chave é uma REDE, não prova de escopo. Garante "houve
//     sinal de aprovação recente", não "Wagner aprovou ESTE push específico".
//   - Cobre gh pr create|merge, gh api escrevendo /pulls|/pulls/N/merge, e git push
//     (+ env-prefix e flags `git -c`). NÃO cobre evasão deliberada exótica (alias
//     custom, script intermediário). A defesa real de publicação é branch protection
//     + enforce_admins (já ativos) — este hook é o PRIMEIRO filtro, não o último.
//   - Conservador: na dúvida, NÃO aprova (falso-negativo < falso-positivo). Lê (GET)
//     e comentários (/pulls/N/comments, /issues/N/comments) NÃO bloqueiam.
//   - Afirmativo curto (1b) é gateado pelo CONTEXTO, não solto: exige que o assistente
//     tenha oferecido publicar no turno anterior. Risco residual conhecido: se o
//     assistente mencionar publicar em tom interrogativo e o "ok" do Wagner for sobre
//     OUTRA coisa, abre janela de 15min — coberto pela defesa de fundo (branch
//     protection). Falha-fecha: sem transcript legível, afirmativo curto NÃO aprova.
//   - Escape ACIONÁVEL de dentro da sessão (LACUNA 2, 2026-06-24): criar o marcador
//     .claude/run/r10-override.txt (tool Write) com a razão — TTL 15min, consumido ao
//     usar, visível no transcript. Só pra quando o Wagner JÁ aprovou e a detecção
//     por palavra-chave falhou. O env OIMPRESSO_PR_APPROVAL_OVERRIDE=1 segue válido,
//     MAS só se setado no AMBIENTE do harness; prefixar inline no comando (ex:
//     `OIMPRESSO_PR_APPROVAL_OVERRIDE=1 git push`) NÃO chega ao processo do hook — a
//     env var pertence ao processo do comando publicado, não ao do harness.
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira razão pro Claude)

import { stdin } from 'node:process';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { existsSync, writeFileSync, readFileSync, unlinkSync, openSync, fstatSync, readSync, closeSync, statSync } from 'node:fs';

const FLAG = join(tmpdir(), 'oimpresso-pr-approval.flag');
const TTL_MIN = 15;

// Override ACIONÁVEL de dentro da sessão (LACUNA 2, 2026-06-24): arquivo-marcador no FS
// real do projeto, criável com a tool Write. Resolve o bug de inacionabilidade do escape
// por env — `OIMPRESSO_PR_APPROVAL_OVERRIDE=1` prefixado no comando NÃO chega ao processo
// do hook (a env var pertence ao processo do comando publicado, não ao do harness que
// avalia o PreToolUse). Auditável: o Write aparece no transcript COM a razão. `.claude/run/`
// é gitignored (efêmero). Path derivado de import.meta.url = raiz da worktree onde o hook vive.
const PROJECT_ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const OVERRIDE_FILE = join(PROJECT_ROOT, '.claude', 'run', 'r10-override.txt');
const OVERRIDE_TTL_MIN = 15;

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

// Afirmativos CURTOS — ambíguos isolados. Só contam como aprovação quando o ÚLTIMO
// turno do assistente ofereceu PUBLICAR (ver assistantAskedToPublish). Muitos destes
// já casam um approvePattern FORTE (pode/manda ver/...); duplicar aqui é inofensivo —
// o forte resolve antes do gate de contexto.
const SHORT_AFFIRMATIVES = new Set([
  'ok', 'okay', 'okok', 'blz', 'beleza', 'bele', 'belê',
  'isso', 'isso mesmo', 'isso ai', 'isso aí', 'exato', 'exatamente',
  'fechou', 'fechado', 'feito',
  'aprovo', 'aprovado', 'aprova',
  'sim', 'sim sim', 'sim pode', 'pode sim', 'pode',
  'manda', 'manda ver', 'manda bala', 'manda brasa', 'bora', 'vamos', 'vamo',
  'vai', 'vai la', 'vai lá', 'vai em frente',
  'perfeito', 'show', 'show de bola', 'certo', 'positivo', 'confirmo', 'confirmado',
  'ta', 'tá', 'ta bom', 'tá bom', 'ta bem', 'tá bem', 'ok pode', 'ok pode sim',
  'combinado', 'correto', 'massa', 'top', 'dale',
  // 'merge' como ORDEM curta (LACUNA 1, caso real Wagner 2026-06-24 "ok merge"): só
  // aprova sob o gate de contexto (assistente acabou de oferecer publicar). 'pode merge'
  // e 'faz o merge' já casam um approvePattern FORTE — duplicar aqui é inofensivo (o forte
  // resolve antes do gate). 'merge'/'ok merge'/'merge agora' SÓ chegam via este Set.
  'merge', 'ok merge', 'pode merge', 'merge agora', 'faz o merge',
  '👍', '✅', '🚀',
]);

function isShortAffirmative(text) {
  const t = String(text)
    .trim()
    .toLowerCase()
    .replace(/[\s!.…,]+$/u, '') // pontuação/espaço final
    .replace(/\s+/g, ' ')
    .trim();
  return SHORT_AFFIRMATIVES.has(t);
}

// Verbos/objetos de PUBLICAÇÃO que indicam que a pergunta/oferta é sobre publicar.
const ASSIST_PUBLISH_INTENT = [
  /\b(?:o|a)\s+pr\b/i, // "abro o PR?", "faço o PR?"
  /\bpull\s+request\b/i,
  /\bcomit\w*\b/i, // comito, comitar
  /\bcommit\w*\b/i, // commito, commitar
  /\bpush\w*\b/i, // push, pushar, pusho
  /\bmerge\w*\b/i, // merge, mergeio, mergear
  /\bpublic\w+\b/i, // publico, publicar
  /\bsub[oi]\w*\b/i, // subo, subir (não casa 'subtotal'/'substituir')
];

// O último turno do assistente perguntou/ofereceu PUBLICAR? Olha SÓ cláusulas
// interrogativas ("...?") + ofertas explícitas ("posso/quer que eu/devo ..."), pra
// não casar uma menção solta de "PR" no meio de texto narrativo.
function assistantAskedToPublish(text) {
  if (!text) return false;
  const questions = (text.match(/[^?!.\n]*\?/g) || []).slice(-6).join(' ');
  if (questions && ASSIST_PUBLISH_INTENT.some((r) => r.test(questions))) return true;
  const offers = (text.match(/\b(?:posso|quer(?:\s+que\s+eu)?|deseja(?:\s+que\s+eu)?|se\s+quiser(?:\s+eu)?|devo)\b[^?!.\n]{0,80}/gi) || [])
    .slice(-6)
    .join(' ');
  if (offers && ASSIST_PUBLISH_INTENT.some((r) => r.test(offers))) return true;
  return false;
}

// Lê os últimos ~256KB do transcript (JSONL) — barato em UserPromptSubmit recorrente.
// Fail-open: qualquer erro/ausência → '' (cai pra conservadoria do gate).
function readTail(path, maxBytes = 262144) {
  let fd;
  try {
    fd = openSync(path, 'r');
    const size = fstatSync(fd).size;
    const start = size > maxBytes ? size - maxBytes : 0;
    const buf = Buffer.alloc(size - start);
    readSync(fd, buf, 0, buf.length, start);
    return buf.toString('utf8');
  } catch {
    return '';
  } finally {
    if (fd !== undefined) {
      try {
        closeSync(fd);
      } catch {
        /* silent */
      }
    }
  }
}

// Texto do ÚLTIMO turno 'assistant' no transcript. No momento do UserPromptSubmit
// esse é o turno que o "ok" do Wagner está respondendo (a mensagem dele chega em
// p.prompt; pode ou não já estar no transcript — varremos só turnos 'assistant').
function lastAssistantText(transcriptPath) {
  if (!transcriptPath || !existsSync(transcriptPath)) return '';
  const raw = readTail(transcriptPath);
  if (!raw) return '';
  const lines = raw.split('\n');
  for (let i = lines.length - 1; i >= 0; i--) {
    const ln = lines[i].trim();
    if (!ln) continue;
    let o;
    try {
      o = JSON.parse(ln);
    } catch {
      continue; // linha parcial (corte do tail) ou corrompida → pula
    }
    if (o.type === 'assistant' && o.message && Array.isArray(o.message.content)) {
      const t = o.message.content
        .filter((b) => b && b.type === 'text' && typeof b.text === 'string')
        .map((b) => b.text)
        .join('\n')
        .trim();
      if (t) return t;
    }
  }
  return '';
}

function isApproval(text, transcriptPath) {
  if (!text) return false;
  if (denyPatterns.some((r) => r.test(text))) return false;
  if (approvePatterns.some((r) => r.test(text))) return true; // sinal FORTE (contexto-independente)
  // Sinal CURTO ("ok"/"aprovo"/...): só aprova logo após o assistente oferecer
  // publicar — casa a intenção real sem afrouxar a conservadoria (opção a).
  if (isShortAffirmative(text) && assistantAskedToPublish(lastAssistantText(transcriptPath))) {
    return true;
  }
  return false;
}

// Ancorados no inicio efetivo do comando (apos ; & |) com limite de palavra — evita
// falso-bloqueio quando a frase aparece dentro de string/comentario/arg de busca (ex:
// rg 'git push', history | grep 'git push'). Cobre os bypasses do review adversarial
// 2026-06-20: env-prefix, `git -c k=v push`, e `gh api` escrevendo em /pulls|/merge.
const publishPatterns = [
  /(^|[;&|]\s*)gh\s+pr\s+(create|merge)(\s|$)/i,
  // git push — tolera prefixo de env (FOO=bar / FOO="a b") e flags do git antes de 'push'
  /(^|[;&|]\s*)([A-Za-z_][A-Za-z0-9_]*=(?:"[^"]*"|'[^']*'|\S+)\s+)*git(\s+-\S+(\s+\S+)?)*\s+push(\s|$)/i,
  // gh api — fazer MERGE de PR (endpoint .../pulls/N/merge)
  /(^|[;&|]\s*)gh\s+api\b[^;&|]*\/pulls\/\d+\/merge\b/i,
  // gh api — CRIAR PR: escrita (POST/-f/-F/--field/--input) no coletivo /pulls.
  // Lookaheads exigem /pulls "folha" (nao /pulls/N/comments) + marcador de escrita →
  // NAO casa reads (GET) nem comentarios de PR.
  /(^|[;&|]\s*)gh\s+api\b(?=[^;&|]*\/pulls(?=[\s?'"]|$))(?=[^;&|]*(?:-X\s+POST|--method\s+POST|-f\b|-F\b|--field\b|--raw-field\b|--input\b))/i,
];
function isPublish(cmd) {
  return !!cmd && publishPatterns.some((r) => r.test(cmd));
}

// Override de arquivo válido? Exige razão NÃO-vazia + mtime dentro do TTL. Consumido
// pelo chamador ao usar (1 override = 1 publicação). Fail-closed em qualquer erro.
function fileOverrideActive() {
  try {
    if (!existsSync(OVERRIDE_FILE)) return false;
    const reason = readFileSync(OVERRIDE_FILE, 'utf8').trim();
    if (!reason) return false; // sem razão explícita → não honra
    const ageMin = (Date.now() - statSync(OVERRIDE_FILE).mtimeMs) / 60000;
    return ageMin >= 0 && ageMin < OVERRIDE_TTL_MIN;
  } catch {
    return false;
  }
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
    if (isApproval(p.prompt || '', p.transcript_path)) {
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

    // Override ACIONÁVEL de dentro da sessão (LACUNA 2): arquivo-marcador com razão +
    // TTL. Só pra quando o Wagner JÁ aprovou mas a detecção por palavra-chave falhou.
    // Consome ao usar (igual à flag).
    if (fileOverrideActive()) {
      try {
        unlinkSync(OVERRIDE_FILE);
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
      'CAMINHO NORMAL: peça aprovação explícita ("pode fazer o PR?") e aguarde o',
      '"pode / sim / manda / merge" do Wagner. A resposta dele grava a aprovação.',
      '',
      `ESCAPE (só se o Wagner JÁ aprovou e a detecção falhou) — crie o marcador com a`,
      `tool Write (TTL ${OVERRIDE_TTL_MIN}min, consumido ao usar, visível no chat):`,
      `  arquivo:  ${OVERRIDE_FILE}`,
      `  conteúdo: a razão concreta (ex.: "Wagner aprovou 'ok merge' às 14h32")`,
      'NÃO funciona prefixar OIMPRESSO_PR_APPROVAL_OVERRIDE=1 no comando — a env var',
      'não chega ao processo do hook (só vale se setada no ambiente do harness).',
    ].join('\n');
    process.stderr.write(msg + '\n');
    process.exit(2);
  }

  process.exit(0);
})();
