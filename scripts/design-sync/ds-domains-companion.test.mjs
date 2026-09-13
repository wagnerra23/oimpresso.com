#!/usr/bin/env node
// ds-domains-companion.test.mjs — BITE-TEST do emissor do companion `cockpit_domains.css`.
//
// POR QUE EXISTE. O `ds-domains-companion.mjs` é invocado pelo `ds-push.mjs` (PASSO 2, código
// real) e citado no runbook `design-sync-push.md`; o `ds-push` roda em CI (`ds-push.test.mjs`)
// e à mão (`npm run ds:push`). Até 2026-09-13 ele não tinha teste nem `--selftest`: nada
// provava que o filtro de DOMÍNIO filtra, que os valores saem verbatim, nem que o `--check`
// morde. Máquina que ninguém exercita é indistinguível de máquina que não olha.
//
// O QUE ELE EXERCITA. O CLI DE FORA, por subprocesso (§5 2026-07-30 · LC-15: assert sobre
// helper puro NÃO prova contrato de pipeline — e aqui não haveria o que assertar: o script
// não exporta nada, a decisão inteira vive no corpo do módulo com side-effect no topo).
//
// CONTRATO MEDIDO NO CONSUMIDOR, não imaginado. `ds-push.mjs:78` faz
// `node('ds-domains-companion.mjs', [tokensDir])` e grava o **stdout** como o arquivo que o
// `ds-token-diff --companion` vai parsear. Daí duas exigências duras que o T1 pina: o CSS sai
// em stdout LIMPO (um log ali corromperia o companion) e o `tokensDir` POSICIONAL é obedecido
// (se fosse ignorado, o push mediria o canon em vez do que lhe deram).
//
//   T0  premissa da sandbox do T12 (só importa builtins node:)
//   T1  stdout puro + tokensDir posicional obedecido
//   T2  filtro INCLUI os 7 grupos de domínio                  → par SOLTA
//   T3  filtro EXCLUI fundação (bg/accent/text/sb-*/font/...) → par MORDE
//   T4  limite do prefixo: `--stage` sem hífen NÃO entra      → controle do startsWith
//   T5  valor VERBATIM (não reprocessa OKLCH; só normaliza espaço)
//   T6  `--origin-OS-bg` (MAIÚSCULA) entra                    → controle do flag /i
//   T7  ordenado + dedup pelo PRIMEIRO valor
//   T8  light e dark em blocos separados, sem vazar um no outro
//   T9  o cabeçalho NÃO mente: `N tokens light + M dark` == o que foi emitido
//   T10 determinístico (2 runs byte-idênticos)
//   T11 tokens ausentes = FALHA, nunca companion vazio        → §5 2026-07-29
//   T12 `--check`/`--write`: ausente e divergente mordem, igual solta (sandbox por cópia)
//
// Node puro, sem deps/rede/DB. Fixtures em tmpdir do Node (nunca "/tmp" literal: §5
// 2026-08-21 — no Windows o Bash e o Node resolvem esse caminho pra lugares diferentes).
//
// Rodar: node scripts/design-sync/ds-domains-companion.test.mjs
//   exit 0 = o emissor filtra/ordena/morde como o docblock dele promete · exit 1 = prova caiu

import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, copyFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { spawnSync } from 'node:child_process';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../..');
const SCRIPT = join(HERE, 'ds-domains-companion.mjs');

