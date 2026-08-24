import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/Arquivos/Index.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). Troque por asserção real de comportamento
// quando a tela Arquivos/Index estiver implementada. Locators RESILIENTES (role/label/text), nunca
// classe CSS (L-24). NÃO edite a tela viva sem charter + gate visual.

test.fixme('UC-INDEX-01: TODO caminho feliz de Arquivos/Index', async ({ page }) => {
  await page.goto('/TODO-rota');
  await expect(page.getByRole('heading', { name: 'Index' })).toBeVisible();
  // TODO: Dado/Quando/Então do UC-INDEX-01.
});
