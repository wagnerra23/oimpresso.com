#!/usr/bin/env node
// scripts/stylelint-baseline.mjs — G5 anti-drift CSS (ADR 0209 pattern)
//
// Gêmeo do scripts/eslint-baseline.mjs pro CSS. Ratchet por path+rule+count:
// falha só em REGRESSÃO (delta>0) vs config/stylelint-baseline.json.
//
// NOTA: usa a API Node do stylelint (não a CLI via npx como o eslint-baseline) de
// propósito — a CLI v17 roteia o JSON pro stderr em exit≠0 no Windows, o que torna a
// captura via execSync frágil. A API é determinística em Windows (local) e Linux (CI).
//
// Comandos:
//   node scripts/stylelint-baseline.mjs --write   # grava baseline do estado atual
//   node scripts/stylelint-baseline.mjs           # valida (default): falha só em regressão
//
// Refs: ADR 0209 · CODE_DESIGN_CONTRACT.md · prototipo-ui/F0-AUDITORIA-ROTINAS-DESIGN-2026-05-31.md (G5)

import stylelint from 'stylelint';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

// Lê o valor de uma flag `--nome <valor>` do argv (retorna fallback se ausente).
function readFlag(name, fallback) {
  const i = process.argv.indexOf(name);
  if (i !== -1 && i + 1 < process.argv.length) return process.argv[i + 1];
  return fallback;
}

// Flags backward-compat (ADR 0209 · self-test gate-selftest):
//   --baseline <path>  → default config/stylelint-baseline.json (comportamento de produção)
//   --target   <glob>  → default resources/css/**/*.css        (comportamento de produção)
// Sem flags = comportamento IDÊNTICO ao gate required em produção. O cwd permanece ROOT
// (pra resolver node_modules/stylelint + stylelint.config.mjs); só baseline+target apontam
// pra fixture no self-test.
const BASELINE_PATH = resolve(process.cwd(), readFlag('--baseline', 'config/stylelint-baseline.json'));
const CONFIG_FILE = resolve(process.cwd(), 'stylelint.config.mjs');
const TARGET = readFlag('--target', 'resources/css/**/*.css');
const MODE_WRITE = process.argv.includes('--write');

async function runStylelint() {
  const { results } = await stylelint.lint({
    files: TARGET,
    configFile: CONFIG_FILE,
    allowEmptyInput: true,
  });
  return results;
}

function buildCounts(results) {
  const counts = {};
  const cwd = process.cwd().replace(/\\/g, '/');
  for (const result of results) {
    const path = result.source.replace(/\\/g, '/').replace(`${cwd}/`, '');
    // `warnings` traz violações de regra; parse errors entram como rule "CssSyntaxError"
    for (const w of result.warnings || []) {
      const rule = w.rule || '__error__';
      const key = `${path}|${rule}`;
      counts[key] = (counts[key] || 0) + 1;
    }
  }
  return counts;
}

async function main() {
  console.log(`Stylelint baseline · ${MODE_WRITE ? 'WRITE' : 'VALIDATE'} mode`);
  console.log(`Scanning ${TARGET}...`);

  const results = await runStylelint();
  const counts = buildCounts(results);
  const total = Object.values(counts).reduce((a, b) => a + b, 0);

  console.log(`Total violations atual: ${total}`);

  if (MODE_WRITE) {
    const out = {
      _meta: {
        generated_at: new Date().toISOString(),
        total_violations: total,
        stylelint_version: '17.x',
        adr: '0209',
        note: 'G5 anti-drift CSS — gêmeo do eslint-baseline. Ratchet por path+rule, falha só em delta>0.',
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
    console.error('   Rode: node scripts/stylelint-baseline.mjs --write');
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
    console.error('Pra ver detalhes: npm run stylelint');
    console.error('Pra atualizar baseline (se regressão aceita): node scripts/stylelint-baseline.mjs --write');
    process.exit(1);
  }

  console.log('✅ Sem regressões vs baseline');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
