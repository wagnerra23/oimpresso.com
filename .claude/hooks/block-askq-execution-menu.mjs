#!/usr/bin/env node
// block-askq-execution-menu.mjs — enforcement POR MÁQUINA da regra
// "não perguntar menu de execução/fato" (feedback-recomendado-quando-tecnico.md).
//
// PROBLEMA (4 ocorrências: 2026-05-26, 28, 28, 06-06): em sessão longa o Claude
// usa a TOOL `AskUserQuestion` pra perguntar coisas que ele mesmo deveria
// decidir/apurar — "fecho ou investigo?", "crio task A ou B?", "qual próximo
// passo?", "a task X está feita?". Wagner: "se eu responder qualquer coisa está
// me induzindo ao erro... resolva. anote para não fazer perguntas idiotas."
//
// POR QUE O HOOK ANTERIOR NÃO PEGOU: `nudge-recommend-not-menu.mjs` é Stop +
// advisory + lê só o TEXTO da resposta. A tool AskUserQuestion não vira texto,
// então passava batido. Este aqui é PreToolUse NA tool — intercepta antes de a
// pergunta chegar no Wagner.
//
// MECÂNICA:
//   PreToolUse(AskUserQuestion) → varre question + labels + descriptions.
//     - Sinal de EXECUÇÃO/FATO (fecho/investigo/crio task/deleto/rodo/próximo
//       passo/está feito/qual dos dois...) E SEM sinal de ESCOPO/UX/PRODUTO
//       → BLOQUEIA (exit 2). Razão volta pro Claude: apure e decida.
//     - Escopo/UX/persona/preço/marca (decisões que SÓ o Wagner sabe) → PERMITE.
//     - Ambíguo (nenhum sinal claro) → PERMITE (fail-open; não sufoca pergunta
//       legítima de escopo).
//
// HONESTIDADE: detecção por palavra-chave é REDE, não prova. Conservador —
// na dúvida permite (falso-negativo < falso-positivo, pra não bloquear escopo).
// Escape valve: OIMPRESSO_ASKQ_OVERRIDE=1 (justificar no chat).
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira razão pro Claude)

import { stdin } from 'node:process';

// Decisões que SÓ o Wagner sabe → SEMPRE pode perguntar (allow-list).
const allowPatterns = [
  /\bqual\s+(m[oó]dulo|feature|tela|p[aá]gina|rota|cliente|persona|vertical)\b/i,
  /\b(larissa|eliana|maiara|felipe|luiz)\b/i,
  /\b(pre[cç]o|pricing|cobran[cç]a|marca|brand|logotipo|dom[ií]nio)\b/i,
  /\b(visual|ux|layout|design|screenshot|mockup|paleta|qual\s+cor)\b/i,
  /\bsubstituir\s+ou\s+criar\b/i,
  /\b(integra[cç][aã]o\s+paga|fornecedor|contrato)\b/i,
];

// Execução (o Claude faz) ou Fato (o Claude apura) → NÃO perguntar, decidir.
const blockPatterns = [
  // verbos de ação como a própria escolha (1ª pessoa ou imperativo)
  /\b(fecho|fechar|encerro|encerrar)\b/i,
  /\b(investigo|investigar|apuro|apurar|checo|checar|verifico|verificar)\b/i,
  /\b(crio|criar|cria|abro|abrir|abre)\s+(a\s+|o\s+|os\s+|as\s+)?(task|tarefa|pr|issue|branch|ticket)/i,
  /\b(deleto|deletar|deleta|removo|remover|remove|apago|apagar|apaga)\b/i,
  /\b(rodo|rodar|disparo|disparar|disparo\s+agora|executo|executar|implemento|implementar)\b/i,
  /\bpr[oó]xim[oa]\s+passo\b/i,
  /\bqual\s+(primeiro|antes|come[cç]o|fa[cç]o\s+antes)\b/i,
  /\b(antes\s+ou\s+depois|agora\s+ou\s+depois|j[aá]\s+ou\s+depois)\b/i,
  // fato que o Claude apura (não o Wagner adjudica)
  /\best[aá]\s+(feit[oa]|pront[oa]|done|conclu[ií]d)/i,
  /\bj[aá]\s+(existe|foi\s+feit[oa]|est[aá]\s+feit[oa])\b/i,
  /\bmarcar\s+(como\s+)?done\b/i,
  /\bqual\s+dos\s+(dois|tr[eê]s)\b/i,
  /\bqual\s+(detalh|explic)/i,
];

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

  if (process.env.OIMPRESSO_ASKQ_OVERRIDE === '1') process.exit(0);
  if (p.hook_event_name !== 'PreToolUse') process.exit(0);
  if (p.tool_name !== 'AskUserQuestion') process.exit(0);

  const questions = p.tool_input?.questions;
  if (!Array.isArray(questions) || questions.length === 0) process.exit(0);

  // Corpus = todo texto da(s) pergunta(s) + labels + descriptions.
  const parts = [];
  for (const q of questions) {
    if (q?.question) parts.push(q.question);
    if (q?.header) parts.push(q.header);
    for (const o of q?.options || []) {
      if (o?.label) parts.push(o.label);
      if (o?.description) parts.push(o.description);
    }
  }
  const corpus = parts.join('  ');
  if (!corpus.trim()) process.exit(0);

  const allow = allowPatterns.some((r) => r.test(corpus));
  const block = blockPatterns.some((r) => r.test(corpus));

  if (block && !allow) {
    const msg = [
      '[BLOCKED: menu de execução/fato — feedback-recomendado-quando-tecnico.md]',
      '',
      'Esta AskUserQuestion parece pedir pro Wagner ESCOLHER um próximo passo de',
      'execução OU adjudicar um FATO que VOCÊ consegue apurar. Wagner já corrigiu',
      'isso ≥4× ("se eu responder qualquer coisa está me induzindo ao erro... resolva").',
      '',
      'A FAZER em vez de perguntar:',
      '  • Se é FATO (task feita? ADR existe? hook existe?) → apure (grep/read) e decida.',
      '  • Se é EXECUÇÃO e as opções não conflitam → faça a melhor, ou faça TODAS.',
      '  • Se precisa cravar técnica (ROI/sequência/arquitetura) → RECOMENDE e siga;',
      '    Wagner valida, não calcula.',
      '',
      'SÓ perguntar quando é decisão que SÓ o Wagner sabe: escopo/intenção,',
      'visual/UX/persona, preço/marca/integração paga, ou publicação (R10).',
      '',
      'Escape (raro, justifique no chat): OIMPRESSO_ASKQ_OVERRIDE=1',
    ].join('\n');
    process.stderr.write(msg + '\n');
    process.exit(2);
  }

  process.exit(0);
})();
