#!/usr/bin/env node
// @ts-check
//
// whats-active-troca-de-alvo.mjs — AVISA (não bloqueia) quando um Edit/Write adota um ALVO
// NOVO no meio da sessão. É o braço mecânico da skill `session-start-check` (ADR 0119):
// ela dispara no `session_start`, e esse gatilho tem um ponto cego estrutural — o alvo
// muda DEPOIS, horas adentro, e o check já passou.
//
// ORIGEM MEDIDA (2026-09-15): quatro sessões atacaram o mesmo tema (`distiller_freshness`)
// no mesmo dia; duas delas eram minhas e viraram PR duplicado (#7332 e #7340, ambos
// fechados). O `dup-detector` acusou certo, mas só depois do PR aberto. A defesa que
// existia (`whats-active`) estava DISPONÍVEL e não foi executada quando o alvo trocou — é
// falha de execução, não gap de ferramenta (§5 2026-09-05).
//
// POR QUE HOOK NOVO e não estender o `modulo-preflight-warning`: aquele só casa
// `Modules/<X>/`, e as colisões deste incidente foram em `scripts/governance/` e
// `memory/requisitos/` — estender o regex dele não pegaria nada e trocaria o propósito
// dele (ler briefing do módulo). Vetor diferente, dono diferente — é o mesmo critério que
// o §5 2026-07-20 usou pra NÃO aposentar o preflight alegando que um irmão "já cobre".
//
// FP MEDIDO ANTES DE INSTALAR (regra "LIGUE A MÁQUINA" item 4), corpus de 1728 transcripts,
// 865 com edits classificáveis: 91,1% das sessões tocam UM alvo só e não recebem nada;
// total de 168 nudges no corpus inteiro = 0,19 por sessão, ~1 a cada 5.
//
// STATELESS de propósito: os alvos já vistos saem do próprio transcript (mesmo idioma do
// `modulo-preflight-warning`, que lê evidência de leitura de lá). Não há arquivo de estado
// pra apodrecer, e o aviso se auto-limita — depois dele o path entra no transcript e o
// mesmo alvo não avisa de novo.
//
// ADVISORY: exit 0 SEMPRE, fail-open. Não é gate e não pode virar um: "rodou o
// whats-active?" mede PRESENÇA, não comportamento (LC-11), e a família de gate sintático
// já tem lápide de sobra no §5.
//
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { pathToFileURL, fileURLToPath } from 'node:url';

const WRITE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit', 'NotebookEdit']);
const BS = String.fromCharCode(92);
const toFwd = (s) => String(s).split(BS).join('/');

/**
 * Reduz um path a um ALVO — a unidade em que uma sessão "trabalha". Grosso de propósito:
 * arquivo a arquivo o aviso vira ruído; módulo/área a área ele casa com o que colide.
 * @param {string} filePath
 * @returns {string|null}
 */
export function alvoDe(filePath) {
  const f = toFwd(filePath);
  let m;
  if ((m = /(?:^|\/)Modules\/([A-Za-z0-9_]+)\//.exec(f))) return 'Modules/' + m[1];
  if ((m = /resources\/js\/Pages\/([A-Za-z0-9_-]+)\//.exec(f))) return 'Pages/' + m[1];
  if ((m = /memory\/requisitos\/([A-Za-z0-9_-]+)\//.exec(f))) return 'requisitos/' + m[1];
  if ((m = /(?:^|\/)scripts\/([A-Za-z0-9_-]+)\//.exec(f))) return 'scripts/' + m[1];
  if (/\.github\/workflows\//.test(f)) return 'workflows';
  if (/memory\/decisions\//.test(f)) return 'decisions';
  if ((m = /(?:^|\/)(memory|tests|app|config|database|resources|governance)\//.exec(f))) return m[1];
  return null;
}

/**
 * NÚCLEO PURO (testável, sem I/O): decide se avisa.
 * Avisa só quando o alvo é NOVO **e** a sessão já tinha alvo — alvo novo numa sessão que
 * ainda não editou nada é o caso do `session_start`, já coberto pela skill.
 * @param {string|null} alvoAtual
 * @param {Set<string>} alvosAnteriores
 */
export function deveAvisar(alvoAtual, alvosAnteriores) {
  if (!alvoAtual) return false;
  if (alvosAnteriores.size === 0) return false;
  return !alvosAnteriores.has(alvoAtual);
}

/** Alvos que a sessão já tocou, lidos do transcript. @param {string} txt */
export function alvosDoTranscript(txt) {
  const out = new Set();
  for (const m of String(txt).matchAll(/"file_path"\s*:\s*"((?:[^"]|\\")*)"/g)) {
    const a = alvoDe(m[1]);
    if (a) out.add(a);
  }
  return out;
}

export function mensagem(alvoNovo, alvosAnteriores) {
  const antes = [...alvosAnteriores].slice(0, 4).join(', ');
  return `
[whats-active-troca-de-alvo] ⚠️  ALVO NOVO nesta sessão: ${alvoNovo}
   (antes você estava em: ${antes}${alvosAnteriores.size > 4 ? ', …' : ''})

A skill session-start-check (ADR 0119) consulta sessões paralelas no INÍCIO da sessão — e
o alvo acabou de mudar, então aquele check não fala deste. Antes de seguir, pergunte quem
mais está em ${alvoNovo}:

  • com MCP:  tool  whats-active
  • sem MCP (worktree filho): o fallback determinístico, ~300ms —
      git log --remotes="origin/claude/*" --not HEAD --since=3.days --oneline -- <path do alvo>

E o ponto cego dos DOIS: eles mostram sessão VIVA e branch PUSHADA. Trabalho que já
MERGEOU não aparece — pra isso, re-rode o comando/gate que motivou este trabalho contra
origin/main fresco: se já sai verde, o trabalho acabou e o seu PR seria ruído.

Advisory — não bloqueia. Origem: §5 2026-09-05 + incidente 2026-09-15 (4 sessões no mesmo
tema, 2 PRs duplicados fechados).
`;
}

const readStdin = () => new Promise((res, rej) => {
  let d = '';
  process.stdin.setEncoding('utf8');
  process.stdin.on('data', (c) => { d += c; });
  process.stdin.on('end', () => res(d));
  process.stdin.on('error', rej);
});

async function main() {
  try {
    let raw = '';
    try { raw = await readStdin(); } catch { process.exit(0); }
    if (!raw) process.exit(0);
    let tool, path, tp;
    try {
      const j = JSON.parse(raw);
      tool = j.tool_name ?? j.tool;
      path = j.tool_input?.file_path ?? j.tool_input?.path;
      tp = j.transcript_path;
    } catch { process.exit(0); }
    if (!WRITE_TOOLS.has(tool) || !path) process.exit(0);
    const alvo = alvoDe(path);
    if (!alvo) process.exit(0);
    let txt = '';
    try { txt = readFileSync(tp, 'utf8'); } catch { process.exit(0); }
    const anteriores = alvosDoTranscript(txt);
    if (!deveAvisar(alvo, anteriores)) process.exit(0);
    console.error(mensagem(alvo, anteriores));
    process.exit(0);
  } catch { process.exit(0); }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./whats-active-troca-de-alvo.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [fileURLToPath(test)], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
