import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/Ponto/Conformidade.casos.md.
// test.fixme = PENDENTE (não executa). O comportamento do UC-CONF-08 já é provado pelo Pest
// (ConformidadeContratoTest); este stub vira asserção quando o harness e2e do Ponto existir.

test.fixme('UC-CONF-08: a tela de Conformidade CLT abre com as verificações', async ({ page }) => {
  await page.goto('/ponto/conformidade');
  await expect(page.getByRole('heading', { name: /Conformidade CLT/ })).toBeVisible();
});
