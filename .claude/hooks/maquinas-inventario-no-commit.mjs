#!/usr/bin/env node
/**
 * maquinas-inventario-no-commit — o INVOCADOR do `--write` (PreToolUse · Bash · `git commit`)
 *
 * POR QUE EXISTE (medido 2026-09-15, [W] pediu: "ligar o invocador do maquinas-inventario --write"):
 * o `maquinas-inventario.mjs` JA tinha invocador — o `--check` advisory no
 * `governance-script-tests.yml:90`. O que faltava era alguem rodar o `--write`. Contado nos
 * ultimos 300 commits do main:
 *   · 81 de 300 (27%) tocam path que o inventario cobre  -> frequencia de disparo
 *   · desses, 9 regeneraram o indice junto
 *   · 72 NAO regeneraram (89% de omissao)  -> a populacao que produziu 108 linhas de churn,
 *     pagas a mao no PR #7351 por quem tropecou nelas
 *
 * O QUE ELE NAO E, e isso importa:
 *   · NAO e presence-gate. O predicado barato "o indice esta no diff?" e EXATAMENTE a forma que
 *     a §5 2026-07-01 baniu ("gate que enforce 'artefato X foi editado junto' por presenca no
 *     diff"). Aqui o filtro de path serve so pra decidir se vale PAGAR a medicao; quem decide e
 *     o `--check`, que mede a arvore. Indice ja fresco => silencio absoluto.
 *   · NAO bloqueia. `exit 0` sempre, fail-open em toda falha (sem git, sem node, sem script,
 *     timeout). Gate mudo e pior que gate ausente (§5), mas travar commit por artefato derivado
 *     seria pior ainda — o conserto e regenerar, e regenerar e o que ele faz.
 *   · NAO estagia nada alem do proprio indice, por path explicito. Nunca `git add -A`
 *     (§5 2026-09-05: foi o `add -A` que transformou efeito colateral em commit).
 *
 * CUSTO: ~0ms quando o commit nao toca path coberto (filtro puro). ~6,5s quando toca — medido
 * 3x: 6949/6561/6063ms no `--write`, 6165/6689ms no `--check`. O gerador e IDEMPOTENTE (dois
 * writes seguidos dao o mesmo hash), entao ele nao fabrica diff espurio.
 *
 * DRIFT HERDADO: se o indice estava stale por commit de TERCEIRO, o `--write` regenera tudo e o
 * diff sai maior que o toque do autor. Isso e inevitavel — o `--check` compara FIDELIDADE TOTAL
 * — e a mensagem diz isso em voz alta, em vez de deixar o autor descobrir no `git diff`.
 */

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';

const INDICE = 'memory/reference/MAQUINAS-INVENTARIO.md';
const GERADOR = 'scripts/governance/maquinas-inventario.mjs';

/** Prefixos que o inventario de fato varre — derivados do proprio gerador, nao adivinhados. */
export const COBERTOS = [
  '.claude/',
  'scripts/governance/',
  '.github/workflows/',
];

/**
 * TOKENIZACAO em vez de mega-regex. Motivo medido: o regex unico errava `git -C /repo commit`
 * (flag COM VALOR — o valor nao comeca com `-`) e `git commit -am x` (cluster curto). Os 3
 * asserts que caiam eram exatamente esses. Tokenizar e testavel e nao adivinha a gramatica.
 */
const OPCOES_COM_VALOR = new Set(['-C', '-c', '--git-dir', '--work-tree', '--namespace', '--exec-path']);

/** Divide o comando em segmentos independentes (`;` `&&` `||` `|`). */
export function segmentos(cmd) {
  if (typeof cmd !== 'string' || !cmd) return [];
  return cmd.split(/\|\||&&|[;&|]/).map((s) => s.trim()).filter(Boolean);
}

/** Args do subcomando `commit` neste segmento, ou `null` se o segmento nao for um git commit. */
export function argsDoCommit(segmento) {
  const t = String(segmento || '').split(/\s+/).filter(Boolean);
  if (t[0] !== 'git') return null;
  let i = 1;
  while (i < t.length) {
    const tok = t[i];
    if (tok === 'commit') return t.slice(i + 1);
    if (tok.startsWith('-')) { i += OPCOES_COM_VALOR.has(tok) ? 2 : 1; continue; }
    return null;                     // outro subcomando (add, log, status...)
  }
  return null;
}

/** E um `git commit`? Mencao dentro de string de outro comando nao conta (t[0] nao e `git`). */
export function ehGitCommit(cmd) {
  return segmentos(cmd).some((s) => argsDoCommit(s) !== null);
}

