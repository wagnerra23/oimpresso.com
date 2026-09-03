import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/Jana/Alertas.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). Os UCs já têm dente na lane MySQL
// (Modules/Jana/Tests/Feature/AlertasContratoTest.php); este stub vira asserção de browser quando a
// suíte E2E tiver seed de meta com apuração. Locators RESILIENTES (role/label/text), nunca classe CSS (L-24).

test.fixme('UC-ALERTA-01: a lista mostra só o que dispara, com o corte do servidor', async ({ page }) => {
  await page.goto('/ia/alertas');
  await expect(page.getByRole('tab', { name: 'Alertas' })).toBeVisible();
  await expect(page.getByText(/disparando · corte em \d+%/)).toBeVisible();
  // TODO: com meta semeada acima do corte, a linha aparece; abaixo, o empty state "Nenhum desvio acima do corte".
});
