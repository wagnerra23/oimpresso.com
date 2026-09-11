#!/usr/bin/env node
// @ts-check
/**
 * block-sonda-que-mente.mjs — PreToolUse (Bash|PowerShell), BLOQUEIA (exit 2).
 *
 * POR QUE EXISTE ([W] 2026-08-21, textual): "esses erros de protocolo escolhas
 * maquinas erros repetidos, arrume e bloqueie. para não errar novamente não
 * adianta colocar em texto. isso não funciona. faça travas que funcionem".
 *
 * A CLASSE, e ela é uma só: comando que RODA, devolve saída PLAUSÍVEL, e a saída
 * está errada — sem erro, sem aviso, sem nada que denuncie. É o pior tipo de
 * defeito de medição, porque o relatório fica igual ao de quem mediu certo. O §5
 * tem lápide para cada instância (2026-07-31 `git grep -F` com \E devolvendo
 * vazio · 2026-08-01 saída plausível de comando que não rodou · 2026-08-02
 * reescrita textual que come o vizinho) e as lápides NÃO impediram a
 * reincidência: medido no corpus abaixo, os três padrões seguem acontecendo.
 *
 * ── FP MEDIDO ANTES DE INSTALAR (regra do CLAUDE.md "Ligue a máquina", passo 4)
 * Corpus: 876 transcripts, 65.103 comandos Bash/PowerShell reais.
 *   P2  .replace com `$` especial ....  4 brutos ->  3 apos o filtro (0,005%)
 *   P3  git grep [flag do rg] ........ 47 brutos -> 10 apos o filtro (0,015%)
 *   P4  grep -E com barra-pipe ....... 193 brutos -> 52 apos o filtro (0,080%)
 * TOTAL apos filtro: 65 de 65.133 = 0,100% — um bloqueio a cada mil comandos.
 * (as linhas P3/P4 antigas abaixo ficam como o BRUTO, antes do filtro de mencao)
 *   P3  git grep --hidden ............ 47 disparos (0,072%)
 *   P4  grep -E com \| ............... 193 disparos (0,296%)
 * Um quarto candidato (`/tmp` em runtime nativo do Windows) foi MEDIDO e
 * DESCARTADO: o regex não separa path-de-código de redirect-de-shell, e
 * reprovava `node -e '…' > /tmp/out.txt`, que é legítimo. Sem FP baixo, não
 * entra — a lápide do guard sintático já matou 4 dessa família.
 *
 * P5 (`jq` local) medido em 2026-09-05: 1.379 transcripts, 116.254 comandos.
 * Medido com o PREDICADO REAL (`achaPadrao`), não com um regex parecido — a 1ª
 * medição usou um regex aproximado e deu 36; o hook de verdade dá 19, porque os
 * filtros (`ehMencao`, `exceto`) descontam o resto. Regex de medição ≠ gate.
 *   19 disparos = 0,016% — ABAIXO do P4 já aceito (0,080%)
 *   dos 19:  17 execução real de `jq`  ·  2 falso-positivo  (FP 10,5% dos
 *   disparos — o MENOR do conjunto: P3 tinha 79,6% e P4 73,1% antes dos filtros)
 * Os 17 verdadeiros são quase todos `gh pr checks … 2>/dev/null | jq -r …`, que
 * é exatamente a forma que transforma o erro em silêncio.
 * ⚠️ Os 2 FP residuais ficam DECLARADOS, não escondidos: (a) `git grep "…| *jq "`
 * procurando pelo próprio padrão, (b) menção em código com escape aninhado. Custo
 * de cada um: reescrever o comando ou usar o escape. 1 a cada ~58 mil comandos.
 *
 * ── POR QUE O P5 NÃO É O GUARD SINTÁTICO QUE A LÁPIDE PROIBIU ───────────────
 * A lápide §5 2026-08-11 diz "NÃO virar gate", e o motivo dela é textual: os 9
 * workflows que usam `jq` rodam em `ubuntu-latest`, onde ele EXISTE, e acusá-los
 * seria falso-positivo. Isso continua verdade — e não alcança este hook: ele é
 * PreToolUse, vê SÓ comando que o agente executa na máquina local; workflow
 * nenhum passa por aqui. Medido: 9 de 9 rodam ubuntu (`runs-on`), 0 localmente.
 * A premissa "não dá pra saber o ambiente-alvo pelo texto" também não vale no
 * subconjunto: quando o comando carrega `ssh`/`tailscale`/`docker exec`, o alvo
 * é outra máquina e o padrão se abstém (campo `exceto`).
 * Não assume nada: `jqExisteLocalmente()` procura o binário no PATH e o P5 se
 * desliga sozinho se alguém instalar `jq` — a trava morre com o problema.
 *
 * ── POR QUE BLOQUEIA, E NÃO AVISA ───────────────────────────────────────────
 * Advisory serve pra achado que o humano pondera. Aqui não há o que ponderar: os
 * três produzem NÚMERO ERRADO com cara de certo, e quem lê a saída não tem como
 * desconfiar. O custo do falso-positivo é reescrever um comando; o custo do
 * falso-negativo é uma conclusão publicada.
 *
 * Escape (emergência, consciente): OIMPRESSO_SONDA_OVERRIDE=1
 * Fail-open em qualquer erro de parse — hook nunca trava a sessão.
 */

