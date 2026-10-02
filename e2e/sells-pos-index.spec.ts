import { test, expect } from '@playwright/test';

// Stub E2E da Lista de POS — contrato em resources/js/Pages/Sells/Pos/Index.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). Os UC-POS-01..05 têm teste de contrato
// em tests/Feature/Sells/SellsPosIndexContratoTest.php; estes três dependem de clique real.

test.fixme('UC-POS-06: Ver detalhe abre o drawer com itens que somam o total', async ({ page }) => {
  await page.goto('/pos');
  await page.getByRole('button', { name: /Ações da venda/ }).first().click();
  await page.getByRole('menuitem', { name: 'Ver detalhe' }).click();
  await expect(page.getByRole('dialog')).toBeVisible();
});

test.fixme('UC-POS-07: Imprimir recibo abre a folha de impressão', async ({ page }) => {
  await page.goto('/pos');
  await page.getByRole('button', { name: /Ações da venda/ }).first().click();
  await expect(page.getByRole('menuitem', { name: 'Imprimir recibo' })).toBeVisible();
});

test.fixme('UC-POS-08: Devolver venda leva à devolução com a venda no contexto', async ({ page }) => {
  await page.goto('/pos');
  await page.getByRole('button', { name: /Ações da venda/ }).first().click();
  await page.getByRole('menuitem', { name: 'Devolver venda' }).click();
  await expect(page).toHaveURL(/\/sell-return\/add\/\d+/);
});
