import { test, expect } from '@playwright/test';

// E2E de Essentials/Painel — contrato em resources/js/Pages/Essentials/Painel.casos.md.
// Continua `test.fixme`: exige sessão autenticada num tenant com dado, e esta suíte ainda não
// tem esse fixture. UC-PAINEL-00..03 são provados por Pest em
// Modules/Essentials/Tests/Feature/HrmPainelTest.php (lane essentials-pest, MySQL real).
// O que falta aqui é a camada de RENDER — `fixme` declara isso em vez de fingir cobertura.

test.fixme('UC-PAINEL-01: o painel abre com cabeçalho e os cards do próprio usuário', async ({ page }) => {
  await page.goto('/hrm/dashboard');
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();
  await expect(page.getByText('Minhas licenças')).toBeVisible();
  await expect(page.getByText('Próximos feriados')).toBeVisible();
});
