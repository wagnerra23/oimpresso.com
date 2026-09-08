#!/usr/bin/env node
// design-coverage.test.mjs — selftest da catraca de cobertura de design (bite/release).
//   Prova que --check MORDE quando `declared` regride vs baseline e SOLTA quando não. Aponta
//   --baseline pra um temp (não toca o baseline real). Roda contra o ancora --json REAL.

import { execFileSync } from 'node:child_process';
import { writeFileSync, readFileSync, rmSync, mkdirSync } from 'node:fs';
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

// ── eixo PARIDADE (Onda 7) — back-compat + bite ────────────────────────────────
const checkP = (obj) => {
  writeFileSync(BASE, JSON.stringify(obj));
  try { execFileSync('node', [SCRIPT, '--check', '--baseline', BASE], { encoding: 'utf8' }); return 0; }
  catch (e) { return e.status ?? 1; }
};
// BACK-COMPAT: baseline anterior a Onda 7 nao tem `parityLinked`. Ausente NAO pode
// virar 0 — se virasse, todo repo reprovaria no dia do merge (a doenca de §5 2026-08-24:
// predicado que cobra do autor um estado que ele nao causou).
ok(checkP({ declared: 0, totalCharters: 0 }) === 0, 'BACK-COMPAT: baseline sem parityLinked → exit 0 (eixo nao cobrado)');
ok(checkP({ declared: 0, totalCharters: 0, parityLinked: 0 }) === 0, 'RELEASE: parityLinked baseline 0 ≤ atual → exit 0');
ok(checkP({ declared: 0, totalCharters: 0, parityLinked: 9999 }) === 1, 'BITE: parityLinked 9999 > atual → exit 1 (vinculo perdido pega)');

// ── modos que ESCREVEM vs modos que LEEM (2026-09-08) ──────────────────────────
// POR QUE ESTES CASOS EXISTEM: até 2026-09-08 a flag `--json` GRAVAVA o baseline e
// não emitia JSON nenhum. O defeito atravessou porque este teste cobria só `--check`
// — sete casos sobre a catraca, zero sobre o modo que escreve. Custo real: uma sessão
// rodou `--json` para LER o estado, subiu o piso de 194 para 210 sem querer e apagou
// o campo `noteParidade` de quebra. Um teste que não exerce o modo que escreve é
// indistinguível de nenhum teste para essa dimensão.
// CONTROLE NEGATIVO medido (2026-09-08): com o script de antes do conserto, 6 dos 8
// casos abaixo falham e a suite sai 1. Os 2 `NAO-LOSSY` passam la por NAO-EXECUCAO —
// `--write-baseline` nem existia, entao nada era escrito e a semente sobrevivia por
// acidente (LC-13). Quem cobre esse flanco sao os 2 `WRITE` adjacentes: se o write nao
// acontecer eles morrem, e se acontecer lossy morrem os NAO-LOSSY. O par e que prova.
const readJson = (p) => JSON.parse(readFileSync(p, 'utf8'));
const SEMENTE = { declared: 1, totalCharters: 1, parityLinked: 1, note: 'NOTA', noteParidade: 'CAMPO EXTRA' };

// --json é READ-ONLY: não pode tocar o baseline, e o stdout tem que ser JSON válido.
writeFileSync(BASE, JSON.stringify(SEMENTE));
const saidaJson = execFileSync('node', [SCRIPT, '--json', '--baseline', BASE], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
ok(readJson(BASE).declared === 1, 'READ-ONLY: --json NAO grava o baseline (o defeito de 2026-09-08)');
ok(readJson(BASE).noteParidade === 'CAMPO EXTRA', 'READ-ONLY: --json preserva os campos do baseline');
let parsed = null; try { parsed = JSON.parse(saidaJson); } catch { /* fica null */ }
ok(parsed !== null && typeof parsed.declared === 'number', '--json emite JSON valido no stdout (o que o nome promete)');
ok(parsed !== null && typeof parsed.parityLinked === 'number', '--json serve o eixo paridade tambem');

// --write-baseline é o modo que ESCREVE — e ele é NAO-LOSSY por contrato.
writeFileSync(BASE, JSON.stringify(SEMENTE));
execFileSync('node', [SCRIPT, '--write-baseline', '--baseline', BASE], { encoding: 'utf8' });
const gravado = readJson(BASE);
ok(gravado.declared > 1, 'WRITE: --write-baseline atualiza `declared` com o valor medido');
ok(typeof gravado.parityLinked === 'number' && gravado.parityLinked > 1, 'WRITE: --write-baseline atualiza `parityLinked`');
ok(gravado.noteParidade === 'CAMPO EXTRA', 'NAO-LOSSY: --write-baseline preserva campo que ele nao mede (noteParidade)');
ok(gravado.note === 'NOTA', 'NAO-LOSSY: --write-baseline preserva a `note` existente');

rmSync(DIR, { recursive: true, force: true });
if (fails) { console.error(`\ndesign-coverage.test: ${fails} FALHA(S)`); process.exit(1); }
console.log('\ndesign-coverage.test: OK');
