import { test, expect } from '@playwright/test';

// E2E de Essentials/Metas (/hrm/sales-target) — contrato em
// resources/js/Pages/Essentials/Metas.casos.md.
//
// PENDENTE de propósito (`test.fixme` = não executa, não quebra o CI). O comportamento dos
// UCs desta tela já é provado HOJE por Pest na lane `essentials-pest` (MySQL real, tenant 98):
// Modules/Essentials/Tests/Feature/HrmMetasTest.php cobre UC-METAS-01..07.
//
// O que só o E2E consegue provar, e que este stub reserva: o diálogo de faixas de ponta a
// ponta no navegador — adicionar linha, digitar em pt-BR, salvar, e ver a lista refletir.
// Isso exige servidor autenticado, que a lane Pest não tem.
//
// Locators RESILIENTES (role/label/text), nunca classe CSS (L-24).

test.fixme('UC-METAS-01: a tela lista os colaboradores e as faixas de meta', async ({ page }) => {
  await page.goto('/hrm/sales-target');

  await expect(page.getByRole('heading', { name: 'Metas de venda' })).toBeVisible();
  await expect(
    page.getByRole('table', { name: 'Colaboradores e as faixas de meta de venda cadastradas' }),
  ).toBeVisible();
});

test.fixme('UC-METAS-05: definir uma faixa pelo diálogo grava e a lista reflete', async ({ page }) => {
  await page.goto('/hrm/sales-target');

  await page.getByRole('button', { name: /Definir meta|Editar faixas/ }).first().click();
  await expect(page.getByRole('dialog')).toBeVisible();

  await page.getByRole('button', { name: 'Adicionar faixa' }).click();
  await page.getByLabel('Vendido de').last().fill('1.000,00');
  await page.getByLabel('até').last().fill('2.000,00');
  await page.getByLabel('Comissão (%)').last().fill('5,00');
  await page.getByRole('button', { name: 'Salvar faixas' }).click();

  // A linha passa a "com meta" — o valor exibido vem do banco, não do que foi digitado.
  await expect(page.getByText('com meta').first()).toBeVisible();
});