let fails = 0;
const check = (name, cond, extra = '') => {
  console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}${cond ? '' : '  <- ' + extra}`);
  if (!cond) fails++;
};

// Roda o CLI REAL como subprocesso. Nunca lança: exit 1 é resultado ESPERADO em T11/T12.
// Devolve stdout e stderr SEPARADOS — o contrato do ds-push é justamente que o CSS não se
// misture com log, e um runner que concatena os dois ficaria cego exatamente nisso.
//
// `spawnSync` e não `execFileSync` por medição, não por gosto: na 1a versão deste arquivo o
// `execFileSync` devolvia só o stdout no caminho de SUCESSO (o stderr só aparece no objeto de
// erro), e o T12c caiu por stderr vazio num run que passou. É a §5 2026-08-14 — "medidor que
// consome saída de subprocesso lê os DOIS streams" — cometida no ramo feliz, que é onde ela
// não dói: um assert sobre a mensagem de OK teria ficado cego pra sempre.
function run(args, opts = {}) {
  const r = spawnSync(process.execPath, [opts.script || SCRIPT, ...args], {
    encoding: 'utf8', cwd: ROOT, stdio: ['ignore', 'pipe', 'pipe'],
  });
  if (r.error) throw r.error;
  return { code: r.status ?? 1, out: r.stdout || '', err: r.stderr || '' };
}

const TMP = mkdtempSync(join(tmpdir(), 'ds-domains-bite-'));

/** Cria um tokensDir com os dois `_generated-cockpit-*.css` e devolve o caminho. */
function tokensDir(nome, { light = '', dark = '', so = null } = {}) {
  const dir = join(TMP, nome);
  mkdirSync(dir, { recursive: true });
  const envolve = (sel, corpo) => `/* fixture */\n${sel} {\n${corpo}\n}\n`;
  if (so !== 'dark') writeFileSync(join(dir, '_generated-cockpit-light.css'), envolve('.cockpit', light), 'utf8');
  if (so !== 'light') writeFileSync(join(dir, '_generated-cockpit-dark.css'), envolve('.cockpit[data-theme="dark"]', dark), 'utf8');
  return dir;
}

/** Fatia a saída nos dois blocos e devolve as linhas de token de cada um. */
function blocos(css) {
  const iLight = css.indexOf('.cockpit {');
  const iDark = css.indexOf('.cockpit[data-theme="dark"] {');
  const linhas = (ini, fim) => css.slice(ini, fim).split('\n').filter((l) => /^ {2}--/.test(l));
  return { light: linhas(iLight, iDark), dark: linhas(iDark, css.length) };
}
const nomes = (ls) => ls.map((l) => l.trim().split(':')[0]);

const DOMINIOS = [
  '  --origin-CRM-bg: oklch(0.92 0.06 220);',
  '  --stage-blue: oklch(0.58 0.13 252);',
  '  --sla-late: oklch(0.55 0.18 27);',
  '  --canal-wa: oklch(0.62 0.14 150);',
  '  --kpi-feature-a: oklch(0.60 0.10 200);',
  '  --kind-soft-ok: oklch(0.90 0.03 150);',
  '  --vip: oklch(0.70 0.16 85);',
  '  --vip-soft: oklch(0.94 0.05 85);',
];
const FUNDACOES = [
  '  --bg: oklch(0.985 0.003 90);',
  '  --accent: oklch(0.55 0.15 295);',
  '  --border: oklch(0.90 0.004 90);',
  '  --text: oklch(0.22 0.006 90);',
  '  --radius: 8px;',
  '  --shadow: 0 1px 2px rgb(0 0 0 / 0.06);',
  '  --sb-accent: oklch(0.55 0.15 295);',
  '  --font-sans: "IBM Plex Sans", sans-serif;',
];

try {
  // ---- T0 — PREMISSA da sandbox do T12 ---------------------------------------
  // Se o script ganhar um import relativo, a CÓPIA em sandbox deixa de ser o script real e o
  // T12 vira teste de outra coisa (§5 2026-08-14: selftest que roda cópia fica verde enquanto
  // o pipeline regride). Aqui a premissa é ASSERTADA, não suposta.
  {
    const fonte = readFileSync(SCRIPT, 'utf8');
    const imports = [...fonte.matchAll(/from\s+'([^']+)'/g)].map((m) => m[1]);
    check('T0 premissa da sandbox: script só importa builtins node:',
      imports.length > 0 && imports.every((i) => i.startsWith('node:')),
      `imports=${JSON.stringify(imports)} — com import relativo, a cópia do T12 mente`);
  }

  // ---- T1 — stdout puro + tokensDir posicional obedecido ----------------------
  {
    const dir = tokensDir('t1', { light: '  --stage-fixture: oklch(0.5 0.1 10);', dark: '  --stage-fixture: oklch(0.4 0.1 10);' });
    const r = run([dir]);
    check('T1a CSS sai no STDOUT (o ds-push grava esse stdout como o companion)',
      r.code === 0 && r.out.includes('.cockpit {') && r.out.includes('--stage-fixture'), `code=${r.code}`);
    check('T1b stderr VAZIO no modo stdout (log misturado corromperia o companion)',
      r.err.trim() === '', `stderr=${JSON.stringify(r.err.slice(0, 120))}`);
    check('T1c tokensDir POSICIONAL obedecido (não leu o canon do repo)',
      !r.out.includes('--stage-slate'), 'saiu token que só existe no canon — o argumento foi ignorado');
  }

  // ---- T2/T3/T4 — o filtro de DOMÍNIO inclui os 7 grupos e exclui fundação ----
  {
    const corpo = [...DOMINIOS, ...FUNDACOES, '  --stage: oklch(0.5 0.1 10);'].join('\n');
    const dir = tokensDir('t2', { light: corpo, dark: corpo });
    const light = nomes(blocos(run([dir]).out).light);

    for (const l of DOMINIOS) {
      const n = l.trim().split(':')[0];
      check(`T2 SOLTA domínio ${n}`, light.includes(n), `emitidos=${light.join(' ')}`);
    }
    for (const l of FUNDACOES) {
      const n = l.trim().split(':')[0];
      check(`T3 MORDE fundação ${n} (fica no colors_and_type, camada UI-0013)`,
        !light.includes(n), 'fundação vazou pro companion e sobrescreveria o scaffold');
    }
    // Controle do `startsWith(prefixo)`: `--stage` sem o hífen NÃO é do grupo `stage-`.
    // Sem este par, um filtro frouxo (`includes`) passaria no T2 e reprovaria só aqui.
    check('T4 MORDE `--stage` sem hífen (o prefixo do grupo é `stage-`, não `stage`)',
      !light.includes('--stage'), `emitidos=${light.join(' ')}`);
  }

  // ---- T5 — valor VERBATIM: normaliza espaço, NÃO reprocessa a cor ------------
  {
    const dir = tokensDir('t5', {
      light: ['  --stage-a:   oklch(0.58   0.13   252 / 0.35)  ;', '  --vip: var(--accent-forte);'].join('\n'),
      dark: '  --stage-a: oklch(0.2 0.1 252);',
    });
    const light = blocos(run([dir]).out).light;
    check('T5a espaço interno colapsado, números INTOCADOS (não reprocessa OKLCH)',
      light.some((l) => l.trim() === '--stage-a: oklch(0.58 0.13 252 / 0.35);'), `linhas=${JSON.stringify(light)}`);
    check('T5b `var(--x)` passa literal (o companion não resolve referência)',
      light.some((l) => l.trim() === '--vip: var(--accent-forte);'), `linhas=${JSON.stringify(light)}`);
  }

  // ---- T6 — token com MAIÚSCULA entra (controle do flag /i do regex) ----------
  // `--origin-OS-bg` é real no canon. Sem o /i, os 10 tokens `--origin-*` somem do companion
  // em silêncio e o Cowork perde a camada inteira de origem.
  {
    const dir = tokensDir('t6', { light: '  --origin-OS-bg: oklch(0.93 0.07 70);', dark: '  --origin-OS-bg: oklch(0.3 0.07 70);' });
    check('T6 SOLTA `--origin-OS-bg` (MAIÚSCULA · controle do flag /i)',
      nomes(blocos(run([dir]).out).light).includes('--origin-OS-bg'), 'token com maiúscula sumiu — regex perdeu o /i');
  }

  // ---- T7 — ordenado + dedup pelo PRIMEIRO valor ------------------------------
  {
    const dir = tokensDir('t7', {
      light: ['  --vip: oklch(0.7 0.16 85);', '  --canal-b: red;', '  --stage-z: blue;', '  --canal-b: green;'].join('\n'),
      dark: '  --vip: oklch(0.2 0.16 85);',
    });
    const light = blocos(run([dir]).out).light;
    check('T7a ordem alfabética estável (determinismo do bundle)',
      nomes(light).join(',') === '--canal-b,--stage-z,--vip', `ordem=${nomes(light).join(',')}`);
    check('T7b dedup mantém o PRIMEIRO valor declarado',
      light.some((l) => l.trim() === '--canal-b: red;'), `linhas=${JSON.stringify(light)}`);
  }

  // ---- T8 — light e dark separados, sem vazamento ----------------------------
  {
    const dir = tokensDir('t8', { light: '  --stage-so-light: red;', dark: '  --stage-so-dark: blue;' });
    const { light, dark } = blocos(run([dir]).out);
    check('T8a bloco `.cockpit` tem só o light', nomes(light).join() === '--stage-so-light', `light=${nomes(light).join()}`);
    check('T8b bloco `.cockpit[data-theme=dark]` tem só o dark', nomes(dark).join() === '--stage-so-dark', `dark=${nomes(dark).join()}`);
  }

  // ---- T9 — o cabeçalho não mente (anti "verde no vácuo", §5 2026-07-29) ------
  {
    const dir = tokensDir('t9', {
      light: ['  --stage-a: red;', '  --vip: blue;', '  --bg: white;'].join('\n'),
      dark: '  --stage-a: black;',
    });
    const out = run([dir]).out;
    const m = out.match(/(\d+) tokens light \+ (\d+) dark/);
    const { light, dark } = blocos(out);
    check('T9a cabeçalho declara a contagem', m !== null, `sem contagem no header: ${out.slice(0, 120)}`);
    check('T9b contagem declarada == tokens emitidos (2 light · 1 dark, fundação fora)',
      m !== null && Number(m[1]) === light.length && Number(m[2]) === dark.length && light.length === 2 && dark.length === 1,
      `header=${m && m.slice(1)} emitidos=${light.length}/${dark.length}`);
  }

  // ---- T10 — determinístico ---------------------------------------------------
  {
    const dir = tokensDir('t10', { light: DOMINIOS.join('\n'), dark: DOMINIOS.join('\n') });
    check('T10 duas execuções byte-idênticas (sem Date/random)', run([dir]).out === run([dir]).out);
  }

  // ---- T11 — tokens ausentes é FALHA, nunca companion vazio -------------------
  // "Não consegui medir" não pode virar "não há domínio": um companion vazio empurrado pro
  // espelho apagaria a camada de domínio inteira sem ninguém ver (§5 2026-07-29).
  {
    const meio = tokensDir('t11', { light: '  --stage-a: red;', so: 'light' }); // sem o dark
    const r = run([meio]);
    check('T11a tokensDir sem `_generated-cockpit-dark.css` FALHA (não emite meio companion)',
      r.code !== 0 && !r.out.includes('.cockpit {'), `code=${r.code} out=${JSON.stringify(r.out.slice(0, 120))}`);
    const vazio = join(TMP, 't11-vazio');
    mkdirSync(vazio, { recursive: true });
    check('T11b tokensDir vazio FALHA (ausência de arquivo != ausência de domínio)',
      run([vazio]).code !== 0);
  }

  // ---- T12 — `--check` e `--write` (sandbox por CÓPIA; premissa no T0) --------
  // O OUT é hardcoded em `<repo>/prototipo-ui/design-system/cockpit_domains.css` e derivado de
  // `import.meta.url` — não dá pra parametrizar. A sandbox recria a árvore mínima ao redor de
  // uma cópia BYTE-A-BYTE do script real, lida do disco AGORA: mutação no arquivo de produção
  // aparece aqui. O T0 é quem garante que a cópia continua fiel.
  {
    const box = join(TMP, 'box');
    mkdirSync(join(box, 'scripts', 'design-sync'), { recursive: true });
    mkdirSync(join(box, 'prototipo-ui', 'design-system'), { recursive: true });
    const tokens = join(box, 'resources', 'css', 'tokens');
    mkdirSync(tokens, { recursive: true });
    const semear = (valor) => {
      writeFileSync(join(tokens, '_generated-cockpit-light.css'), `.cockpit {\n  --stage-a: ${valor};\n}\n`, 'utf8');
      writeFileSync(join(tokens, '_generated-cockpit-dark.css'), '.cockpit[data-theme="dark"] {\n  --stage-a: black;\n}\n', 'utf8');
    };
    semear('red');
    const copia = join(box, 'scripts', 'design-sync', 'ds-domains-companion.mjs');
    copyFileSync(SCRIPT, copia);
    const OUT = join(box, 'prototipo-ui', 'design-system', 'cockpit_domains.css');
    const rodar = (args) => run(args, { script: copia });

    const ausente = rodar(['--check']);
    check('T12a `--check` com companion AUSENTE morde (fail-closed, não "nada a comparar")',
      ausente.code === 1 && /DESATUALIZADO/.test(ausente.err), `code=${ausente.code} err=${JSON.stringify(ausente.err.slice(0, 120))}`);

    const escreveu = rodar(['--write']);
    check('T12b `--write` grava o companion no design-system canônico',
      escreveu.code === 0 && readFileSync(OUT, 'utf8').includes('--stage-a: red;'), `code=${escreveu.code}`);

    const igual = rodar(['--check']);
    check('T12c `--check` SOLTA depois do `--write` (round-trip, tokensDir default)',
      igual.code === 0 && /em sincronia/.test(igual.err), `code=${igual.code} err=${JSON.stringify(igual.err.slice(0, 120))}`);

    semear('GREEN');
    const drift = rodar(['--check']);
    check('T12d `--check` MORDE quando o canon andou e o companion ficou (o sentinela do passo 5)',
      drift.code === 1 && /DESATUALIZADO/.test(drift.err), `code=${drift.code} err=${JSON.stringify(drift.err.slice(0, 120))}`);
  }
} finally {
  try { rmSync(TMP, { recursive: true, force: true }); } catch { /* tmp: melhor esforço */ }
}

console.log(fails
  ? `\n${fails} prova(s) caiu(ram) — o ds-domains-companion não filtra/emite/morde como promete.`
  : '\nds-domains-companion filtra, emite e morde certo (par SOLTA/MORDE por regra).');
process.exit(fails ? 1 : 0);
