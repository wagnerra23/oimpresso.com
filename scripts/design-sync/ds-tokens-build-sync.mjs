#!/usr/bin/env node
// @ts-check
// ds-tokens-build-sync.mjs — os `_generated-*.css` commitados batem com um build fresco?
//
// POR QUE EXISTE: os `_generated-*.css` são build inputs RASTREADOS (committados) consumidos
//   pelo app, pelo espelho e por TODO o loop diff-first (ds-token-diff / ds-mirror-drift /
//   ds-token-version). A premissa do loop é "git = fonte verdadeira". Se alguém editar
//   `semantic.tokens.json` e NÃO rebuildar, o `_generated` commitado fica stale e o loop
//   inteiro valida contra o valor ERRADO — sem ninguém perceber. Este script prova a premissa.
//
// POR QUE VIROU SCRIPT (antes era shell inline no workflow · chip G6a 2026-08-14): a versão
//   em shell (a) ENGOLIA o resultado — `if git diff …; else echo ::warning; exit 0; fi`, ou
//   seja, o ramo que detectava divergência saía 0 e o job ficava verde; (b) não tinha
//   selftest, então "nunca disparou" era indistinguível de cego; e (c) não podia entrar no
//   bite-log (DR-2a da ADR 0336) porque não havia comando pra invocar. Um script com `--check`
//   + `--selftest` resolve os três de uma vez, e é o MESMO caminho que o CI, o selftest e o
//   recorder exercitam (§5 2026-07-28: validar um modo e chamar de verde é gate parcial).
//
// NÃO SUJA A ÁRVORE: o build sai num tmpdir e a comparação é em memória. O `npm run
//   tokens:build` do workflow antigo reescrevia `resources/css/tokens/_generated-*.css` pra
//   depois medir com `git diff` — inviável pro recorder do bite-log, que roda numa árvore de
//   trabalho real. Aqui o disco do repo é só LIDO.
//
// EXIT CODES (o "não consegui medir" é distinto de "está tudo certo" — §5 2026-07-29):
//   0 = em sincronia            (build fresco == commitado, byte-a-byte)
//   1 = DIVERGE                 (é a mordida: alguém mexeu no token e não rebuildou)
//   2 = não consegui medir      (style-dictionary ausente, config ilegível, tokens sumidos)
//   Fail-open é proibido: `2` derruba o step igual, porque gate mudo é pior que gate ausente.
//   Pro recorder do bite-log, `2` vem com `Error:` no stderr → classificado `crashed`, e
//   crash NUNCA vira mordida (evita falso-positivo na contagem DR-2).
//
// COMPARAÇÃO: byte-a-byte com CRLF normalizado pra LF. O contrato defendido é "o VALOR do
//   token commitado é o que a fonte gera", não "o arquivo tem o fim-de-linha X" — sem a
//   normalização, um checkout Windows com autocrlf acusaria os 6 arquivos (falso-positivo).
//
// Uso:
//   node scripts/design-sync/ds-tokens-build-sync.mjs --check [--root <dir>]
//   node scripts/design-sync/ds-tokens-build-sync.mjs --selftest
//
//   --root <dir>  raiz alternativa (default: raiz do repo). Só os DADOS mudam de lugar —
//                 `*.tokens.json` e `_generated-*.css` saem de `<root>/resources/css/tokens/`;
//                 a MAQUINARIA (style-dictionary.config.mjs) é sempre a do repo. É isso que
//                 deixa o selftest hermético: sandbox de dados, motor real.

import { readFileSync, existsSync, mkdtempSync, rmSync, writeFileSync, mkdirSync, cpSync, readdirSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '..', '..');
const CONFIG_MJS = join(REPO, 'resources', 'css', 'tokens', 'style-dictionary.config.mjs');
const TOKENS_REL = join('resources', 'css', 'tokens');

const args = process.argv.slice(2);
const argVal = (flag, def) => {
  const i = args.indexOf(flag);
  return i >= 0 && args[i + 1] ? args[i + 1] : def;
};

/** LF-normalizado: o contrato é o valor do token, não o fim-de-linha do checkout. */
const norm = (s) => String(s).replace(/\r\n/g, '\n');

/** style-dictionary quer glob POSIX mesmo no Windows. */
const posix = (p) => p.replace(/\\/g, '/');

/** erro que o recorder do bite-log reconhece como "crashou", nunca como mordida. */
function naoConsegiuMedir(msg) {
  console.error(`Error: ds-tokens-build-sync não conseguiu medir — ${msg}`);
  process.exit(2);
}

/**
 * Roda o Style Dictionary com a config REAL do repo, apontando a fonte pro `<root>` e a
 * saída pra um tmpdir. Devolve Map<nomeDoArquivo, conteúdo>.
 * @param {string} root
 */
