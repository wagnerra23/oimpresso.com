import { test, expect } from '@playwright/test';

// Stub E2E — contrato em resources/js/Pages/Discount/Index.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). O contrato de comportamento hoje é o Pest
// tests/Feature/Sells/DescontosContratoTest.php. Locators resilientes (role/label/text),
// nunca classe CSS (L-24).

test.fixme('UC-DSC-08: a tela Descontos abre pelo browser', async ({ page }) => {
  await page.goto('/discount');
  await expect(page.getByRole('heading', { name: 'Descontos' })).toBeVisible();
});
