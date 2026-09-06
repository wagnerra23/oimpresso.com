import { test, expect } from '@playwright/test';

// E2E de Essentials/Tipos — contrato em resources/js/Pages/Essentials/Tipos.casos.md.
// Locators RESILIENTES (role/label/text), nunca classe CSS (L-24).
//
// Continua `test.fixme` de propósito: estes casos exigem sessão autenticada num tenant
// com tipos cadastrados, e esta suíte ainda não tem esse fixture. O comportamento que
// eles descrevem NÃO fica descoberto — UC-TIPOS-01..05 são provados por Pest em
// Modules/Essentials/Tests/Feature/HrmTiposIndexTest.php (lane essentials-pest, MySQL
// real) e UC-TIPOS-06..08 por HrmExclusaoGuardaTest.php. O que falta aqui é a camada
// de RENDER; declarar `fixme` é dizer isso, em vez de fingir cobertura de browser.

test.fixme('UC-TIPOS-00: chego na tela pelo menu do HRM, sem digitar a URL', async ({ page }) => {
  // O alcance desta tela PREEXISTE à migração (rota resource + permission + nav_hrm),
  // mas quem prova isso é o clique, não a leitura do Blade.
  await page.goto('/hrm/dashboard');
  await page.getByRole('link', { name: /Tipo de licen/i }).click();
  await expect(page).toHaveURL(/\/hrm\/leave-type$/);
  await expect(page.getByRole('heading', { name: 'Tipos de licença' })).toBeVisible();
});

test.fixme('UC-TIPOS-01: a lista de tipos de licença abre e mostra as colunas do contrato', async ({ page }) => {
  await page.goto('/hrm/leave-type');
  await expect(page.getByRole('heading', { name: 'Tipos de licença' })).toBeVisible();
  await expect(page.getByRole('columnheader', { name: 'Limite' })).toBeVisible();
  await expect(page.getByRole('columnheader', { name: 'Pedidos no ano' })).toBeVisible();
});

test.fixme('UC-TIPOS-02: tipo sem limite cadastrado mostra "sem limite", nunca 0', async ({ page }) => {
  await page.goto('/hrm/leave-type');
  // Dado um tipo sem max_leave_count · Então a célula diz "sem limite".
  await expect(page.getByRole('cell', { name: 'sem limite' }).first()).toBeVisible();
});

test.fixme('UC-TIPOS-06: excluir tipo em uso mantém o diálogo aberto dizendo quantas licenças travam', async ({ page }) => {
  await page.goto('/hrm/leave-type');
  await page.getByRole('button', { name: /^Excluir / }).first().click();
  await page.getByRole('button', { name: 'Excluir', exact: true }).click();

  // O 422 do servidor traz blocked_by.leaves — a tela mostra o MOTIVO, não "erro".
  const motivo = page.getByTestId('tipo-bloqueado');
  await expect(motivo).toBeVisible();
  await expect(motivo).toContainText(/licen/i);
  await expect(motivo).toContainText(/\d+/);
  // E o diálogo continua aberto, agora sem oferecer a ação destrutiva.
  await expect(page.getByRole('heading', { name: 'Não dá para excluir este tipo' })).toBeVisible();
});
