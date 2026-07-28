import { test, expect } from '@playwright/test';

// Stub E2E do trio de Produto/Show — contrato em resources/js/Pages/Produto/Show.casos.md.
// Nascido pelo agent `sdd-from-source` (ADR 0351), triangulando 3 fontes:
//   React (Show.tsx → GET /products/{id} → ProductController@show, branch X-Inertia :810-843)
//   + Blade (resources/views/product/view-modal.blade.php — a ficha que roda em prod HOJE,
//     mais rica que a React) + Delphi (ANTI-REGRESSAO-cadastro-produto-legacy, AR-PROD-*).
//
// test.fixme = PENDENTE (não executa, não quebra o CI). Satisfaz só a rastreabilidade G-2
// (o UC-id é citado por >=1 teste). Os UCs 01/02/03/06 já têm Pest REAL rodando na lane
// `Estoque · MySQL` (tests/Feature/Produto/ProdutoShowContratoTest.php) — aqui ficam por
// redundância de eixo (render, não payload). Os 04/05/07 são render-only: só viram verde
// quando alguém os escrever de verdade.
// Testes NÃO rodam local (ADR 0062). Locators RESILIENTES (role/label/text), nunca classe
// CSS (L-24). NÃO edite a tela viva sem charter + gate visual (R7).

test.fixme('UC-PSHOW-01: ficha não mostra custo a quem não pode ver preço de compra', async ({ page }) => {
  // Dado user com product.view e SEM view_purchase_price · Quando abre /products/{id} ·
  // Então a coluna "Preço compra" não existe (Blade: @can('view_purchase_price');
  // Delphi AR-PROD-015: o campo SOME, não fica read-only).
  await page.goto('/products');
  await expect(page.getByRole('heading', { name: /Produto/ })).toBeVisible();
});

test.fixme('UC-PSHOW-02: ficha de produto de outro business retorna 404', async ({ page }) => {
  // [T0] Verificação real = Pest cross-tenant biz=1 vs biz=2 no CT100 (UC-PSHOW-02).
  await page.goto('/products');
});

test.fixme('UC-PSHOW-03: aba Estoque mostra o nome do local do rack', async ({ page }) => {
  // Dado produto com rack cadastrado · Quando abre a aba Estoque · Então a coluna "Local"
  // mostra o nome da localização — não "—" (Blade view-modal:140 `{{$rd->name}}`).
  await page.goto('/products');
});

test.fixme('UC-PSHOW-04: aba Variações identifica o eixo da variação, não só o valor', async ({ page }) => {
  // Dado produto variável (eixo "Cor", valor "Azul") · Então a linha mostra "Cor - Azul"
  // (Blade: {{$variation->product_variation->name}} - {{$variation->name}}).
  await page.goto('/products');
});

test.fixme('UC-PSHOW-05: preço de compra e de venda declaram a mesma base de imposto', async ({ page }) => {
  // [V0] O Blade rotula 4 colunas (compra exc/inc + venda exc/inc). A ficha React não pode
  // exibir bases diferentes sob rótulos neutros — margem lida a olho sairia errada.
  await page.goto('/products');
});

test.fixme('UC-PSHOW-07: ficha mostra o alerta de estoque mínimo quando o produto controla estoque', async ({ page }) => {
  // Dado produto com enable_stock e alert_quantity · Então a ficha exibe o alerta
  // (Blade view-modal:67-70; Delphi AR-PROD-053 Estoque Mín./Máx.).
  await page.goto('/products');
});
