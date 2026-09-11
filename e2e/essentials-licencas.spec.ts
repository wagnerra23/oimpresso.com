import { test, expect } from '@playwright/test';

// E2E de HRM · Licenças — contrato em resources/js/Pages/Essentials/Licencas/Index.casos.md.
//
// `test.fixme` = PENDENTE: não executa e não quebra o CI. Os casos abaixo cobrem
// exatamente o que o Pest NÃO alcança — comportamento de RENDER (linha urgente,
// stopPropagation, atalhos, estado vazio). Enquanto forem fixme eles seguem como
// `[BACKLOG]` no casos.md: citá-los como UC sem teste que os defenda criaria UC
// órfão (G-2 do ADR 0264, gate required).
//
// Locators RESILIENTES (role/label/text), nunca classe CSS (L-24).

test.fixme('Licenças: o clique na linha abre o drawer; o clique na ação NÃO abre', async ({ page }) => {
  await page.goto('/hrm/leave');
  await expect(page.getByRole('heading', { name: 'Licenças' })).toBeVisible();

  // TODO: clicar numa linha → drawer visível ("Saldo do tipo" presente).
  // TODO: fechar; clicar em "Aprovar" da mesma linha → o drawer NÃO abre
  //       (é o stopPropagation do <td> da ação).
});

test.fixme('Licenças: busca sem resultado mostra o motivo e oferece limpar', async ({ page }) => {
  await page.goto('/hrm/leave');
  await page.getByLabel('Buscar licenças').fill('zzz-inexistente-zzz');

  // TODO: esperar o estado vazio e conferir que "Limpar busca e filtros" aparece
  //       — tabela vazia muda é o anti-padrão que o charter proíbe.
  await expect(page.getByRole('button', { name: 'Limpar busca e filtros' })).toBeVisible();
});

test.fixme('Licenças: os atalhos / e n não disparam com o foco num campo de texto', async ({ page }) => {
  await page.goto('/hrm/leave');

  // TODO: focar a busca, digitar "n" → o formulário "Pedir licença" NÃO abre.
  // TODO: com o foco fora, apertar "n" → o formulário abre.
});
