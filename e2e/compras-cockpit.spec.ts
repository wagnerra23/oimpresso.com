import { test, expect, type Page } from '@playwright/test';

// E2E de comportamento — COCKPIT DE COMPRAS (`/compras`), Gate G-3 (ADR 0264).
//
// FONTE DO QUE SE ASSERTA (nesta ordem, e nenhuma outra):
//   1. governance/design/contracts/compras-cockpit.contract.json — copy e ordem das 4 seções.
//      O contrato deriva da TELA VIVA, não do protótipo (a `_nota_fonte` dele declara o gap:
//      o protótipo desenha tabbar de ÁREA com 3 abas, a tela tem tabbar de FILTRO com 4).
//      Este spec segue o contrato; declarar a copy do protótipo faria o teste nascer vermelho.
//   2. resources/js/Pages/Compras/Index.casos.md — os UC-CMP-* citados em cada título.
//
// ORÁCULO DE DOMÍNIO É O PEST, NÃO ESTE ARQUIVO: cross-tenant (UC-CMP-01..04) vive em
// Modules/Compras/Tests/Feature/MultiTenantTest.php, e valor/estoque (UC-CMP-09) em
// PurchaseCalculoValorEstoqueE2ETest. Aqui se prova o CAMINHO DE TELA — render, navegação,
// querystring e persistência no cliente. Reimplementar regra de domínio duplicaria régua.
//
// SEED DA LANE (database/seeders/VisregTenantSeeder.php, medido): business 1 + 1 location +
// 1 contact + 1 produto `single`. NENHUMA `transactions type=purchase`. Logo o cockpit abre no
// estado VAZIO, e os casos que exigem linha ficam `test.fixme` com a razão escrita — nunca um
// assert frouxo que passaria sem dado (falso-verde é o que o README deste diretório proíbe).
//
// Locators RESILIENTES: role/label/text e a âncora semântica `data-contract` (instrumentada de
// propósito pelo contrato de tela) — nunca classe CSS de estilo (L-24).

const ORDEM_CONTRATO = ['compras-cabecalho', 'compras-abas', 'compras-kpis', 'compras-tabela'];

/** Âncoras `data-contract` presentes no DOM, na ordem em que o documento as declara. */
async function ancorasNoDom(page: Page): Promise<string[]> {
  const vistas = await page
    .locator('[data-contract]')
    .evaluateAll((nos) => nos.map((n) => n.getAttribute('data-contract') ?? ''));

  return vistas.filter((id) => ORDEM_CONTRATO.includes(id));
}

test('UC-CMP-05 · /compras entrega a Page Inertia com a copy e a ordem do contrato', async ({ page }) => {
  await page.goto('/compras');
  await page.waitForLoadState('networkidle');

  // A rota NÃO ramifica Blade/Inertia (ComprasController@index é `Inertia::render` puro) —
  // um GET de browser já recebe a Page. Se um dia virar dual-path, este assert acusa.
  await expect(page.getByRole('heading', { name: 'Compras', level: 1 })).toBeVisible({ timeout: 15_000 });

  // §compras-cabecalho — copy literal do contrato.
  const cabecalho = page.locator('[data-contract="compras-cabecalho"]');
  await expect(cabecalho.getByPlaceholder('Buscar NF-e, fornecedor, ref, chave...')).toBeVisible();
  await expect(cabecalho.getByText('Importar XML')).toBeVisible();
  // `Nova compra` é condicional a `permissions.create` (= `purchase.create`, convergência C1):
  // o admin da lane tem todas as abilities via Gate::before, então aqui ele aparece.
  await expect(cabecalho.getByText('Nova compra')).toBeVisible();

  // §compras-abas — as 4 abas de FILTRO da tela viva. Match por `hasText` e não por texto
  // exato porque a aba `Todas` carrega o contador dentro do próprio `<a>` (`Todas <span>0</span>`):
  // o número é DADO, não copy, e pinar "Todas 0" travaria o assert num estado de seed.
  const abas = page.locator('[data-contract="compras-abas"]');
  for (const rotulo of ['Todas', 'A pagar', 'Rascunhos', 'Em trânsito']) {
    await expect(abas.locator('a').filter({ hasText: rotulo })).toHaveCount(1);
  }

  // §compras-kpis — os 4 KPIs chegam por `Inertia::defer`; o skeleton NÃO carrega a âncora,
  // então esperar por ela é esperar o defer resolver (sem waitForTimeout).
  const kpis = page.locator('[data-contract="compras-kpis"]');
  await expect(kpis).toBeVisible({ timeout: 15_000 });
  for (const rotulo of ['A pagar', 'Em trânsito', 'Volume do mês', 'Fornecedores ativos']) {
    await expect(kpis.getByText(rotulo, { exact: true })).toBeVisible();
  }

  // §compras-tabela, estado `vazio` declarado no contrato. ACHADO: no vazio a âncora
  // `data-contract="compras-tabela"` NÃO existe — ela vive no `<table>`, que só renderiza
  // com linhas; a copy do vazio fica fora de qualquer âncora. Por isso o assert é pela copy.
  await expect(page.getByText('Nenhuma compra encontrada com o filtro atual.')).toBeVisible();

  // ORDEM — as âncoras presentes respeitam a ordem declarada no contrato. Comparar a
  // subsequência (e não a lista inteira) mantém o assert válido nos DOIS estados: hoje o
  // seed não tem compra, amanhã pode ter e a 4ª âncora aparece.
  const vistas = await ancorasNoDom(page);
  const indices = vistas.map((id) => ORDEM_CONTRATO.indexOf(id));
  expect(vistas.length).toBeGreaterThanOrEqual(3);
  expect(indices).toEqual([...indices].sort((a, b) => a - b));
});

