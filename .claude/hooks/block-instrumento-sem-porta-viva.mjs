#!/usr/bin/env node
// block-instrumento-sem-porta-viva.mjs — PreToolUse:Glob|Grep.
//
// Bloqueia o INSTRUMENTO ERRADO quando existe PORTA VIVA registrada pra aquela pergunta.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// LICOES_CODE LC-08 (classe `afirmar-sem-medir-fonte-certa`, 7 ocorrências) +
// memory/proibicoes.md §5 (2026-07-15, 07-16, 07-17 ×3, 07-22 ×2).
// A lei positiva das lápides: "pergunte ao sistema que sabe" — o banco via SELECT,
// o scheduler via runsInEnvironment(), o mapa de tela via porta viva derivada.
//
// ── POR QUE ESTA FORMA (e não as que o §5 já matou) ──────────────────────────
// NÃO é gate de vocabulário ("afirmou sem medir") — essa forma foi MEDIDA e deu
// 130 falso-positivos em árvore limpa (§5 2026-07-16). NÃO é presence-gate
// (§5 2026-07-09). NÃO é mapa novo (§5 2026-07-23): a tabela de pares vive DENTRO
// do hook, como o block-test-fora-ct100 sabe "php artisan test → CT 100".
// O chokepoint é a TOOL CALL real — o instrumento que a lápide flagrou sendo usado.
//
// ── PARES (só entra par MEDIDO — tabela especulativa é o erro que isto mata) ──
//  P1 mapa-de-artefatos-de-tela → `npm run screen:files <Mod>/<Tela>`
//     MEDIDO 2026-07-24: a porta responde a MESMA pergunta e melhor (scorecard, e2e,
//     RUNBOOK, visual-comparison, proto-baseline, UC↔teste, veredito). Glob perdeu
//     todos esses em 07-22. Discriminação anti-FP: só morde com WILDCARD (varredura
//     = "montar mapa"); path literal (ler UM charter) passa.
//  P2 o-que-roda/rota/config → oráculo de runtime (schedule:list / route:list / config:show)
//     Enumerado literalmente em proibicoes.md §5 (2026-07-17, "deduzir quem roda").
//     FP conhecido e ACEITO: quem vai EDITAR o Kernel também grepa por schedule.
//     Mitigação = escape na própria mensagem (1 env var). Reversível: se der FP em
//     série, estreitar o pattern ou demover o par — não apagar o hook.
//
// Fail-open: qualquer erro/parse-fail → exit 0 (NUNCA trava sessão).
// Escape valve: OIMPRESSO_PORTA_VIVA_OK=1 (quando o instrumento cru É mesmo o certo).
// Selftest: node .claude/hooks/block-instrumento-sem-porta-viva.mjs --selftest
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { pathToFileURL, fileURLToPath } from 'node:url';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

// ── classificadores PUROS (exportados → testáveis sem stdin) ─────────────────

/** escape valve explícito (o instrumento cru é mesmo o certo desta vez). */
export function hasOverride(env = process.env) {
  return env.OIMPRESSO_PORTA_VIVA_OK === '1';
}

/** o pattern varre (wildcard/alternância) em vez de apontar UM arquivo? */
export function isVarredura(pattern) {
  return /[*?{}]|\*\*/.test(String(pattern || ''));
}

const ARTEFATO_DE_TELA = /\.charter\.md|\.casos\.md|scorecards[/\\]screens|proto-baseline|visual-comparison/;

/**
 * P1 — pergunta "quais artefatos/arquivos tem a tela?" feita por Glob/Grep.
 * Só morde em VARREDURA: `Produto/Edit.charter.md` (literal) é leitura legítima.
 *
 * DUAS PORTAS DE ENTRADA (a 2ª foi um FURO achado testando o próprio hook, 2026-07-24):
 *  a) `pattern` de path — o Glob do incidente 07-22.
 *  b) `glob` do Grep — escapava inteiro. Aqui a discriminação é o output_mode:
 *     pedir a LISTA DE ARQUIVOS (files_with_matches) de charter/casos é inventário
 *     (= mapa de tela, o antipadrão); buscar CONTEÚDO dentro deles (content/count)
 *     é pergunta legítima e passa — foi como medi "quais charters citam Odoo".
 */
export function isMapaDeTela(pattern, glob, outputMode) {
  const p = String(pattern || '');
  if (isVarredura(p) && ARTEFATO_DE_TELA.test(p)) return true;
  const g = String(glob || '');
  if (!g || !ARTEFATO_DE_TELA.test(g)) return false;
  // sem output_mode explícito o Grep já devolve files_with_matches (default = inventário)
  const modo = String(outputMode || 'files_with_matches');
  return modo === 'files_with_matches';
}

