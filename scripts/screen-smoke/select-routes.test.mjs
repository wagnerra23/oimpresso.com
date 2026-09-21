import assert from 'node:assert/strict';
import { selectSmokeRoutes } from './select-routes.mjs';

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

console.log('select-routes.test: OK — rota exata libera; Page/componente sem rota fica NÃO MEDIDO.');
