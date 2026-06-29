#!/usr/bin/env node
// handoff-changed.mjs — PORTÃO BARATO (zero LLM) da Fase −1 do protocolo aplicar-prototipo.
//
// Responde a UMA pergunta antes de gastar qualquer agente/sessão:
//   "este bundle de handoff Cowork (ZIP extraído) traz algo DIFERENTE do que eu já aceitei,
//    ou é o MESMO snapshot?"
//
// Por que existe (Wagner 2026-06-29): processar um handoff com agente custa tokens. Se o
// bundle é idêntico ao último aceito, processar = consumo à toa. Este script é o gate
// determinístico que vem ANTES: IDÊNTICO → para (exit 0, custo zero); MUDOU → lista
// exatamente o que mudou (exit 1) pra só então valer a pena processar.
//
// As 4 garantias (≠ promessa):
//   1. DETERMINÍSTICO — hash SHA256 do conteúdo NORMALIZADO; mesmo input → mesma saída.
//   2. IMUNE AO RUÍDO — neutraliza cache-bust (`?v=hash`/`_v=hash`), CRLF→LF e BOM, senão
//      cada re-export do Cowork apareceria como "tudo mudou". Provado por --selftest.
//   3. NADA SOME EM SILÊNCIO — enumera TODOS os arquivos; classifica NOVO/ALTERADO/REMOVIDO.
//   4. AUDITÁVEL — baseline versionado em config/ds-handoff-baseline.json + self-test no CI.
//
// Uso:
//   node prototipo-ui/handoff-changed.mjs --staging <dir>           # compara vs baseline → exit 0/1
//   node prototipo-ui/handoff-changed.mjs --staging <dir> --update  # ACEITA: grava baseline novo
//   node prototipo-ui/handoff-changed.mjs --staging <dir> --json    # saída JSON (data-only)
//   node prototipo-ui/handoff-changed.mjs --selftest                # fixture hermético (CI)
//   [--baseline <path>]                                             # override (default config/ds-handoff-baseline.json)
//
// Exit: 0 = idêntico ao baseline (ou --update/--selftest ok) · 1 = mudou (há delta) · 2 = erro de uso.

import { readFile, readdir, writeFile, mkdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join, resolve, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '..');

// Extensões tratadas como TEXTO (normalização de linha/BOM/cache-bust); o resto = binário (hash bruto).
const TEXT_EXT = new Set(['.css', '.scss', '.js', '.mjs', '.cjs', '.jsx', '.ts', '.tsx',
  '.d.ts', '.html', '.htm', '.json', '.md', '.txt', '.svg', '.xml', '.yml', '.yaml']);

// ---- normalização (o coração da garantia #2) -------------------------------
// Remove o token de cache-bust que o exportador Cowork embute (`app.jsx?v=eb2`); no Windows
// o `?` vira `_` ao extrair, então cobrimos as duas formas.
const stripCacheBust = (s) => s.replace(/[?&_]v=[A-Za-z0-9]+/g, '');

function normRelPath(rel) {
  return stripCacheBust(rel.replace(/\\/g, '/'));
}

function hashEntry(relPath, buf) {
  const ext = '.' + (relPath.split('.').pop() || '').toLowerCase();
  let payload;
  if (TEXT_EXT.has(ext)) {
    let s = buf.toString('utf8');
    if (s.charCodeAt(0) === 0xfeff) s = s.slice(1);            // BOM
    s = s.replace(/\r\n/g, '\n').replace(/\r/g, '\n');         // CRLF/CR → LF
    s = stripCacheBust(s);                                     // refs `import ...?v=hash`
    s = s.split('\n').map((l) => l.replace(/[ \t]+$/, '')).join('\n'); // rtrim por linha
    s = s.replace(/\n+$/, '\n');                               // sem linhas em branco no fim
    payload = Buffer.from(s, 'utf8');
  } else {
    payload = buf;                                             // binário: hash do conteúdo cru
  }
  return createHash('sha256').update(payload).digest('hex');
}

// ---- núcleo puro (testável sem disco) --------------------------------------
// entries = [{ rel, buf }]  →  manifesto { normPath: hash }  (ordenado)
function buildManifest(entries) {
  const m = {};
  for (const { rel, buf } of entries) {
    const key = normRelPath(rel);
    m[key] = hashEntry(key, buf);                              // colisão de path normalizado = último vence (raro)
  }
  return Object.fromEntries(Object.keys(m).sort().map((k) => [k, m[k]]));
}

function diffManifests(base, cur) {
  const novo = [], alterado = [], removido = [];
  let identico = 0;
  for (const k of Object.keys(cur)) {
    if (!(k in base)) novo.push(k);
    else if (base[k] !== cur[k]) alterado.push(k);
    else identico++;
  }
  for (const k of Object.keys(base)) if (!(k in cur)) removido.push(k);
  return { novo, alterado, removido, identico };
}

// ---- walker do staging -----------------------------------------------------
async function walk(dir, base, out = []) {
  for (const e of await readdir(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) await walk(p, base, out);
    else out.push({ rel: relative(base, p), buf: await readFile(p) });
  }
  return out;
}

function groupByTop(paths) {
  const g = {};
  for (const p of paths) { const top = p.split('/')[0]; g[top] = (g[top] || 0) + 1; }
  return g;
}

