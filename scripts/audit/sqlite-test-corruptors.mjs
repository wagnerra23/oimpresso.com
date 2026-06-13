#!/usr/bin/env node
// sqlite-test-corruptors.mjs — auditor READ-ONLY de testes que corrompem o
// estado SQLite :memory: compartilhado (lever do "floor" SDD F2b).
//
// Motivação (Wagner 2026-06-13 "a corrupção sqlite já gastou muito recurso"):
// a suíte roda contra SQLite :memory: (phpunit.xml DB_CONNECTION=sqlite). Testes
// que constroem SCHEMA SINTÉTICO MANUAL no beforeEach (Schema::create/drop de
// tabelas compartilhadas — users/contacts/channels/transactions) deixam o schema
// num estado que NÃO é o das migrations reais. Quando outro teste roda depois na
// MESMA conexão, encontra a tabela mutilada → falha em cascata. São re-descobertos
// um a um em ~19 worktrees `era-sqlite`. Este script ACHA todos de uma vez,
// rankeados por raio de impacto, SEM rodar a suíte (custo zero de CI).
//
// NÃO corrige nada. Só relatório priorizado. A correção (converter pra
// RefreshDatabase / migrations reais, ou quarentenar com markTestSkipped
// não-sqlite) é decisão humana — burn-down SDD.
//
// Heurística de risco (transparente, ajustável no topo):
//   +50  não-quarentenado (sem marcador `era-sqlite`)   → ainda no caminho quente
//   +30  por tabela de ALTO raio dropada/criada manualmente (cap 90)
//   +25  dropAllTables (nuke geral do schema)
//   +20  mutação de CONEXÃO (DB::disconnect/purge/reconnect, PRAGMA, config DB,
//        disableForeignKeyConstraints) — vaza além do schema
//   +15  faz writes/DDL SEM trait de isolamento (RefreshDatabase/Transactions)
//   +10  DDL manual presente (Schema::create/drop, DB::statement DDL)
//   -25  já quarentenado (`era-sqlite`) → conhecido + skip no MySQL nightly
//
// Buckets: S(>=80) crítico · A(>=50) alto · B(>=25) médio · C(<25) baixo.
//
// Uso:
//   node scripts/audit/sqlite-test-corruptors.mjs                 (top 30, tabela)
//   node scripts/audit/sqlite-test-corruptors.mjs --top=50
//   node scripts/audit/sqlite-test-corruptors.mjs --tier=S        (só críticos)
//   node scripts/audit/sqlite-test-corruptors.mjs --json          (saída máquina)
//   node scripts/audit/sqlite-test-corruptors.mjs --strict --tier=S  (exit 1 se houver S)

import { readFileSync, readdirSync, statSync } from 'node:fs';
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
const RE_DROP_ALL = /Schema::dropAllTables\(/;
const RE_RAW_DDL = /DB::(statement|unprepared)\(\s*['"`][^'"`]*\b(create|drop|alter)\s+table\b/i;
const RE_PRAGMA = /PRAGMA\s+\w+/i;
const RE_CONN_MUT = /DB::(disconnect|purge|reconnect)\(|disableForeignKeyConstraints\(|->setConnection\(|DB::setDefaultConnection\(|config\(\s*\[?\s*['"]database/;

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

function analyze(file) {
  let src;
  try {
    src = readFileSync(file, 'utf8');
  } catch {
    return null;
  }

  const quarantined = QUARANTINE.test(src);
  const hasIsolation = ISOLATION_TRAITS.test(src);
  const dropsAll = RE_DROP_ALL.test(src);
  const rawDdl = RE_RAW_DDL.test(src) || RE_PRAGMA.test(src);
  const connMut = RE_CONN_MUT.test(src);
  const hasWrites = WRITE_HINTS.test(src);

  const tables = new Set();
  let m;
  RE_SCHEMA_TABLE.lastIndex = 0;
  while ((m = RE_SCHEMA_TABLE.exec(src)) !== null) tables.add(m[2]);
  const highBlast = [...tables].filter((t) => HIGH_BLAST.has(t));
  const manualDdl = tables.size > 0 || rawDdl || dropsAll;

  // Só interessa quem efetivamente mexe em schema/conexão (corruptor real).
  if (!manualDdl && !connMut) return null;

  const reasons = [];
  let score = 0;

  if (!quarantined) { score += 50; reasons.push('não-quarentenado'); }
  else { score -= 25; reasons.push('quarentenado(era-sqlite)'); }

  if (highBlast.length) {
    const pts = Math.min(highBlast.length * 30, 90);
    score += pts;
    reasons.push(`alto-raio[${highBlast.join(',')}]`);
  }
  if (dropsAll) { score += 25; reasons.push('dropAllTables'); }
  if (connMut) { score += 20; reasons.push('mutação-conexão'); }
  if (manualDdl && hasWrites && !hasIsolation) { score += 15; reasons.push('writes-sem-isolamento'); }
  if (manualDdl) { score += 10; reasons.push('DDL-manual'); }

  let tier = 'C';
  if (score >= 80) tier = 'S';
  else if (score >= 50) tier = 'A';
  else if (score >= 25) tier = 'B';

  return {
    file: relative(ROOT, file).split(sep).join('/'),
    score,
    tier,
    quarantined,
    hasIsolation,
    tables: [...tables],
    highBlast,
    reasons,
  };
}

const files = [];
for (const d of SCAN_DIRS) walk(join(ROOT, d), files);

let results = files.map(analyze).filter(Boolean).sort((a, b) => b.score - a.score);

const counts = { S: 0, A: 0, B: 0, C: 0 };
for (const r of results) counts[r.tier]++;

if (TIER_FILTER) results = results.filter((r) => r.tier === TIER_FILTER);

if (AS_JSON) {
  console.log(JSON.stringify({ scanned: files.length, corruptors: results.length, counts, results }, null, 2));
} else {
  console.log('== Auditor SQLite test-corruptors (read-only) ==');
  console.log(`Test files escaneados : ${files.length}`);
  console.log(`Corruptores potenciais: ${results.length}`);
  console.log(`Por bucket            : S=${counts.S} (crítico)  A=${counts.A} (alto)  B=${counts.B} (médio)  C=${counts.C} (baixo)`);
  console.log('');
  console.log('Bucket = raio de cascata. S/A = converter pra RefreshDatabase OU quarentenar primeiro.');
  console.log('');
  const show = results.slice(0, TOP);
  for (const r of show) {
    const q = r.quarantined ? ' (já quarentenado)' : '';
    console.log(`[${r.tier}] ${String(r.score).padStart(3)}  ${r.file}${q}`);
    console.log(`        ${r.reasons.join(' · ')}`);
  }
  if (results.length > show.length) {
    console.log('');
    console.log(`… +${results.length - show.length} (use --top=${results.length} ou --json pra ver todos).`);
  }
}

if (STRICT && results.length > 0) process.exit(1);
