#!/usr/bin/env node
// ds-mirror-drift.test.mjs — selftest do SENTINELA de drift git↔espelho (bite/release/catraca).
//
// POR QUE EXISTE: um sentinela SEM selftest é o anti-padrão "existe-e-não-roda" que a ADR 0256
//   combate e a doutrina 0329 proíbe (regra que ninguém cobra morre). Este prova que o guarda
//   MORDE quando o drift sobe acima do baseline, SOLTA quando está em dia, e que a catraca
//   (baseline) segura. Usa fixtures em dir temporário + o motor real ds-token-diff via os flags
//   --snapshot/--tokens/--baseline. Contrato: runbook design-sync-push.md §"sentinela" + ADR 0328.

import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, rmSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(HERE, 'ds-mirror-drift.mjs');
const ROOT = join(tmpdir(), `ds-mirror-drift-test-${process.pid}`);
const TOK = join(ROOT, 'tokens');
const BASE = join(ROOT, 'baseline.json');

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

mkdirSync(TOK, { recursive: true });
writeFileSync(join(TOK, '_generated-inertia-theme.css'), ':root{ --a: oklch(0.5 0 0); }');
const snap = (name, val) => { const p = join(ROOT, name); writeFileSync(p, `:root{ --a: ${val}; }`); return p; };
const baseline = (n) => writeFileSync(BASE, JSON.stringify({ totalDiverge: n, perScope: {} }) + '\n');
// roda o sentinela; retorna o exit code (0/1)
function run(snapPath, extra = []) {
  const args = [SCRIPT, '--snapshot', snapPath, '--tokens', TOK, '--baseline', BASE, ...extra];
  try { execFileSync('node', args, { encoding: 'utf8' }); return 0; }
  catch (e) { return e.status ?? 1; }
}

console.log('ds-mirror-drift.test — bite / release / catraca');

const match = snap('match.css', 'oklch(0.5 0 0)');   // == git → diverge 0
const drift = snap('drift.css', 'oklch(0.9 0 0)');   // valor difere → diverge 1

// RELEASE — em dia (diverge 0), baseline 0, --enforce → passa
baseline(0);
ok(run(match, ['--enforce']) === 0, 'RELEASE: snapshot == git, baseline 0, --enforce → exit 0');

// BITE — drift acima do baseline, --enforce → morde (exit 1)
ok(run(drift, ['--enforce']) === 1, 'BITE: drift 1 > baseline 0, --enforce → exit 1');

// ADVISORY — mesmo drift SEM --enforce → nunca bloqueia (exit 0)
ok(run(drift) === 0, 'ADVISORY: drift 1 > baseline 0, sem --enforce → exit 0 (só ::warning::)');

// CATRACA — drift dentro do baseline (1 ≤ 1), --enforce → passa (não é regressão)
baseline(1);
ok(run(drift, ['--enforce']) === 0, 'CATRACA: drift 1 == baseline 1, --enforce → exit 0');

// NÃO-MEDIÇÃO ≠ DRIFT (lápide §5 2026-08-14): --enforce distingue por exit code.
// exit 2 = não consegui medir · exit 1 = drift real. Sem isso, snapshot/tokens ausentes
// saíam como "drift" (ou pior: tokens ausente saía VERDE — o motor devolve diverge=0).
baseline(0);
ok(run(join(ROOT, 'nao-existe.css'), ['--enforce']) === 2, 'snapshot ausente + --enforce → exit 2 (não-medi, NÃO drift)');
ok(run(join(ROOT, 'nao-existe.css')) === 0, 'snapshot ausente sem --enforce → exit 0 (advisory)');

// TOKENS AUSENTE — o verde falso medido 2026-09-01: o motor devolve totalDiverge=0 com o dir
// inexistente; sem a checagem própria o enforce passaria. Bite: exit 2, nunca 0 nem 1.
{
  const args = [SCRIPT, '--snapshot', match, '--tokens', join(ROOT, 'tokens-nao-existe'), '--baseline', BASE, '--enforce'];
  let code = 0; try { execFileSync('node', args, { encoding: 'utf8' }); } catch (e) { code = e.status ?? 1; }
  ok(code === 2, 'tokens/ ausente + --enforce → exit 2 (era verde falso: motor devolve diverge=0)');
  const args0 = [SCRIPT, '--snapshot', match, '--tokens', join(ROOT, 'tokens-nao-existe'), '--baseline', BASE];
  let code0 = 0; try { execFileSync('node', args0, { encoding: 'utf8' }); } catch (e) { code0 = e.status ?? 1; }
  ok(code0 === 0, 'tokens/ ausente sem --enforce → exit 0 (advisory)');
}

// CONTROLE NEGATIVO — drift real continua saindo 1 (o código de drift não foi diluído)
ok(run(drift, ['--enforce']) === 1, 'controle: drift real + --enforce segue exit 1');

// --update-baseline grava o drift atual
run(drift, ['--update-baseline']);
ok(JSON.parse(readFileSync(BASE, 'utf8')).totalDiverge === 1, '--update-baseline grava o piso atual (1)');

rmSync(ROOT, { recursive: true, force: true });
if (fails) { console.error(`\nds-mirror-drift.test: ${fails} FALHA(S)`); process.exit(1); }
console.log('\nds-mirror-drift.test: OK');
