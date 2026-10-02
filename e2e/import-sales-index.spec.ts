import { test, expect } from '@playwright/test';

// Importação de vendas (/import-sales) — contrato em resources/js/Pages/ImportSales/Index.casos.md.
// O contrato de comportamento roda em Pest (tests/Feature/Sells/ImportSalesContratoTest.php, lane
// sells-pest). Este stub fica PENDENTE (test.fixme: não executa, não quebra o CI) até existir login de
// E2E para a tela. Locators resilientes (role/label/text), nunca classe CSS (L-24).

test.fixme('UC-IMPV-07: a tela de importação abre com o envio de planilha e as instruções', async ({ page }) => {
  await page.goto('/import-sales');
  await expect(page.getByRole('heading', { name: 'Importação de vendas' })).toBeVisible();
  await expect(page.getByLabel('Arquivo para importar')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Enviar e revisar' })).toBeDisabled();
});
