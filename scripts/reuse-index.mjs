#!/usr/bin/env node
// scripts/reuse-index.mjs — inventário DERIVADO de símbolos do repo (JS/TS + PHP).
//
// POR QUE EXISTE (plano 2026-06-06 · memory/sessions/2026-06-06-plano-inventario-anti-duplicacao.md):
// a IA recria função/componente/Model que JÁ existe porque não tem onde perguntar "isso já existe?".
// Documento-índice escrito à mão APODRECE (ADR 0239: git=SSOT, derivado>escrito). Este índice é REGENERADO do código a cada
// chamada — impossível apodrecer. Responde "reusa ou cria, e em qual arquivo".
//
// Mata os erros reais T-AP-1 / T-AP-7 (inventar Model/Service que não existe — LICOES_F3_FINANCEIRO_REJEITADO).
//
// USO:
//   node scripts/reuse-index.mjs "formatar moeda"   # busca: REUSA em X:linha  OU  pode criar
//   node scripts/reuse-index.mjs "BaixaService"     # ex: "não existe; reais: Titulo/TituloBaixa"
//   node scripts/reuse-index.mjs --json             # índice completo (CI / scorecard)
//   node scripts/reuse-index.mjs --duplicates       # candidatos a duplicata (alimenta gate Frente 2)
//   node scripts/reuse-index.mjs                     # resumo por kind
//
// EVOLUÇÃO (resposta [W] "preparado pra evoluir?"): adicionar um KIND novo = uma entrada nos
// extratores abaixo. Não reescreve nada. Símbolos são pluggable por design.
//
// ALINHAMENTO: fecha o problema #5 do MANUAL-CSS-JS ("colisões de símbolo global / copy-paste"),
// diagnosticado mas sem gate. Complementa o REGISTRY_DS_COMPONENTES (manual/parcial) com um índice
// DERIVADO, consultável e abrangente (JS+PHP). Não substitui css-size/pageheader/conformance gates —
// é a camada de "reusa ou cria, em qual arquivo" que faltava.
//
// Refs: memory/requisitos/_DesignSystem/MANUAL-CSS-JS.md (problema #5)
//       · ADR 0239 (git=SSOT · derivado>escrito) · prototipo-ui/REGISTRY_DS_COMPONENTES.md.

import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join, relative, sep, basename } from 'node:path';
import { execSync } from 'node:child_process';

const ROOT = process.cwd();

// ---------------------------------------------------------------------------
// Áreas escaneadas (pluggable). Cada área diz onde varrer e como classificar.
// ---------------------------------------------------------------------------
const JS_DIRS = ['resources/js/Components', 'resources/js/Hooks', 'resources/js/Lib', 'resources/js/Pages', 'resources/js/Types'];
const PHP_DIRS = ['app', 'Modules'];
const PHP_SKIP = /[\\/](Database[\\/]Migrations|Tests|vendor|node_modules)[\\/]/i;
const JS_EXT = /\.(tsx?|jsx?)$/;
const PHP_EXT = /\.php$/;

// ---------------------------------------------------------------------------
// Walker recursivo simples (sem deps).
// ---------------------------------------------------------------------------
function walk(dir, extRe, skipRe, out = []) {
  let entries;
  try { entries = readdirSync(join(ROOT, dir), { withFileTypes: true }); }
  catch { return out; }
  for (const e of entries) {
    const rel = join(dir, e.name);
    if (e.name === 'node_modules' || e.name === 'vendor' || e.name === '.git') continue;
    if (e.isDirectory()) walk(rel, extRe, skipRe, out);
    else if (extRe.test(e.name) && !(skipRe && skipRe.test(rel))) out.push(rel);
  }
  return out;
}

// ---------------------------------------------------------------------------
// Classificador de KIND para JS/TS.
// ---------------------------------------------------------------------------
function jsKind(name, file, declared) {
  if (declared === 'type' || declared === 'interface' || declared === 'enum') return 'type';
  if (/^use[A-Z]/.test(name)) return 'hook';
  if (file.endsWith('.tsx') && /^[A-Z]/.test(name)) return 'component';
  if (/^[A-Z]/.test(name) && declared === 'class') return 'class';
  return 'util';
}

