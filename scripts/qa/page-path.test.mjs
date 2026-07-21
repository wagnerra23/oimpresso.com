import test from 'node:test';
import assert from 'node:assert/strict';
import { isAuxiliaryPagePath, isPageScreenPath } from './page-path.mjs';

test('reconhece telas executáveis', () => {
  assert.equal(isPageScreenPath('Financeiro/Unificado/Index.tsx'), true);
  assert.equal(isPageScreenPath('resources/js/Pages/Sells/Create.tsx'), true);
});

test('rejeita auxiliares, testes e arquivos sem módulo/tela', () => {
  assert.equal(isPageScreenPath('Financeiro/components/Filtro.tsx'), false);
  assert.equal(isPageScreenPath('Financeiro/Unificado/_components/Card.tsx'), false);
  assert.equal(isPageScreenPath('Financeiro/hooks/useSaldo.tsx'), false);
  assert.equal(isPageScreenPath('Financeiro/Index.test.tsx'), false);
  assert.equal(isPageScreenPath('Index.tsx'), false);
  assert.equal(isAuxiliaryPagePath('Financeiro/Unificado/_components/Card.tsx'), true);
});
