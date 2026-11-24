import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/SellReturn/Create.casos.md.
// O comportamento já é provado no Pest (tests/Feature/Sells/SellReturnCreateContratoTest.php).
// test.fixme = PENDENTE (não executa, não quebra o CI) até a lane E2E ter sessão autenticada.
// Locators RESILIENTES (role/label/text), nunca classe CSS (L-24).

test.fixme('UC-SRCRE-01: + Nova devolução abre a escolha da venda', async ({ page }) => {
  await page.goto('/sell-return');
  await page.getByRole('link', { name: '+ Nova devolução' }).click();
  await expect(page).toHaveURL(/sell-return\/create/);
  await expect(page.getByRole('heading', { name: 'Nova devolução' })).toBeVisible();
});