/** O commit carrega pathspec explicito (`git commit -- a b`)? Ali estagiar nao entraria. */
export function temPathspecExplicito(cmd) {
  return segmentos(cmd).some((s) => {
    const a = argsDoCommit(s);
    if (!a) return false;
    const i = a.indexOf('--');
    return i !== -1 && a.length > i + 1;
  });
}

/** `-a` / `--all` fazem o commit levar o working tree, nao so o stage. */
export function levaWorkingTree(cmd) {
  return segmentos(cmd).some((s) => {
    const a = argsDoCommit(s);
    if (!a) return false;
    for (const tok of a) {
      if (tok === '--') break;                                  // dali pra frente e pathspec
      if (tok === '--all') return true;
      if (/^-[a-zA-Z]+$/.test(tok) && tok.includes('a')) return true;   // cluster curto: -am, -a
    }
    return false;
  });
}

/** Algum dos paths cai sob prefixo coberto? */
export function tocaCoberto(paths) {
  if (!Array.isArray(paths)) return false;
  return paths.some((p) => COBERTOS.some((c) => String(p).startsWith(c)));
}

function git(args, cwd) {
  return execFileSync('git', args, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
}

function lerStdin() {
  try {
    return readFileSync(0, 'utf8');
  } catch {
    return '';
  }
}

function main() {
  const raw = lerStdin();
  let cmd = '';
  try {
    cmd = (JSON.parse(raw).tool_input || {}).command || '';
  } catch {
    return 0;
  }

  if (!ehGitCommit(cmd)) return 0;          // 0ms no caso comum

  const cwd = process.cwd();
  if (!existsSync(GERADOR)) return 0;       // fora do repo / checkout parcial: fail-open

  // --- filtro BARATO: o commit toca path que o inventario cobre?
  let paths = [];
  try {
    paths = git(['diff', '--cached', '--name-only'], cwd).split('\n').filter(Boolean);
    if (levaWorkingTree(cmd)) {
      paths = paths.concat(git(['diff', '--name-only'], cwd).split('\n').filter(Boolean));
    }
  } catch {
    return 0;                               // git falhou: nao invento estado
  }
  if (!tocaCoberto(paths)) return 0;        // nao paga a medicao

  // --- so agora o passo CARO: quem decide e a medicao da arvore, nunca a presenca no diff
  let stale = false;
  try {
    execFileSync('node', [GERADOR, '--check'], { cwd, stdio: 'ignore', timeout: 120000 });
  } catch (e) {
    if (typeof e.status === 'number' && e.status === 1) stale = true;
    else return 0;                          // crash/timeout: fail-open, nao afirmo staleness
  }
  if (!stale) return 0;                     // indice fresco: SILENCIO (aqui morre o presence-gate)

  try {
    execFileSync('node', [GERADOR, '--write'], { cwd, stdio: 'ignore', timeout: 120000 });
  } catch {
    console.error('[maquinas-inventario] o --write falhou; commit segue, indice fica stale.');
    return 0;
  }

  const explicito = temPathspecExplicito(cmd);
  if (explicito) {
    console.error(
      '[maquinas-inventario] REGENEREI ' + INDICE + ' (estava stale e o commit toca maquina).\n' +
      '  Seu commit tem pathspec explicito, entao NAO estagiei — inclua o indice voce mesmo.'
    );
    return 0;
  }

  try {
    git(['add', '--', INDICE], cwd);
  } catch {
    console.error('[maquinas-inventario] regenerei, mas o `git add` falhou. Estagie a mao: ' + INDICE);
    return 0;
  }

  console.error(
    '[maquinas-inventario] REGENEREI e ESTAGIEI ' + INDICE + '.\n' +
    '  Motivo: este commit toca maquina (.claude/ · scripts/governance/ · .github/workflows/)\n' +
    '  e o `--check` acusou drift. Derivado com dono acompanha a mudanca do insumo.\n' +
    '  Se o diff do indice for maior que o seu toque, o excedente e DRIFT HERDADO de commits\n' +
    '  anteriores que nao regeneraram — o `--check` compara fidelidade TOTAL, nao ha como pagar\n' +
    '  so a propria linha. Medido em 2026-09-15: 72 de 81 commits omitiram isso.'
  );
  return 0;
}

if (process.argv[1] && process.argv[1].endsWith('maquinas-inventario-no-commit.mjs')) {
  process.exit(main());
}
