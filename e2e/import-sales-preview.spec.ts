import { test, expect } from '@playwright/test';

// Prévia da importação de vendas — contrato em resources/js/Pages/ImportSales/Preview.casos.md.
// A prévia é a resposta do POST da planilha (não tem URL própria). O contrato de comportamento roda
// em Pest (tests/Feature/Sells/ImportSalesContratoTest.php). Stub PENDENTE (test.fixme) até existir
// login de E2E + planilha de fixture para o upload.

test.fixme('UC-IMPV-09: a prévia mostra as linhas e quantas vendas vão nascer', async ({ page }) => {
  await page.goto('/import-sales');
  await page.getByLabel('Arquivo para importar').setInputFiles('e2e/fixtures/vendas-import.csv');
  await page.getByRole('button', { name: 'Enviar e revisar' }).click();
  await expect(page.getByRole('heading', { name: 'Prévia da importação' })).toBeVisible();
});