// ---- self-test hermético (garantia #2, sem tocar disco) --------------------
function selftest() {
  const baseEntries = [
    { rel: 'components/Button.jsx?v=aaa', buf: Buffer.from('export const B = () => <b/>\n') },
    { rel: 'colors_and_type.css', buf: Buffer.from('--primary: oklch(0.55 0.15 295);\n') },
    { rel: 'styles.css', buf: Buffer.from('body{margin:0}\n') },
  ];
  const curEntries = [
    // 1) mesmo conteúdo, cache-bust diferente → tem que dar IDÊNTICO
    { rel: 'components/Button.jsx?v=zzz', buf: Buffer.from('export const B = () => <b/>\n') },
    // 2) mesmo conteúdo, CRLF + BOM → IDÊNTICO
    { rel: 'colors_and_type.css', buf: Buffer.from('﻿--primary: oklch(0.55 0.15 295);\r\n') },
    // 3) styles.css removido (não está em cur) → REMOVIDO
    // 4) token mudado de fato → ALTERADO
    { rel: 'components/KpiCard.jsx', buf: Buffer.from('export const K = () => <i/>\n') },
  ];
  // muda o token real num arquivo que existe nos dois pra provar ALTERADO:
  baseEntries.push({ rel: 'components/Logo.jsx', buf: Buffer.from('export const L = 1\n') });
  curEntries.push({ rel: 'components/Logo.jsx', buf: Buffer.from('export const L = 2\n') });

  const base = buildManifest(baseEntries);
  const cur = buildManifest(curEntries);
  const d = diffManifests(base, cur);

  const checks = [
    ['cache-bust ignorado (Button IDÊNTICO)', !d.alterado.includes('components/Button.jsx') && !d.novo.includes('components/Button.jsx')],
    ['CRLF+BOM ignorado (colors IDÊNTICO)', !d.alterado.includes('colors_and_type.css')],
    ['mudança real pega (Logo ALTERADO)', d.alterado.includes('components/Logo.jsx')],
    ['arquivo novo pega (KpiCard NOVO)', d.novo.includes('components/KpiCard.jsx')],
    ['arquivo removido pega (styles REMOVIDO)', d.removido.includes('styles.css')],
  ];
  let ok = true;
  for (const [label, pass] of checks) { console.log(`  [${pass ? 'PASS' : 'FAIL'}] ${label}`); if (!pass) ok = false; }
  console.log(ok ? '\nSELFTEST OK — ruído ignorado + mudança real detectada.' : '\nSELFTEST FALHOU.');
  process.exit(ok ? 0 : 1);
}

// ---- main ------------------------------------------------------------------
const argv = process.argv.slice(2);
const has = (f) => argv.includes(f);
const val = (f, d) => { const i = argv.indexOf(f); return i >= 0 && argv[i + 1] ? argv[i + 1] : d; };

if (has('--selftest')) selftest();

const stagingArg = val('--staging', null);
const asJson = has('--json');
const update = has('--update');
const baselinePath = resolve(val('--baseline', join(REPO, 'config/ds-handoff-baseline.json')));

if (!stagingArg) { console.error('uso: --staging <dir-extraido-do-bundle> [--update] [--json]'); process.exit(2); }
const staging = resolve(stagingArg);
if (!existsSync(staging)) { console.error(`staging não existe: ${staging}`); process.exit(2); }

const cur = buildManifest(await walk(staging, staging));

if (update) {
  await mkdir(dirname(baselinePath), { recursive: true });
  await writeFile(baselinePath, JSON.stringify({ files: cur }, null, 2) + '\n', 'utf8');
  console.log(`baseline ACEITO (${Object.keys(cur).length} arquivos) → ${relative(REPO, baselinePath)}`);
  process.exit(0);
}

if (!existsSync(baselinePath)) {
  console.error(`SEM BASELINE ainda (${relative(REPO, baselinePath)}).`);
  console.error('Primeira vez: revise o bundle e rode com --update pra ACEITAR este snapshot como referência.');
  process.exit(1);
}

const base = JSON.parse(await readFile(baselinePath, 'utf8')).files || {};
const d = diffManifests(base, cur);
const mudou = d.novo.length + d.alterado.length + d.removido.length;

if (asJson) {
  console.log(JSON.stringify({ mudou: mudou > 0, ...d, total_atual: Object.keys(cur).length }, null, 2));
  process.exit(mudou > 0 ? 1 : 0);
}

if (mudou === 0) {
  console.log(`IDÊNTICO ao baseline (${d.identico} arquivos). Nada mudou → não processe (custo zero).`);
  process.exit(0);
}

console.log(`MUDOU — ${mudou} arquivo(s) com delta (${d.identico} idênticos):\n`);
const show = (titulo, arr) => { if (arr.length) { console.log(`  ${titulo} (${arr.length}) por pasta: ${JSON.stringify(groupByTop(arr))}`); arr.slice(0, 40).forEach((p) => console.log(`     - ${p}`)); } };
show('NOVO', d.novo);
show('ALTERADO', d.alterado);
show('REMOVIDO', d.removido);
console.log('\n→ Só vale gastar agente/sessão nos arquivos acima. Aceitar este snapshot: rode com --update.');
process.exit(1);