async function buildFresco(root) {
  const srcDir = join(root, TOKENS_REL);
  if (!existsSync(srcDir)) naoConsegiuMedir(`diretório de tokens ausente: ${posix(srcDir)}`);

  let config, StyleDictionary;
  try {
    ({ config } = await import(pathToFileURL(CONFIG_MJS).href));
    StyleDictionary = (await import('style-dictionary')).default;
  } catch (e) {
    naoConsegiuMedir(
      `style-dictionary/config indisponível (${e && e.message ? e.message : e}). ` +
        'Rode `npm ci` — sem a dependência não dá pra provar a sincronia.'
    );
  }

  const out = mkdtempSync(join(tmpdir(), 'ds-tokens-build-sync-'));
  try {
    const cfg = {
      ...config,
      source: [posix(join(srcDir, '*.tokens.json'))],
      platforms: { css: { ...config.platforms.css, buildPath: posix(out) + '/' } },
    };
    const sd = new StyleDictionary(cfg);
    await sd.buildAllPlatforms();
    const emitidos = new Map();
    for (const f of config.platforms.css.files) {
      const p = join(out, f.destination);
      if (!existsSync(p)) naoConsegiuMedir(`o build não emitiu ${f.destination}`);
      emitidos.set(f.destination, norm(readFileSync(p, 'utf8')));
    }
    return emitidos;
  } catch (e) {
    if (e && e.__jaReportado) throw e;
    naoConsegiuMedir(`o build de tokens falhou: ${e && e.message ? e.message : e}`);
  } finally {
    rmSync(out, { recursive: true, force: true });
  }
}

/**
 * Compara build fresco × commitado. Devolve { ausentes[], divergentes[], ok[] }.
 * @param {string} root
 */
async function comparar(root) {
  const emitidos = await buildFresco(root);
  const ausentes = [];
  const divergentes = [];
  const ok = [];
  for (const [nome, esperado] of emitidos) {
    const alvo = join(root, TOKENS_REL, nome);
    if (!existsSync(alvo)) { ausentes.push(nome); continue; }
    if (norm(readFileSync(alvo, 'utf8')) !== esperado) divergentes.push(nome);
    else ok.push(nome);
  }
  return { ausentes, divergentes, ok };
}

// ── modo --check ────────────────────────────────────────────────────────────

async function check(root) {
  const { ausentes, divergentes, ok } = await comparar(root);
  if (!ausentes.length && !divergentes.length) {
    console.log(
      `ds-tokens-build-sync: OK — ${ok.length} arquivo(s) _generated em sincronia com os *.tokens.json (build determinístico).`
    );
    process.exit(0);
  }
  // O DRIFT vai pro STDOUT de propósito: o recorder do bite-log assina (`sig`) e extrai o
  // `motivo` a partir do stdout do gate. Se o detalhe saísse só no stderr, toda violação
  // deste gate produziria a MESMA assinatura (hash de saída vazia) — o dedup colapsaria
  // violações distintas numa só e o ledger registraria motivo em branco.
  // O stderr fica reservado ao "não consegui medir" (`Error:`), que é o que a detecção de
  // crash do recorder lê pra NÃO contar como mordida.
  console.log('ds-tokens-build-sync: [DRIFT] os _generated-*.css commitados NÃO batem com um build fresco.');
  for (const f of divergentes) console.log(`  ✗ diverge : ${posix(join(TOKENS_REL, f))}`);
  for (const f of ausentes) console.log(`  ✗ ausente : ${posix(join(TOKENS_REL, f))}`);
  console.log(
    '  → o token foi editado no JSON sem rebuildar: o loop diff-first (ds-token-diff/mirror-drift/token-version)\n' +
      '    está validando contra valor STALE.\n' +
      '  → conserto: npm run tokens:build && git add resources/css/tokens/_generated-*.css'
  );
  process.exit(1);
}

// ── selftest (hermético — sandbox de dados, motor real) ─────────────────────

function rodarCli(root) {
  const r = spawnSync('node', [join(HERE, 'ds-tokens-build-sync.mjs'), '--check', '--root', root], {
    cwd: REPO,
    encoding: 'utf8',
    timeout: 120000,
  });
  return {
    exit: typeof r.status === 'number' ? r.status : -1,
    saida: (r.stdout || '') + (r.stderr || ''),
  };
}

