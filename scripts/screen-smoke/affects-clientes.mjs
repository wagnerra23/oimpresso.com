#!/usr/bin/env node
// affects-clientes.mjs — quais clientes vivos um PR afeta?
//
// Uso:
//   git diff --name-only <base> HEAD | node scripts/screen-smoke/affects-clientes.mjs
//   node scripts/screen-smoke/affects-clientes.mjs --selftest
//
// Lê da entrada os arquivos alterados (um por linha) e imprime, um por linha, os labels
// `affects/<cliente>` dos clientes que usam alguma tela cujo `source` mudou. A fonte é o
// campo `clientes` de scripts/screen-smoke/routes.json — o mesmo que o job smoke-clientes
// abre em todo deploy. Não decide nada sobre o PR: só rotula (decisão [W] 2026-11-15:
// label, sem comentário automático).
//
// Exit: 0 = calculou (pode não imprimir nada) · 2 = NÃO MEDI (routes.json ilegível ou sem
// nenhuma rota de cliente) — nunca vira "não afeta ninguém".

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { clientesDa } from './select-routes.mjs';

/** Todos os labels que este mecanismo gerencia (para remover os que deixaram de valer). */
export function labelsGerenciados(routes) {
  const todos = new Set();
  for (const r of routes) for (const c of clientesDa(r)) todos.add(`affects/${c}`);
  return [...todos].sort();
}

/** Labels `affects/<cliente>` para a lista de arquivos alterados. */
export function labelsDoDiff(routes, arquivos) {
  const mudou = new Set(arquivos.map((a) => a.trim()).filter(Boolean));
  const out = new Set();
  for (const r of routes) {
    if (!r.source || !mudou.has(r.source)) continue;
    for (const c of clientesDa(r)) out.add(`affects/${c}`);
  }
  return [...out].sort();
}

function selftest() {
  const routes = [
    { label: 'venda', source: 'resources/js/Pages/Sells/Create.tsx', clientes: ['rota-livre'] },
    { label: 'espelho', source: 'resources/js/Pages/Ponto/Espelho/Index.tsx', clientes: ['wr2'] },
    { label: 'dre', source: 'resources/js/Pages/Financeiro/Dre/Index.tsx' },
  ];
  const eq = (a, b, msg) => { if (JSON.stringify(a) !== JSON.stringify(b)) { console.error(`FALHOU: ${msg}: ${JSON.stringify(a)} != ${JSON.stringify(b)}`); process.exit(1); } };
  eq(labelsDoDiff(routes, ['resources/js/Pages/Sells/Create.tsx']), ['affects/rota-livre'], 'tela da Larissa');
  eq(labelsDoDiff(routes, ['resources/js/Pages/Ponto/Espelho/Index.tsx', 'resources/js/Pages/Sells/Create.tsx']), ['affects/rota-livre', 'affects/wr2'], 'as duas');
  eq(labelsDoDiff(routes, ['resources/js/Pages/Financeiro/Dre/Index.tsx', 'README.md']), [], 'tela sem cliente não rotula');
  eq(labelsDoDiff(routes, ['resources/js/Pages/Sells/Create.tsx.bak']), [], 'casamento exato, não prefixo');
  eq(labelsGerenciados(routes), ['affects/rota-livre', 'affects/wr2'], 'labels gerenciados');
  const real = JSON.parse(readFileSync(new URL('./routes.json', import.meta.url), 'utf8')).routes;
  eq(labelsGerenciados(real), ['affects/rota-livre', 'affects/wr2'], 'routes.json real cobre os dois clientes');
  console.log('affects-clientes selftest: OK');
}

function main(argv) {
  if (argv.includes('--selftest')) return selftest();
  let routes;
  try {
    routes = JSON.parse(readFileSync(new URL('./routes.json', import.meta.url), 'utf8')).routes;
  } catch (e) {
    console.error(`NÃO MEDI: routes.json ilegível (${e.message})`);
    process.exit(2);
  }
  if (!Array.isArray(routes) || labelsGerenciados(routes).length === 0) {
    console.error('NÃO MEDI: nenhuma rota com `clientes` em routes.json');
    process.exit(2);
  }
  const entrada = readFileSync(0, 'utf8').split('\n');
  for (const l of labelsDoDiff(routes, entrada)) console.log(l);
}

if (process.argv[1] === fileURLToPath(import.meta.url)) main(process.argv.slice(2));
