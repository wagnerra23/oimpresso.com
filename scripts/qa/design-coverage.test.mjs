#!/usr/bin/env node
// design-coverage.test.mjs — selftest da catraca de cobertura de design (bite/release).
//   Prova que --check MORDE quando `declared` regride vs baseline e SOLTA quando não. Aponta
//   --baseline pra um temp (não toca o baseline real). Roda contra o ancora --json REAL.

import { execFileSync } from 'node:child_process';
import { writeFileSync, rmSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(HERE, 'design-coverage.mjs');
const DIR = join(tmpdir(), `design-coverage-test-${process.pid}`);
mkdirSync(DIR, { recursive: true });
const BASE = join(DIR, 'baseline.json');

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };
const check = (declared) => {
  writeFileSync(BASE, JSON.stringify({ declared, totalCharters: 0 }));
  try { execFileSync('node', [SCRIPT, '--check', '--baseline', BASE], { encoding: 'utf8' }); return 0; }
  catch (e) { return e.status ?? 1; }
};

console.log('design-coverage.test — catraca bite/release');
// declared real ≈ 18; baseline 0 e 18 → passa; baseline absurdamente alto → morde
ok(check(0) === 0, 'RELEASE: baseline 0 ≤ atual → exit 0');
ok(check(18) === 0, 'RELEASE: baseline == atual → exit 0');
ok(check(9999) === 1, 'BITE: baseline 9999 > atual → exit 1 (regressão pega)');
// baseline ausente → falha
rmSync(BASE, { force: true });
let code; try { execFileSync('node', [SCRIPT, '--check', '--baseline', BASE], { encoding: 'utf8' }); code = 0; } catch (e) { code = e.status ?? 1; }
ok(code === 1, 'baseline ausente → exit 1');

rmSync(DIR, { recursive: true, force: true });
if (fails) { console.error(`\ndesign-coverage.test: ${fails} FALHA(S)`); process.exit(1); }
console.log('\ndesign-coverage.test: OK');
