import { test, expect } from '@playwright/test';

// Stub E2E — contrato em resources/js/Pages/Jana/Plataforma.casos.md. test.fixme = PENDENTE (não
// executa, não quebra o CI). Os UCs já têm dente na lane MySQL (PlataformaContratoTest); vira asserção
// de browser quando a suíte E2E logar como superadmin. Locators por role/text (L-24).

test.fixme('UC-PLAT-00: a aba Plataforma só existe pra superadmin e abre as duas listas', async ({ page }) => {
  await page.goto('/ia/superadmin/metas');
  await expect(page.getByRole('tab', { name: 'Plataforma' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Metas da plataforma' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Metas de clientes' })).toBeVisible();
});
