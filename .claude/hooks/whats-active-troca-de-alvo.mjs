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

// ─── 2º GATILHO: antes do `gh pr create` (2026-09-30) ──────────────────────────────────
//
// O aviso de alvo novo olha SESSÕES no momento do Edit. O ponto cego que custou 2 PRs
// duplicados no mesmo dia (#8229×#8226, #8290×#8286 — §5 2026-09-30 e a 2ª) é outro: outra
// sessão já tem PR ABERTO nos mesmos arquivos, e isso só se vê no instante de publicar. A
// sonda existe (`dup-detector --path`, #8235) — faltava o gatilho, e lembrar falhou 2×.
//
// FP MEDIDO ANTES DE INSTALAR, 400 PRs de 24/09 a 30/09, contando todo PR aberto enquanto
// outro já aberto tocava o mesmo arquivo: sem filtro, 165/400 (41%). Tirando os arquivos
// GERADOS/de estado abaixo, 90/400 (22,5%), pegando 11 de 11 duplicatas reais — precisão
// ~12%. Nenhum limiar de sobreposição separou melhor sem perder duplicata. Por isso é AVISO
// que INFORMA (qual PR, quais arquivos), nunca veredito nem bloqueio: a informação é sempre
// verdadeira, a decisão fica com quem publica (mesma forma do aviso de stash, §5 2026-07-27).
//
// Canal: `additionalContext` (JSON no stdout). `stderr` + exit 0 NÃO chega ao agente
// (§5 2026-09-23) — o 1º gatilho acima ainda usa `stderr`, e isso é dívida dele, não daqui.

/** Arquivos gerados/de estado: colidem por construção entre PRs, não sinalizam duplicação. */
export const DERIVADOS = [
  /\/SUPERFICIE\.md$/,
  /\/_STATUS-GENERATED\.md$/,
  /^scripts\/design-sync\/state\//,
  /^memory\/reference\/MAQUINAS-INVENTARIO\.md$/,
  /^memory\/proibicoes\.md$/,
  /^scripts\/governance\/\.cowork-freshness-ledger\.json$/,
  /\.snap$/,
  /^memory\/08-handoff\.md$/,
  /_HOOKS-INDEX\.md$/,
  /_SKILLS-INDEX\.md$/,
  /\.memory-health-baseline\.json$/,
];

/** @param {string} cmd */
export function ehPrCreate(cmd) {
  return /(^|[\s;&|(])gh\s+pr\s+create\b/.test(String(cmd || ''));
}

/** @param {string[]} files */
export function semDerivados(files) {
  return files.map(toFwd).filter((f) => f && !DERIVADOS.some((r) => r.test(f)));
}

/**
 * Texto do aviso a partir da saída do `dup-detector --path`. `null` = nada a dizer.
 * @param {number|null} rc  0 livre · 1 tocado · 2 não medi · null = nem rodou
 * @param {string} saida
 */
export function avisoAntesDoPr(rc, saida) {
  if (rc === 0) return null;
  if (rc === 1) {
    return `[whats-active-troca-de-alvo] ⚠️  Há PR ABERTO de outra sessão nos arquivos deste branch:\n\n${String(saida).trim()}\n\n` +
      'Leia TODOS antes de publicar o seu. É o mesmo trabalho? Coordene ou feche o seu. Não é? ' +
      'Diga por quê no corpo (`Dedup-ack:`). Medido: ~1 em cada 8 destes avisos é duplicação real — ' +
      'o resto é trabalho paralelo legítimo no mesmo módulo. Aviso, não bloqueio (LC-19, §5 2026-09-30).';
  }
  return '[whats-active-troca-de-alvo] ⚠️  NÃO MEDI se há PR aberto nos arquivos deste branch ' +
    `(dup-detector saiu ${rc === null ? 'sem rodar' : rc}). Isso NÃO quer dizer "livre" — rode à mão: ` +
    'node scripts/governance/dup-detector.mjs --path=<arquivos> --self-branch=<branch>';
}

/** Impuro: arquivos do branch e o dup-detector. Nunca lança. @param {string} cwd */
function checarAntesDoPr(cwd) {
  const git = (a) => spawnSync('git', a, { cwd, encoding: 'utf8', timeout: 15000 });
  const branch = git(['rev-parse', '--abbrev-ref', 'HEAD']);
  const diff = git(['diff', '--name-only', 'origin/main...HEAD']);
  if (branch.status !== 0 || diff.status !== 0) return avisoAntesDoPr(null, '');
  const files = semDerivados(diff.stdout.split(/\r?\n/)).slice(0, 100);
  if (!files.length) return null;
  const script = fileURLToPath(new URL('../../scripts/governance/dup-detector.mjs', import.meta.url));
  const r = spawnSync(process.execPath, [script, `--path=${files.join(',')}`, `--self-branch=${branch.stdout.trim()}`],
    { cwd, encoding: 'utf8', timeout: 30000 });
  const linhas = String(r.stdout || '').split(/\r?\n/).filter((l) => /⚠️|↔/.test(l)).join('\n');
  return avisoAntesDoPr(typeof r.status === 'number' ? r.status : null, linhas);
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
    let tool, path, tp, cmd, cwd;
    try {
      const j = JSON.parse(raw);
      tool = j.tool_name ?? j.tool;
      path = j.tool_input?.file_path ?? j.tool_input?.path;
      tp = j.transcript_path;
      cmd = j.tool_input?.command;
      cwd = j.cwd || process.cwd();
    } catch { process.exit(0); }
    if (typeof cmd === 'string' && ehPrCreate(cmd)) {
      const aviso = checarAntesDoPr(cwd);
      if (aviso) {
        process.stdout.write(JSON.stringify({
          hookSpecificOutput: { hookEventName: 'PreToolUse', additionalContext: aviso },
        }) + '\n');
      }
      process.exit(0);
    }
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
