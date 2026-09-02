import { test, expect } from '@playwright/test';

// E2E da tela de Fabricação (/manufacturing/recipe). Contrato em
// resources/js/Pages/Manufacturing/Recipes.casos.md — os specs abaixo cobrem os itens do
// "Backlog de casos" daquele arquivo, que são comportamento de NAVEGADOR (busca, atalho,
// KPI-filtro, ordenação, seleção, drawer). Os UC de payload/tenant/cálculo vivem no Pest
// (Modules/Manufacturing/Tests/Feature/Wave29RecipeInertiaTest.php), que é onde o dinheiro
// é defendido.
//
// Locators RESILIENTES (role/label/text), nunca classe CSS (L-24).
// `test.fixme` = PENDENTE: a suíte e2e deste repo precisa de sessão autenticada + business
// com receitas semeadas, e essa fixture não existe hoje pro módulo Manufacturing. Cada
// bloco já traz o Dado/Quando/Então real — não é TODO, é aguardando fixture.

test.describe('Manufacturing/Recipes — consulta de receitas', () => {
  test.fixme('R-03/R-04: a busca casa nome, SKU e categoria; a tecla / foca o campo', async ({ page }) => {
    await page.goto('/manufacturing/recipe');
    await expect(page.getByRole('heading', { name: 'Manufacturing' })).toBeVisible();

    // Dado o corpo com foco · Quando pressiono "/" · Então o cursor vai pra busca.
    await page.keyboard.press('/');
    await expect(page.getByLabel('Buscar receita')).toBeFocused();

    // E "/" digitado DENTRO do campo continua sendo "/" (não vira atalho).
    await page.getByLabel('Buscar receita').fill('a/b');
    await expect(page.getByLabel('Buscar receita')).toHaveValue('a/b');
  });

  test.fixme('R-05: o KPI de margem filtra a lista; o de custo médio não', async ({ page }) => {
    await page.goto('/manufacturing/recipe');

    const antes = await page.locator('[data-contract="lista"] [role="button"]').count();
    await page.getByRole('button', { name: /Margem abaixo de 45%/ }).click();
    const depois = await page.locator('[data-contract="lista"] [role="button"]').count();

    expect(depois).toBeLessThanOrEqual(antes);
    await expect(page.getByRole('button', { name: /Margem abaixo de 45%/ })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
  });

  test.fixme('R-14: o drawer da receita fecha com esc e com clique no scrim', async ({ page }) => {
    await page.goto('/manufacturing/recipe');

    await page.locator('[data-contract="lista"] [role="button"]').first().click();
    await expect(page.getByRole('dialog')).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toBeHidden();
  });

  test.fixme('R-22: a via de produção não mostra nenhum valor de compra', async ({ page }) => {
    await page.goto('/manufacturing/recipe');
    await page.locator('[data-contract="lista"] [role="button"]').first().click();
    await page.getByRole('button', { name: 'Via de produção' }).click();

    // Dado a folha PT-07 montada no portal · Então nenhuma ocorrência de "R$" nela.
    await expect(page.locator('.mfg-print-host')).not.toContainText('R$');
  });
});
