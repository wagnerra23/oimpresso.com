#!/usr/bin/env node
// @ts-check
/**
 * screen-grades-ratchet.mjs — catraca anti-regressão da nota por tela.
 *
 * Espelha o `module-grades-gate` (ADR 0155): compara a nota de cada scorecard de
 * tela no PR contra a nota em `origin/main` e BLOQUEIA (exit 1) se alguma cair.
 * Robusto contra burla: compara sempre vs `origin/main` (não vs o `baseline_anterior`
 * do próprio arquivo, que o PR poderia baixar junto).
 *
 * Regra (catraca = nota só sobe):
 *   - nota(PR) <  nota(main)   → REGRESSÃO → bloqueia
 *   - nota(PR) >= nota(main)   → ok (subiu ou estável)
 *   - tela nova (ausente em main) → ok, vira o novo baseline
 *
 * Override (Wagner aprova regressão consciente): variável de ambiente
 *   SCREEN_RATCHET_ALLOW_REGRESSION=1  (espelha o label do module-grades-gate).
 *
 * Pré-req no CI: actions/checkout fetch-depth: 0 (precisa de origin/main).
 *
 * Uso:
 *   node scripts/qa/screen-grades-ratchet.mjs
 */

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';

const ROOT = process.cwd();
const DIR = join(ROOT, 'memory', 'governance', 'scorecards', 'screens');
const REL = 'memory/governance/scorecards/screens';
const ALLOW = process.env.SCREEN_RATCHET_ALLOW_REGRESSION === '1';
// Ref de baseline (default origin/main). Configurável pra teste local.
const BASE_REF = process.env.SCREEN_RATCHET_BASE_REF || 'origin/main';

/** Lê `nota:` de um YAML de scorecard (formato controlado pelo seed). */
const parseNota = (text) => {
  const m = text.match(/^nota:\s*(\d+)/m);
  return m ? parseInt(m[1], 10) : null;
};

/** Nota da versão em origin/main, ou null se a tela é nova. */
function notaInMain(relPath) {
  try {
    const out = execSync(`git show ${BASE_REF}:${relPath}`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
    return parseNota(out);
  } catch {
    return null; // ausente em main → tela nova
  }
}

if (!existsSync(DIR)) {
  console.error(`✗ ${REL} não existe — rode screen-grade-seed.mjs primeiro.`);
  process.exit(2);
}

const files = readdirSync(DIR).filter((f) => f.endsWith('.yaml'));
const regress = [];
let novas = 0,
  ok = 0;

for (const f of files) {
  const cur = parseNota(readFileSync(join(DIR, f), 'utf8'));
  if (cur === null) continue;
  const base = notaInMain(`${REL}/${f}`);
  if (base === null) {
    novas++;
    continue;
  }
  if (cur < base) regress.push({ file: f, base, cur, delta: cur - base });
  else ok++;
}

console.log(`\nCatraca screen-grade · ${files.length} telas · ✅ ${ok} ok/subiu · ✨ ${novas} novas · 🔻 ${regress.length} regrediram`);

if (regress.length) {
  console.log('\nRegressões (nota caiu vs origin/main):');
  for (const r of regress.sort((a, b) => a.delta - b.delta)) {
    console.log(`  🔻 ${r.file}: ${r.base} → ${r.cur} (${r.delta})`);
  }
  if (ALLOW) {
    console.log('\n⚠️  SCREEN_RATCHET_ALLOW_REGRESSION=1 — regressão consciente autorizada. PASS.');
    process.exit(0);
  }
  console.error('\n✗ CATRACA: nota de tela caiu. PR bloqueado. (override: SCREEN_RATCHET_ALLOW_REGRESSION=1)');
  process.exit(1);
}

console.log('\n✓ CATRACA: nenhuma tela regrediu.');
