import { test, expect } from '@playwright/test';

// Contrato em resources/js/Pages/Documentacao/Doc.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI) — a tela ainda não existe.
// Cada fixme vira asserção real na F3/F4 do MWART. Locators RESILIENTES (role/label/text),
// nunca classe CSS (L-24). NÃO edite a tela viva sem charter + gate visual.

test.fixme('UC-DOC-01: abre o documento e marca o item ativo no rail', async ({ page }) => {
  await page.goto('/documentacao/reference-gov-programa-documentacao');
  // Então: documento renderizado + item correspondente marcado como ativo no rail.
  await expect(page.getByRole('link', { current: 'page' })).toBeVisible();
});

test.fixme('UC-DOC-02: link relativo resolve pela pasta do próprio documento', async ({ page }) => {
  // Dado doc em subpasta citando ../../decisions/NNNN-*.md · Quando clica · Então chega ao doc certo.
  await page.goto('/documentacao/reference-gov-programa-documentacao');
});

test.fixme('UC-DOC-03: documento de tipo fora da documentação devolve 404', async ({ page }) => {
  // Dado slug que existe no corpus mas é session/handoff · Então 404, nunca o conteúdo.
  const resposta = await page.goto('/documentacao/slug-de-session-qualquer');
  expect(resposta?.status()).toBe(404);
});
