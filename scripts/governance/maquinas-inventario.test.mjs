#!/usr/bin/env node
// maquinas-inventario.test.mjs — bite-test do --check (cobertura) do inventário de máquinas.
// Prova que a catraca MORDE quando uma máquina é adicionada/removida sem regenerar, e LIBERA
// quando o índice está fiel. Usa MAQUINAS_OUT (env) pra apontar o --check pra uma cópia
// temporária mutada — NUNCA toca o doc real. Roda no governance-script-tests.yml (advisory).
import { execFileSync } from 'node:child_process';
import { writeFileSync, readFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const SCRIPT = 'scripts/governance/maquinas-inventario.mjs';
const REAL = 'memory/reference/MAQUINAS-INVENTARIO.md';
const fixture = join(mkdtempSync(join(tmpdir(), 'maq-')), 'inv.md');

// roda o --check com o doc apontado pra `path`; devolve o exit code
const check = (path) => {
  try {
    execFileSync('node', [SCRIPT, '--check'], { env: { ...process.env, MAQUINAS_OUT: path }, stdio: 'pipe' });
    return 0;
  } catch (e) { return e.status ?? 1; }
};

let fails = 0;
const t = (name, ok) => { console.log(`${ok ? '✓' : '✗'} ${name}`); if (!ok) fails++; };

const real = readFileSync(REAL, 'utf8');

// (1) controle: cópia fiel do doc real → --check LIBERA (exit 0). Também pega drift real:
//     se o doc commitado estiver stale vs disco, esta asserção falha.
writeFileSync(fixture, real);
t('doc fiel → --check libera (exit 0)', check(fixture) === 0);

// (2) BITE missing: remove uma linha de máquina (.mjs) do índice → --check MORDE (exit 1)
writeFileSync(fixture, real.replace(/^\| `[\w.-]+\.mjs` .*$/m, ''));
t('máquina sumiu do índice → --check morde (exit 1)', check(fixture) === 1);

// (3) BITE ghost: adiciona máquina inexistente ao índice → --check MORDE (exit 1)
writeFileSync(fixture, real + '\n| `maquina-fantasma-xyz.mjs` | fake |\n');
t('máquina fantasma no índice → --check morde (exit 1)', check(fixture) === 1);

// (4) controle negativo: restaura fiel → volta a LIBERAR (não virou catraca ao contrário)
writeFileSync(fixture, real);
t('restaurado fiel → --check volta a liberar (exit 0)', check(fixture) === 0);

if (fails) { console.error(`\n✗ ${fails} teste(s) falharam`); process.exit(1); }
console.log('\n✅ maquinas-inventario --check: morde missing + ghost, libera fiel (bite provado)');