test('UC-CMP-06 · a busca entra na querystring e sobrevive ao reload; a aba filtra sem mexer na URL', async ({ page }) => {
  await page.goto('/compras');
  await page.waitForLoadState('networkidle');

  const busca = page.getByPlaceholder('Buscar NF-e, fornecedor, ref, chave...');
  await busca.fill('fornecedor-inexistente-e2e');
  await busca.press('Enter');

  // O `router.visit` do Enter é partial reload (`only: rows/summary/filters`) levando a
  // querystring — é o contrato R-COM-004 (`?q=...&stage=...`) visto pela tela.
  await expect(page).toHaveURL(/q=fornecedor-inexistente-e2e/, { timeout: 15_000 });

  // Sobrevive ao reload: o campo é alimentado por `filters.q`, que vem do servidor.
  await page.reload();
  await page.waitForLoadState('networkidle');
  await expect(page.getByPlaceholder('Buscar NF-e, fornecedor, ref, chave...')).toHaveValue(
    'fornecedor-inexistente-e2e'
  );

  // A aba é filtro LOCAL (`setLocalFilter` sobre `rows.data`), não navegação: ela NÃO pode
  // injetar `stage` na URL. É o outro lado do UC-CMP-06 — os dois vocabulários (ids de aba
  // `abertas/rascunhos/transito` × whitelist core `draft/ordered/pending/received`) não se
  // encostam hoje, e este assert trava isso: aba que navegasse com o id de EXIBIÇÃO derruba
  // a listagem em 302, que é exatamente a regressão descrita no UC.
  await page.locator('[data-contract="compras-abas"]').getByText('Rascunhos', { exact: true }).click();
  await expect(page).not.toHaveURL(/stage=rascunhos/);
  await expect(page).toHaveURL(/q=fornecedor-inexistente-e2e/);
});

test('esconder coluna no cockpit persiste no cliente entre reloads', async ({ page }) => {
  // SEM UC-id de propósito: a visibilidade de coluna NÃO tem UC em Compras/Index.casos.md
  // (os UC-CMP-01..10 cobrem tenant, permissão, filtro, sort, localização e valor). Citar um
  // UC vizinho creditaria prova a quem não a pediu. Criar o UC exige editar o `casos.md`, que
  // está fora do prefixo desta thread — fica declarado no _saida/PR como pendência.
  await page.goto('/compras');
  await page.waitForLoadState('networkidle');

  await page.getByRole('button', { name: /Visibilidade da coluna/ }).click();

  const fornecedor = page.getByRole('checkbox', { name: 'Fornecedor' });
  await expect(fornecedor).toBeChecked();
  await fornecedor.uncheck();

  // `useColumnVisibility` grava em localStorage (`compras-cols-v1`) — preferência do cliente,
  // não do servidor. O reload é a prova.
  await page.reload();
  await page.waitForLoadState('networkidle');
  await page.getByRole('button', { name: /Visibilidade da coluna/ }).click();
  await expect(page.getByRole('checkbox', { name: 'Fornecedor' })).not.toBeChecked();

  // Coluna obrigatória continua travada (em COLUMNS, `acao` e `compra` são `required`).
  await expect(page.getByRole('checkbox', { name: 'Ação' })).toBeDisabled();
});

