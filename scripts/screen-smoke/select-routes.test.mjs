import assert from 'node:assert/strict';
import { selectSmokeRoutes, clientesDa, alertasPorCliente } from './select-routes.mjs';
import { readFileSync } from 'node:fs';

const routes = [
  { label: 'login', nav_critical: true },
  { label: 'vendas', nav_critical: true, source: 'resources/js/Pages/Sells/Index.tsx' },
  { label: 'financeiro', source: 'resources/js/Pages/Financeiro/Index.tsx' },
  { label: 'modulo', source: 'Modules/Exemplo/Resources/js/Pages/Index.tsx' },
];

let result = selectSmokeRoutes(routes, '__MANUAL__');
assert.equal(result.routes.length, 4);
assert.deepEqual(result.unmatched, []);

result = selectSmokeRoutes(routes, '__NAV__');
assert.deepEqual(result.routes.map((route) => route.label), ['login', 'vendas']);
assert.deepEqual(result.unmatched, []);

result = selectSmokeRoutes(routes, 'resources/js/Pages/Financeiro/Index.tsx');
assert.deepEqual(result.routes.map((route) => route.label), ['login', 'vendas', 'financeiro']);
assert.deepEqual(result.unmatched, []);

result = selectSmokeRoutes(routes, 'Modules/Exemplo/Resources/js/Pages/Index.tsx');
assert.deepEqual(result.routes.map((route) => route.label), ['login', 'vendas', 'modulo']);
assert.deepEqual(result.unmatched, []);

result = selectSmokeRoutes(routes, 'resources/js/Pages/Jana/_components/JanaAreaHeader.tsx');
assert.deepEqual(result.routes.map((route) => route.label), ['login', 'vendas']);
assert.deepEqual(result.unmatched, ['resources/js/Pages/Jana/_components/JanaAreaHeader.tsx']);

result = selectSmokeRoutes(routes, 'resources/js/Pages/Financeiro/Index.tsx,resources/js/Pages/Nova/Index.tsx');
assert.deepEqual(result.unmatched, ['resources/js/Pages/Nova/Index.tsx']);

// __CLIENTES__: só rotas com `clientes`, independente do diff e do nav_critical.
const comClientes = [
  ...routes,
  { label: 'venda-larissa', source: 'resources/js/Pages/Sells/Create.tsx', clientes: ['rota-livre'] },
  { label: 'ponto-wr2', clientes: ['wr2'] },
  { label: 'lixo', clientes: 'rota-livre' },
  { label: 'vazio', clientes: [] },
];
result = selectSmokeRoutes(comClientes, '__CLIENTES__');
assert.deepEqual(result.routes.map((route) => route.label), ['venda-larissa', 'ponto-wr2']);
assert.deepEqual(result.unmatched, []);
assert.deepEqual(clientesDa({ clientes: ['wr2', '', 3] }), ['wr2']);
assert.deepEqual(clientesDa({}), []);

// ALERT: uma linha por cliente, só com rotas quebradas ou não exercidas; tudo OK = nenhuma.
const alertas = alertasPorCliente([
  { label: 'venda', clientes: ['rota-livre'], verdict: 'QUEBRADO' },
  { label: 'caixa', clientes: ['rota-livre'], verdict: 'OK' },
  { label: 'espelho', clientes: ['wr2'], skipped: true },
  { label: 'aviso', clientes: ['wr2'], verdict: 'AVISO' },
  { label: 'sem-cliente', verdict: 'QUEBRADO' },
], '42');
assert.deepEqual(alertas, [
  '[smoke] ::error::ALERT smoke-clientes cliente=rota-livre run=42 venda=QUEBRADO',
  '[smoke] ::error::ALERT smoke-clientes cliente=wr2 run=42 espelho=NAO-EXERCIDA',
]);
assert.deepEqual(alertasPorCliente([{ label: 'v', clientes: ['wr2'], verdict: 'OK' }]), []);

// routes.json: viewport sem largura/altura inteira derruba TODA rota no smoke
// (setViewportSize recebe undefined). Em 2026-10-09 três rotas entraram no
// array de viewports em vez de routes, e o smoke passou a dar QUEBRADO em tudo.
const cfg = JSON.parse(readFileSync(new URL('./routes.json', import.meta.url), 'utf8'));
for (const vp of cfg.viewports) {
  assert.ok(Number.isInteger(vp.width) && Number.isInteger(vp.height),
    `viewport sem width/height inteiros em routes.json: ${JSON.stringify(vp)}`);
}
const CLIENTES_VALIDOS = new Set(['rota-livre', 'wr2']);
for (const r of cfg.routes) {
  assert.ok(typeof r.label === 'string' && typeof r.path === 'string',
    `rota sem label/path em routes.json: ${JSON.stringify(r)}`);
  if (r.clientes !== undefined) {
    assert.ok(Array.isArray(r.clientes) && r.clientes.length > 0 && r.clientes.every((c) => CLIENTES_VALIDOS.has(c)),
      `rota ${r.label}: clientes precisa ser lista não vazia de ${[...CLIENTES_VALIDOS].join('|')}`);
  }
}
// Os dois clientes vivos têm rota: sem isso o job smoke-clientes abriria zero telas e sairia verde.
for (const c of CLIENTES_VALIDOS) {
  assert.ok(cfg.routes.some((r) => clientesDa(r).includes(c)), `nenhuma rota marcada para o cliente ${c}`);
}

console.log('select-routes.test: OK — rota exata libera; Page/componente sem rota fica NÃO MEDIDO.');
