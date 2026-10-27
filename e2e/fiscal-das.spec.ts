import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/Fiscal/Das.casos.md.
// test.fixme = PENDENTE: a tela está atrás de `fiscal.das_apuracao_enabled` (default false), então
// o E2E só faz sentido depois que [W] ligar a flag. O contrato vivo hoje é o DasControllerTest.

test.fixme('UC-FDAS-03: sem anexo a tela pede o anexo do contador', async ({ page }) => {
  await page.goto('/fiscal/das');
  await expect(page.getByText('Escolha o anexo que o contador usa')).toBeVisible();
});
