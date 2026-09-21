#!/usr/bin/env node
// block-destructive.mjs — PreToolUse:Bash (PORTE cross-plataforma do .ps1).
// BLOQUEIA comandos Bash destrutivos sem confirmação humana.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// US-COPI-085 (Cycle 01, guardrails Bash) + ADR 0063 (composer.lock sem drift)
// + proibições §Ambiente ("nunca composer update sem --lock em prod"). 8 categorias:
//   1. rm -rf fora da whitelist (/tmp, node_modules, vendor, caches de build)
//   2. git push --force / -f (qualquer force exige confirmação explícita Wagner)
//   3. git reset --hard origin/* (descarta trabalho local não-pushed)
//   4. DROP TABLE/DATABASE/SCHEMA
//   5. DELETE FROM sem WHERE  ·  6. DELETE WHERE 1=1
//   7. composer update sem --lock (ADR 0063)
//   8. php artisan migrate:fresh/reset/wipe  ·  TRUNCATE
//
// FIX DE FIDELIDADE À REGRA (documentado no PR do porte): o regex do .ps1 pra
// "DELETE sem WHERE" sofria backtracking (`\w+` recuava e o lookahead negativo
// nunca via o WHERE) — na prática bloqueava TODO `DELETE FROM`, com ou sem WHERE.
// O porte implementa a regra COMO ESCRITA no contrato: `DELETE FROM x WHERE id=1`
// passa; sem WHERE (ou WHERE 1=1) bloqueia.
//
// ── POR QUE .mjs (triagem 2026-07-09, classe Tier-0-esquecido) ───────────────
// Irreversibilidade não tem retry: rm -rf/DROP/force-push destroem trabalho e dado
// de prod SEM caminho de volta, em QUALQUER sistema operacional. O .ps1 só rodava no
// Windows do Wagner — time MCP (Felipe/Maiara/Luiz) em Mac/Linux ficaria sem o
// guardrail em silêncio. Nenhum gate CI substitui (o vetor é runtime, pré-commit).
// grade.mjs (régua R-canon) referencia este hook — baseline 33% preservado.
//
// Fail-open: qualquer erro/parse-fail → exit 0 (NUNCA trava sessão).
// PS `-match` era case-insensitive por default → todos os padrões levam /i (fidelidade).
// Selftest: node .claude/hooks/block-destructive.test.mjs
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { spawnSync } from 'node:child_process';
import { posix as caminhoPosix } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

/** normaliza espaços múltiplos pra regex consistente (fidelidade ao .ps1). */
export function normalizeCmd(cmd) {
  return String(cmd || '').replace(/\s+/g, ' ').trim();
}

// ── FATIAMENTO EM STATEMENTS (fix 2026-09-16 — FP medido da git-force-push) ───
//
// O DEFEITO: `normalizeCmd` colapsa \s+ (INCLUSIVE \n) num espaço só, então um
// bloco multi-linha vira UMA string. Padrão com `.*` passa a atravessar comandos
// sem relação: um `git push` benigno + um `--force` de OUTRO statement (ou de
// prosa dentro de heredoc de PR body) casavam e bloqueavam.
//
// MEDIDO no corpus real (1.786 .jsonl · 153.345 blocos tool_use Bash/PowerShell,
// nunca prosa — em 2026-09-16, reproduzível pelo script do PR):
//   git-force-push .......... 216 bloqueios hoje · 210 per-statement · 6 FP
//     └ os 6 lidos INTEIROS, um a um: `--force` vinha de prosa em heredoc
//       (`migrate --force`, `git fetch --force`) ou de statement irmão
//       (`git worktree remove --force`). ZERO force push genuíno entre eles.
//   falso-negativo .......... 0 (nenhum comando passa a escapar)
//   split agressivo × conservador ... resultado IDÊNTICO (a escolha não é load-bearing)
//
// Por que \n é fatiado ANTES de normalizar: depois de `normalizeCmd` ele já não
// existe, e era justamente o separador do incidente que abriu este fix.
//
// Continuação de linha (`\` no fim) é JUNTADA primeiro — senão `git push \<nl>
// --force` seria fatiado ao meio e viraria falso-NEGATIVO (o lado perigoso).
// Medido: 82 comandos do corpus têm continuação + `git push`; juntar muda o
// veredito em 0 deles — mas a semântica correta protege a forma, não a amostra.

/** junta continuação de linha: `\` + newline é UM comando, não dois. */
function juntaContinuacoes(cmd) {
  return String(cmd || '').replace(/\\\r?\n/g, ' ');
}

/**
 * Fatia o comando nos separadores de shell e normaliza CADA pedaço.
 *
 * Separadores: `\n` `;` `&&` `||`. O `|` e o `&` SOZINHOS ficam de fora **por
 * medição** (2026-09-16): eles são ambíguos fora do contexto de shell — `2>&1`
 * tem `&`, e `grep -E "a|rm -rf|b"` tem `|` como ALTERNAÇÃO dentro de aspas.
 * Fatiar neles isola `rm -rf` de um padrão de grep e fabrica bloqueio: era o
 * único falso-positivo novo da leva de rm, num comando read-only de sonda.
 * Pra `git-force-push` os dois modos deram resultado IDÊNTICO (216→210), então
 * o conservador não custa nada e não fragmenta o que não é statement.
 *
 * @returns {string[]} statements normalizados, sem vazios
 */
