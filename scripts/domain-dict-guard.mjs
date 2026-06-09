#!/usr/bin/env node
// scripts/domain-dict-guard.mjs — Gate G-4 (dicionário de domínio) da Governança executável (ADR 0264).
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// A alucinação da "locação de caçamba" (ADR 0265) atravessou TODOS os gates porque
// nenhum cobria COERÊNCIA DE DOMÍNIO: um enum podia carregar `locacao` sem nada acusar.
// Este guard faz da fonte-única de domínio (memory/dominio/<modulo>.md) uma LEI: o
// vocabulário declarado lá tem que bater com os enum() reais das migrations do módulo.
//
//   Fonte única por módulo: memory/dominio/<modulo>.md, bloco ```json com { module, enums }.
//   enums = { "tabela.coluna": [valores canônicos], ... }.
//
// O guard deriva o ESTADO ATUAL de cada enum percorrendo as migrations do módulo
// (last-write-wins por tabela.coluna, só a região up()) e compara com o dicionário.
//
// VIOLAÇÕES (cada uma uma chave estável pro ratchet):
//   dominio:undeclared-value:<mod>:<tab.col>:<v>    valor no schema mas não no dicionário
//   dominio:stale-dict-value:<mod>:<tab.col>:<v>    valor no dicionário mas não no schema
//   dominio:undeclared-column:<mod>:<tab.col>       coluna enum no schema do módulo, fora do dicionário
//   dominio:missing-column-in-schema:<mod>:<tab.col> coluna no dicionário sem enum correspondente
//   dominio:module-no-dict:<mod>                     módulo TEM enum em migration mas NÃO tem dicionário
//
// =====================================================================================
// RATCHET / BASELINE — gêmeo de no-mock-in-prod.mjs / casos-coverage-guard.mjs
// =====================================================================================
//   node scripts/domain-dict-guard.mjs                  # valida vs baseline (exit 1 se piorou)
//   node scripts/domain-dict-guard.mjs --write-baseline  # (re)grava baseline
//   node scripts/domain-dict-guard.mjs --report          # relatório de dívida (humano)
//   node scripts/domain-dict-guard.mjs --json            # saída JSON pra CI
//
// O baseline (scripts/domain-dict-baseline.json) fotografa as divergências atuais (débito).
// Gate falha só em divergência NOVA (ratchet). Ex.: `order_type=locacao` entra no baseline
// agora; o PR de erradicação (ADR 0265) remove o enum e a divergência some sozinha.
//
// Refs: ADR 0264 (G-4) · ADR 0265 (erradica locação) · ADR 0261 (enforcement faseado) · ADR 0256 (catraca).

import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { resolve, join, relative } from 'node:path';

const ROOT = process.cwd();
const DOMINIO_DIR = resolve(ROOT, 'memory/dominio');
const MODULES_DIR = resolve(ROOT, 'Modules');
const BASELINE_PATH = resolve(ROOT, 'scripts/domain-dict-baseline.json');

const MODE_WRITE = process.argv.includes('--write-baseline');
const MODE_REPORT = process.argv.includes('--report');
const MODE_JSON = process.argv.includes('--json');

const norm = (p) => relative(ROOT, p).replace(/\\/g, '/');

function walk(dir, filter, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, e.name);
    if (e.isDirectory()) {
      if (e.name === 'node_modules' || e.name === 'vendor' || e.name === '.git') continue;
      walk(full, filter, acc);
    } else if (e.isFile() && filter(full, e.name)) {
      acc.push(full);
    }
  }
  return acc;
}

// ---------------------------------------------------------------------------
// Dicionários de domínio (fonte única)
// ---------------------------------------------------------------------------
function loadDicts() {
  // map: moduleName -> { enums: {tab.col: [vals]}, file }
  const dicts = {};
  if (!existsSync(DOMINIO_DIR)) return dicts;
  for (const e of readdirSync(DOMINIO_DIR, { withFileTypes: true })) {
    if (!e.isFile() || !e.name.endsWith('.md')) continue;
    const content = readFileSync(join(DOMINIO_DIR, e.name), 'utf8');
    const m = content.match(/```json\s*([\s\S]*?)```/);
    if (!m) continue;
    let parsed;
    try { parsed = JSON.parse(m[1]); } catch { continue; }
    if (!parsed?.module || !parsed?.enums) continue;
    dicts[parsed.module] = { enums: parsed.enums, file: `memory/dominio/${e.name}` };
  }
  return dicts;
}