import { spawnSync } from 'node:child_process';

/**
 * O binário `jq` roda nesta máquina?
 *
 * O P5 existe porque na máquina do [W] (Windows) ele NÃO existe — medido
 * 2026-09-05: `command -v jq` → rc=1. Em vez de gravar isso como premissa (que
 * apodrece calada no dia em que alguém instalar), o hook PERGUNTA.
 *
 * ⚠️ A 1ª versão disto varria o `PATH` com `existsSync` e ERRAVA — e o erro é a
 * lápide §5 2026-08-21 na veia: `path.delimiter` é `;` no Windows, mas o Git Bash
 * entrega o PATH com `:` e caminhos POSIX (`/c/...`), então o split devolvia UM
 * item que não resolvia e a sonda respondia "não existe" SEMPRE. Como aqui o jq
 * de fato não existe, o veredito saía certo pelo motivo errado — e o
 * auto-desligamento nunca dispararia. Pego pelo controle positivo (plantar um jq
 * no PATH e exigir que a sonda o veja); sem esse controle, passava.
 *
 * Agora mede a CONSEQUÊNCIA (o binário executa?), não a declaração do ambiente.
 * ~5-15ms, uma vez por invocação do hook, e só quando o comando casa o regex.
 */
let _jqCache = null;
export function jqExisteLocalmente() {
  if (_jqCache !== null) return _jqCache;
  try {
    // `shell: true` é necessário no Windows: sem ele o Node só resolve `.exe` e
    // ignora os `.cmd`/`.bat` (que é como scoop/npm-shim instalam). Medido — com
    // `shell: false` um `jq.bat` plantado no PATH ficava invisível. O comando é
    // literal, sem interpolação de input, então o shell aqui não abre superfície.
    // Comando como STRING única (não `cmd, args[]`): com `shell: true` o Node
    // concatena os args sem escapar e emite DEP0190. Aqui não há input externo.
    const r = spawnSync('jq --version', { stdio: 'ignore', timeout: 3000, shell: true });
    _jqCache = r.status === 0;
  } catch {
    _jqCache = false;                        // spawn falhou → tratar como ausente
  }
  return _jqCache;
}

