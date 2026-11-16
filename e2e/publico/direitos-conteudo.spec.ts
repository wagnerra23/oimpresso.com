import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';
import { rotaExiste } from './_lib/feature';

// Uniafibro /direitos — conteúdo de cada página (itens 3, 8, 9 e 10 do plano E2E).
// Fonte do comportamento: UniafibroConteudoController + App\Support\Uniafibro\Layout (#10908).
// Só leitura (o PDF é baixado, nada é gravado no servidor), então roda também no job noturno.
// A rota do PDF tem throttle:10,1 — este arquivo baixa um PDF só por projeto.

const PAGINA = '/direitos/lei-federal'; // primeira página com fontes oficiais, chega no #10912

test('3 · "Baixar em PDF" entrega um PDF com o nome da página', async ({ page }) => {
  test.skip(!(await rotaExiste(page, PAGINA)), 'aguardando PR #10912');

  await page.goto(PAGINA);
  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.getByRole('link', { name: 'Baixar em PDF' }).click(),
  ]);

  expect(download.suggestedFilename()).toBe('uniafibro-lei-federal.pdf');
  const caminho = await download.path();
  expect(readFileSync(caminho).subarray(0, 5).toString('latin1')).toBe('%PDF-');
});

test('8 · aviso "não é consultoria jurídica" no hub e em cada página', async ({ page }) => {
  test.skip(!(await rotaExiste(page, PAGINA)), 'aguardando PR #10912');

  for (const caminho of ['/direitos', PAGINA]) {
    await page.goto(caminho);
    const aviso = page.getByRole('note');
    await expect(aviso).toHaveCount(1);
    await expect(aviso).toContainText('não é consultoria jurídica');
    await expect(aviso).toContainText('Defensoria Pública de SC');
  }
});

test('9 · sem banner de cookies, sem script e sem cookie de terceiros', async ({ page, context }) => {
  test.skip(!(await rotaExiste(page, '/direitos')), 'aguardando PR #10908');

  await page.goto('/direitos');
  // O site não usa rastreador (rodapé), então não há o que consentir: nem banner, nem JS.
  await expect(page.getByText(/cookies?/i)).toHaveCount(0);
  await expect(page.locator('script')).toHaveCount(0);
  await expect(page.getByText('Este site não usa rastreadores nem publicidade.')).toBeVisible();

  // Cookie de sessão do próprio Laravel pode existir; de outro domínio, nenhum.
  const alheios = (await context.cookies()).filter((c) => !c.domain.replace(/^\./, '').endsWith('uniafibro.com.br'));
  expect(alheios.map((c) => `${c.name}@${c.domain}`)).toEqual([]);
});

test('10 · fontes oficiais apontam para HTTPS e não vazam a origem', async ({ page }) => {
  test.skip(!(await rotaExiste(page, PAGINA)), 'aguardando PR #10912');

  await page.goto(PAGINA);
  const links = page.locator('section.fontes a');
  await expect(page.locator('section.fontes h2')).toHaveText('Fontes oficiais');
  expect(await links.count()).toBeGreaterThan(0);

  for (const link of await links.all()) {
    await expect(link).toHaveAttribute('href', /^https:\/\//);
    await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
  }
});

// Item 10 do plano pede que a fonte abra em nova aba. Layout::fontes() não põe target="_blank"
// (medido em 2026-11-16). Fica como fixme, visível no relatório, até [W] decidir se quer a nova aba.
test.fixme('10 · fonte oficial abre em nova aba', async ({ page }) => {
  await page.goto(PAGINA);
  for (const link of await page.locator('section.fontes a').all()) {
    await expect(link).toHaveAttribute('target', '_blank');
  }
});
