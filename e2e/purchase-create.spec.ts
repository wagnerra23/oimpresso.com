import { test, expect, type Page } from '@playwright/test';

// E2E de comportamento — CRIAR COMPRA (`/purchases/create`), Gate G-3 (ADR 0264).
//
// FONTE DO QUE SE ASSERTA:
//   1. governance/design/contracts/purchase-create.contract.json — copy e ordem das 5 seções.
//      A `_nota_fonte` dele é explícita: a tela NÃO tem âncora de protótipo (`n/a`, herda
//      PT-02 Form-Drawer) e o contrato defende regressão de copy/ordem, não fidelidade visual.
//   2. resources/js/Pages/Purchase/Create.casos.md — os UC-PURCRE-* citados em cada título.
//
// DUAL PATH (UC-PURCRE-01, medido em app/Http/Controllers/PurchaseController.php:403):
// `create()` só devolve a Page Inertia quando o request traz o header `X-Inertia` OU `?v=2`;
// o GET normal segue entregando a view Blade legacy. Um `page.goto('/purchases/create')` cru
// receberia o BLADE — por isso o caminho React é exercitado com `?v=2`, que o próprio UC
// declara como afordância canônica ("Quando o request traz o header Inertia ou `?v=2`").
// Isso não é inventar seletor: é usar a porta que o controller e o casos.md declaram.
//
// ORÁCULO DE DOMÍNIO É O PEST: `[T0]` cross-tenant da grade (UC-PURCRE-02/03) vive em
// tests/Feature/Purchase/PurchaseGradeMatrixTest.php, e o `[V0]` de valor/estoque em
// Modules/Compras/Tests/Feature/PurchaseCalculoValorEstoqueE2ETest.php. Aqui se prova o
// CAMINHO DE TELA. Reimplementar a regra de valor duplicaria régua — e regra de valor tem
// exigência própria (dupla prova + antes→depois + [W]), que um E2E não substitui.
//
// SEED DA LANE (VisregTenantSeeder, medido): 1 produto `type=single` sem estoque e 1 contact.
// NÃO existe produto VARIÁVEL, logo a grade tam×cor (US-COM-005) não tem o que carregar: os
// casos 7/8 da ordem de serviço ficam `test.fixme` com a razão escrita, e a fixture NÃO é
// criada aqui (seria fixture paralela ao Pest — PARAR SE (b) da thread).
//
// Locators RESILIENTES (role/label/text) + âncora semântica `data-contract` — nunca classe
// CSS de estilo (L-24).

const ORDEM_CONTRATO = [
  'purchase-dados-gerais',
  'purchase-itens',
  'purchase-descontos',
  'purchase-totais',
  'purchase-notas',
];

/** Âncoras `data-contract` presentes no DOM, na ordem em que o documento as declara. */
async function ancorasNoDom(page: Page): Promise<string[]> {
  const vistas = await page
    .locator('[data-contract]')
    .evaluateAll((nos) => nos.map((n) => n.getAttribute('data-contract') ?? ''));

  return vistas.filter((id) => ORDEM_CONTRATO.includes(id));
}

test('UC-PURCRE-01 · o caminho React entrega as 5 seções do contrato, na ordem declarada', async ({ page }) => {
  await page.goto('/purchases/create?v=2');
  await page.waitForLoadState('networkidle');

  // §purchase-dados-gerais — copy literal do contrato.
  const dadosGerais = page.locator('[data-contract="purchase-dados-gerais"]');
  await expect(dadosGerais).toBeVisible({ timeout: 15_000 });
  await expect(dadosGerais.getByText('Dados gerais', { exact: true })).toBeVisible();
  for (const rotulo of ['Filial *', 'Fornecedor *', 'Data *', 'Status *', 'Ref. Nº']) {
    await expect(dadosGerais.getByText(rotulo, { exact: true })).toBeVisible();
  }

  // §purchase-itens no estado `vazio` — a tela abre sem linha nenhuma.
  const itens = page.locator('[data-contract="purchase-itens"]');
  await expect(itens.getByText('Itens da compra', { exact: true })).toBeVisible();
  await expect(itens.getByText('Nenhum item adicionado.', { exact: true })).toBeVisible();

  // §purchase-descontos / §purchase-totais / §purchase-notas.
  const descontos = page.locator('[data-contract="purchase-descontos"]');
  await expect(descontos.getByText('Descontos / Impostos / Frete', { exact: true })).toBeVisible();

  const totais = page.locator('[data-contract="purchase-totais"]');
  await expect(totais.getByText('Totais', { exact: true })).toBeVisible();
  // Os rótulos de Totais são contrato de COPY, nunca de número: o contrato declara, na própria
  // `_pendente_w`, que pinar o valor aqui não substitui a REGRA MESTRE de valor/estoque.
  //
  // `Frete` fica FORA desta lista, e o motivo é um achado: o contrato o declara junto dos
  // outros quatro, mas a linha dele é CONDICIONAL (`totais.frete > 0` — Create.tsx:645). Com o
  // formulário vazio ela não renderiza, e o gate de contrato não acusa porque casa a copy no
  // fonte, onde a string existe. Assertá-lo como incondicional foi o que reprovou este teste no
  // primeiro run — a tela está certa; o contrato é que declara um rótulo de estado sem dizer
  // que é de estado.
  for (const rotulo of ['Subtotal itens', 'Desconto', 'Impostos', 'Total final']) {
    await expect(totais.getByText(rotulo, { exact: true })).toBeVisible();
  }

  await expect(page.locator('[data-contract="purchase-notas"]').getByText('Notas adicionais', { exact: true })).toBeVisible();

  // ORDEM declarada no contrato — subsequência, para seguir válido se uma seção virar
  // condicional no futuro.
  const vistas = await ancorasNoDom(page);
  const indices = vistas.map((id) => ORDEM_CONTRATO.indexOf(id));
  expect(vistas).toEqual(ORDEM_CONTRATO);
  expect(indices).toEqual([...indices].sort((a, b) => a - b));
});

