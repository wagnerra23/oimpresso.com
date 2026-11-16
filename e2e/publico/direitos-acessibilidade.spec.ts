import { test, expect, type Page } from '@playwright/test';
import { rotaExiste } from './_lib/feature';

// Uniafibro /direitos — acessibilidade visual (itens 5, 6 e 7 do plano E2E).
// Fonte do comportamento: public/uniafibro/direitos.css (#10908). O site não tem JavaScript:
// o tamanho do texto usa rádio + :has() e o modo escuro segue prefers-color-scheme.
// Só leitura, então roda também no job noturno.

async function semRolagemLateral(page: Page): Promise<void> {
  const { largura, visivel } = await page.evaluate(() => ({
    largura: document.documentElement.scrollWidth,
    visivel: document.documentElement.clientWidth,
  }));
  expect(largura, 'a página não pode rolar para o lado').toBeLessThanOrEqual(visivel);
}

const fonteRaiz = (page: Page) => page.evaluate(() => getComputedStyle(document.documentElement).fontSize);

test('5 · A / A+ / A++ aumentam o texto sem quebrar o layout', async ({ page }) => {
  test.skip(!(await rotaExiste(page, '/direitos')), 'aguardando PR #10908');

  await page.goto('/direitos');
  // 112,5% · 125% · 143,75% de 16px (direitos.css).
  await expect.poll(() => fonteRaiz(page)).toBe('18px');

  for (const [rotulo, px] of [['Texto maior', '20px'], ['Texto bem maior', '23px'], ['Texto normal', '18px']] as const) {
    await page.getByTitle(rotulo).click();
    await expect.poll(() => fonteRaiz(page)).toBe(px);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await semRolagemLateral(page);
  }
});

test('6 · modo escuro e claro seguem o sistema (prefers-color-scheme)', async ({ page }) => {
  test.skip(!(await rotaExiste(page, '/direitos')), 'aguardando PR #10908');

  const fundo = () => page.evaluate(() => getComputedStyle(document.body).backgroundColor);
  const texto = () => page.evaluate(() => getComputedStyle(document.body).color);

  await page.emulateMedia({ colorScheme: 'light' });
  await page.goto('/direitos');
  expect(await fundo()).toBe('rgb(250, 247, 253)'); // --fundo claro
  expect(await texto()).toBe('rgb(42, 33, 53)'); // --texto claro

  await page.emulateMedia({ colorScheme: 'dark' });
  expect(await fundo()).toBe('rgb(21, 17, 27)'); // --fundo escuro
  expect(await texto()).toBe('rgb(241, 236, 247)'); // --texto escuro
});

test('7 · celular 375×812: menu cabe na tela, alvos ≥ 44px, texto legível', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'publico-mobile', 'só no projeto mobile (toque + user agent de celular)');
  test.skip(!(await rotaExiste(page, '/direitos')), 'aguardando PR #10908');

  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/direitos');

  // O menu não tem hambúrguer: os itens quebram em linhas. Todos têm de estar visíveis na tela.
  const itens = page.locator('nav.nav ul a');
  expect(await itens.count()).toBe(9);
  for (const item of await itens.all()) {
    await expect(item).toBeVisible();
    const caixa = await item.boundingBox();
    expect(caixa!.x + caixa!.width, `${await item.textContent()} sai da tela`).toBeLessThanOrEqual(375);
  }
  await semRolagemLateral(page);

  // Botões de verdade: A / A+ / A++, o campo e o botão da busca.
  const alvos = [
    page.getByTitle('Texto normal'),
    page.getByTitle('Texto maior'),
    page.getByTitle('Texto bem maior'),
    page.getByLabel('Procurar nos direitos e guias'),
    page.getByRole('button', { name: 'Procurar' }),
  ];
  for (const alvo of alvos) {
    const caixa = await alvo.boundingBox();
    expect(caixa!.height, 'altura do alvo de toque').toBeGreaterThanOrEqual(44);
    expect(caixa!.width, 'largura do alvo de toque').toBeGreaterThanOrEqual(44);
  }

  const corpo = await page.evaluate(() => parseFloat(getComputedStyle(document.body).fontSize));
  expect(corpo, 'texto base').toBeGreaterThanOrEqual(16);
});
