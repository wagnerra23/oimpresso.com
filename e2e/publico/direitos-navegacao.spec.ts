import { test, expect } from '@playwright/test';
import { rotaExiste } from './_lib/feature';

// Uniafibro /direitos — navegação (itens 1, 2 e 4 do plano E2E).
// Fonte do comportamento: UniafibroConteudoController + Layout (PRs #10908–#10916, Onda A).
// Só leitura: nenhum teste aqui escreve nada, então roda também no job noturno contra o site real.
//
// Cada teste pula enquanto a página que ele confere não existe, citando o PR que a entrega.
// Os PRs da Onda A mergeiam em pilha (#10908 → #10912 → #10914 → #10915), então a sonda
// confere a página mais nova que o teste exige.

test('1 · "Seus Direitos" no topo da home leva à lista de direitos', async ({ page }) => {
  test.skip(!(await rotaExiste(page, '/direitos/lei-federal')), 'aguardando PR #10908 e #10912');

  await page.goto('/');
  await page.getByRole('link', { name: 'Seus Direitos', exact: true }).click();

  await expect(page).toHaveURL(/\/direitos$/);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Seus direitos');
  const secao = page.locator('section#direitos');
  await expect(secao.getByRole('heading', { level: 2 })).toHaveText('Seus Direitos');
  await expect(secao.locator('a.cartao').first()).toBeVisible();
});

// Item 2: cada página de direito, aberta a partir do cartão do hub. Agrupadas pelo PR que
// traz o Markdown delas, para o skip ficar preciso.
const PAGINAS: Array<{ pr: string; paginas: Array<{ slug: string; titulo: string }> }> = [
  {
    pr: '#10912',
    paginas: [
      { slug: 'lei-federal', titulo: 'Lei federal' },
      { slug: 'lei-estadual-sc', titulo: 'Lei estadual de Santa Catarina' },
    ],
  },
  {
    pr: '#10914',
    paginas: [
      { slug: 'inss', titulo: 'INSS e benefícios' },
      { slug: 'trabalho', titulo: 'Trabalho (CLT)' },
    ],
  },
  {
    pr: '#10915',
    paginas: [{ slug: 'como-pedir', titulo: 'Como pedir cada benefício' }],
  },
];

for (const grupo of PAGINAS) {
  const nomes = grupo.paginas.map((p) => p.slug).join(', ');
  test(`2 · páginas de direito abrem pelo hub (${nomes})`, async ({ page }) => {
    const ultima = grupo.paginas[grupo.paginas.length - 1];
    test.skip(!(await rotaExiste(page, `/direitos/${ultima.slug}`)), `aguardando PR ${grupo.pr}`);

    for (const { slug, titulo } of grupo.paginas) {
      await page.goto('/direitos');
      const cartao = page.locator(`a.cartao[href="/direitos/${slug}"]`);
      await expect(cartao.locator('strong')).toHaveText(titulo);
      await cartao.click();

      await expect(page).toHaveURL(new RegExp(`/direitos/${slug}$`));
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(titulo);
      // Volta ao hub pela trilha e oferece o PDF (o download em si é o item 3).
      await expect(page.locator('p.trilha a[href="/direitos"]')).toHaveText('Seus Direitos');
      await expect(page.getByRole('link', { name: 'Baixar em PDF' })).toHaveAttribute(
        'href',
        `/direitos/${slug}.pdf`,
      );
    }
  });
}

test('4 · busca por "aposentadoria" traz a página do INSS', async ({ page }) => {
  // A palavra aparece em inss.md (11×) e como-pedir.md; o INSS chega no PR #10914.
  test.skip(!(await rotaExiste(page, '/direitos/inss')), 'aguardando PR #10914');

  await page.goto('/direitos');
  await page.getByLabel('Procurar nos direitos e guias').fill('aposentadoria');
  await page.getByRole('button', { name: 'Procurar' }).click();

  await expect(page).toHaveURL(/\/direitos\?q=aposentadoria$/);
  const resultados = page.locator('section.resultados');
  await expect(resultados.getByRole('heading', { level: 2 })).toContainText('aposentadoria');
  await expect(resultados.locator('a[href="/direitos/inss"]')).toHaveText('INSS e benefícios');
});