export function statements(cmd) {
  return juntaContinuacoes(cmd)
    .split(/\r?\n|;|&&|\|\|/)
    .map(normalizeCmd)
    .filter(Boolean);
}

// ── WHITELIST rm: por STATEMENT, não pelo comando inteiro ([W] 2026-09-16) ────
//
// O `^` destes padrões ancorava no comando INTEIRO normalizado, então um `cd`
// antes do rm derrubava a whitelist: `rm -rf node_modules` passava e o MESMO
// comando depois de `cd x &&` bloqueava — enquanto rodar os dois em chamadas
// separadas sempre funcionou. A proteção era acidental, não real.
//
// MEDIDO no corpus (153.4k blocos tool_use Bash/PowerShell, 2026-09-16):
//   19 destravados — TODOS lidos: `/tmp/*` (14), `public/build-inertia` (2),
//   `node_modules` (2) e 1 prosa de PR body. Zero alvo fora da whitelist.
//   0 novos bloqueios.
//
// ⚠️ Isto AFROUXA um guardrail Tier-0 e foi decisão explícita do [W] — não é
// efeito colateral. O que passa a ser permitido é exatamente o que a whitelist
// já permitia da cwd; muda só o poder existir um `cd` (ou qualquer comando)
// antes. Alvo FORA da whitelist segue bloqueado em qualquer posição.
//
// Efeito colateral BOM: por statement, cada rm é julgado sozinho, então
// `rm -rf /tmp/a && rm -rf src/` bloqueia — antes o 1º comando whitelistava o
// blob inteiro e o 2º passava (falso-negativo; 0 ocorrências medidas).
//
// ── MULTI-ARG: a isenção vale por ALVO, não pelo 1º ([W] 2026-09-16) ─────────
//
// A forma anterior casava o INÍCIO do statement, então o 1º argumento isentava a
// linha inteira: `rm -rf node_modules /etc` passava, e o `/etc` ia junto. Agora a
// whitelist descreve o que sempre quis descrever — o **alvo** — e TODO alvo tem
// que casar. É APERTO: nada que bloqueia hoje deixa de bloquear.
//
// MEDIDO no corpus (153.5k blocos tool_use Bash/PowerShell, 2026-09-16):
//   771 → 772 bloqueios · AFROUXOU **0** · NOVOS **1**
//   O 1 novo é PROSA de PR body (`...&& rm -rf node_modules` bloqueia) que NÃO é o`):
//   depois do `&&` sobra um statement cujos "alvos" são as palavras da frase.
//   FP conhecido e aceito — o caminho é passar a mensagem por arquivo
//   (`git commit -F` / `gh pr create --body-file`), não afrouxar o guard.
//
// ── ESCOPO rm(1): a categoria deixa de exigir FLAG ([W] 2026-09-21) ─────────
//
// Antes o detector exigia `-[rRf]+`, então `rm arquivo.txt` — sem flag — não
// era visto por ninguém. É a forma menos destrutiva (não pega diretório, falha
// em read-only), mas apaga trabalho não-commitado igual. Fechado a pedido do
// [W]: "fecha o rm sem flag também".
//
// ⚠️ Isto APERTA o guard: é o único ponto deste arquivo onde uma mudança
// ADICIONA bloqueio. O custo foi medido ANTES — 1846/1846 jsonl · 160.895
// blocos tool_use Bash/PowerShell:
//
//   APERTOU  561 distintos / 563 ocorrências   AFROUXOU 18 / 18
//
//   O que passa a bloquear, por classe:
//     B) rm(1) de verdade ......... 505 / 506  (90,5%)  ← o alvo da mudança
//     C) `rm` dentro de string .....  ~52 / ~53 ( 9,4%)  ← FP, ver abaixo
//     A) `<tool> rm` residual ......    ~4 /  ~4 ( 0,7%)  ← FP
//   O que deixa de bloquear: 32, TODOS lidos e benignos —
//     18 `git rm -r`/`-f` (FP pré-existente, ver ehToolRm: `git rm` nunca foi
//     rm(1) e é reversível) + 14 `docker exec … rm /tmp/*` (§POSIÇÃO abaixo).
//
// ⚠️ O 9,9% de FP é um PISO, não uma medida — e isso importa pra quem ler o
// número. 47% do universo apertado é comando MULTI-LINHA, onde o `rm` pode
// estar em heredoc, template literal ou string de outra linguagem; nenhuma
// heurística de aspas alcança isso. Duas medições independentes (esta sessão e
// uma sessão irmã, eixos diferentes) deram 9,4% e 12,2% pra mesma classe — a
// diferença É a fatia que cada heurística alcança. O que está medido com
// confiança é o TOTAL (≈560) e a classe B; a partição do resto é estimativa.
//
// ── POSIÇÃO: a isenção não exige mais `rm` como 1º token (2026-09-21) ───────
// `sudo rm -rf vendor` e `docker exec c rm /tmp/x` bloqueavam embora o alvo
// esteja na whitelist — só porque o extrator ancorava em `^rm`. Medidos 5 no
// corpus, todos `docker exec … rm /tmp/*.php` (sonda no CT 100). Corrigido em
// alvosRmRf; os 4 CN do §POSIÇÃO no test provam que prefixo não isenta alvo de
// fora, inclusive remoto.
// ⚠️ NÃO estendi ehToolRm pra `docker exec`/`ssh … rm`, embora sugerido: o
// alvo remoto é um filesystem REAL, e `ssh prod rm -rf /var/www` é exatamente
// o que o guard existe pra pegar. O caso legítimo (alvo reconstruível) já é
// resolvido pelo §POSIÇÃO, sem abrir a porta do alvo arbitrário.
//
// O `<tool> rm` sai pela isenção, e isso NÃO é zelo: sem ele, o mesmo corpus
// acusaria 183 `git rm` (24,6% de tudo). Medido nas duas versões.
//
// ⚠️ REGRESSÃO ASSUMIDA, declarada porque ela desfaz parte do #7586 (do mesmo
// dia): `… | grep -nE "a|rm -rf|b"` volta a bloquear, porque `|rm ` casa e o
// `|` está em `[\s;&|]`. O FP é ESTREITO e foi medido antes de aceitar — só
// pega `rm` logo após `|` ou como token solto. Seguem passando:
//     grep -n "rm " arquivo    ·  grep -rn "rm -rf" scripts/
//     echo "use rm pra limpar" ·  git log | grep "rm"
// Remédio pra quem esbarrar: padrão por arquivo/variável — o mesmo do FP de
// prosa em `git commit -F` já documentado no §MULTI-ARG.
//
// ── FLAG-SET: a isenção casa o CONJUNTO de flags ([W] 2026-09-21) ───────────
//
// HISTÓRICO, fato datado — não apagar. Até 2026-09-21 a isenção casava o
// literal `-rf`, e o efeito não correspondia a nada no SO. Enunciado exato do
// que ela aceitava: **o par ORDENADO `(r|R)(f|F)`**. Sobre o MESMO alvo isento:
//     passavam  : (sem flag) · -rf · -rF · -Rf · -RF
//     BLOQUEAVAM: -f · -r · -R · -F · -fr · -fR · -Fr · -FR
// Duas propriedades, e só a 1ª tinha justificativa registrada:
//   1. VALE de destrutividade — o mais destrutivo (`-rf`) passava, o menos
//      destrutivo (`-f`) bloqueava, e o sem-flag passava. A justificativa era
//      a contagem de 09-16 ("43 comandos afrouxados"), que media QUANTIDADE,
//      não RISCO.
//   2. ORDEM e CAIXA das flags — `rm -rf`, `rm -fr` e `rm -Rf` são o MESMO
//      comando pro SO e saíam daqui com vereditos diferentes. Artefato da
//      forma do literal (posicional + `/i`), nunca decisão de ninguém.
//
// HOJE: `-[rRf]+`, igual ao detector — a isenção vale por CONJUNTO de flags.
//
// ⚠️ Isto AFROUXA um guardrail Tier-0 e foi decisão explícita do [W]
// (2026-09-21: "alinha o regex, pode fechar o vale") — não é efeito colateral,
// exatamente como o afrouxamento de 2026-09-16 no topo deste bloco.
//
// MEDIDO ANTES de aplicar — 1846/1846 jsonl · 160.799 blocos tool_use
// Bash/PowerShell:
//   · 64 distintos / 65 ocorrências afrouxadas · APERTOU **0** (a whitelist de
//     ALVOS não mudou, então alinhar só pode SUBTRAIR bloqueio).
//   · Os 64 lidos um a um: 62 são `/tmp/*` (sonda, `.bak`, `.b64`, JUnit, lint
//     temporário) e 2 removem o SYMLINK `vendor` antes de recriá-lo — que é o
//     uso em que a forma não-recursiva é a correta. Zero alvo perigoso.
//   · Em alvo NÃO isento (`/etc/passwd`): **zero** dos 13 pontos de flag mudou
//     — todos seguem bloqueando. Só a ISENÇÃO afrouxou, e só onde a whitelist
//     já autorizava.
//   · Bordas preservadas: vacuidade (`rm -rf` sem alvo), `xargs … rm`,
//     multi-arg com 1 alvo fora, travessia `..`, `$var` FORA de prefixo isento.
//   · Bordas que mudam, e são coerentes: `rm -f -r /tmp/x` (flags separadas)
//     passa — é o mesmo comando que `rm -rf /tmp/x`, que já passava; e
//     `rm -f /tmp/$X` passa, que é a 2ª decisão abaixo aplicada também ao `-f`.
//
// Reproduz: node -e "import('./.claude/hooks/block-destructive.mjs').then(
//   m=>['','-f ','-r ','-R ','-F ','-rf ','-rF ','-Rf ','-RF ','-fr ','-fR ',
//   '-Fr ','-FR '].forEach(f=>console.log((f||'(sem flag)').padEnd(11),
//   m.matchDestructive('r'+'m '+f+'/tmp/x')?'BLOQ':'passa')))"
//   (troque /tmp/x por /etc/passwd: TODOS com flag bloqueiam, antes e depois.)
//
// Os 13 pontos e as bordas estão nos asserts §FLAG-SET de
// `block-destructive.test.mjs`. ⚠️ E não suponha que `-[rf]+` seria uma
// variante "sem o R": MEDIDO — com o `/i` presente, `-[rf]+` e `-[rRf]+` são
// equivalentes aqui. O `R` vem do flag, não da classe.
//
// ⚠️ Decisão que a medição sustenta, e que NÃO se refaz sem re-medir:
//   · alvo NÃO-VERIFICÁVEL (`$var`, glob) dentro de prefixo whitelisted
//     (`rm -rf /tmp/$X`) segue ISENTO: bloqueá-lo mede **0** no corpus e criaria
//     FP em temp-dir dinâmico. Fora de prefixo whitelisted, `$X` já bloqueia
//     por não casar alvo nenhum — a proteção vem de graça.
//
// ── TRAVESSIA: o alvo tem que casar ANTES **E DEPOIS** de normalizar ─────────
//
// `rm -rf node_modules/../../etc` casava `^node_modules\b` e escapava do alvo
// isento — o prefixo dizia "reconstruível" e o `..` levava pra fora. Fechado
// exigindo que o alvo case a whitelist nas DUAS formas.
//
// Por que "antes E depois", e não só "depois": normalizar e casar sozinho
// AFROUXARIA (`./node_modules` → `node_modules` viraria isento, e hoje bloqueia).
// Com a conjunção, o fix só pode SUBTRAIR isenção — é aperto por construção, e a
// medição confirma (AFROUXOU=0, que aqui é teorema, não sorte).
//
// MEDIDO no corpus (153.7k blocos tool_use Bash/PowerShell, 2026-09-16):
//   774 → 774 bloqueios · AFROUXOU 0 · NOVOS 0 (zero alvos com `..` hoje)
//   equivalência: 217 alvos distintos, 217 sem `..`, **0** mudam de veredito —
//   é o que prova que a barra final sobrevive (`storage/framework/views/`
//   normalizado SEM a barra não casaria a entrada dela).
//
// `path.posix.normalize` é a stdlib: não se escreve normalizador de path à mão
// (conferido caso a caso contra uma versão própria — 12/12 idênticos).
// POSIX de propósito: o hook governa Bash, onde `\` é escape, não separador.
// Alvo com `\`: 0 no corpus. Vetor declarado, não coberto.