// Extrai símbolos exportados de um arquivo JS/TS.
function extractJs(file) {
  const txt = readFileSync(join(ROOT, file), 'utf8');
  const lines = txt.split('\n');
  const found = [];
  const seen = new Set();
  const push = (name, declared, ln) => {
    if (!name || seen.has(name)) return;
    seen.add(name);
    found.push({ name, kind: jsKind(name, file, declared), file, line: ln + 1, lang: 'js' });
  };
  lines.forEach((line, i) => {
    let m;
    if ((m = line.match(/^\s*export\s+(?:default\s+)?function\s+([A-Za-z0-9_]+)/))) push(m[1], 'function', i);
    else if ((m = line.match(/^\s*export\s+(?:abstract\s+)?class\s+([A-Za-z0-9_]+)/))) push(m[1], 'class', i);
    else if ((m = line.match(/^\s*export\s+const\s+([A-Za-z0-9_]+)/))) push(m[1], 'const', i);
    else if ((m = line.match(/^\s*export\s+(?:type|interface|enum)\s+([A-Za-z0-9_]+)/))) push(m[1], line.includes('interface') ? 'interface' : line.includes('enum') ? 'enum' : 'type', i);
    // export default <PascalName>  (componente default sem function)
    else if ((m = line.match(/^\s*export\s+default\s+([A-Z][A-Za-z0-9_]+)\s*;?\s*$/))) push(m[1], 'const', i);
  });
  // default export = nome do arquivo se for componente .tsx sem nome explícito
  if (file.endsWith('.tsx') && /export\s+default/.test(txt)) {
    const base = basename(file).replace(/\.tsx$/, '');
    if (/^[A-Z]/.test(base)) push(base, 'class', 0);
  }
  return found;
}

