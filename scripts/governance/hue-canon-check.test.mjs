#!/usr/bin/env node
// Teste bite/release do hue-canon-check (US-GOV-052 P32 — padrão gate-selftest).
// Prova as duas metades da ressalva do adversário:
//   MORDE: construção declarativa (expected_hue/hue_correct/primary_hue) != canon;
//   NÃO MORDE: "145" cru em prosa nem hue de grupo do sidebar (falso-positivo proibido).
//
// Hermetico: repo temporario com governance/hue-canon.json + docs fixture,
// roda o check como subprocesso (cwd=tmp).
//
// Rodar: node scripts/governance/hue-canon-check.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'hue-canon-check.mjs');

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK] ' : '[FAIL] ') + name);
  if (!cond) fails++;
}

function makeRepo(docContent) {
  const tmp = mkdtempSync(join(tmpdir(), 'hue-canon-test-'));
  mkdirSync(join(tmp, 'governance'), { recursive: true });
  writeFileSync(join(tmp, 'governance', 'hue-canon.json'), JSON.stringify({ primary_hue: 295, fonte: 'ADR 0190' }), 'utf8');
  mkdirSync(join(tmp, '.claude', 'skills', 'fixture'), { recursive: true });
  writeFileSync(join(tmp, '.claude', 'skills', 'fixture', 'SKILL.md'), docContent, 'utf8');
  return tmp;
}
const run = (tmp) => spawnSync('node', [SCRIPT], { cwd: tmp, encoding: 'utf8' });
const drop = (tmp) => rmSync(tmp, { recursive: true, force: true });

// ── release: declaração igual ao canon passa ──
let tmp = makeRepo('expected_hue: 295, // primary universal\nhue_correct: bg.includes("295"),\n');
let r = run(tmp);
check('declaração 295 = canon passa (release)', r.status === 0 && /2 declaração/.test(r.stdout));
drop(tmp);

// ── bite 1: expected_hue morto (145) ──
tmp = makeRepo('expected_hue: 145, // hue morto pré-ADR-0190\n');
r = run(tmp);
check('MORDE expected_hue: 145 (exit 1 + acusação com file:line)', r.status === 1 && /expected_hue declara 145 ≠ canon 295/.test(r.stderr) && /SKILL\.md:1/.test(r.stderr));
drop(tmp);

// ── bite 2: hue_correct aprovando hue morto ──
tmp = makeRepo("hue_correct: bg.includes('145'), // check aprovando o morto\n");
r = run(tmp);
check("MORDE hue_correct includes('145')", r.status === 1 && /hue_correct declara 145/.test(r.stderr));
drop(tmp);

// ── bite 3: canon paralelo (primary_hue divergente em outro arquivo) ──
tmp = makeRepo('config:\n  primary_hue: 202\n');
r = run(tmp);
check('MORDE primary_hue: 202 (canon paralelo)', r.status === 1 && /primary_hue declara 202/.test(r.stderr));
drop(tmp);

// ── falso-positivo proibido 1: "145" cru em prosa NÃO flagra ──
tmp = makeRepo('O hue-per-grupo (145 financas | 60 vender) vale SÓ no sidebar, hue 145 aqui é prosa.\n');
r = run(tmp);
check('NÃO morde "145" cru em prosa (detecção por construção, não número)', r.status === 0);
drop(tmp);

// ── falso-positivo proibido 2: mapa do sidebar (fora de escopo) ──
tmp = makeRepo('export const SIDEBAR_GROUP_HUE = { financas: 145, vender: 60 };\n');
r = run(tmp);
check('NÃO morde SIDEBAR_GROUP_HUE (fonte é shared.ts, fora do escopo)', r.status === 0);
drop(tmp);

// ── canon ilegível/absurdo: falha alto, não passa calado ──
tmp = mkdtempSync(join(tmpdir(), 'hue-canon-test-'));
mkdirSync(join(tmp, 'governance'), { recursive: true });
writeFileSync(join(tmp, 'governance', 'hue-canon.json'), JSON.stringify({ primary_hue: 'roxo' }), 'utf8');
r = run(tmp);
check('canon inválido falha alto (exit 1), não passa calado', r.status === 1 && /não é hue 0-360/.test(r.stderr));
drop(tmp);

console.log('');
if (fails === 0) {
  console.log('[PASS] hue-canon-check morde e libera (7/7).');
  process.exit(0);
} else {
  console.log(`[FAIL] ${fails} caso(s) — o verificador de hue regrediu.`);
  process.exit(1);
}
