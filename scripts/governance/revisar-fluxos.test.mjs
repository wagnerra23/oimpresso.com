#!/usr/bin/env node
// revisar-fluxos.test.mjs — bite-test: path fantasma, dimensão ausente, wiring e prova.
import { mkdirSync, mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { revisar, slugDoRemote } from './revisar-fluxos.mjs';

const root = mkdtempSync(join(tmpdir(), 'revisar-fluxos-'));
const put = (p, s) => { const a = join(root, p); mkdirSync(dirname(a), { recursive: true }); writeFileSync(a, s); };
let fails = 0;
const test = (nome, ok) => { console.log(`${ok ? '✓' : '✗'} ${nome}`); if (!ok) fails++; };

test('remote com ponto no nome resolve o slug', slugDoRemote('git@github.com:wagnerra23/oimpresso.com.git') === 'wagnerra23/oimpresso.com');

put('memory/reference/FLUXO-TESTE.md', `---
authority: canonical
lifecycle: ativo
---
# Fluxo
## E1
Entrada confiável. O workflow é o invocador. A decisão bloqueia.
Grava saída e recibo. Falso-verde conhecido. Prova por teste. Limite: não mede runtime.
Máquina: \`scripts/demo.mjs\`.
`);
put('scripts/demo.mjs', '// demo.mjs — máquina de fixture\n');
put('scripts/demo.test.mjs', '// teste\n');
put('.github/workflows/demo.yml', `on: pull_request
jobs:
  t:
    steps:
      - run: node scripts/demo.mjs --check
      - run: node scripts/demo.test.mjs
`);
put('package.json', '{}\n');

let r = revisar(root);
test('descobre fluxo e todas as dimensões', r.resumo.fluxos === 1 && r.documentos[0].faltam.length === 0);
test('resolve máquina existente', r.maquinas.length === 1 && r.maquinas[0].existe);
test('localiza invocador real', r.maquinas[0].invocadores.includes('.github/workflows/demo.yml'));
test('prova exige teste + wiring', r.maquinas[0].prova === 'teste+wiring');
test('controle: fixture fiel não tem falha concreta', r.falhas_concretas.length === 0);

put('memory/reference/FLUXO-TESTE.md', '# Fluxo\nMáquina: `scripts/fantasma.mjs`.\n');
r = revisar(root);
test('MORDE path fantasma', r.falhas_concretas.some((x) => /path fantasma/.test(x)));
test('MORDE contrato documental incompleto', r.documentos[0].faltam.includes('falso_verde') && r.documentos[0].faltam.includes('prova'));
test('não usa baseline', r._meta.baseline_usada === false && r.enforcement.status === 'NAO_MEDIDO');

console.log(fails ? `\n${fails} falha(s)` : '\nOK — revisão morde path fantasma e falta documental, e prova wiring sem baseline.');
process.exit(fails ? 1 : 0);