/** @type {{id:string, nome:string, re:RegExp, porque:string, saida:string, exceto?:(cmd:string)=>boolean}[]} */
export const PADROES = [
  {
    id: 'P3',
    nome: 'git grep --hidden',
    // `--hidden` é flag do RIPGREP. O git grep sai rc=129 e NÃO lista nada.
    re: /git\s+grep\b[^\n]*--hidden\b/,
    porque:
      '`--hidden` não existe no `git grep` (é do ripgrep). O comando sai com erro e lista ZERO — ' +
      'e um `wc -l` depois conta a mensagem de erro como "0 ocorrências".',
    saida:
      '`git grep` já varre dotfile versionado — a flag é desnecessária. Se quer mesmo o ripgrep: `rg --hidden`.',
  },
  {
    id: 'P4',
    nome: 'grep -E com \\| (alternância escapada)',
    // Em ERE a alternância é `|` PURO. `\|` casa um pipe LITERAL — e o padrão
    // inteiro deixa de casar, devolvendo 0 sem erro nenhum.
    re: /\bgrep\s+-[a-zA-Z]*E[a-zA-Z]*\s+(['"])(?:(?!\1)[^\n])*\\\|/,
    porque:
      'Em `grep -E` (ERE) a alternância é `|` PURO. O `\\|` casa um pipe LITERAL, o padrão para de ' +
      'casar e o comando devolve 0 linhas — sem erro, sem aviso.',
    saida: 'Use `|` sem barra: `grep -E "a|b"`. Para pipe literal de verdade: `[|]` ou `grep -F`.',
  },
  {
    id: 'P2',
    nome: 'String.replace com $ especial na substituição',
    // `$&` `$\`` `$'` são metacaracteres da string de SUBSTITUIÇÃO. O `$\``
    // injeta TODO o texto antes do match — 139 linhas, num caso real de 2026-08-21.
    re: /\.replace\s*\([^)]*?(['"`])[^'"`\n]*\$[`&'][^'"`\n]*\1/,
    porque:
      'Na string de substituição do `String.replace`, `$&`, `$` + crase e `$` + apóstrofo são ' +
      'METACARACTERES. O `$` + crase injeta TODO o texto ANTES do match — em 2026-08-21 isso ' +
      'inseriu 139 linhas do próprio arquivo num ledger.',
    saida:
      'Passe uma FUNÇÃO: `.replace(alvo, () => novo)` — o retorno de função não é interpretado.',
  },
  {
    id: 'P5',
    nome: 'jq no shell local (binário ausente nesta máquina)',
    // `jq` em POSIÇÃO DE COMANDO: início, ou depois de | ; & $( ` — nunca `--jq`,
    // que é FLAG do gh (9.611 dos 9.717 usos do corpus) e não invoca binário nenhum.
    re: /(^|[|;&]|\$\(|`)\s*jq\s/,
    // Duas abstenções, ambas medidas no corpus:
    //  (a) o comando executa em OUTRA máquina (CT 100 / Hostinger / container) —
    //      lá o jq pode existir, e este hook só sabe do ambiente local;
    //  (b) o "jq" está DENTRO do código passado a `node -e` / `python -c`, onde é
    //      texto de string, não comando de shell. Isto NÃO pode virar regra geral
    //      do `ehMencao`: pro P2 o código dentro de `node -e` É executado e tem de
    //      morder. Achado do jeito mais direto possível — o hook bloqueou o meu
    //      próprio comando de medição, que citava `'cat x.json | jq .'` numa string.
    exceto: (cmd) => /\bssh\b|tailscale|docker\s+exec/.test(cmd)
      || dentroDeCodigoInline(cmd, /(^|[|;&]|\$\(|`)\s*jq\s/),
    porque:
      '`jq` NÃO existe nesta máquina. O erro `command not found` costuma ir pro stderr — e com ' +
      '`2>/dev/null`, ou dentro de um monitor que só lê stdout, some: a saída fica VAZIA e vazio ' +
      'lê como "nada a reportar". Pior no lugar onde mais aconteceu (vigia de CI): silêncio de ' +
      'vigia é indistinguível de "não há vermelhos". Fallback da própria ferramenta (`// "x"`) ' +
      'não salva — ele nunca roda, porque o jq não chega a iniciar.',
    saida:
      'Use `node -e` (o repo garante node) — ex.: `gh pr checks N --json name,bucket | node -e "…"`. ' +
      'Se o que você quer é filtrar saída do `gh`, a flag `--jq` dele é built-in e NÃO usa o binário.',
  },
];

/**
 * MENÇÃO x EXECUÇÃO — a diferença que decide se este hook presta.
 *
 * MEDIDO no corpus (876 transcripts / 65.103 comandos) DEPOIS do primeiro uso
 * real, que mordeu o próprio commit que documentava o padrão:
 *   P3  49 disparos = 10 execução + 39 menção  -> FP 79,6%
 *   P4  193 disparos = 52 execução + 141 menção -> FP 73,1%
 * Três em cada quatro disparos eram o padrão DENTRO de uma string — alguém
 * escrevendo sobre o defeito, não cometendo o defeito. Sem este filtro o hook
 * seria ruído, e ruído desliga trava (a lápide do guard sintático já matou 4).
 *
 * O sinal é determinístico: o trecho casado vem depois de `echo`/`printf`,
 * está dentro de heredoc, ou é argumento de `-m`/`--body`/`--title` (mensagem
 * de commit ou corpo de PR). Nenhum desses EXECUTA o comando casado.
 *
 * @param {string} cmd @param {RegExp} re
 */
/**
 * O trecho casado está DENTRO do código passado a `node -e` / `python -c` / `php -r`?
 *
 * Só o P5 usa isto, e de propósito: ali o `jq` dentro de uma string JS é TEXTO, não
 * comando de shell. Para o P2 o oposto vale — o código de `node -e` é executado e
 * precisa morder —, então isto não entra no `ehMencao` geral.
 *
 * Heurística explícita (e o limite dela): considera "dentro" quando existe um
 * `-e`/`-c`/`-r` com aspas ABERTAS antes do match — mesma forma que o `ehMencao`
 * usa pra `-m`/`--body`. Um `node -e "…" | jq .` tem o pipe FORA das aspas, então
 * o contador fecha e o padrão morde, que é o certo.
 *
 * @param {string} cmd @param {RegExp} re
 */
export function dentroDeCodigoInline(cmd, re) {
  const m = cmd.match(re);
  if (!m || m.index === undefined) return false;
  const antes = cmd.slice(0, m.index);
  const flag = /\s-(?:e|c|r)\s+(["'])/.exec(antes);
  if (!flag) return false;
  const aspa = flag[1];
  // conta as aspas do MESMO tipo depois da abertura: ímpar = ainda dentro da string
  const depoisDaAbertura = antes.slice(flag.index + flag[0].length - 1);
  const n = (depoisDaAbertura.match(new RegExp(aspa, 'g')) || []).length;
  return n % 2 === 1;
}

/** @param {string} cmd @param {RegExp} re */
export function ehMencao(cmd, re) {
  const m = cmd.match(re);
  if (!m || m.index === undefined) return false;
  const antes = cmd.slice(0, m.index);
  return /\b(echo|printf)\b[^\n]*$/.test(antes)
    || /<<\s*['"]?\w+['"]?[\s\S]*$/.test(antes)
    || /\s(-m|--body|--body-file|--title)\s+["'][^"']*$/.test(antes);
}

/**
 * @param {string} cmd
 * @param {{jqExiste?:boolean}} [amb] injetável pelo selftest — em produção o
 *   ambiente é medido, não assumido (ver `jqExisteLocalmente`).
 */
export function achaPadrao(cmd, amb = {}) {
  if (typeof cmd !== 'string' || !cmd) return null;
  for (const p of PADROES) {
    if (!p.re.test(cmd)) continue;
    if (ehMencao(cmd, p.re)) continue;
    if (p.exceto && p.exceto(cmd)) continue;
    // P5 só faz sentido onde o binário falta: se `jq` está instalado, o comando
    // roda e não mente — a trava se desliga sozinha em vez de virar ruído.
    // A sonda é consultada AQUI, depois do regex casar, e não no topo: senão todo
    // comando do agente pagaria o spawn, e só ~0,03% deles chega a este ponto.
    if (p.id === 'P5' && (amb.jqExiste ?? jqExisteLocalmente())) continue;
    return p;
  }
  return null;
}

/** @param {{id:string,nome:string,porque:string,saida:string}} p */
export function mensagem(p) {
  return [
    `[block-sonda-que-mente] BLOQUEADO (${p.id} · ${p.nome}).`,
    '',
    `O QUE ACONTECE: ${p.porque}`,
    `A SAÍDA CERTA: ${p.saida}`,
    '',
    'Esta trava existe porque a saída do comando seria PLAUSÍVEL e ERRADA — não há',
    'erro que denuncie, e o número entra no relatório como se tivesse sido medido.',
    'Escape consciente (emergência): OIMPRESSO_SONDA_OVERRIDE=1',
  ].join('\n');
}

// ── selftest ────────────────────────────────────────────────────────────────
const FIXTURES = [
  // [comando, deve bloquear?]
  ['git grep --hidden -n "foo" -- .', true],
  ['rg --hidden -n "foo"', false],
  ['git grep -n "foo" -- .', false],
  ['grep -E "foo\\|bar" a.txt', true],
  ['grep -E "foo|bar" a.txt', false],
  ['grep -F "foo|bar" a.txt', false],
  ['git fetch origin main 2>&1 | head -20', false],
  [`node -e 's.replace(a, "x $\` y")'`, true],
  ['node -e "s.replace(a, () => novo)"', false],
  ['echo "custa $5 e $10"', false],
  // MENÇÃO — o padrão aparece dentro de string; ninguém executa nada disso.
  // Medido: 79,6% (P3) e 73,1% (P4) dos disparos reais eram destes.
  ['echo "nao use git grep --hidden, ele nao existe"', false],
  ['git commit -m "fix: grep -E com \\| devolvia zero"', false],
  ['gh pr create --title "conserta grep -E \\| no lint"', false],
  // P5 — jq local. MORDE a execução; ignora tudo que não invoca o binário aqui.
  [`gh pr checks 6789 --json name,bucket | jq -r '.[].name'`, true],
  [`jq -r '.x' dados.json`, true],
  [`cat x.json | jq '.a' && echo ok`, true],
  // FLAG do gh — built-in, não usa o binário (9.611 de 9.717 usos do corpus)
  [`gh pr checks 6789 --json name,bucket --jq '.[].name'`, false],
  [`gh api repos/o/r/actions/jobs/1/logs --jq '.id'`, false],
  // executa em OUTRA máquina, onde o jq pode existir → o padrão se abstém
  [`tailscale ssh root@ct100-mcp 'jq -r .status /tmp/x.json'`, false],
  [`ssh -4 user@host "cat a.json | jq ."`, false],
  [`docker exec oimpresso-staging sh -c "jq . /app/x.json"`, false],
  // menção, não execução
  ['echo "o jq nao existe nesta maquina, use node"', false],
  ['git commit -m "fix: troca jq por node no monitor"', false],
  // jq como TEXTO dentro de código inline — foi o FP que o hook cometeu contra o
  // próprio comando que o media (2 casos no corpus de 116.248)
  [`node -e "console.log('cat x.json | jq .')"`, false],
  [`python -c "print('use | jq para filtrar')"`, false],
  // …mas o pipe FORA das aspas é execução de verdade e tem de morder
  [`node -e "console.log(1)" | jq -r '.'`, true],
  // CONTROLE do controle: a abstenção de código inline NÃO pode desarmar o P2,
  // cujo alvo (.replace com $ especial) vive justamente dentro de `node -e`
  [`node -e 'x.replace(a, "y $\` z")'`, true],
];

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

const ehEntrypoint = process.argv[1] && import.meta.url.endsWith(process.argv[1].replace(/\\/g, '/').split('/').pop());

if (ehEntrypoint) {
  if (process.argv.includes('--selftest')) {
    let falhas = 0;
    // jqExiste:false é INJETADO — sem isso o resultado das fixtures P5 dependeria
    // da máquina: no CI (ubuntu) o jq EXISTE e o P5 se desliga, então elas
    // falhariam lá e passariam aqui. Fixture não pode medir o runner.
    for (const [cmd, esperado] of FIXTURES) {
      const bloqueou = achaPadrao(cmd, { jqExiste: false }) !== null;
      const ok = bloqueou === esperado;
      if (!ok) falhas++;
      console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${esperado ? 'MORDE  ' : 'ignora '} ${cmd.slice(0, 52)}`);
    }

    // O P5 se desliga sozinho onde o binário existe (senão viraria ruído no CI).
    const desliga = achaPadrao(`gh pr checks 1 --json x | jq -r '.'`, { jqExiste: true }) === null;
    if (!desliga) falhas++;
    console.log(`  [${desliga ? 'PASS' : 'FAIL'}] P5 SE DESLIGA quando jq existe no PATH`);

    // A sonda de ambiente é a peça de que o desligamento depende. Aqui ela só pode
    // ser checada em FORMA (boolean, estável) — o veredito dela varia com a máquina
    // por construção, então cravá-lo faria a fixture medir o runner (o erro que a
    // injeção acima evita). O controle POSITIVO — plantar um jq e exigir que a sonda
    // o veja — é o que pegou o bug do `path.delimiter` e está no corpo do PR.
    const v1 = jqExisteLocalmente(), v2 = jqExisteLocalmente();
    const sondaOk = typeof v1 === 'boolean' && v1 === v2;
    if (!sondaOk) falhas++;
    console.log(`  [${sondaOk ? 'PASS' : 'FAIL'}] jqExisteLocalmente → boolean estável (aqui: ${v1})`);

    console.log(falhas ? `\nSELFTEST FALHOU — ${falhas} caso(s).` : '\nSELFTEST OK — morde os 4 padrões, ignora os controles negativos, e o P5 se desliga onde o jq existe.');
    process.exit(falhas ? 1 : 0);
  }

  if (process.env.OIMPRESSO_SONDA_OVERRIDE === '1') process.exit(0);

  let raw;
  try { raw = await readStdin(); } catch { process.exit(0); }
  if (!raw) process.exit(0);

  let cmd = '';
  try {
    const j = JSON.parse(raw);
    cmd = j?.tool_input?.command ?? '';
  } catch { process.exit(0); }               // parse-fail → fail-open

  const p = achaPadrao(cmd);
  if (p) { process.stderr.write(mensagem(p) + '\n'); process.exit(2); }
  process.exit(0);
}
