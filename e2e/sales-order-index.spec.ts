import { test, expect } from '@playwright/test';

// Stub E2E — contrato em resources/js/Pages/SalesOrder/Index.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). O contrato de comportamento hoje é o Pest
// tests/Feature/Sells/SalesOrderIndexContratoTest.php. Locators resilientes (role/label/text),
// nunca classe CSS (L-24).

test.fixme('UC-SORD-03: a tela Pedido de venda abre pelo browser', async ({ page }) => {
  await page.goto('/sales-order');
  await expect(page.getByRole('heading', { name: 'Pedido de venda' })).toBeVisible();
});
