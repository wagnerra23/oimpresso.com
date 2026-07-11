#!/usr/bin/env node
// ds-token-version.test.mjs — selftest do versionador de tokens (catraca: prova que MORDE).
//   Usa fixtures em dir temporário (não toca resources/css/tokens). Cobre seed + os 3 bumps
//   (valor→MINOR, remoção→MAJOR, adição→MINOR) + o gate --check (drift falha, em-dia passa).

import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, readFileSync, rmSync, existsSync, cpSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(HERE, 'ds-token-version.mjs');
const ROOT = join(tmpdir(), `ds-token-version-test-${process.pid}`);
const T = join(ROOT, 'tokens'), P = join(ROOT, 'prev');
const V = join(ROOT, 'version.json'), C = join(ROOT, 'changelog.md');

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

// escreve a superfície (light + cockpit-light) num dir de fixture.
function writeSurface(dir, lightBody, cockpitBody) {
  mkdirSync(dir, { recursive: true });
  writeFileSync(join(dir, '_generated-inertia-theme.css'), `:root{\n${lightBody}\n}`);
  writeFileSync(join(dir, '_generated-cockpit-light.css'), `.cockpit{\n${cockpitBody}\n}`);
}
function run(extra) {
  try {
    const out = execFileSync('node', [SCRIPT, '--tokens', T, '--version-file', V, '--changelog', C, ...extra], { encoding: 'utf8' });
    return { code: 0, out };
  } catch (e) { return { code: e.status ?? 1, out: (e.stdout || '') + (e.stderr || '') }; }
}
const ver = () => JSON.parse(readFileSync(V, 'utf8')).version;

console.log('ds-token-version.test — seed + 3 bumps + gate');
if (existsSync(ROOT)) rmSync(ROOT, { recursive: true, force: true });

// SEED
writeSurface(T, '  --a: 1;\n  --b: 2;', '  --x: red;');
let r = run(['--write', '--prev', T, '--date', '2026-07-10']);
ok(r.code === 0 && ver() === '1.0.0', `seed → v1.0.0 (got ${existsSync(V) ? ver() : 'nada'})`);
ok(run(['--check']).code === 0, 'check em-dia passa (exit 0)');
cpSync(T, P, { recursive: true });

// VALOR alterado → MINOR
writeSurface(T, '  --a: 1;\n  --b: 99;', '  --x: red;');
ok(run(['--check']).code === 1, 'check com drift FALHA (exit 1)');
// RECUSA quando o baseline não vê delta (prev==cur) — o path do bug adversarial #1:
// rodar --write DEPOIS de commitar sem --prev daria delta vazio; NÃO pode fingir MINOR.
ok(run(['--write', '--prev', T, '--date', '2026-07-10']).code === 1, 'recusa (exit 1) quando prev==cur — não inventa bump numa mudança/remoção');
ok(ver() === '1.0.0', 'versão NÃO muda na recusa (continua 1.0.0)');
r = run(['--write', '--prev', P, '--date', '2026-07-10']);
ok(r.code === 0 && ver() === '1.1.0', `valor mudado → MINOR v1.1.0 (got ${ver()})`);
ok(/Valor alterado/.test(readFileSync(C, 'utf8')) && /--b/.test(readFileSync(C, 'utf8')), 'changelog registra "Valor alterado" --b');
cpSync(P, join(ROOT, 'p2'), { recursive: true }); rmSync(P, { recursive: true, force: true }); cpSync(T, P, { recursive: true });

// REMOÇÃO → MAJOR
writeSurface(T, '  --a: 1;', '  --x: red;'); // --b removido
r = run(['--write', '--prev', P, '--date', '2026-07-10']);
ok(r.code === 0 && ver() === '2.0.0', `remoção → MAJOR v2.0.0 (got ${ver()})`);
ok(/BREAKING/.test(readFileSync(C, 'utf8')), 'changelog marca remoção como BREAKING');
rmSync(P, { recursive: true, force: true }); cpSync(T, P, { recursive: true });

// ADIÇÃO → MINOR
writeSurface(T, '  --a: 1;\n  --c: 7;', '  --x: red;'); // +--c
r = run(['--write', '--prev', P, '--date', '2026-07-10']);
ok(r.code === 0 && ver() === '2.1.0', `adição → MINOR v2.1.0 (got ${ver()})`);

rmSync(ROOT, { recursive: true, force: true });
if (fails) { console.error(`\nds-token-version.test: ${fails} FALHA(S)`); process.exit(1); }
console.log('\nds-token-version.test: OK');
