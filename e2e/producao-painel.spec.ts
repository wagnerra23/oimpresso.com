import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em Modules/Producao/Resources/js/Pages/Producao/Painel/Index.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). Troque por asserção real de comportamento
// quando a tela Producao/Painel estiver implementada. Locators RESILIENTES (role/label/text), nunca
// classe CSS (L-24). NÃO edite a tela viva sem charter + gate visual.

test.fixme('UC-PCV-01: TODO caminho feliz de Producao/Painel', async ({ page }) => {
  await page.goto('/producao/painel');
  await expect(page.getByText('Atrasadas ou vencendo')).toBeVisible();
  // TODO: Dado/Quando/Então do UC-PCV-01.
});
