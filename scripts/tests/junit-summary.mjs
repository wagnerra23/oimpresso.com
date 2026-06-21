#!/usr/bin/env node
/**
 * junit-summary.mjs — sumário JSON por arquivo de teste a partir de JUnit XML (PHPUnit/Pest).
 *
 * Uso: node scripts/tests/junit-summary.mjs <junit.xml> [--out <summary.json>]
 *
 * Contrato (FV-F1 — plano SDD 2026-06-12, semana 0):
 *   - XML ausente ou 0 bytes                       → exit 1 (tripwire do "artefato 0 bytes")
 *   - 0 <testcase> ou contado != declarado no raiz → exit 2 (coleta incoerente)
 *   - ok                                           → JSON em stdout (+ --out) e exit 0
 *
 * Zero deps (node >=18). NÃO inclui mensagens de falha no output — só contagens
 * e paths de arquivo de teste (repo é PÚBLICO; anti-PII por construção).
 */
import { readFileSync, writeFileSync, existsSync, statSync, appendFileSync } from 'node:fs';
import { resolve } from 'node:path';

function fail(code, msg) {
  console.error(`junit-summary: ${msg}`);
  process.exit(code);
}

const args = process.argv.slice(2);
const xmlPath = args[0];
const outIdx = args.indexOf('--out');
const outPath = outIdx !== -1 ? args[outIdx + 1] : null;

if (!xmlPath) fail(1, 'uso: node scripts/tests/junit-summary.mjs <junit.xml> [--out <summary.json>]');
if (!existsSync(xmlPath)) fail(1, `XML nao existe: ${xmlPath} (pest rodou com --log-junit apontando pra ca?)`);
if (statSync(xmlPath).size === 0) fail(1, `XML tem 0 bytes: ${xmlPath} (processo morto antes do flush — o bug FV-F1 original)`);

const raw = readFileSync(xmlPath, 'utf8');
// PHPUnit escapa texto de failure/error (sem CDATA), mas removemos CDATA/comentarios
// por seguranca antes do walker de tags.
const xml = raw
  .replace(/<!\[CDATA\[[\s\S]*?\]\]>/g, '')
  .replace(/<!--[\s\S]*?-->/g, '')
  .replace(/<\?[\s\S]*?\?>/g, '');

const attr = (s, name) => {
  const m = s.match(new RegExp(`\\b${name}="([^"]*)"`));
  return m ? m[1] : null;
};
const unesc = (s) =>
  s.replace(/&quot;/g, '"').replace(/&apos;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');

const cwdPrefix = resolve(process.cwd()).replace(/\\/g, '/') + '/';
const norm = (p) => {
  if (!p) return null;
  let s = unesc(p).replace(/\\/g, '/');
  if (s.startsWith(cwdPrefix)) s = s.slice(cwdPrefix.length);
  // Pest emite file="Path.php::it nome do teste" no <testcase> — corta o sufixo
  // pra agregacao ser de fato POR ARQUIVO (validado no run 27415097837).
  const sep = s.indexOf('::');
  if (sep !== -1) s = s.slice(0, sep);
  return s;
};

const tagRe = /<(\/?)([\w.:-]+)((?:"[^"]*"|'[^']*'|[^>"'])*?)(\/?)>/g;
const files = new Map();
const totals = { passed: 0, failed: 0, errors: 0, skipped: 0 };
const suiteFileStack = [];
let suiteDepth = 0;
let declared = 0;
let counted = 0;
let current = null;
let curStatus = null;

function commitTestcase() {
  if (!current) return;
  counted++;
  const entry =
    files.get(current.file) ??
    { file: current.file, tests: 0, passed: 0, failed: 0, errors: 0, skipped: 0, time: 0 };
  entry.tests++;
  entry[curStatus]++;
  totals[curStatus]++;
  entry.time += current.time;
  files.set(current.file, entry);
  current = null;
  curStatus = null;
}

for (const m of xml.matchAll(tagRe)) {
  const [, close, name, rawAttrs, selfClose] = m;
  if (name === 'testsuite') {
    if (close) {
      suiteDepth--;
      suiteFileStack.pop();
    } else if (!selfClose) {
      // soma so os <testsuite> de nivel raiz (filhos diretos de <testsuites>):
      // e o total agregado que o PHPUnit declara — base do check de coerencia.
      if (suiteDepth === 0) declared += parseInt(attr(rawAttrs, 'tests') ?? '0', 10) || 0;
      suiteDepth++;
      suiteFileStack.push(norm(attr(rawAttrs, 'file')));
    }
    continue;
  }
  if (name === 'testcase') {
    if (!close) {
      current = {
        file: norm(attr(rawAttrs, 'file')) || [...suiteFileStack].reverse().find(Boolean) || '(sem arquivo)',
        time: parseFloat(attr(rawAttrs, 'time') ?? '0') || 0,
      };
      curStatus = 'passed';
      if (selfClose) commitTestcase();
    } else {
      commitTestcase();
    }
    continue;
  }
  if (current && !close) {
    if (name === 'failure') curStatus = 'failed';
    else if (name === 'error') curStatus = 'errors';
    else if (name === 'skipped') curStatus = 'skipped';
  }
}

const result = {
  schema: 'junit-summary/v1',
  generated_at: new Date().toISOString(),
  source: xmlPath.replace(/\\/g, '/'),
  n_testcases: counted,
  n_testcases_declared: declared,
  coherent: counted > 0 && counted === declared,
  totals,
  files: [...files.values()]
    .sort((a, b) => a.file.localeCompare(b.file))
    .map((f) => ({ ...f, time: Math.round(f.time * 1000) / 1000 })),
};

if (outPath) writeFileSync(outPath, JSON.stringify(result, null, 2) + '\n');
console.log(JSON.stringify(result, null, 2));

if (process.env.GITHUB_STEP_SUMMARY) {
  const lines = [
    `### JUnit summary — ${counted} testcases (${result.coherent ? 'coerente' : 'INCOERENTE'})`,
    '',
    '| arquivo de teste | tests | pass | fail | err | skip |',
    '|---|---:|---:|---:|---:|---:|',
    ...result.files.map((f) => `| ${f.file} | ${f.tests} | ${f.passed} | ${f.failed} | ${f.errors} | ${f.skipped} |`),
  ];
  appendFileSync(process.env.GITHUB_STEP_SUMMARY, lines.join('\n') + '\n');
}

if (counted === 0) fail(2, 'XML existe mas tem 0 <testcase> — coleta quebrada (sintoma do artefato 0 bytes)');
if (counted !== declared) fail(2, `incoerencia: contados ${counted} != declarados ${declared} no(s) <testsuite> raiz`);
