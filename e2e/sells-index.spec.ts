import { test, expect } from '@playwright/test';

// E2E de comportamento — LISTA DE VENDAS (Onda Q2, ADR 0264 G-3).
// Contrato: resources/js/Pages/Sells/Index.casos.md
//   UC-S10 · a lista de vendas abre com as pílulas de status (visão de cobrança).
// (UC-S11 · menu Ações→Devolução vive em sells-venda-balcao.spec.ts, onde há
//  uma venda real criada por UC-S01 pra ter linha na lista.)
// Locators RESILIENTES — role/text (L-24).

test('UC-S10 · lista de vendas renderiza com pílulas de status', async ({ page }) => {
  await page.goto('/sells');
  await page.waitForLoadState('networkidle');

  await expect(page.getByRole('heading', { name: 'Vendas' })).toBeVisible({ timeout: 15_000 });
  // Pílulas de classificação por payment_status (Todas é a default).
  await expect(page.getByText('Todas', { exact: true }).first()).toBeVisible();
});
