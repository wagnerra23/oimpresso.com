#!/usr/bin/env node
// scripts/eslint-baseline.mjs — Onda 1.2 (ADR 0209)
//
// Geração e verificação de baseline JSON pra ESLint 9.
// Padrão idêntico ao `ui-lint.yml` PHP-side: ratchet por path+rule+count.
//
// Comandos:
//   node scripts/eslint-baseline.mjs --write     # gera baseline JSON do estado atual
//   node scripts/eslint-baseline.mjs             # valida (default): falha só em REGRESSÃO
//
// Refs: ADR 0209 — ESLint 9 flat-config baseline ratchet

import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

// Flags backward-compat (Onda selftest — entrada no gate-selftest REQUIRED):
//   --baseline <path>  baseline JSON alvo (default: config/eslint-baseline.json)
//   --target   <path>  diretório/arquivo linado (default: resources/js)
// SEM flags = comportamento IDÊNTICO ao original (gate de produção intocado).
// cwd continua sendo o ROOT do repo (acha node_modules/eslint + eslint.config.js).
// Usado pelas fixtures good/bad em tests/governance-fixtures/eslint/ pelo MESMO code path.
function argVal(flag, def) {
  const i = process.argv.indexOf(flag);
  return i >= 0 && process.argv[i + 1] ? process.argv[i + 1] : def;
}
const BASELINE_PATH = resolve(process.cwd(), argVal('--baseline', 'config/eslint-baseline.json'));
const TARGET = argVal('--target', 'resources/js');
const MODE_WRITE = process.argv.includes('--write');

function runEslint() {
  // Windows: `npx` é .cmd; usar shell: true pra portabilidade. Linux CI também OK.
  const cmd = `npx --no-install eslint "${TARGET}" --format=json --max-warnings=999999`;
  try {
    return JSON.parse(execSync(cmd, {
      encoding: 'utf8',
      maxBuffer: 100 * 1024 * 1024,
      stdio: ['ignore', 'pipe', 'ignore'],
      shell: true,
    }));
  } catch (err) {
    // ESLint exit 1 quando tem erros, mas stdout é válido
    if (err.stdout) return JSON.parse(err.stdout);
    throw err;
  }
}

function buildCounts(results) {
  const counts = {};
  for (const result of results) {
    const path = result.filePath.replace(/\\/g, '/').replace(`${process.cwd().replace(/\\/g, '/')}/`, '');
    for (const msg of result.messages) {
      const rule = msg.ruleId || '__parser_error__';
      const key = `${path}|${rule}`;
      counts[key] = (counts[key] || 0) + 1;
    }
  }
  return counts;
}

function main() {
  console.log(`ESLint baseline · ${MODE_WRITE ? 'WRITE' : 'VALIDATE'} mode`);
  console.log(`Scanning ${TARGET}...`);

  const results = runEslint();
  const counts = buildCounts(results);
  const total = Object.values(counts).reduce((a, b) => a + b, 0);

  console.log(`Total violations atual: ${total}`);

  if (MODE_WRITE) {
    const out = {
      _meta: {
        generated_at: new Date().toISOString(),
        total_violations: total,
        eslint_version: 'flat-config 9.x',
        adr: '0209',
      },
      counts,
    };
    writeFileSync(BASELINE_PATH, JSON.stringify(out, null, 2) + '\n');
    console.log(`✅ Baseline gravado em ${BASELINE_PATH} (${Object.keys(counts).length} entradas)`);
    return;
  }

  // VALIDATE mode
  if (!existsSync(BASELINE_PATH)) {
    console.error(`❌ Baseline não existe em ${BASELINE_PATH}`);
    console.error('   Rode: node scripts/eslint-baseline.mjs --write');
    process.exit(1);
  }

  const baseline = JSON.parse(readFileSync(BASELINE_PATH, 'utf8'));
  const baselineCounts = baseline.counts || {};
  const baselineTotal = Object.values(baselineCounts).reduce((a, b) => a + b, 0);

  console.log(`Total baseline: ${baselineTotal} · Delta: ${total - baselineTotal > 0 ? '+' : ''}${total - baselineTotal}`);

  // Detecta regressões por path+rule
  const regressions = [];
  for (const [key, atual] of Object.entries(counts)) {
    const base = baselineCounts[key] || 0;
    if (atual > base) {
      regressions.push({ key, base, atual, delta: atual - base });
    }
  }

  if (regressions.length > 0) {
    console.error('');
    console.error(`❌ REGRESSÃO — ${regressions.length} entrada(s) com hits AUMENTADOS:`);
    for (const r of regressions.sort((a, b) => b.delta - a.delta).slice(0, 30)) {
      const [path, rule] = r.key.split('|');
      console.error(`   ${path} · ${rule} · ${r.base} → ${r.atual} (Δ+${r.delta})`);
    }
    if (regressions.length > 30) {
      console.error(`   ... e mais ${regressions.length - 30} entrada(s)`);
    }
    console.error('');
    console.error('Pra ver detalhes: npm run lint');
    console.error('Pra atualizar baseline (se regressão aceita): node scripts/eslint-baseline.mjs --write');
    process.exit(1);
  }

  console.log('✅ Sem regressões vs baseline');
}

main();
