import { test, expect } from '@playwright/test';

// Stub E2E — contrato em resources/js/Pages/Jana/Acoes.casos.md. test.fixme = PENDENTE (não executa,
// não quebra o CI). Os UCs já têm dente na lane MySQL (Modules/Jana/Tests/Feature/AcoesContratoTest.php);
// vira asserção de browser quando a suíte E2E tiver seed do agregado de vendas. Locators por role/text (L-24).

test.fixme('UC-ACAO-01: a fila lista as 5 ações com CTA "Revisar …" e prévia do servidor', async ({ page }) => {
  await page.goto('/ia/acoes');
  await expect(page.getByRole('tab', { name: 'Ações' })).toBeVisible();
  await expect(page.getByRole('button', { name: /^Revisar / })).toHaveCount(5);
});
