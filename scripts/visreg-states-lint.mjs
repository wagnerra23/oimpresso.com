#!/usr/bin/env node
// visreg-states-lint.mjs — charter `states:` ⇄ manifesto do gate L2 (estados isolados do VRT).
//
// O QUE PROVA: todo estado declarado num `<Tela>.charter.md` (campo `states:`) tem entrada
// correspondente em tests/Browser/visreg-states.json (e vice-versa) — entao toda tela×estado
// declarado VAI virar snapshot no IsolatedStatesBaselineTest, e nenhum estado de manifesto
// fica orfao sem o charter humano declarar. Sem este lint, charter e manifesto driftam em
// silencio: o snapshot some (ou nunca nasce) e a regressao volta a passar batido.
//
// REGRAS (falha = exit > 0):
//   1. Todo screen do manifesto aponta um charter que EXISTE.
//   2. O conjunto `states:` do charter == o conjunto `states` do manifesto (igualdade exata).
//   3. Todo estado (dos dois lados) esta no vocabulario `valid_states` do manifesto.
//   4. Nenhum charter declara `states:` SEM ter entrada no manifesto (estado sem snapshot).
//
// Node puro, sem deps. Exit = nº de problemas (MORDE). O step no workflow NASCE ADVISORY
// (continue-on-error, ADR 0271/0275) → reporta sem travar merge ate 2 verdes; promover =
// tirar o continue-on-error. Self-test (sensibilidade + especificidade, L-31):
//   node scripts/visreg-states-lint.mjs --selftest
//
// Doc: tests/Browser/visreg-states.json (fonte unica) + IsolatedStatesBaselineTest.php.

import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { readFileSync, existsSync } from 'node:fs';
import { execSync } from 'node:child_process';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = (() => { const i = process.argv.indexOf('--root'); return i >= 0 ? resolve(process.argv[i + 1]) : resolve(HERE, '..'); })();
const MANIFEST_REL = 'tests/Browser/visreg-states.json';
const log = (...a) => console.log(...a);

