import { test, expect } from '@playwright/test';

// E2E de comportamento — COCKPIT FISCAL (`/fiscal`). Gate G-3 (ADR 0264).
//
// POR QUE ESTE ARQUIVO EXISTE, E O QUE ELE ACRESCENTA
// O Fiscal tem 7 telas em produção com trio completo e tinha ZERO spec em `e2e/` (medido em
// 2026-09-13 contra `origin/main`: 20 specs no diretório, nenhum do módulo). Mas o valor daqui
// não é "mais um teste" — é o eixo que os testes jsdom do próprio módulo declaram FORA do
// alcance deles, por escrito: `Cockpit.casos.md` UC-FCKP-11 diz que "o jsdom não implementa a
// travessia por Tab do browser (...) a travessia física, o anel pintado e o leitor de tela são
// olho humano no smoke"; `Nfe.casos.md` UC-FNFE-14, que "a navegação HTTP real entre as rotas —
// o jsdom não a faz". Navegador real fecha esse resíduo. Onde o caso pede DADO que a lane não
// tem, ele nasce `fixme` com o motivo medido (README: "NUNCA falso-verde").
//
// CONTRATO (lido inteiro, não de lembrança):
// `governance/design/contracts/fiscal-cockpit.contract.json` — seção `fiscal-cockpit-kpis`, 6
// rótulos, nesta ordem. Medido no `Cockpit.tsx:423-460`: a ordem do código é a do contrato. O
// próprio contrato declara que a copy dele saiu da TELA VIVA, logo NÃO prova conformidade com
// o protótipo (ADR UI-0029: no eixo FORMA o protótipo é soberano) — serve de âncora de
// CAMINHO, que é o que um E2E prova.
//
// Locators RESILIENTES (L-24); `data-contract` não é classe, é a âncora que o contrato nomeia
// e o gate `contrato-de-tela` vigia. Zero `waitForTimeout`. Testes NÃO rodam local (ADR 0062)
// — a lane é `e2e-gate.yml` (MySQL + `VisregTenantSeeder`, biz=1, login `/_visreg-login/1`).

/** Os 6 rótulos da seção `fiscal-cockpit-kpis`, na ordem que o contrato declara. */
const RIBBON_CONTRATO = [
  'Emitidas', 'Autorizadas', 'Rejeitadas',
  'DF-e p/ manifestar', 'Certif. A1', 'Faturado fiscal',
] as const;

test('UC-FCKP-03: o ribbon carrega as medidas do contrato, na ordem declarada', async ({ page }) => {
  await page.goto('/fiscal');
  await page.waitForLoadState('networkidle');

  await expect(page.getByRole('heading', { name: 'Notas Fiscais' })).toBeVisible({ timeout: 15_000 });

  const ribbon = page.locator('[data-contract="fiscal-cockpit-kpis"]');
  await expect(ribbon).toBeVisible();

  // Presença E ordem numa medida só: o texto do ribbon traz os 6 rótulos em índices
  // crescentes. Asserir apenas presença deixaria passar um refactor que reordena a régua
  // que a contadora lê da esquerda para a direita — e a ordem é o que o contrato fixa.
  const texto = (await ribbon.innerText()).replace(/\s+/g, ' ');
  let anterior = -1;
  for (const rotulo of RIBBON_CONTRATO) {
    const pos = texto.indexOf(rotulo);
    expect(pos, `o ribbon não traz "${rotulo}" — uma medida do contrato sumiu`).toBeGreaterThan(-1);
    expect(pos, `"${rotulo}" saiu da ordem que o contrato declara`).toBeGreaterThan(anterior);
    anterior = pos;
  }
});

test('UC-FCKP-07: o chip da visão salva e a tabela contam a MESMA lista', async ({ page }) => {
  await page.goto('/fiscal');
  await page.waitForLoadState('networkidle');

  const visoes = page.getByRole('tablist', { name: 'Filtros salvos' });
  await expect(visoes).toBeVisible();

  // `Todas` é a visão default — o `aria-selected` é o que o leitor de tela anuncia.
  const todas = visoes.getByRole('tab', { name: /^Todas/ });
  await expect(todas).toHaveAttribute('aria-selected', 'true');

  // O contrato aqui é a CONCORDÂNCIA: chip e tabela saem do mesmo
  // `NotasUnifiedService::listar()` (`CockpitController:53`), então não podem se contradizer.
  // Discrimina nos DOIS sentidos — chip com N>0 e empty na tela reprova, chip com 0 e linhas
  // na tabela também. É a divergência que o #6541 consertou (header "0 notas" × 10 linhas
  // mockadas × chip "Todas 18").
  const contador = Number((await todas.innerText()).replace(/[^0-9]+/g, '') || '0');
  if (contador === 0) {
    await expect(page.getByText('Nenhuma nota pra esses filtros')).toBeVisible();
    await expect(page.getByRole('table')).toHaveCount(0);
  } else {
    await expect(page.getByRole('table')).toBeVisible();
    await expect(page.getByText('Nenhuma nota pra esses filtros')).toHaveCount(0);
  }
});

