import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/Documentacao/CasosPorTela.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). Troque por asserção real de comportamento
// quando a tela Documentacao/CasosPorTela estiver implementada. Locators RESILIENTES (role/label/text), nunca
// classe CSS (L-24). NÃO edite a tela viva sem charter + gate visual.

test.fixme('UC-CASOSP-01: a equipe abre Documentacao/CasosPorTela e vê a lista de casos', async ({ page }) => {
  await page.goto('/documentacao/casos-por-tela');
  await expect(page.getByRole('heading', { name: 'Casos de uso por tela' })).toBeVisible();
  // O contrato roda no Pest (tests/Feature/Documentacao/CasosPorTelaContratoTest.php); este
  // stub fica pendente até a lane E2E ter um usuário da equipe semeado.
});
