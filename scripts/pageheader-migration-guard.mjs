#!/usr/bin/env node
// scripts/pageheader-migration-guard.mjs — F4 do roadmap de convergência UI (MANUAL-CSS-JS.md §5)
//
// Congela o crescimento do PageHeader ANTIGO (`@/Components/shared/PageHeader`) enquanto a
// migração pro canon novo (`@/Components/PageHeader`, v3.8 · ADR 0189/0190) acontece tela-a-tela.
//
//   (a) RATCHET: o nº de telas importando o header antigo só pode CAIR (migração) — nunca subir.
//       → nenhuma tela NOVA adota o `shared/PageHeader`; header novo = sempre o canon.
//   (b) Migrar uma tela (antigo→canon) baixa o contador (= progresso); o baseline acompanha no --write.
//
// NÃO toca pixel — é guard de import. A migração visual em si (104 telas) é incremental e exige
// aprovação visual por PR (gate MWART / PR UI Judge), fora deste script.
//
// Comandos (gêmeo do idioma scripts/css-size-baseline.mjs):
//   node scripts/pageheader-migration-guard.mjs           # valida: falha se algum import novo do antigo
//   node scripts/pageheader-migration-guard.mjs --write    # grava baseline do estado atual
//
// Refs: MANUAL-CSS-JS.md §5 (F4) · ADR 0189/0190 (PageHeader canon v3) · INDEX-DESIGN-MEMORIAS.md

import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { resolve, join, relative } from 'node:path';

const ROOT = process.cwd();
const SCAN_DIR = resolve(ROOT, 'resources/js');
const BASELINE_PATH = resolve(ROOT, 'config/pageheader-shared-baseline.json');
const MODE_WRITE = process.argv.includes('--write');

// Import EXATO do componente antigo (não os irmãos PageHeaderActions/ModuleNav/Tabs).
const OLD_IMPORT = /from\s+['"]@\/Components\/shared\/PageHeader['"]/;

function listTsx(dir) {
  const out = [];
  if (!existsSync(dir)) return out;
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) out.push(...listTsx(full));
    else if (entry.isFile() && /\.(tsx|ts|jsx|js)$/.test(entry.name)) out.push(full);
  }
  return out;
}

function findOldAdopters() {
  const files = [];
  for (const f of listTsx(SCAN_DIR)) {
    if (OLD_IMPORT.test(readFileSync(f, 'utf8'))) {
      files.push(relative(ROOT, f).replace(/\\/g, '/'));
    }
  }
  return files.sort((a, b) => a.localeCompare(b));
}

const current = findOldAdopters();
const count = current.length;

if (MODE_WRITE) {
  const out = {
    _meta: {
      generated_at: new Date().toISOString(),
      count,
      note: 'F4 — ratchet do PageHeader antigo (shared/PageHeader). Contador só desce (migração pro canon @/Components/PageHeader). Tela nova nunca adota o antigo. Ver MANUAL-CSS-JS.md §5.',
    },
    files: current,
  };
  writeFileSync(BASELINE_PATH, JSON.stringify(out, null, 2) + '\n');
  console.log(`✅ Baseline gravado: ${count} telas no PageHeader antigo → ${relative(ROOT, BASELINE_PATH)}`);
  process.exit(0);
}

if (!existsSync(BASELINE_PATH)) {
  console.error(`❌ Baseline ausente (${relative(ROOT, BASELINE_PATH)}). Rode: npm run pageheader:guard:write`);
  process.exit(1);
}

const baseline = JSON.parse(readFileSync(BASELINE_PATH, 'utf8'));
const baseCount = baseline._meta?.count ?? (baseline.files || []).length;
const baseSet = new Set(baseline.files || []);
const novos = current.filter((f) => !baseSet.has(f));

console.log(`PageHeader migration guard · ${count} telas no antigo (baseline: ${baseCount})`);

if (novos.length) {
  console.error(`\n❌ ${novos.length} tela(s) NOVA(s) adotando o PageHeader ANTIGO (shared/PageHeader):\n`);
  for (const f of novos) console.error('  🆕 ' + f);
  console.error(
    `\nTela nova usa o canon: \`import PageHeader from '@/Components/PageHeader'\` (v3.8, ADR 0189/0190).` +
      `\nO header antigo está em migração (F4) — não ganha adotantes novos. Ver MANUAL-CSS-JS.md §5.`,
  );
  process.exit(1);
}

if (count > baseCount) {
  console.error(`\n❌ Contador subiu (${baseCount} → ${count}) sem arquivo novo identificável. Investigue o diff.`);
  process.exit(1);
}

console.log(`✅ Sem novos adotantes do header antigo (migração ${baseCount - count > 0 ? `avançou −${baseCount - count}` : 'estável'}).`);
process.exit(0);
