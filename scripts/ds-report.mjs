#!/usr/bin/env node
// scripts/ds-report.mjs — placar de adoção do Design System (ds/* por regra × módulo)
//
// O baseline (scripts/eslint-baseline.mjs) agrega TODO ds/* sob `no-restricted-syntax`,
// então não dá pra ver "quanto falta no módulo X" nem "qual regra domina". Este script
// roda o mesmo ESLint mas quebra as violações pela MENSAGEM (`ds/no-native-select` …) e
// pelo módulo (segmento após Pages/ ou Modules/). É o "placar" que o roadmap de adoção
// referencia: `npm run ds:report` → meta ds/* = 0.
//
// Uso:
//   node scripts/ds-report.mjs                 # tabela por regra + por módulo
//   node scripts/ds-report.mjs --json          # saída JSON (CI / DS_ADOCAO_INDICE.md)
//   node scripts/ds-report.mjs --module Sells  # filtra um módulo
//
// Refs: ADR 0209 (ratchet ESLint 9), ADR 0239 (governança DS — git SSOT),
//       prototipo-ui/DS_ADOCAO_INDICE.md (o placar canônico que isto alimenta).

import { execSync } from 'node:child_process';

const TARGET = 'resources/js';
const AS_JSON = process.argv.includes('--json');
const modIdx = process.argv.indexOf('--module');
const ONLY_MODULE = modIdx !== -1 ? process.argv[modIdx + 1] : null;

function runEslint() {
  // Windows: npx é .cmd → shell:true. ESLint sai 1 quando há warnings/erros, mas stdout é JSON válido.
  const cmd = `npx --no-install eslint "${TARGET}" --format=json --max-warnings=999999`;
  try {
    return JSON.parse(execSync(cmd, {
      encoding: 'utf8',
      maxBuffer: 100 * 1024 * 1024,
      stdio: ['ignore', 'pipe', 'ignore'],
      shell: true,
    }));
  } catch (err) {
    if (err.stdout) return JSON.parse(err.stdout);
    throw err;
  }
}

const RULE_RE = /^(ds\/[a-z0-9-]+)/; // a message começa com o pseudo-rule "ds/no-..."

function moduleOf(path) {
  const p = path.replace(/\\/g, '/');
  const m = p.match(/\/(?:Pages|Modules)\/([^/]+)/);
  return m ? m[1] : '(outros)';
}

function main() {
  const cwd = process.cwd().replace(/\\/g, '/');
  const results = runEslint();

  const byRule = {};
  const byModule = {};
  const byModuleRule = {};
  let total = 0;

  for (const result of results) {
    const path = result.filePath.replace(/\\/g, '/').replace(`${cwd}/`, '');
    const mod = moduleOf(path);
    if (ONLY_MODULE && mod.toLowerCase() !== ONLY_MODULE.toLowerCase()) continue;
    for (const msg of result.messages) {
      const mm = (msg.message || '').match(RULE_RE);
      if (!mm) continue; // só conta ds/*
      const rule = mm[1];
      byRule[rule] = (byRule[rule] || 0) + 1;
      byModule[mod] = (byModule[mod] || 0) + 1;
      byModuleRule[`${mod}|${rule}`] = (byModuleRule[`${mod}|${rule}`] || 0) + 1;
      total++;
    }
  }

  if (AS_JSON) {
    console.log(JSON.stringify(
      { total, byRule, byModule, byModuleRule, generated_at: new Date().toISOString() },
      null, 2,
    ));
    return;
  }

  const pad = (s, n) => String(s).padEnd(n);
  const lpad = (s, n) => String(s).padStart(n);

  console.log(`\n  DS adoption · ds/* = ${total}${ONLY_MODULE ? ` · módulo ${ONLY_MODULE}` : ''}\n`);

  console.log('  Por regra');
  console.log('  ' + '-'.repeat(48));
  const rules = Object.entries(byRule).sort((a, b) => b[1] - a[1]);
  if (rules.length === 0) console.log('  (nenhuma)');
  for (const [rule, n] of rules) {
    const pct = total ? ((n / total) * 100).toFixed(1) : '0.0';
    console.log(`  ${pad(rule, 30)} ${lpad(n, 5)}  ${lpad(pct, 5)}%`);
  }

  console.log('\n  Por módulo (top 20)');
  console.log('  ' + '-'.repeat(48));
  const mods = Object.entries(byModule).sort((a, b) => b[1] - a[1]).slice(0, 20);
  if (mods.length === 0) console.log('  (nenhum)');
  for (const [mod, n] of mods) {
    console.log(`  ${pad(mod, 30)} ${lpad(n, 5)}`);
  }

  console.log(`\n  Meta: ds/* -> 0. Hoje: ${total}.`);
  if (total === 0) console.log('  ZERO — adocao completa.');
  console.log('');
}

main();
