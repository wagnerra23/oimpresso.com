#!/usr/bin/env node
// ds-push.test.mjs — selftest do orquestrador ds-push (catraca: prova que o comando MORDE,
//   não vira defesa-fantasma "existe-e-não-roda"). NÃO faz upload DesignSync (interativo).
//
// PROVA a PLUMBING do orquestrador: dry-run sai 0, monta os 2 arquivos do bundle e a
//   validação interna reporta VALOR:0 (montou colors_and_type dos valores do git + companion,
//   e o ds-token-diff --companion do bundle contra o git fecha em 0).
//
// NÃO assere "bundle == mirror-snapshot commitado" de propósito: essa igualdade é DRIFT
//   git↔snapshot, cujo dono é o sentinela ds-mirror-drift (passo 5 do runbook). Amarrar aqui
//   faria este selftest falhar a cada mudança de Fundação até refrescar o snapshot — dois
//   pontos de falha pro mesmo drift (achado adversarial #3). Roda com --out em dir TEMP e limpa
//   (não suja .push-bundle no repo — achado adversarial #4).

import { execFileSync } from 'node:child_process';
import { existsSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(HERE, 'ds-push.mjs');
const OUT = join(tmpdir(), `ds-push-test-${process.pid}`);

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

console.log('ds-push.test — dry-run + plumbing (VALOR:0), sem acoplar drift do snapshot');

let out = '', code = 0;
try {
  out = execFileSync('node', [SCRIPT, '--out', OUT], { encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 });
} catch (e) {
  code = e.status ?? 1;
  out = (e.stdout || '') + (e.stderr || '');
}

ok(code === 0, 'dry-run sai 0');
ok(/divergências de VALOR: 0/.test(out), 'validação interna reporta VALOR:0 (bundle consistente com git)');
ok(existsSync(join(OUT, 'colors_and_type.css')), 'montou colors_and_type.css');
ok(existsSync(join(OUT, 'cockpit_domains.css')), 'montou cockpit_domains.css');

if (existsSync(OUT)) rmSync(OUT, { recursive: true, force: true });

if (fails) { console.error(`\nds-push.test: ${fails} FALHA(S)`); process.exit(1); }
console.log('\nds-push.test: OK');
