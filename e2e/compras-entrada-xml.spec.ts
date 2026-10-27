import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/Compras/EntradaXml.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). Troque por asserção real de comportamento
// quando a tela Compras/EntradaXml estiver implementada. Locators RESILIENTES (role/label/text), nunca
// classe CSS (L-24). NÃO edite a tela viva sem charter + gate visual.

test.fixme('UC-ENTRAD-01: sem a flag a tela não abre; com a flag, o cabeçalho aparece', async ({ page }) => {
  await page.goto('/compras/entrada-xml');
  await expect(page.getByRole('heading', { name: 'Entrada por XML' })).toBeVisible();
  // TODO: Dado/Quando/Então do UC-ENTRAD-01.
});
