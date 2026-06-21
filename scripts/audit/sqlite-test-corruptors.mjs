#!/usr/bin/env node
// sqlite-test-corruptors.mjs — auditor READ-ONLY de testes que corrompem o
// schema MySQL persistente do nightly (lever do "floor" SDD F2b).
//
// Motivação (Wagner 2026-06-13 "a corrupção sqlite já gastou muito recurso"):
// a suíte nightly roda contra um MySQL COMPARTILHADO/PERSISTENTE. Testes que
// fazem DDL MANUAL (Schema::create/drop de tabelas compartilhadas — business/
// users/contacts/mcp_*/rb_*) DROPAM tabelas reais → o próximo teste na mesma
// conexão acha tabela ausente → cascata "Base table not found". Este script
// ACHA os corruptores SEM rodar a suíte (custo zero de CI).
//
// ===========================================================================
// HONESTIDADE (2026-06-15) — a triagem refutada da cauda longa (ADR 0276)
// provou que a v1 tinha ~48% de FALSO-POSITIVO: text-match de `Schema::` que
// ignorava (a) guarda `markTestSkipped`+driver no beforeEach, (b) DDL dentro de
// `expect()->toContain('Schema::...')` (source-reader), (c) `DB::purge` (não-DDL).
// A v2 classifica por COMPORTAMENTO-NO-MYSQL, não por text-match:
//   • só conta DDL EM CÓDIGO (fora de strings/comentários) → mata source-readers;
//   • exige DROP real pra corromper (create idempotente/erro-próprio não cascateia);
//   • beforeEach guardado (skip no MySQL) ⇒ setup/corpo NÃO rodam no MySQL;
//   • MAS afterEach/tearDown SEM guarda AINDA corrompe (PHPUnit 12 roda teardown
//     em teste pulado) — o caso Governance. Reconhecer a guarda NÃO pode
//     sub-contar esse caso. Contrato travado em tests/sqliteCorruptors.spec.ts.
// Campo-chave: `corruptsOnMysql` (a verdade). `!corruptsOnMysql` = seguro/handled.
// ===========================================================================
//
// NÃO corrige nada. Só relatório priorizado. A correção (converter pra
// RefreshDatabase/DatabaseTransactions, OU guardar o teardown, OU quarentenar
// com markTestSkipped não-sqlite) é decisão humana — burn-down SDD.
//
// Heurística de risco (transparente, ajustável no topo):
//   +50  corrompe no MySQL (drop não-guardado que roda no nightly)
//   +30  por tabela de ALTO raio dropada/criada manualmente (cap 90)
//   +25  dropAllTables (nuke geral do schema)
//   +15  faz writes/DDL SEM trait de isolamento (RefreshDatabase/Transactions)
//   +10  DDL manual presente (Schema::create/drop, DB::statement DDL)
//   -25  NÃO corrompe no MySQL (guardado dos 2 lados, ou já era-sqlite)
//
// Buckets (entre os que corrompem): S(>=80) crítico · A(>=50) alto · B(>=25) médio · C(<25).
//
// Uso:
//   node scripts/audit/sqlite-test-corruptors.mjs                 (top 30, tabela)
//   node scripts/audit/sqlite-test-corruptors.mjs --top=50
//   node scripts/audit/sqlite-test-corruptors.mjs --tier=S        (só críticos)
//   node scripts/audit/sqlite-test-corruptors.mjs --json          (saída máquina)
//   node scripts/audit/sqlite-test-corruptors.mjs --strict --tier=S  (exit 1 se houver S real)

import { readFileSync, readdirSync } from 'node:fs';
import { join, relative, sep } from 'node:path';

const ROOT = process.cwd();
const SCAN_DIRS = ['Modules', 'tests'];

// Tabelas de ALTO raio — muitos testes dependem delas; dropá-las/recriá-las
// manualmente corrompe o schema pra todo mundo que roda depois na mesma conexão.
const HIGH_BLAST = new Set([
  'users', 'contacts', 'channels', 'transactions', 'business', 'businesses',
  'products', 'variations', 'roles', 'permissions',
  'model_has_permissions', 'model_has_roles', 'role_has_permissions',
  'activity_log', 'transaction_payments', 'transaction_sell_lines',
]);

