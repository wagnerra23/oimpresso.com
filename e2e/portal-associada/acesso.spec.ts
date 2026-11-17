import { existsSync, readFileSync } from 'node:fs';
import { test, expect, type Page } from '@playwright/test';

// Área da associada — `/associacoes/m/{token}` (US-ASSOC-005 · ADR 0451 D6). Itens 11, 13, 16, 17 e 18
// do plano E2E. Contrato: Modules/Associacoes/Resources/js/Pages/Associacoes/Public/Membro.casos.md.
//
// O link e o PIN vêm do VisregAssociacoesPortalSeeder (emitidos pelo Service real, só no runner do CI).
// Sem o arquivo — antes do #10880, ou no job noturno contra o site real — tudo se pula.
// A associada não é usuária do ERP: os testes abrem sem a sessão do admin.

const FIXTURE = 'storage/app/e2e/portal-associada.json';

type Fixture = { aberto: { token: string; pin: string; nome: string; associada_id: number }; vencido: { token: string } };

const fixture: Fixture | null = existsSync(FIXTURE) ? JSON.parse(readFileSync(FIXTURE, 'utf8')) : null;

test.use({ storageState: { cookies: [], origins: [] } });
test.skip(fixture === null, 'aguardando PR #10880 (área da associada) — sem link de teste semeado');

const LINK_VAZIO = 'Este link não abre';

async function digitarPin(page: Page, pin: string): Promise<void> {
  for (const n of pin) await page.getByRole('button', { name: `Número ${n}` }).click();
  await page.getByRole('button', { name: 'Entrar' }).click();
}

/** Texto visível da página, para comparar estados vazios. */
const textoDaPagina = (page: Page) => page.locator('body').innerText();

test('11 · link sem PIN mostra só o teclado, nenhum dado da associada', async ({ page }) => {
  const { token, nome } = fixture!.aberto;

  const resposta = await page.goto(`/associacoes/m/${token}`);
  await expect(page.getByRole('heading', { name: 'Digite seu PIN' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Meus dados' })).toHaveCount(0);

  // Nem o HTML da primeira visita nem a página renderizada trazem o nome.
  expect(await resposta!.text()).not.toContain(nome);
  await expect(page.getByText(nome)).toHaveCount(0);
});

test('13 · PIN correto abre "Meus dados" e "Minhas autorizações"', async ({ page }) => {
  const { token, pin, nome } = fixture!.aberto;

  await page.goto(`/associacoes/m/${token}`);
  await digitarPin(page, pin);

  await expect(page.getByRole('heading', { name: 'Meus dados' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Minhas autorizações' })).toBeVisible();
  await expect(page.getByText(nome)).toBeVisible();
  await expect(page.getByText('Ativa', { exact: true })).toBeVisible();
});

test('16 · link de outra empresa é o mesmo estado vazio de um link qualquer (anti-leak)', async ({ page }) => {
  const { token, associada_id } = fixture!.aberto;

  // Payload apontando para outra empresa, com a assinatura do link verdadeiro: a assinatura não confere.
  // (Que uma associada de outra empresa nunca abre aqui é provado no Pest UC-AMB-02, com dois tenants.)
  const [, assinatura] = token.split('.');
  const payload = Buffer.from(JSON.stringify({ b: 2, a: associada_id, n: 'x' })).toString('base64url');

  await page.goto(`/associacoes/m/${payload}.${assinatura}`);
  await expect(page.getByRole('heading', { name: LINK_VAZIO })).toBeVisible();
  const outraEmpresa = await textoDaPagina(page);

  await page.goto('/associacoes/m/qualquer-coisa');
  await expect(page.getByRole('heading', { name: LINK_VAZIO })).toBeVisible();
  expect(await textoDaPagina(page)).toBe(outraEmpresa);

  // E o PIN do link verdadeiro não abre o forjado. Mesmo pedido que a página faz (CSRF da meta + cookie
  // da sessão do contexto); sem o token o 419 do CSRF esconderia a resposta do controller.
  const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  const r = await page.request.post(`/associacoes/m/${payload}.${assinatura}/pin`, {
    data: { pin: fixture!.aberto.pin },
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf ?? '', 'X-Requested-With': 'XMLHttpRequest' },
  });
  expect(r.status()).toBe(404);
});

test('17 · link vencido é o mesmo estado vazio', async ({ page }) => {
  await page.goto('/associacoes/m/qualquer-coisa');
  const vazio = await textoDaPagina(page);

  await page.goto(`/associacoes/m/${fixture!.vencido.token}`);
  await expect(page.getByRole('heading', { name: LINK_VAZIO })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Digite seu PIN' })).toHaveCount(0);
  expect(await textoDaPagina(page)).toBe(vazio);
});

// Item 18 pede "lembrar este aparelho" por cookie (não LocalStorage, LGPD). O #10880 deixou isso no
// backlog do Membro.casos.md. Fica como fixme, visível no relatório, até a onda que o implementar.
test.fixme('18 · aparelho lembrado por cookie dispensa o PIN na volta', async ({ page, context }) => {
  const { token, pin } = fixture!.aberto;
  await page.goto(`/associacoes/m/${token}`);
  await digitarPin(page, pin);
  await expect(page.getByRole('heading', { name: 'Meus dados' })).toBeVisible();

  const lembrado = (await context.cookies()).find((c) => /aparelho|lembr/i.test(c.name));
  expect(lembrado, 'cookie do aparelho lembrado').toBeDefined();
  expect(await page.evaluate(() => Object.keys(localStorage))).toEqual([]);

  const outra = await context.newPage();
  await outra.goto(`/associacoes/m/${token}`);
  await expect(outra.getByRole('heading', { name: 'Meus dados' })).toBeVisible();
});
