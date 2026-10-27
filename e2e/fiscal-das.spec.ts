import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/Fiscal/Das.casos.md.
// test.fixme = PENDENTE: a tela está atrás da flag por empresa `useV2FiscalDas` (desligada por padrão), então
// o E2E só faz sentido com a flag ligada no tenant do E2E. O contrato vivo hoje é o DasControllerTest.

test.fixme('UC-FDAS-03: sem anexo a tela pede o anexo do contador', async ({ page }) => {
  await page.goto('/fiscal/das');
  await expect(page.getByText('Escolha o anexo que o contador usa')).toBeVisible();
});
