import { test, expect } from '@playwright/test';

// E2E — formulário de regra tributária (`/nfe-brasil/tributacao/regras/create`). Gate G-3 (ADR 0264).
// Playbook Fiscal thread 05 · UC-NFRF-08 (`resources/js/Pages/NfeBrasil/Tributacao/RegraForm.casos.md`).
//
// A regressão que este caso defende é "o backend aceita e a tela nunca manda": a validação dos 5
// campos de IBS/CBS (thread 04) não serve se o formulário não os envia. O lado do servidor (reabrir
// a edição traz os 5) é o Pest `RegraTributariaIbsCbsValidacaoTest` · UC-NFRF-08.
//
// O POST é INTERCEPTADO e abortado: o caso lê o corpo que a tela mandaria e não grava regra nenhuma
// no tenant do ambiente. Locators resilientes (L-24), zero `waitForTimeout`, não roda local (ADR 0062).

test('UC-NFRF-08 · a seção Reforma tributária vai no corpo do POST', async ({ page }) => {
  let corpo: Record<string, unknown> | null = null;
  await page.route('**/nfe-brasil/tributacao/regras', (route) => {
    if (route.request().method() !== 'POST') return route.continue();
    corpo = route.request().postDataJSON() as Record<string, unknown>;
    return route.abort();
  });

  await page.goto('/nfe-brasil/tributacao/regras/create');
  await expect(page.getByRole('heading', { name: 'Nova regra tributária' })).toBeVisible({ timeout: 15_000 });
  await expect(page.getByText('Reforma tributária', { exact: true })).toBeVisible();

  await page.getByLabel('NCM (8 dígitos) *').fill('39219019');
  await page.getByLabel('cClassTrib (6 dígitos)').fill('000001');
  await page.getByLabel('CST IBS (3 dígitos)').fill('000');
  await page.getByLabel('CST CBS (3 dígitos)').fill('000');
  await page.getByLabel('IBS', { exact: true }).fill('0.001');
  await page.getByLabel('CBS', { exact: true }).fill('0.009');

  // CONTROLE POSITIVO do UC: trocar o regime (CSOSN → CST → CSOSN) não apaga a seção da reforma.
  const regime = page.getByRole('combobox').filter({ hasText: 'Simples (CSOSN)' });
  await regime.click();
  await page.getByRole('option', { name: 'Normal (CST)' }).click();
  await page.getByRole('combobox').filter({ hasText: 'Normal (CST)' }).click();
  await page.getByRole('option', { name: 'Simples (CSOSN)' }).click();
  await expect(page.getByLabel('cClassTrib (6 dígitos)')).toHaveValue('000001');

  await page.getByRole('button', { name: 'Criar' }).click();
  await expect.poll(() => corpo, { message: 'o formulário não disparou o POST da regra' }).not.toBeNull();

  const enviado = corpo as unknown as Record<string, unknown>;
  expect(enviado.c_class_trib).toBe('000001');
  expect(enviado.cst_ibs).toBe('000');
  expect(enviado.cst_cbs).toBe('000');
  expect(enviado.aliquota_ibs).toBe(0.001);
  expect(enviado.aliquota_cbs).toBe(0.009);
});