/**
 * P2 — pergunta "o que roda / qual rota / qual config?" feita por leitura estática.
 * Exige as DUAS pernas: o arquivo-fonte-errado E o vocabulário da pergunta-de-runtime.
 */
export function isPerguntaDeRuntime(pattern, path) {
  const p = String(pattern || '');
  const alvo = String(path || '');
  const fonteEstatica = /Kernel\.php|routes[/\\][\w-]+\.php|\.env|config[/\\][\w-]+\.php/.test(alvo + ' ' + p);
  if (!fonteEstatica) return false;
  return /schedule|environments|runsInEnvironment|->(daily|hourly|cron|weekly)|QUEUE_CONNECTION|Route::(get|post)|config\(/.test(p);
}

/**
 * P3 — `git log` perguntando DATA/CRIAÇÃO num repo com a history TRUNCADA.
 *
 * Duas pernas, e a 2ª é o que mantém o FP em zero:
 *  a) o comando pede data/criação (é isso que o piso do clone falsifica) — `git log
 *     --oneline -5` não pede nada disso e PASSA;
 *  b) o repo está DE FATO truncado. Em clone completo (o caso normal) nunca morde.
 */
export function pedeDataOuCriacao(command) {
  const c = String(command || '');
  if (!/\bgit\s+log\b/.test(c)) return false;
  return /--diff-filter=A|--follow|--date[= ]|--since|--until|--before|--after|%a[dDicrIs]|%c[dDirIs]/.test(c);
}

/**
 * A history do HEAD está truncada?
 *
 * ESPELHA `scripts/governance/sdd-scorecard.mjs::isShallowHistory()` — o **DONO** desta
 * regra (mesmo padrão que o `anchor-lint` já adota: espelhar, não importar). E espelha
 * inclusive a sutileza que o dono documenta: `--is-shallow-repository` sozinho é **grosso
 * demais** — um `git fetch <ref> --depth 1` marca o repo shallow SEM truncar a history do
 * HEAD. Só invalida a medição se algum boundary do `.git/shallow` for ANCESTRAL do HEAD.
 *
 * Fail-open em qualquer erro: um hook nunca trava a sessão por não conseguir medir.
 */
export function historicoTruncado(run = spawnSync, root = process.cwd()) {
  const git = (args) => {
    const r = run('git', args, { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
    if (!r || r.status !== 0) throw new Error('git falhou');
    return String(r.stdout || '').trim();
  };
  try {
    if (git(['rev-parse', '--is-shallow-repository']) !== 'true') return false;
    const gitDir = git(['rev-parse', '--git-dir']);
    let boundaries = [];
    try {
      boundaries = readFileSync(`${gitDir}/shallow`, 'utf8').split('\n').map((s) => s.trim()).filter(Boolean);
    } catch { return true; } // marcado shallow e sem conseguir ler os boundaries → conservador
    for (const sha of boundaries) {
      const r = run('git', ['merge-base', '--is-ancestor', sha, 'HEAD'], { cwd: root, stdio: 'ignore' });
      if (r && r.status === 0) return true; // boundary é ancestral do HEAD → history truncada
    }
    return false;
  } catch {
    return false; // fail-open: não sei medir → não bloqueio
  }
}

/** veredito único: qual par mordeu? (null = passa) */
export function classificar({ pattern, path, glob, output_mode, command } = {}, env = process.env, deps = {}) {
  if (hasOverride(env)) return null;
  if (isMapaDeTela(pattern, glob, output_mode)) return 'P1';
  if (isPerguntaDeRuntime(pattern, path)) return 'P2';
  // ordem importa: a perna barata (regex no comando) filtra ANTES de gastar spawn de git.
  if (pedeDataOuCriacao(command) && (deps.truncado ?? historicoTruncado)()) return 'P3';
  return null;
}

export const MENSAGENS = {
  P1: `[LC-08 / proibicoes §5 2026-07-22] BLOQUEADO: mapa de artefatos de tela por Glob/Grep.

O mapa de "quais arquivos cada tela tem" e um COMANDO derivado da arvore, nao uma busca
por nome. Glob-por-nome e incompleto POR CONSTRUCAO: em 2026-07-22 ele perdeu os
scorecards, os visual-comparison, o veredito de trio e a ambiguidade de resolucao.

PORTA VIVA (responde a mesma pergunta, e melhor):

  npm run screen:files <Mod>/<Tela>        # ex: npm run screen:files Produto/Edit
  npm run screen-coverage:report           # panorama de todas as telas
  npm run casos:report                     # cobertura de casos/UC

Ref: memory/how-trabalhar.md "Mapa de arquivos por tela — e COMANDO, nao arquivo".
Se o glob cru E mesmo o certo aqui: OIMPRESSO_PORTA_VIVA_OK=1`,

  P2: `[LC-08 / proibicoes §5 2026-07-17] BLOQUEADO: deduzir runtime lendo fonte estatica.

"O que roda / quem invoca / qual rota / qual config" NAO se responde por grep em
Kernel.php / routes / .env — num app modular a fonte e MULTIPLA (ServiceProviders de
modulo registram os proprios), entao analise file-scoped e incompleta por construcao.
Em 2026-07-17 errei 3x parseando e acertei na 1a vez que perguntei ao runtime.

PERGUNTE AO SISTEMA QUE SABE (no CT 100 — ADR 0062):

  tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan schedule:list"
  ... php artisan route:list        # rota
  ... php artisan config:show <k>   # config (nao leia o .env)
  Event::runsInEnvironment()        # a MESMA funcao que o schedule:run usa pra filtrar

Sem oraculo disponivel? Meca pela CONSEQUENCIA (artefato gerado, fila vazia, log com
timestamp) — nunca pela declaracao.
Se voce vai EDITAR o Kernel/rotas (nao perguntar o que roda): OIMPRESSO_PORTA_VIVA_OK=1`,

  P3: `[LC-08 / incidente 2026-07-24] BLOQUEADO: data de \`git log\` em repo com history TRUNCADA.

Este checkout e SHALLOW e algum boundary do .git/shallow e ANCESTRAL do HEAD. Nesse
estado \`git log\` NAO devolve a historia: devolve o PISO DO CLONE. Toda data de
--diff-filter=A vira "criado no dia em que o clone comeca", e --follow para no piso.

FOI EXATAMENTE ASSIM QUE ERREI EM 2026-07-24 (8a ocorrencia da LC-08): reportei como
FATO que 3 casos.md tinham nascido em 23/07 no #4719. Nasceram em 15/07, 19/07 e 22/07
(#4300, #4417, #4658). A assinatura estava a vista e eu quase deixei passar: o commit
do piso aparecia como ROOT (zero parents) com 16.081 arquivos "adicionados".

CONSERTE O INSTRUMENTO (nao contorne):

  git rev-parse --is-shallow-repository     # confirme
  git fetch --unshallow origin <branch>     # e re-meca

Sem poder desrasar? Pergunte ao sistema que sabe — a API do GitHub tem a historia
inteira: mcp__github__list_commits(owner, repo, path: "<arquivo>").

Ref: LICOES_CODE LC-08 · o DONO da regra e sdd-scorecard.mjs::isShallowHistory().
Se voce sabe que a data nao sustenta conclusao nenhuma aqui: OIMPRESSO_PORTA_VIVA_OK=1`,
};

// ── selftest (fixtures BOA/RUIM — controle-negativo prova que morde) ─────────
function selftest() {
  const casos = [
    // RUIM (deve bloquear)
    ['P1 varredura de charter', { pattern: '**/*.charter.md' }, 'P1'],
    ['P1 varredura de casos', { pattern: 'resources/js/Pages/**/*.casos.md' }, 'P1'],
    ['P1 scorecards por tela', { pattern: 'memory/governance/scorecards/screens/*.yaml' }, 'P1'],
    ['P2 grep schedule no Kernel', { pattern: '->daily\\(', path: 'app/Console/Kernel.php' }, 'P2'],
    ['P2 grep environments', { pattern: 'environments', path: 'app/Console/Kernel.php' }, 'P2'],
    ['P2 config pelo .env', { pattern: 'QUEUE_CONNECTION', path: '.env' }, 'P2'],
    // FURO achado testando o hook (2026-07-24): Grep entra por `glob`, não por `pattern`
    ['P1 inventario via glob do Grep', { pattern: 'related_prototype', glob: '**/*.charter.md', output_mode: 'files_with_matches' }, 'P1'],
    ['P1 glob sem output_mode (default = lista)', { pattern: 'UC-', glob: '**/*.casos.md' }, 'P1'],
    // BOA (deve passar — se qualquer uma bloquear, o hook backfira)
    ['ler UM charter literal', { pattern: 'resources/js/Pages/Produto/Edit.charter.md' }, null],
    ['glob de tsx comum', { pattern: 'resources/js/Pages/**/*.tsx' }, null],
    ['grep de negocio no Kernel', { pattern: 'PiiRedactor', path: 'app/Console/Kernel.php' }, null],
    ['grep schedule fora do Kernel', { pattern: 'schedule', path: 'Modules/Jana/README.md' }, null],
    ['glob de migrations', { pattern: '**/Database/Migrations/*.php' }, null],
    ['charter literal em Grep', { pattern: 'Non-Goals', path: 'Produto/Edit.charter.md' }, null],
    // busca de CONTEUDO dentro de charters e legitima (foi como medi "quais citam Odoo")
    ['conteudo em charters (count)', { pattern: 'Odoo|Shopify', glob: '**/*.charter.md', output_mode: 'count' }, null],
    ['conteudo em charters (content)', { pattern: 'Odoo', glob: '**/*.charter.md', output_mode: 'content' }, null],
  ];
  let ok = 0;
  const falhas = [];
  for (const [nome, input, esperado] of casos) {
    const got = classificar(input, {});
    if (got === esperado) ok++;
    else falhas.push(`  ✗ ${nome}: esperado ${esperado}, veio ${got}`);
  }

  // ── P3: as DUAS pernas, testadas separadamente ────────────────────────────
  // A perna do repo é injetada (`deps.truncado`) pra o selftest não depender do
  // estado do checkout de quem roda — senão o resultado muda de máquina pra máquina,
  // que é o oposto de um controle-negativo.
  const TRUNCADO = () => true;
  const COMPLETO = () => false;
  const p3 = [
    // RUIM: pede data E o repo está truncado
    ['P3 diff-filter=A em repo raso', { command: "git log --diff-filter=A --format='%ad' -- x.md" }, TRUNCADO, 'P3'],
    ['P3 --follow em repo raso', { command: 'git log --follow --date=short -- a/b.md' }, TRUNCADO, 'P3'],
    ['P3 --since em repo raso', { command: 'git log --since=2026-07-01 --oneline' }, TRUNCADO, 'P3'],
    // BOA: mesma pergunta, repo COMPLETO → a medida é válida, não morde
    ['P3 diff-filter=A em repo completo', { command: "git log --diff-filter=A --format='%ad' -- x.md" }, COMPLETO, null],
    ['P3 --follow em repo completo', { command: 'git log --follow --date=short -- a/b.md' }, COMPLETO, null],
    // BOA: repo truncado, mas o comando NÃO pergunta data → nada a falsificar
    ['P3 git log --oneline em repo raso', { command: 'git log --oneline -5' }, TRUNCADO, null],
    ['P3 git log --format=%s em repo raso', { command: 'git log --format=%s -3' }, TRUNCADO, null],
    ['P3 git status em repo raso', { command: 'git status --short' }, TRUNCADO, null],
    ['P3 comando sem git em repo raso', { command: 'npm run casos:report' }, TRUNCADO, null],
  ];
  for (const [nome, input, truncado, esperado] of p3) {
    const got = classificar(input, {}, { truncado });
    if (got === esperado) ok++;
    else falhas.push(`  ✗ ${nome}: esperado ${esperado}, veio ${got}`);
  }
  // a perna do repo, isolada: fail-open quando o git não responde
  const semGit = historicoTruncado(() => ({ status: 1, stdout: '' }));
  if (semGit === false) ok++;
  else falhas.push('  ✗ historicoTruncado deveria fail-open quando o git falha');
  // controle-negativo do escape
  const comOverride = classificar({ pattern: '**/*.charter.md' }, { OIMPRESSO_PORTA_VIVA_OK: '1' });
  if (comOverride === null) ok++;
  else falhas.push('  ✗ escape valve nao liberou');

  // ── INVOCAÇÃO REAL (não só o classificador puro) ──────────────────────────
  // Por que existe: em 2026-07-24 este selftest deu 13/13 VERDE enquanto o hook
  // NÃO mordia — `require()` em ESM lançava, o fail-open engolia e virava exit 0.
  // Classificador correto ≠ hook que morde (§5 "correção-do-mecanismo ≠ invocação").
  // Estes 2 casos rodam o PRÓPRIO script por stdin e conferem o EXIT CODE.
  const eu = fileURLToPath(import.meta.url);
  const roda = (payload) => spawnSync(process.execPath, [eu], {
    input: JSON.stringify(payload), encoding: 'utf8',
    env: { ...process.env, OIMPRESSO_PORTA_VIVA_OK: '' },
  });
  const bloqueio = roda({ tool_name: 'Glob', tool_input: { pattern: '**/*.charter.md' } });
  if (bloqueio.status === 2 && /PORTA VIVA/.test(bloqueio.stderr || '')) ok++;
  else falhas.push(`  ✗ invocacao real: esperado exit 2 + razao, veio exit ${bloqueio.status}`);

  const passagem = roda({ tool_name: 'Glob', tool_input: { pattern: 'resources/js/Pages/**/*.tsx' } });
  if (passagem.status === 0) ok++;
  else falhas.push(`  ✗ invocacao real (controle-negativo): esperado exit 0, veio ${passagem.status}`);

  // 2ª porta (glob do Grep) — o furo de 2026-07-24. Sem ESTE caso na invocação real, o
  // entrypoint pode deixar de repassar `glob` e o selftest do classificador segue verde.
  const viaGlob = roda({ tool_name: 'Grep', tool_input: { pattern: 'related_prototype', glob: '**/*.charter.md' } });
  if (viaGlob.status === 2) ok++;
  else falhas.push(`  ✗ invocacao real (porta glob): esperado exit 2, veio ${viaGlob.status}`);

  const conteudo = roda({ tool_name: 'Grep', tool_input: { pattern: 'Odoo', glob: '**/*.charter.md', output_mode: 'count' } });
  if (conteudo.status === 0) ok++;
  else falhas.push(`  ✗ invocacao real (conteudo legitimo): esperado exit 0, veio ${conteudo.status}`);

  // 3ª porta (Bash → P3). O `command` vinha de um matcher que o hook NEM TINHA até
  // 2026-07-24 (era Glob|Grep) — sem este caso, o P3 seria um chokepoint fantasma:
  // classificador verde, matcher que nunca casa (§5 2026-07-09).
  //
  // A perna do repo NÃO é injetável na invocação real (é subprocesso), então o veredito
  // depende do checkout de quem roda. Por isso o assert é CONDICIONAL: em repo raso tem
  // que bloquear; em repo completo tem que passar. Nos dois casos o `command` PRECISA
  // chegar ao classificador — é isso que o caso prova.
  const rasoAqui = historicoTruncado();
  const viaBash = roda({ tool_name: 'Bash', tool_input: { command: "git log --diff-filter=A --format='%ad' -- x.md" } });
  const esperadoBash = rasoAqui ? 2 : 0;
  if (viaBash.status === esperadoBash) ok++;
  else falhas.push(`  ✗ invocacao real (porta Bash/P3, repo ${rasoAqui ? 'raso' : 'completo'}): esperado exit ${esperadoBash}, veio ${viaBash.status}`);

  // controle-negativo da MESMA porta: comando sem pergunta-de-data passa SEMPRE.
  const bashInocente = roda({ tool_name: 'Bash', tool_input: { command: 'git log --oneline -5' } });
  if (bashInocente.status === 0) ok++;
  else falhas.push(`  ✗ invocacao real (Bash inocente): esperado exit 0, veio ${bashInocente.status}`);

  // casos (P1/P2) + p3 (as duas pernas) + 1 escape + 1 fail-open + 6 da invocação real
  const total = casos.length + p3.length + 8;
  console.log(`block-instrumento-sem-porta-viva selftest: ${ok}/${total}`);
  if (falhas.length) { console.error(falhas.join('\n')); process.exit(1); }
  console.log('  MORDE: varredura de charter/casos/scorecard + pergunta-de-runtime em fonte estatica');
  console.log('  PASSA: path literal, glob comum, grep de negocio, escape valve');
  console.log('  INVOCACAO REAL: exit 2 com razao no bloqueio, exit 0 na passagem');
  process.exit(0);
}

// ── entrypoint ───────────────────────────────────────────────────────────────
function main() {
  let raw = '';
  try { raw = readFileSync(0, 'utf8'); } catch { process.exit(0); }
  let par = null;
  try {
    const ev = JSON.parse(raw);
    const inp = ev.tool_input || {};
    // repassar TODOS os campos que o classificador lê — em 2026-07-24 o entrypoint
    // ficou pra trás 2× (require em ESM; depois glob/output_mode não repassados) e o
    // selftest do classificador seguiu VERDE. Por isso a invocação real cobre cada porta.
    par = classificar(
      { pattern: inp.pattern, path: inp.path, glob: inp.glob, output_mode: inp.output_mode, command: inp.command },
      process.env,
    );
  } catch { process.exit(0); } // fail-open
  if (!par) process.exit(0);
  console.error(MENSAGENS[par]);
  process.exit(2);
}

if (import.meta.url === pathToFileURL(process.argv[1] || '').href) {
  if (process.argv.includes('--selftest')) selftest();
  else main();
}
