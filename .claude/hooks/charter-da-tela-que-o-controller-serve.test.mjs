#!/usr/bin/env node
// Bite-test. Um advisory que nunca fala é decorativo; um que fala sempre é papel de parede.
// Fixture usa o caso REAL do incidente (controller que renderiza tela com charter).

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'charter-da-tela-que-o-controller-serve.mjs');
const raiz = mkdtempSync(join(tmpdir(), 'charter-tela-'));
let falhas = 0;

const ok = (n, c, d = '') => { if (c) console.log(`  ok   ${n}`); else { falhas++; console.log(`  FALHA ${n} ${d}`); } };
const roda = (payload, env = {}) => {
  const r = spawnSync(process.execPath, [HOOK], { input: JSON.stringify(payload), encoding: 'utf8', timeout: 20000, env: { ...process.env, ...env } });
  return { code: r.status, err: String(r.stderr || '') };
};

// árvore mínima: controller que renderiza + charter da tela
mkdirSync(join(raiz, 'Modules/NfeBrasil/Http/Controllers'), { recursive: true });
mkdirSync(join(raiz, 'resources/js/Pages/NfeBrasil/Tributacao'), { recursive: true });
const COM = join(raiz, 'Modules/NfeBrasil/Http/Controllers/TributacaoController.php');
writeFileSync(COM, `<?php\nclass X { public function i() { return Inertia::render('NfeBrasil/Tributacao/Index', []); } }\n`);
writeFileSync(join(raiz, 'resources/js/Pages/NfeBrasil/Tributacao/Index.charter.md'), '# charter\n');

// controller que renderiza tela SEM charter
const SEM = join(raiz, 'Modules/NfeBrasil/Http/Controllers/OrfaoController.php');
writeFileSync(SEM, `<?php\nclass Y { public function i() { return Inertia::render('NfeBrasil/NaoExiste/Index', []); } }\n`);

// controller que não renderiza nada (API pura)
const API = join(raiz, 'Modules/NfeBrasil/Http/Controllers/WebhookController.php');
writeFileSync(API, `<?php\nclass Z { public function i() { return response()->json([]); } }\n`);

console.log('charter-da-tela — bite-test');

const r1 = roda({ tool_name: 'Read', tool_input: { file_path: COM } });
ok('FALA: controller que renderiza tela COM charter → aponta o charter', /Index\.charter\.md/.test(r1.err), `(err=${r1.err.slice(0, 70)})`);
ok('FALA: cita §Non-Goals / Anti-hooks (o que a tela NÃO faz)', /Non-Goals|Anti-hooks/.test(r1.err));
ok('ADVISORY: nunca bloqueia — exit 0 mesmo falando', r1.code === 0, `(exit=${r1.code})`);

// controles negativos — silêncio total
for (const [nome, payload, env] of [
  ['controller cuja tela NÃO tem charter', { tool_name: 'Read', tool_input: { file_path: SEM } }, {}],
  ['controller sem Inertia::render (API pura)', { tool_name: 'Read', tool_input: { file_path: API } }, {}],
  ['Edit não dispara (o par é Read — medido: 59% dos casos)', { tool_name: 'Edit', tool_input: { file_path: COM } }, {}],
  ['arquivo que não é Controller', { tool_name: 'Read', tool_input: { file_path: join(raiz, 'Modules/NfeBrasil/Http/Controllers/naoehcontroller.txt') } }, {}],
  ['arquivo inexistente', { tool_name: 'Read', tool_input: { file_path: join(raiz, 'Modules/X/Http/Controllers/FantasmaController.php') } }, {}],
  ['payload sem tool_input', { tool_name: 'Read' }, {}],
  ['modo off', { tool_name: 'Read', tool_input: { file_path: COM } }, { OIMPRESSO_CHARTER_TELA_HOOK: 'off' }],
]) {
  const r = roda(payload, env);
  ok(`SILÊNCIO: ${nome}`, r.code === 0 && r.err === '', `(exit=${r.code} err=${r.err.slice(0, 60)})`);
}

const r9 = spawnSync(process.execPath, [HOOK], { input: 'nao é json', encoding: 'utf8', timeout: 15000 });
ok('SILÊNCIO: stdin inválido → fail-open', r9.status === 0 && String(r9.stderr || '') === '');

console.log(falhas === 0 ? '\n✓ bite-test verde' : `\n✗ ${falhas} falha(s)`);
process.exit(falhas === 0 ? 0 : 1);
