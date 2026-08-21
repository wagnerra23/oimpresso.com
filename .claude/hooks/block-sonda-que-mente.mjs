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
 * ── POR QUE BLOQUEIA, E NÃO AVISA ───────────────────────────────────────────
 * Advisory serve pra achado que o humano pondera. Aqui não há o que ponderar: os
 * três produzem NÚMERO ERRADO com cara de certo, e quem lê a saída não tem como
 * desconfiar. O custo do falso-positivo é reescrever um comando; o custo do
 * falso-negativo é uma conclusão publicada.
 *
 * Escape (emergência, consciente): OIMPRESSO_SONDA_OVERRIDE=1
 * Fail-open em qualquer erro de parse — hook nunca trava a sessão.
 */

/** @type {{id:string, nome:string, re:RegExp, porque:string, saida:string}[]} */
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
export function ehMencao(cmd, re) {
  const m = cmd.match(re);
  if (!m || m.index === undefined) return false;
  const antes = cmd.slice(0, m.index);
  return /\b(echo|printf)\b[^\n]*$/.test(antes)
    || /<<\s*['"]?\w+['"]?[\s\S]*$/.test(antes)
    || /\s(-m|--body|--body-file|--title)\s+["'][^"']*$/.test(antes);
}

/** @param {string} cmd */
export function achaPadrao(cmd) {
  if (typeof cmd !== 'string' || !cmd) return null;
  for (const p of PADROES) if (p.re.test(cmd) && !ehMencao(cmd, p.re)) return p;
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
    for (const [cmd, esperado] of FIXTURES) {
      const bloqueou = achaPadrao(cmd) !== null;
      const ok = bloqueou === esperado;
      if (!ok) falhas++;
      console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${esperado ? 'MORDE  ' : 'ignora '} ${cmd.slice(0, 52)}`);
    }
    console.log(falhas ? `\nSELFTEST FALHOU — ${falhas} caso(s).` : '\nSELFTEST OK — morde os 3 padrões e ignora os controles negativos.');
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