test('UC-CMP-05 · o cockpit é de LEITURA: nenhuma mutação sai para /compras e "Nova compra" delega /purchases', async ({ page }) => {
  // Invariante 1 do módulo — as rotas de `Modules/Compras/Routes/web.php` são só
  // `GET /compras` e `GET /compras/{id}/detalhe`. Esta é a prova pelo eixo de REDE:
  // qualquer verbo de escrita apontando para /compras significa que a tela passou a mutar
  // onde não há endpoint (e a convergência C1 com /purchases se rompeu).
  const mutacoesNoCockpit: string[] = [];
  page.on('request', (req) => {
    const url = new URL(req.url());
    if (req.method() !== 'GET' && url.pathname.startsWith('/compras')) {
      mutacoesNoCockpit.push(`${req.method()} ${url.pathname}`);
    }
  });

  await page.goto('/compras');
  await page.waitForLoadState('networkidle');
  await page.locator('[data-contract="compras-abas"]').getByText('A pagar', { exact: true }).click();
  const busca = page.getByPlaceholder('Buscar NF-e, fornecedor, ref, chave...');
  await busca.fill('x');
  await busca.press('Enter');
  await page.waitForLoadState('networkidle');

  expect(mutacoesNoCockpit).toEqual([]);

  // `+ Nova compra` delega o CRUD (Non-Goal C1 do Purchase/Create: não nasce
  // Pages/Compras/Create.tsx). Aqui se asserta só o DESTINO — o render de
  // /purchases/create é assunto de e2e/purchase-create.spec.ts.
  await page.locator('[data-contract="compras-cabecalho"]').getByText('Nova compra').click();
  await expect(page).toHaveURL(/\/purchases\/create/, { timeout: 15_000 });
});

test.fixme('UC-CMP-07 · ordenar pela coluna muda sort/dir na querystring e reordena a lista', async ({ page }) => {
  // PENDENTE POR SEED, não por falta de comportamento: o `<thead>` só existe com linhas, e o
  // VisregTenantSeeder não cria `transactions type=purchase`. Criar fixture aqui seria fixture
  // paralela ao Pest (o oráculo do vocabulário de `sort` é ComprasContratoFiltrosTest).
  //
  // ACHADO DE ACESSIBILIDADE (medido, e contraria o que a ordem de serviço desta thread
  // afirmava): o `SortHeader` de Compras/Index.tsx:478-486 é um `<th onClick>` — SEM
  // `aria-sort` e SEM `<button type="button">` interno. O assert canônico de ordenação
  // acessível (`aria-sort` none → ascending) NÃO tem onde morder hoje. Este spec não inventa
  // o seletor: a semântica entra por um PR próprio da tela, e só então este teste a asserta.
  await page.goto('/compras');
  await page.getByRole('columnheader', { name: /Fornecedor/ }).click();
  await expect(page).toHaveURL(/sort=contact_name/);
});

test.fixme('UC-CMP-02 · clicar na linha abre o drawer de detalhe', async ({ page }) => {
  // PENDENTE POR SEED (mesma razão do anterior): sem compra não há linha para clicar. O drawer
  // chega por `Inertia::defer` em `compra_detalhe` via `GET /compras?compra_id=...`.
  // O lado [T0] do UC-CMP-02 (detalhe cross-tenant responde 404) é do Pest MultiTenantTest e
  // NÃO se reimplementa aqui.
  await page.goto('/compras');
  await page.getByRole('row').nth(1).click();
  await expect(page).toHaveURL(/compra_id=/);
});
