#!/usr/bin/env node
// ds-push.test.mjs — selftest do orquestrador ds-push (catraca: prova que o comando MORDE,
//   não vira defesa-fantasma "existe-e-não-roda"). Registrado no CI via governance-script-tests.
//
// Cobre: (1) dry-run sai 0 e reporta VALOR:0 contra o snapshot commitado; (2) monta os 2
//   arquivos do bundle; (3) o bundle montado é byte-idêntico ao mirror-snapshot commitado
//   (o motor é determinístico e o canon está em dia). NÃO faz upload DesignSync (interativo).

import { execFileSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join, resolve } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(HERE, 'ds-push.mjs');
const MIRROR = join(HERE, 'mirror-snapshot');
const BUNDLE = join(HERE, '.push-bundle');

let fails = 0;
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { console.error(`  ✗ ${msg}`); fails++; } };

console.log('ds-push.test — dry-run + determinismo');

let out = '', code = 0;
try {
  out = execFileSync('node', [SCRIPT], { encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 });
} catch (e) {
  code = e.status ?? 1;
  out = (e.stdout || '') + (e.stderr || '');
}

ok(code === 0, 'dry-run sai 0');
ok(/divergências de VALOR: 0/.test(out), 'reporta VALOR:0 (bundle == git canon)');
ok(existsSync(join(BUNDLE, 'colors_and_type.css')), 'montou colors_and_type.css');
ok(existsSync(join(BUNDLE, 'cockpit_domains.css')), 'montou cockpit_domains.css');

const same = (a, b) => existsSync(a) && existsSync(b) && readFileSync(a, 'utf8') === readFileSync(b, 'utf8');
ok(same(join(BUNDLE, 'colors_and_type.css'), join(MIRROR, 'colors_and_type.css')),
   'colors_and_type montado == snapshot commitado (determinístico)');
ok(same(join(BUNDLE, 'cockpit_domains.css'), join(MIRROR, 'cockpit_domains.css')),
   'cockpit_domains montado == snapshot commitado (determinístico)');

if (fails) { console.error(`\nds-push.test: ${fails} FALHA(S)`); process.exit(1); }
console.log('\nds-push.test: OK');
