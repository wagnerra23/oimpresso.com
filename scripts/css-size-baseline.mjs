#!/usr/bin/env node
// scripts/css-size-baseline.mjs — ratchet de TAMANHO do CSS (anti-regrowth).
//
// Passo 1 do MANUAL-CSS-JS ("Congelar o crescimento"): trava os bundles cowork
// (e todo resources/css/) pra só ENCOLHEREM. Gêmeo de size do stylelint-baseline:
// falha se (a) algum .css cresceu em linhas vs baseline, OU (b) surgiu .css novo.
//
// Por quê: a limpeza de CSS morto (#2291/#2293/#2295, ~8.3k linhas) só fica travada
// se nada puder re-inflar os bundles. Encolher é sempre permitido (baseline desce no
// próximo --write); crescer exige decisão consciente (rodar --write + justificar).
//
// Comandos:
//   node scripts/css-size-baseline.mjs --write   # grava baseline do estado atual
//   node scripts/css-size-baseline.mjs           # valida (default): falha em crescimento/arquivo novo
//
// Refs: memory/requisitos/_DesignSystem/MANUAL-CSS-JS.md (passo 1 + métrica-mãe) · ADR UI-0013

import { readFileSync, writeFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { resolve, join, relative } from 'node:path';

const ROOT = process.cwd();
const BASELINE_PATH = resolve(ROOT, 'config/css-size-baseline.json');
const CSS_DIR = resolve(ROOT, 'resources/css');
const MODE_WRITE = process.argv.includes('--write');

function listCss(dir) {
  const out = [];
  for (const name of readdirSync(dir)) {
    const full = join(dir, name);
    const st = statSync(full);
    if (st.isDirectory()) out.push(...listCss(full));
    else if (name.endsWith('.css')) out.push(full);
  }
  return out;
}

function countLines(file) {
  const txt = readFileSync(file, 'utf8');
  if (txt === '') return 0;
  // conta linhas independente de CRLF/LF; ignora newline final
  return txt.replace(/\n$/, '').split('\n').length;
}

function buildCounts() {
  const counts = {};
  for (const full of listCss(CSS_DIR)) {
    const rel = relative(ROOT, full).replace(/\\/g, '/');
    counts[rel] = countLines(full);
  }
  return counts;
}

function main() {
  console.log(`CSS size ratchet · ${MODE_WRITE ? 'WRITE' : 'VALIDATE'} mode`);
  console.log('Scanning resources/css/**/*.css...');

  const counts = buildCounts();
  const total = Object.values(counts).reduce((a, b) => a + b, 0);
  console.log(`Total linhas CSS atual: ${total} (${Object.keys(counts).length} arquivos)`);

  if (MODE_WRITE) {
    const out = {
      _meta: {
        generated_at: new Date().toISOString(),
        total_lines: total,
        file_count: Object.keys(counts).length,
        note: 'Ratchet de tamanho — passo 1 MANUAL-CSS-JS. Cada .css só pode encolher; arquivo novo exige --write (decisão consciente / ADR).',
        refs: ['MANUAL-CSS-JS#1', 'ADR UI-0013'],
      },
      files: counts,
    };
    writeFileSync(BASELINE_PATH, JSON.stringify(out, null, 2) + '\n');
    console.log(`✅ Baseline gravado em ${BASELINE_PATH} (${Object.keys(counts).length} arquivos)`);
    return;
  }

  if (!existsSync(BASELINE_PATH)) {
    console.error(`❌ Baseline não existe em ${BASELINE_PATH}`);
    console.error('   Rode: node scripts/css-size-baseline.mjs --write');
    process.exit(1);
  }

  const baseline = JSON.parse(readFileSync(BASELINE_PATH, 'utf8'));
  const baseFiles = baseline.files || {};
  const baseTotal = Object.values(baseFiles).reduce((a, b) => a + b, 0);
  console.log(`Total baseline: ${baseTotal} · Delta: ${total - baseTotal > 0 ? '+' : ''}${total - baseTotal}`);

  const grew = [];
  const novos = [];
  for (const [path, atual] of Object.entries(counts)) {
    if (!(path in baseFiles)) {
      novos.push({ path, atual });
    } else if (atual > baseFiles[path]) {
      grew.push({ path, base: baseFiles[path], atual, delta: atual - baseFiles[path] });
    }
  }

  if (grew.length === 0 && novos.length === 0) {
    console.log('✅ Nenhum CSS cresceu e nenhum arquivo novo — ratchet OK');
    return;
  }

  console.error('');
  if (grew.length) {
    console.error(`❌ CRESCIMENTO — ${grew.length} arquivo(s) com MAIS linhas vs baseline:`);
    for (const g of grew.sort((a, b) => b.delta - a.delta)) {
      console.error(`   ${g.path} · ${g.base} → ${g.atual} (Δ+${g.delta})`);
    }
  }
  if (novos.length) {
    console.error(`❌ ARQUIVO .css NOVO — ${novos.length} não no baseline (exige ADR + --write):`);
    for (const n of novos) console.error(`   ${n.path} (${n.atual} linhas)`);
  }
  console.error('');
  console.error('Crescer/adicionar CSS bespoke vai contra o passo 1 do MANUAL-CSS-JS (congelar sprawl).');
  console.error('Se for crescimento CONSCIENTE e justificado: node scripts/css-size-baseline.mjs --write + explique no PR.');
  process.exit(1);
}

main();
