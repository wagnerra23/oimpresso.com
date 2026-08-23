import { test, expect } from '@playwright/test';

// Contrato em resources/js/Pages/Documentacao/Programa.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI) — a tela ainda não existe.
// Cada fixme vira asserção real na F3/F4 do MWART. Locators RESILIENTES (role/label/text),
// nunca classe CSS (L-24). NÃO edite a tela viva sem charter + gate visual.

test.fixme('UC-PROGRA-01: o estado da onda vem do MCP, nunca do markdown', async ({ page }) => {
  // Dado task done no MCP (fake) · Então a onda aparece done — e nenhum status escrito no plano/TSX.
  await page.goto('/documentacao/programa');
  await expect(page.getByRole('heading', { name: /trilha d/i })).toBeVisible();
});

test.fixme('UC-PROGRA-02: mudar o plano muda a tela, sem tocar PHP nem TSX', async ({ page }) => {
  // Dado a § Trilha D alterada numa fixture · Então o conteúdo muda; lacuna volta vazia, sem default.
  await page.goto('/documentacao/programa');
});

test.fixme('UC-PROGRA-03: sem MCP, a tela declara que não sabe', async ({ page }) => {
  // Dado MCP indisponível · Então ondas sem estado + indicação explícita — nunca status default.
  await page.goto('/documentacao/programa');
  await expect(page.getByText(/indispon[íi]vel/i)).toBeVisible();
});

test.fixme('UC-PROGRA-04: a tela é read-only — nenhum controle de mutação', async ({ page }) => {
  await page.goto('/documentacao/programa');
  // Então: nenhum caminho para marcar onda, DoD ou task pela UI; só navegação.
  await expect(page.getByRole('button', { name: /marcar|concluir|salvar/i })).toHaveCount(0);
});
