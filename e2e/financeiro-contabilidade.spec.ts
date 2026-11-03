import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/Financeiro/Contabilidade.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). Troque por asserção real de comportamento
// quando a tela Financeiro/Contabilidade estiver implementada. Locators RESILIENTES (role/label/text), nunca
// classe CSS (L-24). NÃO edite a tela viva sem charter + gate visual.

test.fixme('UC-CONTAB-01: baixo o arquivo do mês para o contador', async ({ page }) => {
  await page.goto('/financeiro/exportacao-contabil');
  await expect(page.getByRole('heading', { name: 'Contabilidade' })).toBeVisible();
  // Quando: escolhe competência e Domínio e clica em gerar · Então: download lancamentos_dominio_AAAAMM.txt.
  await expect(page.getByRole('button', { name: /Gerar arquivo do Domínio/ })).toBeVisible();
});

test.fixme('UC-CONTAB-00: chego na tela pelo menu do Financeiro, sem digitar URL', async ({ page }) => {
  await page.goto('/financeiro/unificado');
  await page.getByRole('link', { name: 'Contabilidade' }).click();
  await expect(page).toHaveURL(/\/financeiro\/exportacao-contabil/);
});
