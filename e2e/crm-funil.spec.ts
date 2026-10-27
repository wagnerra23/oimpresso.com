import { test, expect } from '@playwright/test';

// Stub E2E do funil de vendas — contrato em Modules/Crm/Resources/js/Pages/Crm/Funil/Index.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). O contrato HTTP (UC-CRMFN-01..05) já roda
// no Pest MySQL (CrmFunilContratoTest). Locators RESILIENTES (role/label/text), nunca classe CSS (L-24).

test.fixme('UC-CRMFN-01: o funil abre com as 6 etapas para quem pode ver', async ({ page }) => {
  await page.goto('/crm/funil');
  await expect(page.getByRole('heading', { name: 'Funil de vendas' })).toBeVisible();
  for (const etapa of ['Lead', 'Qualificado', 'Proposta', 'Negociação', 'Ganho', 'Perdido']) {
    await expect(page.getByRole('region', { name: `Etapa ${etapa}` })).toBeVisible();
  }
});