const ISOLATION_TRAITS = /\b(RefreshDatabase|LazilyRefreshDatabase|DatabaseTransactions|DatabaseMigrations)\b/;
const WRITE_HINTS = /->\s*(create|insert|save|update|delete)\s*\(|::create\s*\(|factory\s*\(|DB::table\([^)]*\)->\s*insert/;
const QUARANTINE = /era-sqlite/;

const RE_SCHEMA_TABLE = /Schema::(create|dropIfExists|drop)\(\s*['"]([a-z0-9_]+)['"]/g;
// Qualquer DROP — inclui nome via VARIÁVEL/loop (`Schema::dropIfExists($tbl)`), que o
// regex de literal acima não pega. É a forma que o RE_SCHEMA_TABLE perde (sub-contagem).
const RE_DROP_ANY = /Schema::drop(?:IfExists)?\(/g;
const RE_DROP_ALL = /Schema::dropAllTables\(/g;
const RE_RAW_DDL = /DB::(?:statement|unprepared)\(\s*['"`][^'"`]*\b(?:create|drop|alter)\s+table\b/gi;
const RE_RAW_DROP = /DB::(?:statement|unprepared)\(\s*['"`][^'"`]*\b(?:drop|alter)\s+table\b/gi;
const RE_CONN_MUT = /DB::(?:disconnect|purge|reconnect)\(|disableForeignKeyConstraints\(|->setConnection\(|DB::setDefaultConnection\(/g;

// Sinaliza que um trecho pula/abortara no MySQL (guarda de driver sqlite).
const GUARD_TOKEN = /database\.default|getDriverName|:memory:|isSqlite|SqliteMemory|sqliteMemory/i;
const SKIP_CALL = /markTestSkipped/;

const args = process.argv.slice(2);
const opt = (name, def) => {
  const hit = args.find((a) => a.startsWith(`--${name}=`));
  return hit ? hit.split('=')[1] : def;
};
const flag = (name) => args.includes(`--${name}`);

const TOP = parseInt(opt('top', '30'), 10);
const TIER_FILTER = (opt('tier', '') || '').toUpperCase();
const AS_JSON = flag('json');
const STRICT = flag('strict');

function walk(dir, acc) {
  let entries;
  try {
    entries = readdirSync(dir, { withFileTypes: true });
  } catch {
    return acc;
  }
  for (const e of entries) {
    const full = join(dir, e.name);
    if (e.isDirectory()) {
      if (e.name === 'vendor' || e.name === 'node_modules' || e.name === '.git') continue;
      walk(full, acc);
    } else if (e.isFile() && e.name.endsWith('Test.php')) {
      acc.push(full);
    }
  }
  return acc;
}

// --- Detecção de regiões NÃO-CÓDIGO (strings PHP ' e " + comentários // # /* */) -----------
// Um `Schema::create(...)` dentro de uma string (source-reader, toContain) ou comentário
// NÃO executa DDL. Calculamos os spans pra ignorar matches que caem dentro deles.
function nonCodeSpans(src) {
  const spans = [];
  let i = 0;
  const n = src.length;
  while (i < n) {
    const c = src[i];
    if (c === "'" || c === '"') {
      const quote = c;
      const start = i;
      i++;
      while (i < n) {
        if (src[i] === '\\') { i += 2; continue; }
        if (src[i] === quote) { i++; break; }
        i++;
      }
      spans.push([start, i]);
    } else if (c === '/' && src[i + 1] === '/') {
      const start = i;
      while (i < n && src[i] !== '\n') i++;
      spans.push([start, i]);
    } else if (c === '#') {
      const start = i;
      while (i < n && src[i] !== '\n') i++;
      spans.push([start, i]);
    } else if (c === '/' && src[i + 1] === '*') {
      const start = i;
      i += 2;
      while (i < n && !(src[i] === '*' && src[i + 1] === '/')) i++;
      i += 2;
      spans.push([start, Math.min(i, n)]);
    } else {
      i++;
    }
  }
  return spans;
}

function inNonCode(idx, spans) {
  for (const [s, e] of spans) {
    if (idx < s) break;
    if (idx >= s && idx < e) return true;
  }
  return false;
}

function codeMatches(re, src, spans) {
  const out = [];
  re.lastIndex = 0;
  let m;
  while ((m = re.exec(src)) !== null) {
    if (!inNonCode(m.index, spans)) out.push(m);
  }
  return out;
}

// Extrai [start,end) do bloco de um hook Pest (`kw(function () {...}`) ou método de
// classe (`function kw(...): void {...}`). Brace-match simples.
function blockRange(src, kw) {
  let anchor = src.indexOf(`${kw}(function`);
  if (anchor < 0) {
    const m = new RegExp(`function\\s+${kw}\\b`).exec(src);
    anchor = m ? m.index : -1;
  }
  if (anchor < 0) return null;
  const open = src.indexOf('{', anchor);
  if (open < 0) return null;
  let depth = 0;
  for (let j = open; j < src.length; j++) {
    if (src[j] === '{') depth++;
    else if (src[j] === '}') {
      depth--;
      if (depth === 0) return [anchor, j + 1];
    }
  }
  return [anchor, src.length];
}

// Condição de `if (...)` que é VERDADEIRA só no sqlite (dual-mode `if(sqlite){drop}else{...}`).
// POLARIDADE IMPORTA: `=== 'sqlite'` / `:memory:` = positiva (DDL gated, não roda no MySQL);
// `!== 'sqlite'` = roda no MySQL → NÃO é positiva (continua contando). Sem polaridade o
// linter sub-contaria `if(!==sqlite){drop}` — travado no meta-teste.
function isSqlitePositiveCond(cond) {
  const c = cond.trim();
  // negativa explícita → o bloco RODA no MySQL (não é gate sqlite)
  if (/!==?\s*['"]sqlite['"]|['"]sqlite['"]\s*!==?/.test(c)) return false;
  if (/^!\s*\$?\w*(?:isSqlite|sqliteMemory)\w*$/i.test(c)) return false; // `! $isSqliteMemory`
  // positiva (literal driver/:memory: OU variável-flag tipo `$isSqliteMemory`)
  if (/===?\s*['"]sqlite['"]|['"]sqlite['"]\s*===?|getDriverName\(\)\s*===?\s*['"]sqlite|:memory:/.test(c)) return true;
  if (/^\$?\w*(?:isSqlite|sqliteMemory)\w*$/i.test(c)) return true; // `$isSqliteMemory`
  return false;
}

// Ranges [start,end) de blocos `if (<cond sqlite-positiva>) { ... }` em CÓDIGO. DDL aqui
// dentro só executa no sqlite → não corrompe o MySQL persistente do nightly.
function sqlitePositiveIfRanges(src, spans) {
  const ranges = [];
  const re = /\bif\s*\(/g;
  let m;
  while ((m = re.exec(src)) !== null) {
    if (inNonCode(m.index, spans)) continue;
    const parenStart = m.index + m[0].length - 1; // posição do '('
    let depth = 0;
    let condEnd = -1;
    for (let j = parenStart; j < src.length; j++) {
      if (src[j] === '(') depth++;
      else if (src[j] === ')') { depth--; if (depth === 0) { condEnd = j; break; } }
    }
    if (condEnd < 0) continue;
    if (!isSqlitePositiveCond(src.slice(parenStart + 1, condEnd))) continue;
    const open = src.indexOf('{', condEnd);
    if (open < 0 || !/^\s*$/.test(src.slice(condEnd + 1, open))) continue; // `{` logo após a condição
    let bd = 0;
    let blockEnd = -1;
    for (let j = open; j < src.length; j++) {
      if (src[j] === '{') bd++;
      else if (src[j] === '}') { bd--; if (bd === 0) { blockEnd = j + 1; break; } }
    }
    if (blockEnd < 0) continue;
    ranges.push([m.index, blockEnd]);
  }
  return ranges;
}

// Classificador puro (testável) — recebe o source + caminho relativo.
// Retorna null quando o arquivo NÃO faz DDL manual em código (não é corruptor).
export function classifySource(src, rel = '') {
  const spans = nonCodeSpans(src);

  const tableMatches = codeMatches(RE_SCHEMA_TABLE, src, spans);
  const tables = new Set(tableMatches.map((m) => m[2]));
  const dropAnyMatches = codeMatches(RE_DROP_ANY, src, spans);
  const dropAllMatches = codeMatches(RE_DROP_ALL, src, spans);
  const rawDdlMatches = codeMatches(RE_RAW_DDL, src, spans);
  const rawDropMatches = codeMatches(RE_RAW_DROP, src, spans);

  const manualDdl = tables.size > 0 || dropAnyMatches.length > 0
    || dropAllMatches.length > 0 || rawDdlMatches.length > 0;
  // Só interessa quem faz DDL MANUAL em código. DDL em string (source-reader) ou
  // só `DB::purge` (gate de browser) NÃO corrompe schema → fora.
  if (!manualDdl) return null;

  // Blocos de setup/teardown (Pest hooks ou métodos de classe).
  const setupRanges = ['beforeEach', 'setUp'].map((k) => blockRange(src, k)).filter(Boolean);
  const teardownRanges = ['afterEach', 'tearDown'].map((k) => blockRange(src, k)).filter(Boolean);
  const inRanges = (idx, ranges) => ranges.find(([s, e]) => idx >= s && idx < e);
  const setupText = setupRanges.map(([s, e]) => src.slice(s, e)).join('\n');

  // beforeEach/setUp guardado = aborta no MySQL antes do DDL destrutivo.
  const guardedSetup = SKIP_CALL.test(setupText) && GUARD_TOKEN.test(setupText);

  // Todos os DROPS em código (Schema::drop/dropIfExists + dropAllTables + raw DROP/ALTER).
  const dropIdxs = [
    ...dropAnyMatches.map((m) => m.index),   // literal E variável (Schema::dropIfExists($tbl))
    ...dropAllMatches.map((m) => m.index),
    ...rawDropMatches.map((m) => m.index),
  ];

  // Dual-mode: DDL dentro de `if (sqlite-positiva) { ... }` só roda no sqlite → não corrompe.
  const sqliteIfRanges = sqlitePositiveIfRanges(src, spans);
  const inSqliteIf = (idx) => sqliteIfRanges.some(([s, e]) => idx >= s && idx < e);

  let teardownUnguardedDrop = false;
  let setupOrBodyDrop = false;
  for (const idx of dropIdxs) {
    if (inSqliteIf(idx)) continue; // dual-mode — drop só executa no sqlite
    const td = inRanges(idx, teardownRanges);
    if (td) {
      const txt = src.slice(td[0], td[1]);
      // teardown guardado = early-return em não-sqlite (`if(!==sqlite){return}`) OU markTestSkipped.
      const tdGuarded = /(!==?\s*['"]sqlite['"]|['"]sqlite['"]\s*!==?|!\s*\$?\w*(?:isSqlite|sqliteMemory)\w*)[\s\S]{0,40}\breturn\b/i.test(txt) || SKIP_CALL.test(txt);
      if (!tdGuarded) teardownUnguardedDrop = true;
    } else {
      // está no setup ou no corpo do teste; só roda no MySQL se o setup NÃO guarda.
      setupOrBodyDrop = true;
    }
  }

  // A corrupção exige um DROP que EFETIVAMENTE roda no MySQL.
  const corruptsOnMysql = (setupOrBodyDrop && !guardedSetup) || teardownUnguardedDrop;

  const literalQuarantine = QUARANTINE.test(src);
  const hasIsolation = ISOLATION_TRAITS.test(src);
  const hasWrites = WRITE_HINTS.test(src);
  const connMut = codeMatches(RE_CONN_MUT, src, spans).length > 0;
  const highBlast = [...tables].filter((t) => HIGH_BLAST.has(t));

  const reasons = [];
  let score = 0;

  if (corruptsOnMysql) {
    score += 50;
    reasons.push('corrompe-no-mysql');
  } else {
    score -= 25;
    reasons.push(literalQuarantine ? 'quarentenado(era-sqlite)' : 'guardado(skip-no-mysql)');
  }

  if (corruptsOnMysql && highBlast.length) {
    score += Math.min(highBlast.length * 30, 90);
    reasons.push(`alto-raio[${highBlast.join(',')}]`);
  }
  if (dropAllMatches.length) { score += 25; reasons.push('dropAllTables'); }
  if (teardownUnguardedDrop) reasons.push('teardown-sem-guarda');
  if (guardedSetup) reasons.push('beforeEach-guardado');
  if (connMut) reasons.push('mutação-conexão');
  if (corruptsOnMysql && hasWrites && !hasIsolation) { score += 15; reasons.push('writes-sem-isolamento'); }
  if (manualDdl) { score += 10; reasons.push('DDL-manual'); }

  let tier = 'C';
  if (score >= 80) tier = 'S';
  else if (score >= 50) tier = 'A';
  else if (score >= 25) tier = 'B';

  return {
    file: rel,
    score,
    tier,
    corruptsOnMysql,
    quarantined: literalQuarantine,
    effectivelyGuarded: manualDdl && !corruptsOnMysql,
    safe: !corruptsOnMysql,
    hasIsolation,
    tables: [...tables],
    highBlast,
    reasons,
  };
}

function analyze(file) {
  let src;
  try {
    src = readFileSync(file, 'utf8');
  } catch {
    return null;
  }
  return classifySource(src, relative(ROOT, file).split(sep).join('/'));
}

function main() {
  const files = [];
  for (const d of SCAN_DIRS) walk(join(ROOT, d), files);

  let results = files.map(analyze).filter(Boolean).sort((a, b) => b.score - a.score);

  const real = results.filter((r) => r.corruptsOnMysql);
  const safeCount = results.length - real.length;
  const counts = { S: 0, A: 0, B: 0, C: 0 };
  for (const r of real) counts[r.tier]++;

  let view = TIER_FILTER ? real.filter((r) => r.tier === TIER_FILTER) : real;

  if (AS_JSON) {
    console.log(JSON.stringify({
      scanned: files.length,
      withManualDdl: results.length,
      corruptors: real.length,
      effectivelyGuarded: safeCount,
      counts,
      results,
    }, null, 2));
  } else {
    console.log('== Auditor SQLite test-corruptors (read-only · v2 comportamento-no-MySQL) ==');
    console.log(`Test files escaneados : ${files.length}`);
    console.log(`Com DDL manual        : ${results.length}`);
    console.log(`Corruptores REAIS     : ${real.length}  (corrompem o MySQL persistente)`);
    console.log(`Guardados/seguros     : ${safeCount}  (DDL manual, mas não roda destrutivo no MySQL)`);
    console.log(`Por bucket (reais)    : S=${counts.S} (crítico)  A=${counts.A} (alto)  B=${counts.B} (médio)  C=${counts.C} (baixo)`);
    console.log('');
    console.log('Bucket = raio de cascata. S/A = converter pra RefreshDatabase OU guardar teardown OU quarentenar.');
    console.log('');
    const show = view.slice(0, TOP);
    for (const r of show) {
      console.log(`[${r.tier}] ${String(r.score).padStart(3)}  ${r.file}`);
      console.log(`        ${r.reasons.join(' · ')}`);
    }
    if (view.length > show.length) {
      console.log('');
      console.log(`… +${view.length - show.length} (use --top=${view.length} ou --json pra ver todos).`);
    }
  }

  if (STRICT && view.length > 0) process.exit(1);
}

// Só roda o CLI quando invocado direto (não quando importado pelo meta-teste vitest).
if (process.argv[1] && process.argv[1].endsWith('sqlite-test-corruptors.mjs')) {
  main();
}
