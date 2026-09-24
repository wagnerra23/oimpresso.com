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

import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const INDICE = 'memory/reference/MAQUINAS-INVENTARIO.md';
const GERADOR = 'scripts/governance/maquinas-inventario.mjs';
const GERADOR_SUPERFICIE = 'scripts/governance/module-surface.mjs';

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

/**
 * Tira do comando o que NAO e comando: o corpo de heredoc e o texto entre aspas com espaco.
 *
 * MEDIDO em 2026-09-23 (revisao adversarial do #7824, corpus de transcripts): 77,7% dos commits
 * reais passam a mensagem por heredoc (`git commit -F - <<'EOF'`). Sem esta limpeza, palavra da
 * MENSAGEM virava argumento: `--all` na mensagem ligava o `-a` a toa (85 comandos) e `--` ligava o
 * "pathspec explicito" (105), caso em que o hook regenera e NAO estagia. Foi assim que o inventario
 * entrou "certo" no #7794: a mensagem dele continha `--all`.
 *
 * Aspas SEM espaco ficam (so perdem as aspas), para `git add "a.md"` continuar sendo lido; aspas
 * COM espaco viram um marcador neutro — o hook nao interpreta path com espaco (limite declarado).
 */
export function limpaComando(cmd) {
  const out = [];
  let fim = null;
  for (const linha of String(cmd || '').split(/\r?\n/)) {
    if (fim !== null) {
      if (linha.trim() === fim) fim = null;              // terminador do heredoc
      continue;                                          // corpo do heredoc: nao e comando
    }
    const m = linha.match(/<<-?\s*(['"]?)([A-Za-z_][A-Za-z0-9_]*)\1/);
    if (m) { fim = m[2]; out.push(linha.slice(0, m.index)); continue; }
    out.push(linha);
  }
  return out.join('\n').replace(/"([^"]*)"|'([^']*)'/g, (_, a, b) => {
    const v = a !== undefined ? a : b;
    return v === '' || /\s/.test(v) ? ' __TEXTO__ ' : v;
  });
}

/**
 * Divide o comando em segmentos independentes (`;` `&&` `||` `|` e QUEBRA DE LINHA).
 * A quebra de linha entrou em 2026-09-23: `git add x` numa linha e `git commit` na seguinte
 * (776 comandos no corpus, ~14% dos commits) nao era nem reconhecido como commit.
 */
