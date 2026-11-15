import { test, expect } from '@playwright/test';
import { rotaExiste } from './_lib/feature';

// Site público da Uniafibro — prova da infra dos projetos publico-desktop / publico-mobile.
// Rota: Route::domain('uniafibro.com.br') em routes/web.php (UniafibroSiteController@home).
// Só leitura: roda no CI (app local) e no job noturno contra o site real.

test('home da Uniafibro abre pelo domínio, sem sessão do ERP', async ({ page, context }) => {
  // O projeto público não herda o cookie de sessão do ERP (storageState vazio).
  expect(await context.cookies()).toEqual([]);

  const resposta = await page.goto('/');
  expect(resposta?.status()).toBe(200);

  await expect(page).toHaveTitle(/Uniafibro/);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Uniafibro');
});

test('sonda de feature: caminho desconhecido conta como ausente', async ({ request }) => {
  // Hoje o domínio redireciona caminho desconhecido para a home (302). A sonda não segue o
  // redirect, então responde "ausente" — é o comportamento que os specs de /direitos usam
  // para pular com motivo enquanto o PR deles não mergeia.
  expect(await rotaExiste(request, '/caminho-que-nao-existe-e2e')).toBe(false);
  expect(await rotaExiste(request, '/')).toBe(true);
});