function git(args) {
  try { return execSync(`git ${args}`, { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim(); }
  catch { return ''; }
}

/**
 * Extrai o campo `states:` do frontmatter YAML de um charter. Suporta as duas formas:
 *   inline →  states: [default, empty, dark]
 *   bloco  →  states:\n      - default\n      - empty
 * Retorna array de strings, ou null se o charter nao declara `states:`.
 */
function parseCharterStates(md) {
  const fm = md.match(/^---\n([\s\S]*?)\n---/);
  if (!fm) return null;
  const body = fm[1];

  // Forma inline.
  const inline = body.match(/^states:[ \t]*\[([^\]]*)\]/m);
  if (inline) {
    return inline[1].split(',').map((s) => s.trim().replace(/^['"]|['"]$/g, '')).filter(Boolean);
  }

  // Forma bloco: `states:` seguido de linhas `- item` indentadas.
  const blockHead = body.match(/^states:[ \t]*$/m);
  if (blockHead) {
    const after = body.slice(blockHead.index + blockHead[0].length).split('\n');
    const out = [];
    for (const line of after) {
      const m = line.match(/^[ \t]+-[ \t]*['"]?([^'"\n]+?)['"]?[ \t]*$/);
      if (m) { out.push(m[1].trim()); continue; }
      if (line.trim() === '') continue;       // linha em branco intermediaria — tolera
      break;                                   // outra chave do frontmatter — fim da lista
    }
    return out;
  }

  return null;
}

const eqSet = (a, b) => a.length === b.length && [...a].sort().join('|') === [...b].sort().join('|');

/**
 * Comparador PURO (sem I/O) — coracao do lint, exercitado pelo --selftest. Retorna a lista de
 * problemas (strings) de UM screen. Vazio = ok.
 */
function diffScreen(slug, charterStates, manifestStates, validStates) {
  const problems = [];

  if (charterStates === null) {
    problems.push(`${slug}: charter NAO declara \`states:\` (manifesto pede [${manifestStates.join(', ')}]).`);
    return problems;
  }

  const badManifest = manifestStates.filter((s) => !validStates.includes(s));
  if (badManifest.length) problems.push(`${slug}: estado(s) fora do vocab no manifesto: ${badManifest.join(', ')}.`);

  const badCharter = charterStates.filter((s) => !validStates.includes(s));
  if (badCharter.length) problems.push(`${slug}: estado(s) fora do vocab no charter: ${badCharter.join(', ')}.`);

  if (!eqSet(charterStates, manifestStates)) {
    problems.push(`${slug}: charter [${[...charterStates].sort().join(', ')}] != manifesto [${[...manifestStates].sort().join(', ')}].`);
  }

  return problems;
}

function lint() {
  const manifestPath = resolve(ROOT, MANIFEST_REL);
  if (!existsSync(manifestPath)) { log(`❌ manifesto ausente: ${MANIFEST_REL}`); return 1; }

  let manifest;
  try { manifest = JSON.parse(readFileSync(manifestPath, 'utf8')); }
  catch (e) { log(`❌ manifesto invalido (${MANIFEST_REL}): ${e.message}`); return 1; }

  const validStates = Array.isArray(manifest.valid_states) ? manifest.valid_states : [];
  const screens = manifest.screens || {};
  const problems = [];
  const manifestCharters = new Set();

  // Regras 1-3 — por screen do manifesto.
  for (const [slug, screen] of Object.entries(screens)) {
    const charterRel = screen.charter;
    if (!charterRel) { problems.push(`${slug}: manifesto sem campo \`charter\`.`); continue; }
    manifestCharters.add(charterRel.replace(/\\/g, '/'));

    const charterAbs = resolve(ROOT, charterRel);
    if (!existsSync(charterAbs)) { problems.push(`${slug}: charter inexistente: ${charterRel}.`); continue; }

    const charterStates = parseCharterStates(readFileSync(charterAbs, 'utf8'));
    problems.push(...diffScreen(slug, charterStates, (screen.states || []), validStates));
  }

  // Regra 4 — charter declara `states:` mas NAO ha entrada no manifesto (estado sem snapshot).
  const allCharters = (git('ls-files -- "*.charter.md"') || '').split('\n').filter(Boolean);
  for (const rel of allCharters) {
    if (manifestCharters.has(rel)) continue;
    const states = parseCharterStates(readFileSync(resolve(ROOT, rel), 'utf8'));
    if (states && states.length) {
      problems.push(`${rel}: declara \`states:\` [${states.join(', ')}] mas NAO esta no manifesto (snapshot nunca nasce).`);
    }
  }

  log(`visreg-states-lint · ${Object.keys(screens).length} screen(s) no manifesto · ${problems.length} problema(s)`);
  for (const p of problems) log('  ✗ ' + p);
  if (problems.length) {
    log(`\n❌ ${problems.length} drift(s) charter ⇄ manifesto. Edite o \`states:\` do charter E ${MANIFEST_REL} JUNTOS.`);
    return problems.length;
  }
  log('✅ charter `states:` ⇄ manifesto em sincronia — todo estado declarado tem snapshot.');
  return 0;
}

// --selftest: prova que o comparador MORDE (sensibilidade) E PASSA num caso legitimo
// (especificidade) — L-31 "todo check tem que ter sido visto falhar dos dois lados".
function selftest() {
  const V = ['default', 'empty', 'loading', 'dark', 'error', 'long-data'];
  const cases = [
    // [nome, charterStates, manifestStates, esperaProblema?]
    ['match exato',            ['default', 'empty', 'dark'], ['dark', 'empty', 'default'], false],
    ['charter sem states',     null,                        ['default'],                  true],
    ['estado faltando',        ['default'],                 ['default', 'empty'],         true],
    ['estado a mais',          ['default', 'loading'],      ['default'],                  true],
    ['estado fora do vocab',   ['default', 'xpto'],         ['default', 'xpto'],          true],
  ];
  let fail = 0;
  for (const [nome, cs, ms, espera] of cases) {
    const got = diffScreen('teste', cs, ms, V).length > 0;
    const ok = got === espera;
    log(`${ok ? '✓' : '✗'} selftest: ${nome} → ${got ? 'MORDEU' : 'passou'} (esperado ${espera ? 'MORDER' : 'passar'})`);
    if (!ok) fail++;
  }
  if (fail) { log(`\n❌ selftest falhou em ${fail} caso(s) — o lint nao e confiavel.`); return fail; }
  log('\n✅ selftest ok — comparador morde drift e passa match (sensibilidade + especificidade).');
  return 0;
}

const isSelftest = process.argv.includes('--selftest');
process.exit((isSelftest ? selftest() : lint()) ? 1 : 0);