/** whitelist rm -rf: ALVOS reconstruíveis por build (âncora: comentário US-COPI-085). */
const RM_WHITELIST_ALVOS = [
  /^\/tmp\//i,
  /^~\/\.cache\//i,
  /^node_modules\b/i,
  /^vendor\b/i,
  /^storage\/framework\/(views|cache|sessions)\//i,
  /^bootstrap\/cache\//i,
  /^public\/build/i,
  /^\.next\//i,
  /^dist\//i,
  /^coverage\//i,
];

/**
 * Alvos de um statement `rm -rf ...` — PARA no 1º operador de shell, senão
 * `rm -rf /tmp/x 2>&1 | tail -10` contaria `|` e `tail` como alvos (medido).
 * @returns {string[]|null} null = o statement não é um `rm -rf`
 */
export function alvosRmRf(stmt) {
  // O `rm` pode NAO ser o 1o token do statement: `docker exec c rm /tmp/x`,
  // `timeout 200 rm /tmp/x`, `sudo rm /tmp/x`. Ancorar em `^rm` deixava esses
  // FORA da isencao mesmo com alvo whitelisted — medido: 5 casos no corpus,
  // todos `docker exec … rm /tmp/*.php`. Achar em qualquer posicao NAO
  // afrouxa o que importa: o veredito segue vindo de alvoIsento() sobre CADA
  // alvo, e `xargs … rm -rf` continua com alvos=[] (vacuidade nao isenta).
  const m = /(^|[\s;&|])rm(\s+|$)/i.exec(String(stmt || ''));
  if (!m) return null;
  const toks = String(stmt).slice(m.index + m[0].length).match(/"[^"]*"|'[^']*'|\S+/g) || [];
  const alvos = [];
  for (let t of toks) {
    if (/^(\||&|;|\d*>|<|>>)/.test(t)) break;   // operador de shell → acabou o rm
    if (/^-/.test(t)) continue;                  // outra flag
    t = t.replace(/^["']|["']$/g, '');           // aspas envolventes
    if (t) alvos.push(t);
  }
  return alvos;
}

/** o alvo é reconstruível — e continua sendo depois de resolver `..`? (ver §TRAVESSIA) */
const alvoIsento = (a) => RM_WHITELIST_ALVOS.some((w) => w.test(a))
  && RM_WHITELIST_ALVOS.some((w) => w.test(caminhoPosix.normalize(a)));

/** o statement é um `rm -rf` cujos alvos são TODOS reconstruíveis? */
function rmIsento(stmt) {
  const alvos = alvosRmRf(stmt);
  if (!alvos || alvos.length === 0) return false;  // sem alvo ≠ isento (`xargs … rm -rf`)
  return alvos.every(alvoIsento);
}

/**
 * `git rm` / `docker rm` / `svn rm` NAO sao o rm(1) do shell: o subcomando
 * pertence a outra ferramenta, tem semantica propria e — no caso do git —
 * e reversivel (o arquivo fica staged; `git checkout` restaura).
 *
 * MEDIDO: sem esta exclusao, abrir o detector pro rm sem flag acusaria 183
 * ocorrencias de `git rm` no corpus (24,6% de tudo que passaria a bloquear).
 */
export const ehToolRm = (stmt) =>
  /(^|[\s;&|])(git|docker|docker-compose|podman|kubectl|svn|hg|cargo|helm)\s+rm(\s|$)/i
    .test(String(stmt || ''));

/** categorias proibidas — ordem determinística (primeiro match dá a mensagem). */
const PADROES = [
  {
    // A key continua `rm-rf-perigoso` de propósito: ela é citada na mensagem de
    // bloqueio e em docs/handoffs, e renomeá-la quebraria essas referências sem
    // ganho. O ESCOPO é que mudou — hoje a categoria é "qualquer rm(1)".
    key: 'rm-rf-perigoso',
    // `(\s|$)` em vez de `\s+`: isolado, um statement pode TERMINAR nas flags —
    // `xargs -a lista.txt rm -rf` é rm recursivo com os alvos vindos do arquivo.
    // No blob isso casava por acidente (o espaço vinha do comando SEGUINTE); sem
    // o `$`, fatiar trocaria um falso-positivo por um falso-NEGATIVO. Delta da
    // mudança de regex sozinha, medido no blob: 0.
    // FLAG deixou de ser exigida em 2026-09-21 ([W]) — ver §ESCOPO rm(1).
    //
    // ⚠️ O `/i` FICA, e não é herança decorativa do porte .ps1. Foi proposto
    // tirá-lo com o argumento "shell POSIX é case-sensitive, `RM` daria
    // command not found, logo `/i` só gera FP". A premissa é verdadeira em
    // Linux/macOS e FALSA na plataforma onde este hook roda. MEDIDO no Git
    // Bash/Windows (NTFS case-insensitive), 2026-09-21:
    //     command -v RM   →  /usr/bin/RM
    //     RM --version    →  rm (GNU coreutils) 8.32     (executa!)
    // Ou seja, `RM -rf src/` apaga de verdade aqui. O assert 'RM -RF
    // maiúsculo' no test protege caso real, não fantasma.
    // CUSTO ACEITO do `/i`: o idioma `const RM = 'r' + 'm'` — que as sessões
    // usam pra escrever SOBRE o hook sem disparar o hook — passa a ser
    // acusado. Medido: 4 de 559 (0,7%), concentrado em quem mexe neste
    // arquivo. Há assert fixando esse FP, pra ele não virar surpresa.
    regex: /(^|[\s;&|])rm(\s|$)/i,
    // Isenção por STATEMENT e por ALVO — ver §MULTI-ARG / RM_WHITELIST_ALVOS.
    // `<tool> rm` (git/docker/…) não é rm(1) e sai pela isenção — ver ehToolRm.
    porStatement: true,
    razao: 'rm pode apagar trabalho não commitado / config / dados de prod — sem -r não pega diretório, mas pega o arquivo',
    sugestao: 'alvo reconstruível (whitelist: /tmp/, node_modules, vendor, storage/framework/{views,cache}, public/build*); OU `git rm` se o arquivo é versionado (fica staged, reversível); OU peça ao Wagner',
  },
  {
    key: 'git-force-push',
    regex: /git\s+push\s+(--force\b|-f\b|.*\s--force(-with-lease)?\b)/i,
    // `.*` ganancioso: SÓ avaliado dentro de um statement (ver §FATIAMENTO).
    // A categoria NÃO afrouxa: `git push origin main --force-with-lease` (flag
    // depois do remote, MESMO statement) segue bloqueado — o selftest asserta.
    porStatement: true,
    razao: 'force push sobrescreve histórico remoto — risco de perder commits do time',
    sugestao: 'rebase local + push normal, OU usar --force-with-lease com confirmação explícita do Wagner',
  },
  {
    key: 'git-reset-hard-origin',
    regex: /git\s+reset\s+--hard\s+(origin|upstream)\//i,
    razao: 'reset --hard contra remote descarta TODO trabalho local não-pushed',
    sugestao: 'git stash primeiro, depois reset; OU criar branch backup antes',
  },
  {
    key: 'sql-drop-table',
    regex: /\bDROP\s+(TABLE|DATABASE|SCHEMA)\b/i,
    razao: 'DROP TABLE/DATABASE é irreversível — perde dados de produção',
    sugestao: 'rodar em staging primeiro, OU criar migration drop_*_table com plan mode + revisão Wagner',
  },
  {
    key: 'sql-delete-no-where',
    // \b após \w+ impede o backtracking que anulava o lookahead no .ps1 (ver header)
    regex: /\bDELETE\s+FROM\s+\w+\b(?!\s+WHERE\b)/i,
    razao: 'DELETE sem WHERE apaga TODA a tabela',
    sugestao: 'sempre adicionar WHERE explícito, mesmo que seja WHERE id IN (...)',
  },
  {
    key: 'sql-delete-where-1',
    regex: /\bDELETE\s+FROM\s+\w+\s+WHERE\s+1(\s*=\s*1)?\b/i,
    razao: 'DELETE WHERE 1=1 = wipe da tabela inteira',
    sugestao: 'usar filtro real (WHERE id < N OR created_at < ...)',
  },
  {
    key: 'sql-truncate',
    regex: /\bTRUNCATE\s+(TABLE\s+)?\w+/i,
    razao: 'TRUNCATE wipa a tabela inteira (mais rápido que DELETE, mesmo efeito)',
    sugestao: 'só em fixtures/seed locais; em prod usar migration formal',
  },
  {
    key: 'composer-update-sem-lock',
    // `(\.phar)?` — GAP FECHADO JUNTO com o §STATEMENT INERTE (2026-09-21), e a
    // medição é o motivo: os 2 únicos `php composer.phar update X --no-install`
    // do corpus (159.8k blocos) bloqueavam **por acidente**, via um `echo "==
    // composer update ... =="` ao lado. O regex exigia `composer` + ESPAÇO, então
    // `composer.phar update` nunca casou por mérito próprio. Tirar o acidente sem
    // fechar o gap deixaria a categoria com saldo NEGATIVO — por isso as duas
    // coisas andam juntas. Forma-imagem (`docker run composer:2 update`) segue
    // NÃO coberta: 0 ocorrências no corpus, residual declarado e não estimado.
    regex: /(?<!#\s)composer(\.phar)?\s+update(?!\s+--lock\b)(?!.*\s--lock\b)/i,
    // Aqui o `.*` mora num lookahead NEGATIVO, então atravessar statement
    // afrouxava: um `--lock` de qualquer comando posterior SUPRIMIA o bloqueio
    // (medido — `composer update && echo "use --lock"` saía rc=0). Per-statement
    // FECHA esse buraco: é aperto, não folga. Ocorrências no corpus: 0 (latente).
    porStatement: true,
    razao: 'composer update sem --lock causa drift do composer.lock (ADR 0063)',
    sugestao: 'composer update --lock (atualiza só o lock sem instalar) OU composer require pacote:versao',
  },
  {
    key: 'artisan-migrate-fresh-prod',
    regex: /php\s+artisan\s+migrate:(fresh|reset|wipe|rollback\s+--step=\d{2,})/i,
    razao: 'migrate:fresh/reset/wipe DROPA todas as tabelas — apaga produção',
    sugestao: 'usar migrate:rollback --step=1 com revisão; OU em prod, criar migration formal com down() controlado',
  },
];

// ── STATEMENT INERTE: `echo`/`printf` EMITE texto — não executa ──────────────
//
// O DEFEITO (medido 2026-09-21): uma consulta READ-ONLY foi bloqueada porque a
// string `rm -f` aparecia dentro de um `echo "=== nota sobre rm -f ==="`. O
// regex da categoria casa em QUALQUER posição do statement, e a isenção
// (`alvosRmRf`) exige `rm` no INÍCIO — então prosa dentro de `echo` casa o
// bloqueio e não alcança isenção nenhuma. O hook chegou a impedir que se LESSE
// o próprio hook.
//
// ⚠️ ESCOPO — isto NÃO reabre a decisão do [W] de 2026-09-16 (§MULTI-ARG, "FP
// conhecido e aceito"). Aquela é sobre PROSA DE HEREDOC: linha solta de PR body
// que, depois do `&&`, vira um statement cujos "alvos" são as palavras da frase
// — lá não há comando, e o remédio declarado segue sendo passar a mensagem por
// arquivo (`git commit -F` / `gh pr create --body-file`). Aqui o statement TEM
// comando, e o comando é um emissor de texto: o argumento é dado por
// CONSTRUÇÃO, não por convenção. São vetores diferentes.
//
// POR QUE É SEGURO — as 3 portas que ficam FECHADAS de propósito:
//   · `$(...)` / backtick → EXECUTA. `echo "$(rm -rf /)"` NÃO é inerte.
//   · `<` `>` `>>`        → ESCREVE arquivo. `echo "rm -rf /" > s.sh` segue
//                           bloqueado — veredito idêntico ao de hoje.
//   · statement IRMÃO     → o fatiamento é ANTERIOR a isto: `echo ok && rm -rf
//                           /etc` são DOIS statements, e o 2º bloqueia igual.
//
// A escala respeita a assimetria que já existe (ver §matchDestructive): no blob
// (as 7 categorias legadas) a isenção só vale quando TODOS os statements são
// inertes. Filtrar o blob pelo 1º token seria falso-NEGATIVO —
// `echo hi && mysql -e "DROP TABLE x"` começa com `echo` e perderia o bloqueio.

/** o statement só EMITE texto? (argumento é dado, não comando) */
export function ehStatementInerte(stmt) {
  const s = normalizeCmd(stmt);
  if (!/^(echo|printf)(\s|$)/i.test(s)) return false;
  if (/\$\(|`/.test(s)) return false;   // substituição EXECUTA
  if (/[<>]/.test(s)) return false;     // redireciona: escreve arquivo
  return true;
}

/**
 * Veredito único: {key, razao, sugestao} da primeira categoria que casar, ou null.
 *
 * Duas escalas de avaliação, e a assimetria é DELIBERADA:
 *  · `porStatement: true` → testa cada statement isolado (só as 2 regex com `.*`).
 *  · default              → testa o comando inteiro normalizado, BYTE A BYTE como antes.
 *
 * As outras 7 categorias ficam intactas porque foram MEDIDAS e não sofrem do
 * atravessamento (delta 0 no corpus: reset-hard, DROP, DELETE s/ WHERE, DELETE
 * WHERE 1, TRUNCATE, migrate:fresh — nenhuma tem `.*`).
 *
 * A rm-rf entrou no per-statement por decisão do [W] (2026-09-16) — ver o bloco
 * §WHITELIST rm. Ali a escala importa DUAS vezes: o padrão E a whitelist são
 * julgados no MESMO statement, senão a isenção de um `rm` cobriria outro.
 */
export function matchDestructive(cmd) {
  const cmdNorm = normalizeCmd(cmd);
  if (!cmdNorm) return null;
  const stmts = statements(cmd);
  // §STATEMENT INERTE: tira de consideração o que só emite texto. Só SUBTRAI
  // superfície de avaliação — nada que hoje passa pode passar a bloquear.
  const executaveis = stmts.filter((s) => !ehStatementInerte(s));
  const todosInertes = stmts.length > 0 && executaveis.length === 0;
  const isento = (p, alvo) => p.key === 'rm-rf-perigoso' && (rmIsento(alvo) || ehToolRm(alvo));
  for (const p of PADROES) {
    // a isenção acompanha a escala: por statement ela vale só pro statement que
    // a ganhou; no blob (comportamento legado das demais) vale pro comando todo.
    const alvos = p.porStatement ? executaveis : (todosInertes ? [] : [cmdNorm]);
    if (!alvos.some((a) => p.regex.test(a) && !isento(p, a))) continue;
    return p;
  }
  return null;
}

export function blockMessage(p) {
  return `[block-destructive] Bash BLOQUEADO (${p.key}). Motivo: ${p.razao}. Sugestão: ${p.sugestao}. Se for intencional e Wagner autorizou explicitamente, use abordagem alternativa OU peça Wagner pra rodar manualmente. NUNCA forçar bypass deste hook sem ADR justificando.`;
}

// ── AVISO (advisory, NUNCA bloqueia): `git stash pop` consumindo entry alheia ──
//
// CLASSE: §5 2026-07-27 ("consumir estado GLOBAL do repo por posição"). 2ª
// ocorrência em 2026-08-11 — pela ADR 0344 two-strikes, vira defesa. O par
// candidato já vinha MEDIDO na própria lápide; isto o arma.
//
// O VETOR, que é contraintuitivo: `git stash -u` numa árvore LIMPA **não cria
// entry**. Então o `pop` seguinte consome `stash@{0}`, que é de quem empilhou por
// último — em repo com worktrees paralelos, quase sempre outra sessão. Nas duas
// ocorrências o que salvou foi o CONFLITO (o git preserva a entry); aplicando
// limpo, trabalho alheio entra na árvore em silêncio.
//
// POR QUE ADVISORY E NÃO BLOQUEIO (a lápide mediu e recusou a forma dura):
// `stash push` na branch A → `checkout` B → `pop` é fluxo LEGÍTIMO e comum, e
// nele o topo é sempre de outra branch. Bloquear puniria o uso correto — a
// doença dos guards sintáticos que o §5 já matou 5×. Então: informa de quem é o
// topo e deixa o humano decidir.
//
// POPULAÇÃO MEDIDA (653 transcripts, só `tool_use` de Bash/PowerShell — nunca
// prosa): 84 `pop|apply` executados, 79 (94%) sem entry explícita. O filtro
// "topo de outra branch" estreita isso, mas NÃO é medível retroativamente:
// depende do estado da pilha no instante do comando. Declarado, não estimado.

/** o comando consome o topo por POSIÇÃO (sem `stash@{N}` explícito)? */
export function consomeTopoPorPosicao(cmd) {
  const c = normalizeCmd(cmd);
  if (!/git\s+stash\s+(pop|apply)\b/i.test(c)) return false;
  return !/stash@\{\d+\}/.test(c); // com entry explícita, o autor sabe o que pega
}

/**
 * Função PURA (o estado do git entra por parâmetro, pra ser testável sem repo).
 * @param {string} cmd
 * @param {{branchAtual: string, topo: string|null}} ctx  topo = 1ª linha de `git stash list`
 * @returns {string|null} aviso, ou null quando não há o que avisar
 */
export function avisoStashPop(cmd, ctx) {
  if (!consomeTopoPorPosicao(cmd)) return null;
  const { branchAtual, topo } = ctx || {};
  if (!topo) {
    // pilha vazia: o pop vai falhar sozinho — nada a avisar (e nada a perder)
    return null;
  }
  // `git stash list` → "stash@{0}: On <branch>: msg" ou "... WIP on <branch>: sha msg"
  const m = /^stash@\{\d+\}:\s+(?:WIP on|On)\s+([^:]+):/i.exec(topo);
  const dono = m ? m[1].trim() : null;
  if (!dono || !branchAtual) return null;      // não sei dizer de quem é → calo
  if (dono === branchAtual) return null;       // topo é seu → silêncio (o caso comum e correto)
  return `[block-destructive] AVISO (nao bloqueia): "${normalizeCmd(cmd)}" consome o TOPO por posicao, e o topo NAO e desta branch.
  topo da pilha : ${topo.trim()}
  sua branch    : ${branchAtual}
Se a arvore estava LIMPA, o seu "git stash" nao criou entry — entao este pop pega trabalho de OUTRA sessao (§5 2026-07-27, 2 ocorrencias).
Confira com "git stash list" antes. Para consumir o SEU, empilhe com nome ("git stash push -m <marcador>") e passe a entry explicita.`;
}

// ── AVISO (advisory, NUNCA bloqueia): `git push --delete` com nome NÃO-LITERAL ──
// 3ª ocorrência da classe LC-12 (2026-09-06): um `for b in $(git ls-remote --heads origin
// 'claude/q*')` seguido de `git push origin --delete "$b"` apagou 4 branches de OUTRAS
// sessões (quick-sync-lock-cleanup, quizzical-*) — o glob presumia "são meus". O nome
// vinha de variável, então nem o autor leu quais eram. Mesma raiz do stash-pop: destruir
// estado GLOBAL do repo por presunção de posse. Restaurados pelo head dos PRs.
//
// Predicado PURO e estreito: o comando apaga branch remoto E o nome NÃO é literal
// (variável, glob, subshell, backtick). Nome literal fica em silêncio — apagar a própria
// branch depois do merge é o fluxo comum e correto, e avisar ali seria ruído (família dos
// guards sintáticos que o §5 mede e reprova). FP do predicado estreito ≈ 0: loop/variável
// sobre `--delete` é exatamente o vetor, e o aviso só imprime o que conferir.
const APAGA_BRANCH_REMOTO = /\bgit\s+push\b(?=.*\s(?:--delete|-d)\s)|\bgit\s+push\b[^|;&]*\s:refs\/heads\//i;
const NOME_NAO_LITERAL = /\$\{?[A-Za-z_]|\$\(|`|\*|\?/;

/**
 * @param {string} cmd
 * @returns {string|null}
 */
export function avisoPushDelete(cmd) {
  const c = normalizeCmd(cmd);
  if (!APAGA_BRANCH_REMOTO.test(c)) return null;
  // o trecho depois de --delete/-d (ou o comando inteiro no formato :refs/heads/)
  const alvo = (/(?:--delete|-d)\s+(.+)$/i.exec(c) || [, c])[1];
  if (!NOME_NAO_LITERAL.test(alvo)) return null;   // nome literal → silêncio
  return `[block-destructive] AVISO (nao bloqueia): "${c}" apaga branch REMOTO com nome NAO-LITERAL (variavel/glob/subshell).
Branch remota e estado GLOBAL do repositorio, nao do seu worktree: um glob como 'claude/q*' casa branches de OUTRAS sessoes
(3a ocorrencia LC-12, 2026-09-06: 4 branches alheias apagadas por loop sobre ls-remote). Antes de apagar:
  1. liste os nomes RESOLVIDOS e leia um a um;
  2. confira a posse de cada um: gh pr list --state all --head <branch> --author @me
  3. apague por nome LITERAL, nunca por padrao.`;
}

// ── stdin wrapper (fail-open em TUDO) ────────────────────────────────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  let raw;
  try { raw = await readStdin(); } catch { process.exit(0); }
  if (!raw) process.exit(0);
  let cmd = '';
  try {
    const payload = JSON.parse(raw);
    if (String((payload && payload.tool_name) || '') !== 'Bash') process.exit(0);
    cmd = String((payload && payload.tool_input && payload.tool_input.command) || '');
  } catch { process.exit(0); }        // parse-fail → fail-open
  if (!cmd) process.exit(0);
  const p = matchDestructive(cmd);
  if (p) { process.stderr.write(blockMessage(p) + '\n'); process.exit(2); }

  // advisory do stash — depois do bloqueio, e SEMPRE exit 0. O estado do git é
  // lido aqui (impuro) e passado pra função pura, que é quem o selftest exercita.
  if (consomeTopoPorPosicao(cmd)) {
    try {
      const git = (args) => spawnSync('git', args, { encoding: 'utf8', timeout: 4000 });
      const b = git(['branch', '--show-current']);
      const s = git(['stash', 'list']);
      // rc != 0 (fora de repo, git ausente) → não invento estado, apenas calo
      if (b.status === 0 && s.status === 0) {
        const aviso = avisoStashPop(cmd, {
          branchAtual: String(b.stdout || '').trim(),
          topo: String(s.stdout || '').split('\n')[0] || null,
        });
        if (aviso) process.stderr.write(aviso + '\n');
      }
    } catch { /* fail-open: aviso nunca trava sessão */ }
  }
  // advisory do push --delete não-literal — puro, sem ler estado; SEMPRE exit 0.
  try {
    const avisoDel = avisoPushDelete(cmd);
    if (avisoDel) process.stderr.write(avisoDel + '\n');
  } catch { /* fail-open */ }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-destructive.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [fileURLToPath(test)], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