// ---------------------------------------------------------------------------
// Classificador de KIND para PHP (pelo caminho — é o que distingue Model/Service/Controller).
// ---------------------------------------------------------------------------
function phpKind(file) {
  const p = file.replace(/\\/g, '/');
  if (/\/Http\/Controllers\//.test(p)) return 'controller';
  if (/\/Services\//.test(p)) return 'service';
  if (/\/(Entities|Models)\//.test(p)) return 'model';
  if (/\/Jobs\//.test(p)) return 'job';
  if (/\/Events\//.test(p)) return 'event';
  if (/\/Listeners\//.test(p)) return 'listener';
  // app/ raiz UltimatePOS = models legados (Account, Business, ...)
  if (/^app\/[A-Z][A-Za-z0-9_]+\.php$/.test(p)) return 'model';
  return 'class';
}

function extractPhp(file) {
  const txt = readFileSync(join(ROOT, file), 'utf8');
  const lines = txt.split('\n');
  const found = [];
  const kind = phpKind(file);
  lines.forEach((line, i) => {
    const m = line.match(/^\s*(?:final\s+|abstract\s+|readonly\s+)*(class|trait|interface|enum)\s+([A-Za-z0-9_]+)/);
    if (m) found.push({ name: m[2], kind: m[1] === 'class' ? kind : m[1], file, line: i + 1, lang: 'php' });
  });
  return found;
}

// ---------------------------------------------------------------------------
// Constrói o índice.
// ---------------------------------------------------------------------------
function buildIndex() {
  const idx = [];
  for (const d of JS_DIRS) for (const f of walk(d, JS_EXT, null)) idx.push(...extractJs(f));
  for (const d of PHP_DIRS) for (const f of walk(d, PHP_EXT, PHP_SKIP)) idx.push(...extractPhp(f));
  return idx;
}

function sha() {
  try { return execSync('git rev-parse --short HEAD', { cwd: ROOT }).toString().trim(); }
  catch { return 'unknown'; }
}

// camelCase / PascalCase / snake → tokens minúsculos
function tokens(s) {
  return s.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/[_\-./]/g, ' ').toLowerCase().split(/\s+/).filter(Boolean);
}

// IDF: token que aparece em MUITOS símbolos (ex "service", "order", "form") vale pouco;
// token raro (ex "baixa", "moeda") vale muito → mata o ruído de nomes genéricos.
function buildIdf(index) {
  const df = new Map();
  const N = index.length;
  for (const it of index) {
    const seen = new Set([...tokens(it.name), ...tokens(it.file)]);
    for (const t of seen) df.set(t, (df.get(t) || 0) + 1);
  }
  return (t) => Math.log((N + 1) / ((df.get(t) || 0) + 1)); // ~0 p/ token onipresente, alto p/ raro
}

function score(query, item, idf) {
  // Match EXATO de nome ganha de tudo (responde "isso existe?" sem ambiguidade).
  if (item.name.toLowerCase() === query.toLowerCase().trim()) return 100;
  const q = tokens(query);
  const nameTokens = new Set(tokens(item.name));
  const hay = new Set([...nameTokens, ...tokens(item.file)]);
  let s = 0;
  for (const t of q) {
    const w = idf(t);
    if (nameTokens.has(t)) s += 4 * w;        // token bate no NOME
    else if (hay.has(t)) s += 2 * w;          // token bate no caminho
    else if ([...hay].some((h) => h.includes(t) || t.includes(h))) s += 0.5 * w;
  }
  return s;
}

// ---------------------------------------------------------------------------
// Modos
// ---------------------------------------------------------------------------
const args = process.argv.slice(2);
const idx = buildIndex();

if (args.includes('--json')) {
  process.stdout.write(JSON.stringify({ measured_against_sha: sha(), total: idx.length, symbols: idx }, null, 2));
  process.exit(0);
}

// Nomes de página são CONVENÇÃO do Inertia (toda tela tem Index/Create/Edit/Show) — não é
// duplicação de lógica. FILOSOFIA (Frente 2): a duplicação que QUEBRA é na camada JS compartilhada
// SEM namespace (Lib/Hooks/Components/utils) — dois `formatCurrency` ali é bug. No PHP, mesmo
// nome em módulos diferentes é NAMESPACED por design (DataController × módulo) → não é duplicação.
const PAGE_CONVENTION = /^(Index|Create|Edit|Show|Board|Form|List|Print|View|Detail|Settings)$/;
const RISKY_KINDS = new Set(['util', 'hook', 'const', 'function', 'component']);
const BASELINE_PATH = 'scripts/reuse-duplicates-baseline.json';

function computeDuplicates(index, showAll = false) {
  const interesting = (it) => showAll || (
    RISKY_KINDS.has(it.kind)
    && !(it.kind === 'component' && PAGE_CONVENTION.test(it.name) && it.file.replace(/\\/g, '/').includes('resources/js/Pages/'))
  );
  const byKey = new Map();
  for (const it of index) {
    if (!interesting(it)) continue;
    const key = `${it.kind}:${it.name}`;
    if (!byKey.has(key)) byKey.set(key, []);
    byKey.get(key).push(it);
  }
  return [...byKey.entries()].filter(([, v]) => v.length > 1); // [ [key, items[]], ... ]
}

if (args.includes('--duplicates')) {
  const dups = computeDuplicates(idx, args.includes('--all'));
  console.log(`# Duplicatas candidatas (mesmo nome+kind em 2+ arquivos) — sha ${sha()}`);
  console.log(`Total: ${dups.length}\n`);
  for (const [key, v] of dups.sort((a, b) => b[1].length - a[1].length)) {
    console.log(`${key}  (${v.length}×)`);
    for (const it of v) console.log(`   - ${it.file}:${it.line}`);
  }
  process.exit(0);
}

// --- F2: ratchet anti-duplicação ("teste pra não piorar") -------------------
// Baseline = conjunto de chaves duplicadas conhecidas HOJE. O gate falha só se nascer
// uma chave NOVA (duplicata inédita) — o legado existente é tolerado, igual eslint-baseline (ADR 0209).
if (args.includes('--write-baseline')) {
  const keys = computeDuplicates(idx).map(([k]) => k).sort();
  const payload = { measured_against_sha: sha(), generated_by: 'reuse-index.mjs --write-baseline', count: keys.length, keys };
  writeFileSync(join(ROOT, BASELINE_PATH), JSON.stringify(payload, null, 2) + '\n');
  console.log(`✓ baseline gravado: ${BASELINE_PATH} (${keys.length} duplicatas conhecidas · sha ${sha()})`);
  process.exit(0);
}

if (args.includes('--gate')) {
  let baseline;
  try { baseline = JSON.parse(readFileSync(join(ROOT, BASELINE_PATH), 'utf8')); }
  catch { console.error(`✗ baseline ausente (${BASELINE_PATH}). Rode: node scripts/reuse-index.mjs --write-baseline`); process.exit(2); }
  const known = new Set(baseline.keys || []);
  const current = computeDuplicates(idx);
  const novos = current.filter(([k]) => !known.has(k));
  if (novos.length === 0) {
    console.log(`✓ gate reuse:duplicates OK — nenhuma duplicata NOVA (baseline: ${known.size}).`);
    process.exit(0);
  }
  console.error(`✗ gate reuse:duplicates FALHOU — ${novos.length} duplicata(s) NOVA(s) (não estavam no baseline):\n`);
  for (const [key, v] of novos) {
    console.error(`  ${key}  (${v.length}×)`);
    for (const it of v) console.error(`     - ${it.file}:${it.line}`);
  }
  console.error(`\nReuse > recriar. Se a duplicata for legítima (convenção), rode --write-baseline conscientemente.`);
  process.exit(1);
}

const query = args.find((a) => !a.startsWith('--'));

if (!query) {
  const byKind = {};
  for (const it of idx) byKind[it.kind] = (byKind[it.kind] || 0) + 1;
  console.log(`# reuse-index — ${idx.length} símbolos · sha ${sha()}`);
  console.log('\nPor kind:');
  for (const [k, n] of Object.entries(byKind).sort((a, b) => b[1] - a[1])) console.log(`  ${k.padEnd(12)} ${n}`);
  console.log('\nUso: node scripts/reuse-index.mjs "<o que procura>"  ·  --json  ·  --duplicates');
  process.exit(0);
}

// BUSCA
const idf = buildIdf(idx);
const isIdentifier = /^[A-Za-z][A-Za-z0-9_]*$/.test(query.trim()); // "BaixaService" vs "formatar moeda"
const ranked = idx.map((it) => ({ it, s: score(query, it, idf) })).filter((r) => r.s > 0).sort((a, b) => b.s - a.s).slice(0, 8);
console.log(`# reuse:check "${query}" · sha ${sha()}\n`);
const exact = ranked.find((r) => r.s >= 100);
if (exact) {
  console.log(`✅ JÁ EXISTE — REUSA (não crie outro):\n   ${exact.it.name}  (${exact.it.kind})  →  ${exact.it.file}:${exact.it.line}`);
  process.exit(0);
}
if (isIdentifier) {
  console.log(`❌ NÃO existe símbolo chamado "${query}".`);
  if (ranked.length) console.log(`   Antes de criar, confira se um destes é o que você quer (reusar > recriar):\n`);
  else { console.log(`   Nada parecido também → pode criar (confirme o kind certo + onde mora).`); process.exit(0); }
} else if (ranked.length === 0) {
  console.log(`❌ Nenhum símbolo parecido → provavelmente não existe, pode criar.`);
  process.exit(0);
} else {
  console.log(`🟡 Candidatos pra reusar antes de criar:\n`);
}
for (const r of ranked) console.log(`   ${r.s.toFixed(1).padStart(5)}  ${r.it.name.padEnd(28)} ${r.it.kind.padEnd(11)} ${r.it.file}:${r.it.line}`);
process.exit(0);
