import assert from 'node:assert/strict';
import { selectSmokeRoutes } from './select-routes.mjs';
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

// routes.json: viewport sem largura/altura inteira derruba TODA rota no smoke
// (setViewportSize recebe undefined). Em 2026-10-09 três rotas entraram no
// array de viewports em vez de routes, e o smoke passou a dar QUEBRADO em tudo.
const cfg = JSON.parse(readFileSync(new URL('./routes.json', import.meta.url), 'utf8'));
for (const vp of cfg.viewports) {
  assert.ok(Number.isInteger(vp.width) && Number.isInteger(vp.height),
    `viewport sem width/height inteiros em routes.json: ${JSON.stringify(vp)}`);
}
for (const r of cfg.routes) {
  assert.ok(typeof r.label === 'string' && typeof r.path === 'string',
    `rota sem label/path em routes.json: ${JSON.stringify(r)}`);
}

console.log('select-routes.test: OK — rota exata libera; Page/componente sem rota fica NÃO MEDIDO.');
