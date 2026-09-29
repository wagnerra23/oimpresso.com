import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/Ponto/Mobile/Index.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). O comportamento hoje é provado pelo Pest
// (RepPMobileContratoTest). Vira teste real quando houver GPS simulado no Playwright
// (context.setGeolocation) — sem ele o botão fica desabilitado por desenho (W5).
// Locators RESILIENTES (role/label/text), nunca classe CSS (L-24).

test.fixme('UC-REPP-01: bater ponto registra a marcação e ela aparece em Hoje', async ({ page, context }) => {
  await context.grantPermissions(['geolocation']);
  await context.setGeolocation({ latitude: -28.336, longitude: -48.926, accuracy: 15 });
  await page.goto('/ponto/mobile');
  await page.getByRole('button', { name: /Bater ponto — Entrada/ }).click();
  await expect(page.getByRole('region', { name: 'Hoje' }).getByText('Entrada')).toBeVisible();
});
