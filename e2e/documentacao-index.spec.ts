import { test, expect } from '@playwright/test';

// Contrato em resources/js/Pages/Documentacao/Index.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI) — a tela ainda não existe.
// Cada fixme vira asserção real na F3/F4 do MWART. Locators RESILIENTES (role/label/text),
// nunca classe CSS (L-24). NÃO edite a tela viva sem charter + gate visual.

test.fixme('UC-INDEX-01: lê o Guia com rail e sumário', async ({ page }) => {
  await page.goto('/documentacao');
  // Então: conteúdo renderizado + sumário + rail; payload traz HTML, não markdown cru.
  await expect(page.getByRole('navigation', { name: /documenta/i })).toBeVisible();
});

test.fixme('UC-INDEX-02: fonte ausente devolve 503 nomeando o arquivo', async ({ page }) => {
  // Dado o Guia ausente no deploy (fixture) · Então 503 com o path na mensagem.
  const resposta = await page.goto('/documentacao');
  expect(resposta?.status()).toBe(503);
  await expect(page.getByText(/GUIA-DO-SISTEMA\.md/)).toBeVisible();
});

test.fixme('UC-INDEX-03: trocar de lente mantém ordinal contínuo', async ({ page }) => {
  await page.goto('/documentacao?lente=operar');
  // Então: itens visíveis numerados 1,2,3… sem buracos (ordinal ≠ nav_order).
});
