import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/Marketplaces/Pedidos.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). Troque por asserção real de comportamento
// quando a tela Marketplaces/Pedidos estiver implementada. Locators RESILIENTES (role/label/text), nunca
// classe CSS (L-24). NÃO edite a tela viva sem charter + gate visual.

test.fixme('UC-PEDIDO-01: a lista de pedidos dos marketplaces abre com o título', async ({ page }) => {
  await page.goto('/marketplaces/pedidos');
  await expect(page.getByRole('heading', { name: 'Pedidos dos marketplaces' })).toBeVisible();
});
