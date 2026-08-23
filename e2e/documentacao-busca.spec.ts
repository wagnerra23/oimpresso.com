import { test, expect } from '@playwright/test';

// Contrato em resources/js/Pages/Documentacao/Busca.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI) — a tela ainda não existe.
// Cada fixme vira asserção real na F3/F4 do MWART. Locators RESILIENTES (role/label/text),
// nunca classe CSS (L-24). NÃO edite a tela viva sem charter + gate visual.

test.fixme('UC-BUSCA-01: termo curto continua achando (rede de segurança no título)', async ({ page }) => {
  await page.goto('/documentacao/buscar?q=MCP');
  // Então: resultados > 0, mesmo o termo sendo curto demais para o índice full-text.
  await expect(page.getByRole('list', { name: /resultado/i })).toBeVisible();
});

test.fixme('UC-BUSCA-02: índice fora do ar diz "indisponível" com HTTP 200', async ({ page }) => {
  // Dado corpus inacessível (fixture) · Então estado indisponível, distinto de "nada encontrado".
  const resposta = await page.goto('/documentacao/buscar?q=qualquer');
  expect(resposta?.status()).toBe(200);
  await expect(page.getByText(/indispon[íi]vel/i)).toBeVisible();
});

test.fixme('UC-BUSCA-03: termo com menos de 2 caracteres não consulta o banco', async ({ page }) => {
  await page.goto('/documentacao/buscar?q=a');
  // Então: nenhuma consulta emitida e nenhum resultado exibido.
});
