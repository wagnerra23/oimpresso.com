import test from 'node:test';
import assert from 'node:assert/strict';
import { isAuxiliaryPagePath, isPageScreenPath, isUnderPagesRoot } from './page-path.mjs';

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

// As DUAS raizes: nucleo e modulo dono. Sem esta cobertura, um consumidor que escreva a
// propria regex ancorada em resources/js/Pages fica cego pras 88 telas que moram em
// Modules/<X>/Resources/js/Pages e sai VERDE sem medir (medido 2026-09-03).
test('isUnderPagesRoot aceita as duas raizes de Pages', () => {
  assert.equal(isUnderPagesRoot('resources/js/Pages/Sells/Index.tsx'), true);
  assert.equal(isUnderPagesRoot('Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.tsx'), true);
  // a convencao nWidart deste repo e Resources/ maiusculo, mas a minuscula tambem conta
  assert.equal(isUnderPagesRoot('Modules/Whatsapp/resources/js/Pages/Inbox/Index.tsx'), true);
  // separador do Windows normalizado antes do teste
  assert.equal(isUnderPagesRoot(String.raw`resources\js\Pages\Sells\Index.tsx`), true);
});

test('isUnderPagesRoot rejeita o que nao esta sob raiz de Pages', () => {
  assert.equal(isUnderPagesRoot('resources/js/Components/ui/Button.tsx'), false);
  assert.equal(isUnderPagesRoot('Modules/Forja/Http/Controllers/TrabalhoController.php'), false);
  assert.equal(isUnderPagesRoot('Modules/Forja/Resources/js/Components/Card.tsx'), false);
  assert.equal(isUnderPagesRoot(''), false);
});