export function segmentos(cmd) {
  if (typeof cmd !== 'string' || !cmd) return [];
  return limpaComando(cmd).split(/\|\||&&|[;&|\n]/).map((s) => s.trim()).filter(Boolean);
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

/**
 * O `git add` que vem ANTES do commit no MESMO comando (2026-09-23).
 *
 * O hook e PreToolUse: roda antes do shell executar o comando inteiro. Em `git add X && git
 * commit`, quando ele olha o indice o `add` ainda nao aconteceu — ele via o stage VAZIO e nao
 * fazia nada. MEDIDO no corpus de transcripts (1913 arquivos, 5535 comandos com `git commit`):
 * 4657 (84,1%) tem o `git add` na mesma chamada. Ou seja, os tres passos deste hook ficavam
 * inertes em 84% dos commits reais — e os indices envelheciam com o hook "instalado".
 *
 * Por isso os passos passaram a somar, ao que ja esta no stage, o que esses `add` vao estagiar.
 * O estado de cada arquivo sai do `git status --porcelain` (nao de adivinhar pelo nome).
 * LIMITE DECLARADO: pathspec com espaco/aspas complexas nao e interpretado; nesse caso o hook
 * volta ao comportamento antigo (ve so o stage), nunca inventa arquivo.
 */
export function argsDosAddsAntes(cmd) {
  const out = [];
  for (const s of segmentos(cmd)) {
    if (argsDoCommit(s) !== null) break;                // so os `add` ANTES do commit
    const t = String(s).split(/\s+/).filter(Boolean);
    if (t[0] !== 'git') continue;
    let i = 1;
    while (i < t.length && t[i].startsWith('-')) i += OPCOES_COM_VALOR.has(t[i]) ? 2 : 1;
    if (t[i] !== 'add') continue;
    for (const a of t.slice(i + 1)) out.push(a.replace(/^['"]|['"]$/g, ''));
  }
  return out;
}

/** Converte a saida do `git status --porcelain` em linhas `STATUS<TAB>path` + os nao rastreados. */
export function statusDoPorcelain(porcelain, { incluiNaoRastreados = true } = {}) {
  const TAB = String.fromCharCode(9);
  const linhas = [];
  const entrando = new Set();
  for (const l of String(porcelain || '').split(/\r?\n/)) {
    if (l.length < 4) continue;
    const xy = l.slice(0, 2);
    const p = l.slice(3).trim();
    if (!p || p.startsWith('"')) continue;              // path citado (espaco/unicode): nao interpreto
    if (xy === '??') {
      if (!incluiNaoRastreados) continue;
      linhas.push('A' + TAB + p); entrando.add(p);
    } else if (xy.includes('D')) linhas.push('D' + TAB + p);
    else linhas.push('M' + TAB + p);
  }
  return { ns: linhas.join('\n'), entrando };
}

/** O que os `git add` deste comando vao estagiar. `{ ns, entrando, docs }` — vazio se nao ha add. */
function pendentesDoAdd(cmd, cwd) {
  const vazio = { ns: '', entrando: new Set() };
  const args = argsDosAddsAntes(cmd);
  if (!args.length) return vazio;
  const tudo = args.some((a) => ['-A', '--all', '.', ':/', '-u', '--update'].includes(a));
  const specs = args.filter((a) => !a.startsWith('-'));
  if (!tudo && !specs.length) return vazio;
  const soRastreados = args.includes('-u') || args.includes('--update');
  try {
    const por = git(['status', '--porcelain', '--untracked-files=all', '--no-renames', ...(tudo ? [] : ['--', ...specs])], cwd);
    return statusDoPorcelain(por, { incluiNaoRastreados: !soRastreados });
  } catch {
    return vazio;                                       // git falhou: fico so com o stage
  }
}

/** Commit so de documento: dispara quando o diff do `.md` cita maquina (ou quando toca `CLAUDE.md`). */
function docTocaMaquina(cmd, paths, cwd, pend = { entrando: new Set() }) {
  if (paths.includes('CLAUDE.md')) return true;
  const docs = [...new Set(paths.filter(ehDocDoCorpus))];
  if (!docs.length) return false;
  let diff = '';
  try {
    diff = git(['diff', '--cached', '--no-renames', '-U0', '--', ...docs], cwd);
    // working tree: o `-a` OU o `git add` do mesmo comando vao levar estas mudancas
    const rastreados = docs.filter((d) => !pend.entrando.has(d));
    if (rastreados.length && (levaWorkingTree(cmd) || argsDosAddsAntes(cmd).length)) {
      diff += git(['diff', '--no-renames', '-U0', '--', ...rastreados], cwd);
    }
    // arquivo NOVO que o add vai estagiar nao tem diff: o conteudo inteiro e linha adicionada
    for (const d of docs.filter((x) => pend.entrando.has(x))) {
      try { diff += '\n' + readFileSync(resolve(cwd, d), 'utf8').split(/\r?\n/).map((l) => '+' + l).join('\n'); } catch { /* sumiu */ }
    }
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

async function main() {
  const raw = lerStdin();
  let cmd = '';
  try {
    cmd = (JSON.parse(raw).tool_input || {}).command || '';
  } catch {
    return 0;
  }

  if (!ehGitCommit(cmd)) return 0;          // 0ms no caso comum

  const cwd = process.cwd();
  const pend = pendentesDoAdd(cmd, cwd);    // o que o `git add` deste mesmo comando vai estagiar
  passoInventario(cmd, cwd, pend);
  await passoSuperficie(cmd, cwd, pend);
  passoIndices(cmd, cwd, pend);
  await passoStatus(cmd, cwd, pend);
  return 0;
}

/** Passo 1 — o indice de maquinas. */
function passoInventario(cmd, cwd, pend = { ns: '', entrando: new Set() }) {
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
  paths = paths.concat(pathsTocados(pend.ns));
  if (!tocaCoberto(paths) && !docTocaMaquina(cmd, paths, cwd, pend)) return 0;   // nao paga a medicao

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

/**
 * Passo 2 — a SUPERFICIE.md de cada modulo (`module-surface.mjs`), estendido em 2026-09-23.
 *
 * POR QUE: o `module-surface --all --check` reprova QUALQUER PR aberto quando um
 * modulo esta com drift — nao so o PR que o causou. Medido em 2026-09-23: #7761 e #7762 criaram
 * `.casos.md` em Essentials sem regerar, e #7765 e #7780 (que nao tocavam aquele mapa) ficaram
 * vermelhos por isso. O conserto e o mesmo do passo 1: quem muda o insumo regenera o derivado.
 *
 * FILTRO BARATO: a superficie depende so de QUAIS paths existem (o gerador nao le conteudo para
 * montar o mapa). Logo so importa o commit que CRIA ou APAGA arquivo (`A`/`D` com
 * `--no-renames`, que desdobra rename em D+A). Editar arquivo existente custa ~0ms.
 *
 * QUEM DECIDE e o `--check` do modulo, que compara o CONTEUDO INTEIRO. Quais modulos medir
 * vem do proprio gerador (`modulosAfetados`, que le as mesmas raizes do `coletar`): o hook nao
 * tem lista propria de raizes, porque lista escrita a mao apodrece.
 *
 * ESCOPO: so regenera modulo que ESTE commit tocou. Drift herdado de outro modulo nao entra no
 * commit alheio — foi o que tive de desfazer a mao no #7780.
 *
 * LIMITES DECLARADOS (o hook pula e avisa, nunca grava um mapa que o CI nao reproduz):
 *   · arquivo NAO RASTREADO sob as raizes do modulo: o gerador le `--others`, entao ele
 *     entraria no mapa sem entrar no commit;
 *   · `git commit -a` com arquivo APAGADO sem `git rm`: o indice ainda o lista, o commit nao.
 * O aviso sai em stderr com exit 0; o efeito que importa (gravar + estagiar) nao depende dele.
 */
export function pathsQueMudamOMapa(nameStatus) {
  const out = [];
  for (const linha of String(nameStatus || '').split(/\r?\n/)) {
    const partes = linha.split(String.fromCharCode(9));
    if (partes.length === 2 && (partes[0] === 'A' || partes[0] === 'D') && partes[1].trim()) out.push(partes[1].trim());
  }
  return out;
}

async function passoSuperficie(cmd, cwd, pend = { ns: '', entrando: new Set() }) {
  if (!existsSync(GERADOR_SUPERFICIE)) return;             // sandbox/checkout parcial: fail-open
  let staged = '';
  let wt = '';
  try {
    staged = git(['diff', '--cached', '--name-status', '--no-renames'], cwd);
    if (levaWorkingTree(cmd)) wt = git(['diff', '--name-status', '--no-renames'], cwd);
  } catch {
    return;                                               // git falhou: nao invento estado
  }
  const paths = pathsQueMudamOMapa(staged + '\n' + wt + '\n' + pend.ns);
  if (!paths.length) return;                              // so edicao: o mapa nao muda

  let ms;
  try {
    ms = await import(pathToFileURL(resolve(cwd, GERADOR_SUPERFICIE)).href);
  } catch {
    return;
  }
  let mods = [];
  try { mods = ms.modulosAfetados(paths); } catch { return; }
  if (!mods.length) return;

  let soltos = [];
  try { soltos = git(['ls-files', '--others', '--exclude-standard'], cwd).split(/\r?\n/).filter(Boolean); } catch { return; }
  // o que o `git add` deste mesmo comando vai estagiar deixa de ser "solto": vai entrar no commit
  soltos = soltos.filter((p) => !pend.entrando.has(p));
  const apagadosSoNoDisco = pathsQueMudamOMapa(wt).filter((p) => !existsSync(p));
  const explicito = temPathspecExplicito(cmd);

  for (const mod of mods) {
    const alvo = `memory/requisitos/${mod}/SUPERFICIE.md`;
    let exige = false;
    try { exige = ms.isSurfaceRequired(mod); } catch { /* sem opiniao: so segue se o arquivo existir */ }
    if (!existsSync(alvo) && !exige) continue;            // modulo sem opt-in: nao cria arquivo

    const doMod = (lista) => lista.filter((p) => ms.modulosAfetados([p]).includes(mod));
    const soltosDoMod = doMod(soltos);
    const fantasmas = doMod(apagadosSoNoDisco);
    if (soltosDoMod.length || fantasmas.length) {
      const motivo = soltosDoMod.length
        ? soltosDoMod.length + ' arquivo(s) nao rastreado(s) sob o modulo (ex.: ' + soltosDoMod.slice(0, 2).join(', ') + ')'
        : 'arquivo apagado sem `git rm` num commit -a (ex.: ' + fantasmas.slice(0, 2).join(', ') + ')';
      console.error('[module-surface] NAO regenerei ' + alvo + ': ' + motivo + '.\n' +
        '  O mapa sairia diferente do que o CI ve. Resolva (add ou .gitignore / git rm) e rode:\n' +
        '  node ' + GERADOR_SUPERFICIE + ' ' + mod + ' --write');
      continue;
    }

    const check = spawnSync('node', [GERADOR_SUPERFICIE, mod, '--check'], { cwd, encoding: 'utf8', timeout: 120000 });
    if (check.status === 0) continue;                     // fresco: SILENCIO
    if (check.status !== 1 || !String(check.stderr || '').includes('DRIFT em')) continue;  // nao medi: fail-open

    const w = spawnSync('node', [GERADOR_SUPERFICIE, mod, '--write'], { cwd, encoding: 'utf8', timeout: 120000 });
    if (w.status !== 0) {
      console.error('[module-surface] o --write de ' + mod + ' falhou; commit segue com ' + alvo + ' stale.');
      continue;
    }
    if (explicito) {
      console.error('[module-surface] REGENEREI ' + alvo + ' (o commit cria/apaga arquivo do modulo).\n' +
        '  Seu commit tem pathspec explicito, entao NAO estagiei — inclua o arquivo voce mesmo.');
      continue;
    }
    try {
      git(['add', '--', alvo], cwd);
    } catch {
      console.error('[module-surface] regenerei, mas o `git add` falhou. Estagie a mao: ' + alvo);
      continue;
    }
    console.error('[module-surface] REGENEREI e ESTAGIEI ' + alvo + '.\n' +
      '  Motivo: este commit cria ou apaga arquivo de ' + mod + ', e o mapa do modulo divergia da arvore.\n' +
      '  Sem isso o `module-surface --all --check` reprova este PR e todo PR aberto depois dele.');
  }
}

/**
 * Passo 3 — indices GLOBAIS derivados de documento (estendido em 2026-09-23).
 *
 * POR QUE: no mesmo dia, dois indices gerados envelheceram no `main` porque quem mudou o
 * insumo nao regenerou, e o Governance Gate reprovou o PR seguinte (#7799): o backlog nao
 * contava a US nova, e o indice de planos nao listava o plano da Onda 2 (#7746). O conserto
 * e o mesmo dos passos 1 e 2: quem muda o insumo regenera o derivado.
 *
 * Cada entrada diz QUAL gerador, QUAL arquivo ele grava e QUAIS paths o alimentam. O filtro
 * e deliberadamente MAIS LARGO que o gerador (so decide se vale pagar a medicao); quem decide
 * e o `--check` do proprio gerador, que compara o arquivo INTEIRO. Indice fresco = silencio.
 *
 * DRIFT HERDADO: estes indices sao um arquivo so para o repo inteiro, entao a regeneracao
 * traz tambem o que outros commits deixaram de regenerar — igual ao passo 1, e a mensagem diz.
 *
 * `ler(path, rev)` e injetado para o filtro ser testavel sem git.
 */
export const INDICES = [
  {
    nome: 'backlog',
    gerador: 'scripts/governance/tasks-index-generate.mjs',
    saida: 'memory/requisitos/_BACKLOG-GENERATED.md',
    marca: 'drift',
    // o gerador le TODO memory/requisitos/<Mod>/SPEC.md (blocos US + linha de metadados)
    toca: (p) => /^memory\/requisitos\/[^/]+\/SPEC\.md$/.test(p),
  },
  {
    nome: 'planos',
    gerador: 'scripts/governance/plans-index.mjs',
    saida: 'memory/requisitos/_processo/PLANS-INDEX-GENERATED.md',
    marca: 'DESATUALIZADO',
    // REGISTRADO = `.md` com bloco `## Status vivo` em memory/requisitos/** ou memory/sessions/**;
    // PENDENTE = `.md` de nome *plan* em memory/requisitos/**. O bloco pode ter sido posto OU
    // tirado neste commit, entao olha a versao atual e a do HEAD.
    toca: (p, ler) => {
      if (!p.endsWith('.md')) return false;
      if (!p.startsWith('memory/requisitos/') && !p.startsWith('memory/sessions/')) return false;
      if (p === 'memory/requisitos/_processo/PLANS-INDEX-GENERATED.md' || p === 'memory/requisitos/_processo/PLANS-INDEX.md') return false;
      if (p.startsWith('memory/requisitos/') && /plan/i.test(p.split('/').pop())) return true;
      return ['', 'HEAD'].some((rev) => String(ler(p, rev) || '').includes('Status vivo'));
    },
  },
];

/** Paths tocados pelo commit, qualquer status (M conta: estes indices leem CONTEUDO). */
export function pathsTocados(nameStatus) {
  const out = [];
  for (const linha of String(nameStatus || '').split(/\r?\n/)) {
    const partes = linha.split(String.fromCharCode(9));
    if (partes.length === 2 && /^[AMDT]$/.test(partes[0]) && partes[1].trim()) out.push(partes[1].trim());
  }
  return out;
}

/** Quais indices este conjunto de paths pode ter envelhecido. Puro, testavel. */
export function indicesAfetados(paths, ler) {
  return INDICES.filter((ix) => paths.some((p) => {
    try { return ix.toca(p, ler); } catch { return false; }
  }));
}

function passoIndices(cmd, cwd, pend = { ns: '', entrando: new Set() }) {
  let ns = '';
  try {
    ns = git(['diff', '--cached', '--name-status', '--no-renames'], cwd);
    if (levaWorkingTree(cmd)) ns += '\n' + git(['diff', '--name-status', '--no-renames'], cwd);
  } catch {
    return;                                               // git falhou: nao invento estado
  }
  const paths = pathsTocados(ns + '\n' + pend.ns);
  if (!paths.length) return;
  const ler = (p, rev) => {
    try {
      return rev ? git(['show', `${rev}:${p}`], cwd) : readFileSync(resolve(cwd, p), 'utf8');
    } catch {
      return '';                                          // arquivo novo (sem HEAD) ou apagado
    }
  };
  const alvos = indicesAfetados(paths, ler).filter((ix) => existsSync(resolve(cwd, ix.gerador)));
  if (!alvos.length) return;
  const explicito = temPathspecExplicito(cmd);

  for (const ix of alvos) {
    const check = spawnSync('node', [ix.gerador, '--check'], { cwd, encoding: 'utf8', timeout: 120000 });
    if (check.status === 0) continue;                     // fresco: SILENCIO
    const saidaCheck = String(check.stdout || '') + String(check.stderr || '');
    if (check.status !== 1 || !saidaCheck.includes(ix.marca)) continue;   // nao medi: fail-open

    const w = spawnSync('node', [ix.gerador, '--write'], { cwd, encoding: 'utf8', timeout: 120000 });
    if (w.status !== 0) {
      console.error('[indices] o --write do indice de ' + ix.nome + ' falhou; commit segue com ' + ix.saida + ' stale.');
      continue;
    }
    if (explicito) {
      console.error('[indices] REGENEREI ' + ix.saida + ' (o commit toca o que o alimenta).\n' +
        '  Seu commit tem pathspec explicito, entao NAO estagiei — inclua o arquivo voce mesmo.');
      continue;
    }
    try {
      git(['add', '--', ix.saida], cwd);
    } catch {
      console.error('[indices] regenerei, mas o `git add` falhou. Estagie a mao: ' + ix.saida);
      continue;
    }
    console.error('[indices] REGENEREI e ESTAGIEI ' + ix.saida + ' (indice de ' + ix.nome + ').\n' +
      '  Motivo: este commit toca o que alimenta o indice, e o arquivo divergia do gerador.\n' +
      '  Se o diff for maior que o seu toque, o excedente e DRIFT HERDADO de commits que nao regeneraram.');
  }
}

/**
 * Passo 4 — o `_STATUS-GENERATED.md` de cada módulo (`requisitos-status.mjs`), 2026-09-23.
 *
 * POR QUE: o status é a cadeia US (SPEC) → CU (SDD) → UC (casos) → teste, derivada. Quem mexe
 * num elo sem regenerar deixa o arquivo drifado — medido no dia: o do Financeiro estava drifado
 * no `main`, e o Governance Gate reprova o PR que tocar o módulo.
 *
 * DOIS CAMINHOS DE ENTRADA, os dois perguntados ao PRÓPRIO gerador (o hook não tem lista):
 *   · path do módulo (SPEC, SDD, `_telas/`, bases de Pages) → `modulosDoStatus`;
 *   · TESTE: o corpus de testes é global, e um teste só muda o status do módulo cujo UC ele cita.
 *     Os UC saem das linhas que o commit muda nesses testes (adicionadas ou removidas; teste
 *     novo entra inteiro), pelo regex canônico `scripts/lib/uc-regex.mjs` → `modulosComUC`.
 * Só módulo que JÁ tem `_STATUS-GENERATED.md`: o hook não cria arquivo.
 *
 * CUSTO: cada `--check` custa 2–4 s (o gerador varre todos os testes do repo), por isso o filtro
 * tem de acertar o módulo — rodar os 18 a cada commit daria perto de 1 minuto.
 *
 * LIMITE DECLARADO: o gerador lê o DISCO. Mudança não estagiada num insumo do módulo, que não
 * vai no commit, entra no status regenerado — o mesmo limite do passo 1.
 */
async function passoStatus(cmd, cwd, pend = { ns: '', entrando: new Set() }) {
  const GER = 'scripts/governance/requisitos-status.mjs';
  if (!existsSync(resolve(cwd, GER))) return;               // sandbox/checkout parcial: fail-open
  let ns = '';
  try {
    ns = git(['diff', '--cached', '--name-status', '--no-renames'], cwd);
    if (levaWorkingTree(cmd)) ns += '\n' + git(['diff', '--name-status', '--no-renames'], cwd);
  } catch {
    return;                                                 // git falhou: nao invento estado
  }
  const paths = pathsTocados(ns + '\n' + pend.ns);
  if (!paths.length) return;

  let rs;
  let uc;
  try {
    rs = await import(pathToFileURL(resolve(cwd, GER)).href);
    uc = await import(pathToFileURL(resolve(cwd, 'scripts/lib/uc-regex.mjs')).href);
  } catch {
    return;
  }
  const mods = new Set();
  try { for (const m of rs.modulosDoStatus(paths)) mods.add(m); } catch { return; }

  const testes = paths.filter((p) => { try { return rs.ehTesteDaCadeia(p); } catch { return false; } });
  if (testes.length) {
    let diff = '';
    try {
      diff = git(['diff', '--cached', '--no-renames', '-U0', '--', ...testes], cwd);
      const rastreados = testes.filter((t) => !pend.entrando.has(t));
      if (rastreados.length && (levaWorkingTree(cmd) || argsDosAddsAntes(cmd).length)) {
        diff += '\n' + git(['diff', '--no-renames', '-U0', '--', ...rastreados], cwd);
      }
      for (const t of testes.filter((x) => pend.entrando.has(x))) {
        try { diff += '\n' + readFileSync(resolve(cwd, t), 'utf8').split(/\r?\n/).map((l) => '+' + l).join('\n'); } catch { /* sumiu */ }
      }
    } catch {
      diff = '';                                            // sem diff legivel: so o caminho por path
    }
    const ids = new Set();
    for (const l of diff.split(/\r?\n/)) {
      if (!/^[+-]/.test(l) || /^(\+\+\+|---)( |$)/.test(l)) continue;
      for (const m of l.matchAll(uc.ucScanRe())) ids.add(m[0].toUpperCase());
    }
    try { for (const m of rs.modulosComUC([...ids])) mods.add(m); } catch { /* fica so o que ja achou */ }
  }
  if (!mods.size) return;

  const explicito = temPathspecExplicito(cmd);
  for (const mod of [...mods].sort()) {
    const alvo = `memory/requisitos/${mod}/_STATUS-GENERATED.md`;
    const check = spawnSync('node', [GER, mod, '--check'], { cwd, encoding: 'utf8', timeout: 120000 });
    if (check.status === 0) continue;                       // fresco: SILENCIO
    if (check.status !== 1 || !String(check.stdout || '').includes('DRIFADO')) continue;   // nao medi: fail-open

    const w = spawnSync('node', [GER, mod, '--write'], { cwd, encoding: 'utf8', timeout: 120000 });
    if (w.status !== 0) {
      console.error('[status] o --write de ' + mod + ' falhou; commit segue com ' + alvo + ' stale.');
      continue;
    }
    if (explicito) {
      console.error('[status] REGENEREI ' + alvo + ' (o commit toca a cadeia do modulo).\n' +
        '  Seu commit tem pathspec explicito, entao NAO estagiei — inclua o arquivo voce mesmo.');
      continue;
    }
    try {
      git(['add', '--', alvo], cwd);
    } catch {
      console.error('[status] regenerei, mas o `git add` falhou. Estagie a mao: ' + alvo);
      continue;
    }
    console.error('[status] REGENEREI e ESTAGIEI ' + alvo + '.\n' +
      '  Motivo: este commit mexe na cadeia US → CU → UC → teste de ' + mod + ', e o status divergia.\n' +
      '  Se o diff for maior que o seu toque, o excedente e DRIFT HERDADO de commits que nao regeneraram.');
  }
}

if (process.argv[1] && process.argv[1].endsWith('maquinas-inventario-no-commit.mjs')) {
  main().then((rc) => process.exit(rc), () => process.exit(0));   // fail-open ate no erro inesperado
}