test('UC-FCKP-13: a procedência liga por superfície, e acompanha o número em vez de escondê-lo', async ({ page }) => {
  await page.goto('/fiscal');
  await page.waitForLoadState('networkidle');

  const botao = page.getByRole('button', { name: 'Procedência' });
  await expect(botao).toBeVisible();

  // Lê o estado ANTES de agir em vez de assumir "desligado": a preferência mora em
  // `localStorage` (`oimpresso.fiscal.procedencia`, `_lib/procedencia.ts:35`), então cravar
  // o inicial acoplaria o caso à ordem dos testes. O contrato aqui é o TOGGLE.
  const ligadoAntes = (await botao.getAttribute('aria-pressed')) === 'true';
  const ribbon = page.locator('[data-contract="fiscal-cockpit-kpis"]');
  const selos = page.locator('[data-contract="procedencia-selo"]');

  if (ligadoAntes) {
    expect(await selos.count(), 'ligado no cabeçalho e nenhum selo na tela').toBeGreaterThan(0);
    await botao.click();
    await expect(botao).toHaveAttribute('aria-pressed', 'false');
    await expect(selos).toHaveCount(0);
  } else {
    await expect(selos).toHaveCount(0);
    await botao.click();
    await expect(botao).toHaveAttribute('aria-pressed', 'true');
    expect(await selos.count(), 'o toggle ligou e nenhuma superfície se declarou').toBeGreaterThan(0);
  }

  // O selo ACOMPANHA o número — nunca o substitui (`SeloProcedencia.tsx:40`). Se ligar a
  // procedência apagasse a régua, a contadora perderia a leitura ao pedir a explicação.
  await expect(ribbon).toContainText('Emitidas');
});

// PENDENTES POR FALTA DE SUJEITO — não por falta de código. Os dois casos abaixo precisam de
// NOTA na lista, e a lane não tem: `notas` vem do `NotasUnifiedService` (`CockpitController:53`)
// e — medido em 2026-09-13, `git grep` em `database/seeders/` + `Modules/*/Database/Seeders/` —
// NENHUM seeder cria `nfe_emissoes`; o `Nfe.casos.md` registra o mesmo ("nenhuma lane de hoje
// tem `nfe_emissoes`"). Sem emissão o `Cockpit.tsx:566` entrega o empty state e a `<table>` não
// existe. `fixme`, não `skip` condicional, porque a ausência é do AMBIENTE — fixture de nota
// aqui abriria um segundo jeito de semear, paralelo ao Pest.

test.fixme('UC-FCKP-11: Tab alcança a linha e Enter/Space abrem o drawer daquela nota', async ({ page }) => {
  // O RESÍDUO que o `fiscal-cockpit-teclado.test.tsx` declara fora do alcance dele: no jsdom
  // "Tab alcança todas" é medido como "toda linha é focável na ordem do DOM"; a travessia
  // FÍSICA só existe em navegador, e aqui ela é o sujeito.
  await page.goto('/fiscal');
  await page.waitForLoadState('networkidle');

  const primeira = page.getByRole('row').filter({ has: page.locator('[aria-label^="Abrir "]') }).first();
  await primeira.focus();
  await expect(primeira).toBeFocused();

  await page.keyboard.press('Enter');
  await expect(page.getByRole('dialog')).toBeVisible();

  // Space faz o mesmo E não rola a página (`preventDefault`, `Cockpit.tsx:613`).
  await page.keyboard.press('Escape');
  const yAntes = await page.evaluate(() => window.scrollY);
  await primeira.focus();
  await page.keyboard.press(' ');
  await expect(page.getByRole('dialog')).toBeVisible();
  expect(await page.evaluate(() => window.scrollY), 'o Space rolou a página').toBe(yAntes);

  // Papel implícito `row` — nunca `role="button"` (`Cockpit.charter.md:226` proíbe).
  await expect(primeira).toHaveAttribute('role', /^$|row/);
});

test.fixme('UC-FCKP-09: Anterior/Próxima trocam as linhas e desabilitam nos extremos', async ({ page }) => {
  // Precisa de MAIS notas do que cabem numa página (default 8), então o seed teria de ser não
  // só existente como volumoso. A paginação já está em produção (`Cockpit.tsx:721`,
  // `data-contract="paginacao-notas"`) e provada por `fiscal-cockpit-paginacao.test.tsx` — o
  // que falta aqui é só o eixo navegador.
  await page.goto('/fiscal');
  await page.waitForLoadState('networkidle');

  const pager = page.locator('[data-contract="paginacao-notas"]');
  await expect(pager).toBeVisible();
  await expect(pager.getByRole('button', { name: 'Anterior' })).toBeDisabled();
});
