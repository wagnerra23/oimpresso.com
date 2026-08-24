#!/usr/bin/env node
// block-pergunta-que-eu-resolvo.mjs — Stop, BLOQUEADOR (R16).
//
// ── POR QUE EXISTE ───────────────────────────────────────────────────────────
// [W] 2026-08-24, palavras textuais: *"eu ter que ficar repetindo a missão é um erro
// grosseiro, não me pergunte mais"* · *"resolva não pergunte"* · *"crie o hook de
// bloqueio, não me chateie mais"*.
//
// A regra JÁ EXISTIA em dois lugares e mesmo assim eu perguntei:
//   R13 (recomendar, não devolver menu) → hook `nudge-recommend-not-menu` ADVISORY
//   R15 (medir em vez de perguntar)     → SEM hook nenhum
// O R13 ainda ERROU meu caso real por variante de gênero: o regex dele testa
// `qual (você|prefere|escolh|deles|opção)` e eu terminei com "Qual delas?".
// Advisory + predicado furado = a regra existe e não morde. Este hook morde.
//
// ── O QUE ELE BLOQUEIA ───────────────────────────────────────────────────────
// Turno que TERMINA devolvendo a decisão pro [W] em algo que eu resolveria medindo:
// menu de leituras, "qual delas?", "quer que eu...?", "me diga qual".
//
// ── O QUE ELE NUNCA BLOQUEIA (escalação legítima — R15 a/b/c) ────────────────
//   (a) soberania [W]: merge, push, PR, deploy, promover gate a required, podar
//       capacidade, token/componente novo  → R10 EXIGE perguntar; bloquear seria
//       empurrar o agente a violar outra regra Tier 0.
//   (b) irreversível sob qualquer hipótese
//   (c) preferência/gosto: cor, nome, ordem — não há resposta técnica "certa" (R13)
//
// ── ANTI-LOOP ────────────────────────────────────────────────────────────────
// `stop_hook_active: true` no payload ⇒ exit 0. Sem isso o bloqueio recursiona.
//
// FP medido no corpus real ANTES de ligar — ver cabeçalho do .test.mjs.
// Selftest: node .claude/hooks/block-pergunta-que-eu-resolvo.mjs --selftest

import { spawnSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { readFileSync, existsSync } from 'node:fs';

export const MOTIVO = [
  '[block-pergunta-que-eu-resolvo] [R16] BLOQUEADO: você terminou o turno devolvendo a decisão pro [W].',
  '',
  'A missão não se pergunta — se lê e se cumpre. Fazer [W] repetir a missão é erro grosseiro',
  '([W] 2026-08-24: "resolva não pergunte").',
  '',
  'Faça agora, nesta ordem:',
  '  1. RODE o comando/porta viva que responderia a pergunta (R15). Quase sempre existe.',
  '  2. Se ainda houver leituras possíveis, ESCOLHA a mais consequential e defensável,',
  '     DIGA qual escolheu e por quê, e EXECUTE (R13 — crave, não devolva menu).',
  '  3. Só escale se for soberania [W] (merge/push/PR/deploy/required/podar capacidade),',
  '     irreversível, ou puro gosto. Aí a pergunta passa e este hook não bloqueia.',
].join('\n');

// Escalação LEGÍTIMA — R10 exige perguntar. Bloquear aqui empurraria pra violar Tier 0.
const ESCALACAO = new RegExp(
  '\\b(' +
  'merge|mergear|mergeio|mergear\\?|abro o pr|abrir o pr|abro a pr|pr\\b|push|pushar|' +
  'deploy|deployar|publicar|subir pra (prod|produ)|' +
  'promover.{0,24}(required|gate)|podar|deletar|apagar|remover.{0,20}(m[oó]dulo|capacidade)|' +
  'irrevers[íi]vel|rotacionar|revogar|autoriza|aprova' +
  ')\\b', 'i');

// Gosto/preferência — R13 permite menu explicitamente.
const GOSTO = /\b(cor|roxo|azul|nome|nomear|t[ií]tulo|ordem dos|prefere.{0,18}(visual|est[eé]tic))\b/i;

// Pergunta que DEVOLVE decisão. Cobre as variantes de gênero/número que o R13 perdeu.
const DEVOLVE = new RegExp(
  '(' +
  'qual (delas|deles|dessas|desses|dos dois|das duas|op[cç][aã]o|caminho|leitura|prefere|voc[eê])' +
  '|o que voc[eê] (quer|prefere|acha que)' +
  '|(quer|deseja|prefere) que eu\\b' +
  '|me diga qual|me diz qual|qual (voc[eê] )?quer' +
  '|(fa[cç]o|sigo|ataco|come[cç]o por) .{0,60}\\bou\\b.{0,60}\\?' +
  '|qual (é |e )?(a )?(sua )?(prioridade|prefer[eê]ncia)' +
  '|prefiro perguntar' +
  '|qual delas' +
  ')', 'i');

// Devolução em forma IMPERATIVA não tem '?' e escapava do predicado — "Me diga qual eu
// ataco." é tão devolução quanto "Qual delas?". Lista curta e literal de propósito: cada
// entrada é um pedido explícito de decisão, não uma construção que aparece em prosa comum.
const IMPERATIVO = new RegExp(
  '\\b(me diga qual|me diz qual|me informe qual|me fala qual' +
  '|escolha (qual|entre|uma)|decida (qual|entre)|voc[eê] (decide|escolhe|que sabe))\\b', 'i');

/** Classificador PURO: este texto final devolve decisão que eu deveria ter resolvido? */
export function deveBloquear(text) {
  if (!text) return false;
  // Só olha o FECHO: é onde a devolução acontece. Pergunta no meio de um relatório
  // que termina em execução não é devolução — é contexto.
  const linhas = String(text).trim().split('\n').filter((l) => l.trim());
  const fecho = linhas.slice(-8).join('\n');
  const imperativo = IMPERATIVO.test(fecho);
  if (!fecho.includes('?') && !imperativo) return false;
  if (!DEVOLVE.test(fecho) && !imperativo) return false;
  if (ESCALACAO.test(fecho)) return false; // R10/soberania — perguntar é obrigatório
  if (GOSTO.test(fecho)) return false;     // R13 permite menu pra gosto
  return true;
}

/** último texto de mensagem assistant no transcript JSONL. */
export function lastAssistantText(transcriptPath) {
  if (!transcriptPath || !existsSync(transcriptPath)) return '';
  let lines;
  try { lines = readFileSync(transcriptPath, 'utf8').split('\n').filter(Boolean); } catch { return ''; }
  const tail = lines.slice(-50);
  for (let i = tail.length - 1; i >= 0; i--) {
    let o;
    try { o = JSON.parse(tail[i]); } catch { continue; }
    if (o && o.type === 'assistant' && o.message && Array.isArray(o.message.content)) {
      const t = o.message.content.filter((c) => c && c.type === 'text').map((c) => c.text).join('\n');
      if (t) return t;
    }
  }
  return '';
}

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  try {
    let raw;
    try { raw = await readStdin(); } catch { process.exit(0); }
    if (!raw) process.exit(0);
    let payload = {};
    try { payload = JSON.parse(raw) || {}; } catch { process.exit(0); }
    // ANTI-LOOP: já bloqueei uma vez neste ciclo — não bloqueio de novo.
    if (payload.stop_hook_active) process.exit(0);
    const text = lastAssistantText(String(payload.transcript_path || ''));
    if (deveBloquear(text)) {
      process.stderr.write(MOTIVO + '\n');
      process.exit(2); // 2 = bloqueia o Stop e devolve o motivo pro modelo
    }
    process.exit(0);
  } catch { process.exit(0); } // fail-open: hook quebrado nunca trava a sessão
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-pergunta-que-eu-resolvo.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [fileURLToPath(test)], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
