import { test, expect } from '@playwright/test';

// E2E do Painel do Patrimônio — contrato em resources/js/Pages/Patrimonio/Index.casos.md.
//
// test.fixme = PENDENTE (não executa, não quebra o CI). Fica assim de propósito: 11 dos 15
// specs deste diretório são `fixme`, e marcar como executável um teste que a lane não roda
// seria afirmar cobertura inexistente. Os 3 UCs do SubNav (UC-PAT-02/03/04) têm teste que
// RODA em tests/patrimonioSubNav.spec.ts — é de lá que vem a prova hoje.
//
// Locators RESILIENTES (role/label/text), nunca classe CSS (L-24).

test.fixme('UC-PAT-01: o painel mostra os 4 KPIs do próprio business', async ({ page }) => {
  await page.goto('/asset/dashboard');
  await expect(page.getByRole('heading', { name: 'Patrimônio' })).toBeVisible();

  // Os 4 KPIs, com a copy literal do protótipo.
  for (const rotulo of ['Patrimônio bruto', 'Valor residual', 'Alocados', 'Garantia vencida ou vencendo']) {
    await expect(page.getByText(rotulo, { exact: true })).toBeVisible();
  }

  // Os 3 blocos de análise.
  for (const titulo of ['Patrimônio por categoria', 'Situação da garantia', 'Manutenção em aberto']) {
    await expect(page.getByRole('heading', { name: titulo })).toBeVisible();
  }
});

test.fixme('UC-PAT-05: número sem fonte mostra travessão, nunca zero', async ({ page }) => {
  await page.goto('/asset/dashboard');
  // "Valor residual" não é calculado (a regra de depreciação é decisão [W] em aberto) —
  // o card mostra o travessão. Um `R$ 0,00` aqui seria afirmar que não sobrou valor nenhum,
  // que é diferente de não saber.
  const residual = page.getByText('Valor residual', { exact: true }).locator('xpath=ancestor::*[@data-slot][1]');
  await expect(residual).toContainText('—');
  await expect(residual).not.toContainText('R$');
});