// ---------------------------------------------------------------------------
// Estado atual dos enums por migration (last-write-wins, up() only)
// ---------------------------------------------------------------------------
const parseEnumValues = (raw) =>
  [...raw.matchAll(/['"]([a-zA-Z0-9_]+)['"]/g)].map((x) => x[1]);

// Extrai definições de enum de uma região de texto (já só up()).
// Retorna [{ index, table, col, values }] na ordem do texto.
function extractEnumDefs(upRegion) {
  const defs = [];

  // Marcadores de tabela (Schema::create/table) com posição.
  const tableMarkers = [];
  for (const m of upRegion.matchAll(/Schema::(?:create|table)\(\s*['"]([a-z0-9_]+)['"]/g)) {
    tableMarkers.push({ index: m.index, table: m[1] });
  }
  const tableAt = (idx) => {
    let t = null;
    for (const mk of tableMarkers) { if (mk.index < idx) t = mk.table; else break; }
    return t;
  };

  // (a) ->enum('col', [ ... ])  — tabela vem do Schema::create/table mais próximo antes.
  for (const m of upRegion.matchAll(/->enum\(\s*['"]([a-z0-9_]+)['"]\s*,\s*\[([\s\S]*?)\]/g)) {
    const col = m[1];
    const values = parseEnumValues(m[2]);
    const table = tableAt(m.index);
    if (table && values.length) defs.push({ index: m.index, table, col, values });
  }

  // (b) ALTER TABLE <t> ... MODIFY [COLUMN] <col> ENUM( ... )  — tabela+coluna explícitas.
  for (const m of upRegion.matchAll(/ALTER\s+TABLE\s+([a-z0-9_]+)[\s\S]*?MODIFY\s+(?:COLUMN\s+)?([a-z0-9_]+)\s+ENUM\(([\s\S]*?)\)/gi)) {
    const table = m[1];
    const col = m[2];
    const values = parseEnumValues(m[3]);
    if (values.length) defs.push({ index: m.index, table, col, values });
  }

  return defs.sort((a, b) => a.index - b.index);
}

// Para um módulo: percorre migrations sorted, last-write-wins por tab.col.
// Retorna map: "tab.col" -> [valores atuais].
function currentEnums(moduleName) {
  const dir = join(MODULES_DIR, moduleName, 'Database', 'Migrations');
  const files = walk(dir, (full, name) => name.endsWith('.php')).sort((a, b) =>
    a.localeCompare(b),
  );
  const state = {};
  for (const file of files) {
    const content = readFileSync(file, 'utf8');
    const upRegion = content.split(/function\s+down\s*\(/)[0];
    // Cola literais de string concatenados em PHP ("ALTER ... MODIFY col " . "ENUM(...)")
    // pra o ALTER...ENUM voltar a ser uma sequência contígua antes do regex.
    const glued = upRegion.replace(/['"]\s*\.\s*['"]/g, '');
    for (const def of extractEnumDefs(glued)) {
      state[`${def.table}.${def.col}`] = def.values; // last write wins
    }
  }
  return state;
}

// Conjunto de módulos que têm migrations com enum (pra detectar module-no-dict).
function modulesWithEnums() {
  const out = new Set();
  if (!existsSync(MODULES_DIR)) return out;
  for (const e of readdirSync(MODULES_DIR, { withFileTypes: true })) {
    if (!e.isDirectory()) continue;
    const migDir = join(MODULES_DIR, e.name, 'Database', 'Migrations');
    const files = walk(migDir, (full, name) => name.endsWith('.php'));
    for (const f of files) {
      const c = readFileSync(f, 'utf8');
      if (/->enum\(|ENUM\(/i.test(c)) { out.add(e.name); break; }
    }
  }
  return out;
}

// ---------------------------------------------------------------------------
// Cálculo de violações
// ---------------------------------------------------------------------------
function computeViolations() {
  const dicts = loadDicts();
  const withEnums = modulesWithEnums();
  const violations = [];
  const stats = { modules_with_dict: Object.keys(dicts).length, modules_with_enums: withEnums.size, divergences: 0, modules_no_dict: 0 };

  // Módulos com enum mas sem dicionário → débito (ratchet pra F3 cobrir todos).
  for (const mod of withEnums) {
    if (!dicts[mod]) { violations.push(`dominio:module-no-dict:${mod}`); stats.modules_no_dict++; }
  }

  // Módulos COM dicionário → comparação valor-a-valor.
  for (const [mod, dict] of Object.entries(dicts)) {
    const actual = currentEnums(mod);
    const declaredCols = new Set(Object.keys(dict.enums));
    const actualCols = new Set(Object.keys(actual));

    // Coluna enum no schema do módulo, fora do dicionário.
    for (const col of actualCols) {
      if (!declaredCols.has(col)) { violations.push(`dominio:undeclared-column:${mod}:${col}`); stats.divergences++; }
    }

    for (const [col, canonicalVals] of Object.entries(dict.enums)) {
      const actualVals = actual[col];
      if (!actualVals) { violations.push(`dominio:missing-column-in-schema:${mod}:${col}`); stats.divergences++; continue; }
      const canon = new Set(canonicalVals);
      const real = new Set(actualVals);
      for (const v of real) if (!canon.has(v)) { violations.push(`dominio:undeclared-value:${mod}:${col}:${v}`); stats.divergences++; }
      for (const v of canon) if (!real.has(v)) { violations.push(`dominio:stale-dict-value:${mod}:${col}:${v}`); stats.divergences++; }
    }
  }

  return { violations: violations.sort((a, b) => a.localeCompare(b)), stats };
}

function loadBaseline() {
  if (!existsSync(BASELINE_PATH)) return null;
  return JSON.parse(readFileSync(BASELINE_PATH, 'utf8'));
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
function main() {
  const { violations, stats } = computeViolations();

  if (MODE_JSON) {
    const baseline = loadBaseline();
    const baseSet = new Set(baseline?.violations || []);
    const novos = violations.filter((v) => !baseSet.has(v));
    console.log(JSON.stringify({ stats, total: violations.length, baseline: baseSet.size, novos, ok: novos.length === 0 }, null, 2));
    process.exit(novos.length === 0 ? 0 : 1);
  }

  if (MODE_REPORT) {
    console.log('# Relatório de dívida — dominio:check (ADR 0264 G-4)\n');
    console.log(`Módulos com dicionário: ${stats.modules_with_dict} · com enum: ${stats.modules_with_enums}`);
    console.log(`Módulos com enum SEM dicionário: ${stats.modules_no_dict}`);
    console.log(`Divergências enum⇔dicionário (nos módulos com dict): ${stats.divergences}`);
    console.log(`\nTOTAL de violações (débito): ${violations.length}`);
    if (violations.length) { console.log('\nDetalhe:'); for (const v of violations) console.log('  · ' + v); }
    console.log('\n→ F1 fotografa no baseline (não-bloqueante). PR erradicação (ADR 0265) zera `order_type=locacao`.');
    process.exit(0);
  }

  if (MODE_WRITE) {
    const out = {
      _meta: {
        generated_at: new Date().toISOString(),
        gate: 'dominio:check (ADR 0264 G-4 — dicionário de domínio ⇔ enum de migration)',
        stats,
        nota: 'Divergências ATUAIS fotografadas (débito). Gate falha só em divergência NOVA (ratchet). order_type=locacao zera quando o PR de erradicação (ADR 0265) baixar o enum.',
        refs: ['ADR 0264', 'ADR 0265', 'ADR 0261', 'ADR 0256'],
      },
      violations,
    };
    writeFileSync(BASELINE_PATH, JSON.stringify(out, null, 2) + '\n');
    console.log(`✅ Baseline gravado: ${violations.length} violações (${stats.divergences} divergência · ${stats.modules_no_dict} módulo sem dict) → ${norm(BASELINE_PATH)}`);
    process.exit(0);
  }

  // VALIDATE
  console.log(`dominio:check · ${violations.length} violações (dicts: ${stats.modules_with_dict}, divergências: ${stats.divergences})`);
  const baseline = loadBaseline();
  if (!baseline) {
    console.error(`\n❌ Baseline ausente (${norm(BASELINE_PATH)}). Rode: npm run dominio:baseline:write`);
    process.exit(1);
  }
  const baseSet = new Set(baseline.violations || []);
  const novos = violations.filter((v) => !baseSet.has(v));

  if (novos.length) {
    console.error(`\n❌ ${novos.length} divergência(s) NOVA(s) de domínio (não no baseline):\n`);
    for (const v of novos) console.error('  🆕 ' + v);
    console.error(
      `\nUm enum de migration divergiu do dicionário do módulo (memory/dominio/<mod>.md) — ADR 0264 G-4.` +
        `\nReintroduzir \`locacao\`/\`locada\` viola a ADR 0265 (ver memory/proibicoes.md).` +
        `\nSe a mudança de domínio for legítima: atualize o dicionário + npm run dominio:baseline:write`,
    );
    process.exit(1);
  }

  const delta = (baseline.violations?.length || 0) - violations.length;
  console.log(`✅ Sem divergências novas (débito ${delta > 0 ? `caiu −${delta}` : 'estável'} vs baseline).`);
  process.exit(0);
}

main();
