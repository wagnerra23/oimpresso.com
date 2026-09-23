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
 * DRIFT HERDADO: se o indice estava stale por commit de TERCEIRO, a regeneracao traz tudo e o
 * diff sai maior que o toque do autor. Isso e inevitavel — a comparacao e do conteudo INTEIRO do
 * arquivo — e a mensagem diz isso em voz alta, em vez de deixar o autor descobrir no `git diff`.
 * (Ate 2026-09-22 este comentario dizia que o `--check` comparava "fidelidade total". Era falso:
 * o `--check` compara so os nomes das maquinas. Por isso o hook deixou de usa-lo.)
 *
 * COMMIT SO DE DOCUMENTO (2026-09-23): a coluna Documento e derivada das CITACOES em `.md`
 * de `memory/**`, `docs/**` e `.claude/**` — editar doc sem tocar maquina tambem envelhece o
 * indice. Ate esta data o hook ignorava esse caso ("ampliar o filtro faria toda edicao de
 * memory/ pagar os ~6,5s"). O filtro agora e o MESMO sinal do gerador: so paga a medicao se
 * as linhas ADICIONADAS ou REMOVIDAS do `.md` carregam o token de alguma maquina do indice,
 * tokenizadas pelos 3 regex do gerador (copiados abaixo; o bite-test compara com a fonte).
 * Doc que nao cita maquina segue custando ~0ms. `CLAUDE.md` dispara sempre: os `@imports`
 * dele decidem a precedencia (rank) da coluna inteira.
 * MEDIDO (2026-09-23, 70 commits do main — clone raso, e o que havia): 29 commits so de doc;
 * rodando o gerador no pai e no commit de cada um, 16 mudaram o indice. O filtro dispara em 20:
 * pega os 16 (0 falso-negativo) e paga ~6,5s a toa em 4, que saem em silencio (indice fresco).
 * Os 9 que ele pula de fato nao mudavam o indice.
 *
 * LIMITE DECLARADO: o filtro olha o DIFF, nao a arvore — mudanca que altera o desempate sem
 * mencionar maquina nas linhas tocadas (ex.: renomear um doc citador) so e pega quando o diff
 * sem renames mostra as linhas; `--no-renames` garante isso para rename puro.
 */

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';

const INDICE = 'memory/reference/MAQUINAS-INVENTARIO.md';
const GERADOR = 'scripts/governance/maquinas-inventario.mjs';

/** Prefixos que o inventario de fato varre — derivados do proprio gerador, nao adivinhados. */
export const COBERTOS = [
  '.claude/',
  'scripts/governance/',
  '.github/workflows/',
];

/** Corpus de documento que alimenta a coluna Documento (espelha o `corpusDocs` do gerador). */
export const DOCS_GERADOS = new Set([
  'memory/reference/MAQUINAS-INVENTARIO.md',
  '.claude/hooks/_HOOKS-INDEX.md',
  '.claude/skills/_SKILLS-INDEX.md',
  'memory/governance/AUTOMATIONS.md',
  'memory/reference/PAINEL-SISTEMA.md',
]);
export function ehDocDoCorpus(p) {
  const s = String(p || '');
  if (DOCS_GERADOS.has(s) || !s.endsWith('.md')) return false;
  return s.startsWith('memory/') || s.startsWith('docs/');
}

/** Copia literal dos 3 regex do gerador (`maquinas-inventario.mjs`). O bite-test acusa drift. */
export const RX_ARQUIVO = /[\w.-]+\.(?:json|mjs|cjs|js|yaml|yml)\b/g;
export const RX_CRASE = /`([a-z0-9][\w.-]*)`/gi;
export const RX_PASTA = /(?:skills|agents)\/([\w.-]+)/g;

/** Tokens que o gerador extrairia deste texto. */
export function tokensDe(texto) {
  const t = new Set();
  const s = String(texto || '');
  for (const m of s.matchAll(RX_ARQUIVO)) t.add(m[0]);
  for (const m of s.matchAll(RX_CRASE)) t.add(m[1]);
  for (const m of s.matchAll(RX_PASTA)) t.add(m[1]);
  return t;
}

/** Nomes pelos quais cada maquina do indice pode ser citada: nome, basename e nome nu com hifen. */
export function tokensDasMaquinas(indice) {
  const t = new Set();
  for (const m of String(indice || '').matchAll(/^\| `([^`]+)`/gm)) {
    const nome = m[1];
    const base = nome.split('/').pop();
    t.add(nome); t.add(base);
    const nu = base.replace(/\.(mjs|js|cjs|yml|yaml|json|md)$/, '');
    if (nu.includes('-')) t.add(nu);
  }
  return t;
}

/** O diff (formato unificado) toca, em linha adicionada ou removida, o token de alguma maquina? */
export function diffCitaMaquina(diff, maquinas) {
  if (!maquinas || !maquinas.size) return false;
  for (const linha of String(diff || '').split('\n')) {
    if (!/^[+-]/.test(linha) || /^(\+\+\+|---)( |$)/.test(linha)) continue;
    for (const tok of tokensDe(linha.slice(1))) if (maquinas.has(tok)) return true;
  }
  return false;
}

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

/** Commit so de documento: dispara quando o diff do `.md` cita maquina (ou quando toca `CLAUDE.md`). */
function docTocaMaquina(cmd, paths, cwd) {
  if (paths.includes('CLAUDE.md')) return true;
  const docs = [...new Set(paths.filter(ehDocDoCorpus))];
  if (!docs.length) return false;
  let diff = '';
  try {
    diff = git(['diff', '--cached', '--no-renames', '-U0', '--', ...docs], cwd);
    if (levaWorkingTree(cmd)) diff += git(['diff', '--no-renames', '-U0', '--', ...docs], cwd);
  } catch {
    return false;                           // git falhou: nao invento estado
  }
  let indice = '';
  try { indice = readFileSync(INDICE, 'utf8'); } catch { return true; }   // sem indice: regenera
  return diffCitaMaquina(diff, tokensDasMaquinas(indice));
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
  if (!tocaCoberto(paths) && !docTocaMaquina(cmd, paths, cwd)) return 0;   // nao paga a medicao

  // --- so agora o passo CARO: quem decide e a medicao da arvore, nunca a presenca no diff.
  // Compara o CONTEUDO INTEIRO (saida do gerador × arquivo), nao so a cobertura. Ate 2026-09-22
  // este passo chamava o `--check`, que compara apenas os NOMES das maquinas (faltou/sobrou) —
  // e as colunas derivadas (Invocador, Leitor, Evidencia, Documento) envelheciam caladas: medido
  // no dia, o `main` tinha 2 linhas stale (`replica-inconsistencias` ganhou invocador `ci`;
  // `selftest-registry-check` mudou de documento) com o `--check` verde. O gerador e IDEMPOTENTE,
  // entao comparar bytes nao fabrica diff espurio; e a saida do modo dry E o que o `--write` grava,
  // logo ela e escrita direto — sem pagar os ~6,5s uma segunda vez.
  let fresco;
  try {
    fresco = execFileSync('node', [GERADOR], {
      cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 120000, maxBuffer: 64 * 1024 * 1024,
    });
  } catch {
    return 0;                               // crash/timeout: fail-open, nao afirmo staleness
  }
  if (typeof fresco !== 'string' || !fresco.trim()) return 0;   // saida vazia = nao medi
  let atual = null;
  try { atual = readFileSync(INDICE, 'utf8'); } catch { /* ausente: regenera */ }
  if (atual === fresco) return 0;           // indice fresco: SILENCIO (aqui morre o presence-gate)

  try {
    writeFileSync(INDICE, fresco);
  } catch {
    console.error('[maquinas-inventario] nao consegui gravar o indice; commit segue, indice fica stale.');
    return 0;
  }

  const explicito = temPathspecExplicito(cmd);
  if (explicito) {
    console.error(
      '[maquinas-inventario] REGENEREI ' + INDICE + ' (estava stale e o commit toca maquina ou doc que a cita).\n' +
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
    '  ou um documento que cita maquina (a coluna Documento deriva das citacoes),\n' +
    '  e o conteudo do indice divergia da arvore. Derivado com dono acompanha a mudanca do insumo.\n' +
    '  Se o diff do indice for maior que o seu toque, o excedente e DRIFT HERDADO: commits\n' +
    '  anteriores que nao regeneraram, ou documentos editados sem tocar maquina (a coluna\n' +
    '  Documento muda com eles). A comparacao e do arquivo INTEIRO, nao ha como pagar so a\n' +
    '  propria linha. Medido em 2026-09-15: 72 de 81 commits omitiram isso.'
  );
  return 0;
}

if (process.argv[1] && process.argv[1].endsWith('maquinas-inventario-no-commit.mjs')) {
  process.exit(main());
}
