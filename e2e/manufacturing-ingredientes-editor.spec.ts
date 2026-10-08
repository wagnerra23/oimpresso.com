import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/Manufacturing/IngredientesEditor.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). Troque por asserção real de comportamento
// quando a tela Manufacturing/IngredientesEditor estiver implementada. Locators RESILIENTES (role/label/text), nunca
// classe CSS (L-24). NÃO edite a tela viva sem charter + gate visual.

test.fixme('UC-INGRED-01: TODO caminho feliz de Manufacturing/IngredientesEditor', async ({ page }) => {
  await page.goto('/manufacturing/add-ingredient?tela=nova');
  await expect(page.getByRole('heading', { name: 'IngredientesEditor' })).toBeVisible();
  // TODO: Dado/Quando/Então do UC-INGRED-01.
});