test('UC-PURCRE-01 · lançamento incompleto não chega à rede — a tela barra antes do POST', async ({ page }) => {
  // ERRATA MEDIDA: a 1ª versão deste teste esperava um POST ao clicar em "Salvar compra" com o
  // formulário vazio, e reprovou com `posts = []`. A causa não é a tela estar errada — são os
  // 4 campos `required` de §purchase-dados-gerais (Create.tsx:339/362/386/396: Filial,
  // Fornecedor, Data, Status). A validação do browser barra o submit ANTES da rede, e o
  // `PageHeader` não usa portal (o `type="submit"` está mesmo dentro do `<form>`). Eu havia
  // afirmado "sem filial o servidor recusa" sem medir quem recusa primeiro.
  //
  // O que ficou é o comportamento REAL e verde: compra incompleta não sai da tela. A forma do
  // envio (1 POST com N linhas, UC-PURCRE-05) exige preencher os obrigatórios e ter item —
  // fica no `test.fixme` abaixo, junto com a grade, porque depende do mesmo seed.
  const requisicoes: string[] = [];
  page.on('request', (req) => {
    const url = new URL(req.url());
    if (req.method() !== 'GET' && url.pathname.startsWith('/purchases')) {
      requisicoes.push(`${req.method()} ${url.pathname}`);
    }
  });

  await page.goto('/purchases/create?v=2');
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-contract="purchase-dados-gerais"]')).toBeVisible({ timeout: 15_000 });

  await page.getByRole('button', { name: /Salvar compra/ }).click();
  await page.waitForLoadState('networkidle');

  expect(requisicoes).toEqual([]);
  // E a tela continua ali — não navegou nem limpou o formulário.
  await expect(page.locator('[data-contract="purchase-totais"]')).toBeVisible();
});

test.fixme('UC-PURCRE-01 · controle negativo: GET sem Inertia e sem ?v=2 devolve o Blade legacy', async ({ page }) => {
  // PENDENTE POR AMBIENTE NÃO MEDIDO, não por falta de comportamento: o dual path está no
  // controller (`PurchaseController@create:403` → senão `view('purchase.create')`) e o Pest
  // Wave2CreateInertiaTest já o prova por casamento no fonte. O que falta medir é se a view
  // Blade legacy RENDERIZA no seed mínimo da lane (ela puxa taxas, unidades e layouts que o
  // VisregTenantSeeder não cria). Deixar verde sem medir seria afirmar cobertura inexistente;
  // deixar vermelho por ambiente seria gate flaky (ADR 0261). Fica declarado até alguém medir.
  await page.goto('/purchases/create');
  await expect(page.locator('[data-contract="purchase-dados-gerais"]')).toHaveCount(0);
});

test.fixme('UC-PURCRE-05 · a grade tam×cor expande N células em N linhas num POST único', async ({ page }) => {
  // PENDENTE POR SEED: exige produto VARIÁVEL (`type=variable` com dois eixos) e fornecedor,
  // e o VisregTenantSeeder só cria um `single`. Criar a fixture aqui seria fixture paralela ao
  // Pest — PurchaseGradeMatrixTest é o dono do dado da grade (UC-PURCRE-02/03/04).
  // Quando o seed ganhar o produto variável: preencher os 4 campos `required` (sem eles o
  // browser barra o submit — ver a errata no teste acima), abrir "Adicionar por grade
  // (tam × cor)", preencher K células, salvar, interceptar o POST /purchases e contar
  // `linhas` === K, com `variation_id` distinto por célula.
  await page.goto('/purchases/create?v=2');
  await expect(page.getByText('Adicionar por grade (tam × cor)')).toBeVisible();
});

test.fixme('UC-PURCRE-04 · a grade nunca abre vazia — degrada 2D → 1 eixo → single', async ({ page }) => {
  // PENDENTE POR SEED (mesma razão). O layout vem de `GET /purchases/grade-matrix` e o modo
  // (`2d` | `matrix-1d` | `single`) é auto-detectado no servidor. O caso de tela a provar é o
  // aviso visível quando o produto tem 1 eixo — nunca uma matriz vazia em silêncio.
  await page.goto('/purchases/create?v=2');
  await expect(page.getByText('Carregando grade…')).toBeVisible();
});

test.fixme('sem a permission purchase.create o acesso a /purchases/create é recusado', async ({ page }) => {
  // SEM UC-id: o gate de permissão é citado DENTRO do UC-PURCRE-01 ("com o gate
  // `purchase.create` preservado") e provado por Wave2CreateBaselineTest — não tem UC próprio,
  // e inventar um creditaria prova a quem não a pediu.
  // PENDENTE POR SEED: a lane loga o admin `Admin#1`, que recebe TODAS as abilities via
  // Gate::before (VisregTenantSeeder). Provar o 403 exige um segundo usuário sem a permission,
  // e esse usuário é fixture de tenant — decisão de seed, fora do prefixo desta thread.
  await page.goto('/purchases/create?v=2');
  await expect(page.locator('body')).toContainText('403');
});