function selftest() {
  let fail = 0;
  const assert = (cond, msg, extra = '') => {
    console.log(`  ${cond ? '✓' : '✗'} ${msg}${extra ? ` — ${extra}` : ''}`);
    if (!cond) fail++;
  };

  console.log('ds-tokens-build-sync --selftest (par boa/ruim: sem ele, "nunca disparou" == cego)\n');

  const sb = mkdtempSync(join(tmpdir(), 'ds-tbs-selftest-'));
  try {
    const sbTokens = join(sb, TOKENS_REL);
    mkdirSync(sbTokens, { recursive: true });

    // A fonte do sandbox são os *.tokens.json REAIS (copiados). O que se prova aqui é a
    // DECISÃO (bate / não bate / falta), que vale pra qualquer conteúdo — e o controle
    // positivo abaixo garante que a decisão não está sendo tomada sobre o vazio.
    const fontes = readdirSync(join(REPO, TOKENS_REL)).filter((f) => f.endsWith('.tokens.json'));
    assert(fontes.length > 0, 'há *.tokens.json reais pra semear o sandbox', `${fontes.length} arquivo(s)`);
    for (const f of fontes) cpSync(join(REPO, TOKENS_REL, f), join(sbTokens, f));

    // ── FIXTURE BOA: o _generated do sandbox é a própria saída do build ──────
    const r = spawnSync(
      'node',
      ['--input-type=module', '-e',
        `import {config} from ${JSON.stringify(pathToFileURL(CONFIG_MJS).href)};` +
        `import SD from 'style-dictionary';` +
        `const cfg={...config,source:[${JSON.stringify(posix(join(sbTokens, '*.tokens.json')))}],` +
        `platforms:{css:{...config.platforms.css,buildPath:${JSON.stringify(posix(sbTokens) + '/')}}}};` +
        `await new SD(cfg).buildAllPlatforms();`],
      { cwd: REPO, encoding: 'utf8', timeout: 120000 }
    );
    if (r.status !== 0) {
      console.error(`  ✗ não consegui semear o sandbox: ${(r.stderr || '').slice(0, 400)}`);
      process.exit(1);
    }

    const gerados = readdirSync(sbTokens).filter((f) => /^_generated-.*\.css$/.test(f));
    assert(gerados.length === 6, 'o build semeia os 6 _generated do sandbox', `${gerados.length} arquivo(s)`);

    // CONTROLE POSITIVO — a decisão não pode ser tomada sobre arquivos vazios (§5 2026-08-08:
    // medidor que percorre o nada devolve verde e ninguém desconfia).
    const totalVars = gerados.reduce(
      (n, f) => n + (readFileSync(join(sbTokens, f), 'utf8').match(/^\s*--[a-z0-9-]+\s*:/gim) || []).length,
      0
    );
    assert(totalVars >= 50, 'controle positivo: o sandbox tem token de verdade pra comparar', `${totalVars} vars`);

    const boa = rodarCli(sb);
    assert(boa.exit === 0, 'FIXTURE BOA (build == commitado) → exit 0', `exit ${boa.exit}`);
    assert(/em sincronia/.test(boa.saida), 'FIXTURE BOA fala "em sincronia"');

    // ── FIXTURE RUIM 1: valor adulterado (o caso real — editou e não rebuildou) ──
    const alvo = join(sbTokens, '_generated-cockpit-light.css');
    const original = readFileSync(alvo, 'utf8');
    const adulterado = original.replace(/^(\s*--[a-z0-9-]+\s*:\s*)([^;\n]+);/im, '$1oklch(0.01 0.99 13);');
    assert(adulterado !== original, 'a adulteração da fixture ruim de fato mudou o arquivo');
    writeFileSync(alvo, adulterado, 'utf8');

    const ruim = rodarCli(sb);
    assert(ruim.exit === 1, 'FIXTURE RUIM (valor adulterado) → exit 1', `exit ${ruim.exit}`);
    assert(/diverge/.test(ruim.saida), 'FIXTURE RUIM aponta QUAL arquivo diverge');
    assert(/tokens:build/.test(ruim.saida), 'FIXTURE RUIM entrega a receita de conserto');

    // ── FIXTURE RUIM 2: _generated ausente (o build emitiu, o repo não tem) ──────
    writeFileSync(alvo, original, 'utf8');
    rmSync(join(sbTokens, '_generated-inertia-dark.css'));
    const faltando = rodarCli(sb);
    assert(faltando.exit === 1, 'FIXTURE RUIM (_generated ausente) → exit 1', `exit ${faltando.exit}`);
    assert(/ausente/.test(faltando.saida), 'FIXTURE RUIM (ausente) diz qual arquivo falta');

    // ── NÃO-CONSEGUI-MEDIR ≠ verde: sem tokens.json o exit é 2, nunca 0 ─────────
    const vazio = mkdtempSync(join(tmpdir(), 'ds-tbs-vazio-'));
    try {
      const semMedida = rodarCli(vazio);
      assert(semMedida.exit === 2, 'SEM FONTE (não dá pra medir) → exit 2, nunca 0', `exit ${semMedida.exit}`);
      assert(/Error:/.test(semMedida.saida), 'o "não medi" sai como Error: (o bite-log lê como crash, não mordida)');
    } finally {
      rmSync(vazio, { recursive: true, force: true });
    }
  } finally {
    rmSync(sb, { recursive: true, force: true });
  }

  console.log(
    fail
      ? `\n[SELFTEST FALHOU] ${fail} asserção(ões) — o par boa/ruim não discrimina.`
      : '\n[SELFTEST OK] a máquina morde (diverge/ausente), solta (em sincronia) e não finge medir.'
  );
  process.exit(fail ? 1 : 0);
}

// ── main ────────────────────────────────────────────────────────────────────

if (args.includes('--selftest')) {
  selftest();
} else if (args.includes('--check')) {
  await check(resolve(argVal('--root', REPO)));
} else {
  console.error('uso: node scripts/design-sync/ds-tokens-build-sync.mjs --check [--root <dir>] | --selftest');
  process.exit(2);
}
