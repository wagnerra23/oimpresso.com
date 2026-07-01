import { test, expect } from '@playwright/test';

// E2E de comportamento — LISTA DE VENDAS (Onda Q2, ADR 0264 G-3).
// Contrato: resources/js/Pages/Sells/Index.casos.md
//   UC-S10 · a lista de vendas abre com as pílulas de status (visão de cobrança).
//   UC-S11 · da lista, o menu de Ações da venda oferece Devolução → /sell-return/add.
// Locators RESILIENTES — role/text (L-24).

test('UC-S10 · lista de vendas renderiza com pílulas de status', async ({ page }) => {
  await page.goto('/sells');
  await page.waitForLoadState('networkidle');

  await expect(page.getByRole('heading', { name: 'Vendas' })).toBeVisible({ timeout: 15_000 });
  // Pílulas de classificação por payment_status (Todas é a default).
  await expect(page.getByText('Todas', { exact: true }).first()).toBeVisible();
});

test('UC-S11 · menu de Ações da venda oferece Devolução → /sell-return/add', async ({ page }) => {
  await page.goto('/sells');
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: 'Vendas' })).toBeVisible({ timeout: 15_000 });

  // Abre o menu ⋮ da primeira linha (kebab restaurado — regressão do #1032).
  const acoes = page.getByRole('button', { name: 'Ações da venda' }).first();
  await expect(acoes).toBeVisible({ timeout: 15_000 });
  await acoes.click();

  // O item Devolução existe e aponta pro formulário de retorno da venda (→ estoque).
  const devolucao = page.getByRole('menuitem', { name: 'Devolução' });
  await expect(devolucao).toBeVisible();
  await expect(devolucao).toHaveAttribute('href', /\/sell-return\/add\/\d+/);
});
