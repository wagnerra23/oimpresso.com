import { test, expect } from '@playwright/test';

// Stub E2E do trio de Produto/Edit — contrato em resources/js/Pages/Produto/Edit.casos.md.
// Nascido pelo piloto do agent `sdd-from-source` (ADR 0351), triangulando 3 fontes:
//   React (Edit.tsx → PUT /products/{id} → ProductController@update) + Blade
//   (resources/views/product/edit.blade.php) + Delphi (ANTI-REGRESSAO-cadastro-produto-legacy).
//
// test.fixme = PENDENTE (não executa, não quebra o CI). Satisfaz só a rastreabilidade
// G-2 (o UC-id é citado por >=1 teste). Troque por asserção real / migre pra Pest
// `ProdutoEditContratoTest` (cross-tenant biz=1 vs biz=2) no CT100 — testes NÃO rodam
// local (ADR 0062). Locators RESILIENTES (role/label/text), nunca classe CSS (L-24).
// NÃO edite a tela viva sem charter + gate visual (R7).

test.fixme('UC-PEDIT-01: editar produto variável não apaga as variações existentes', async ({ page }) => {
  // Dado produto variável com N variações · Quando PUT /products/{id} altera só o nome ·
  // Então as N variações persistem (update() não recria/apaga a grade).
  await page.goto('/products');
  await expect(page.getByRole('heading', { name: /Editar/ })).toBeVisible();
});

test.fixme('UC-PEDIT-02: tipo (single/variable/combo) não muda após criação', async ({ page }) => {
  // Dado produto single · Quando PUT forja type=variable · Então continua single.
  await page.goto('/products');
});

test.fixme('UC-PEDIT-03: editar produto de outro business retorna 404 (não vaza, não 500)', async ({ page }) => {
  // [T0] Dado GET/PUT /products/{id_alheio} · Então 404 (padrão UC-PCAD-06 / UC-PTAB-04).
  // Verificação real = Pest cross-tenant biz=1 vs biz=2 no CT100.
  await page.goto('/products');
});

test.fixme('UC-PEDIT-04: campo monetário no update não infla no parser pt-BR', async ({ page }) => {
  // [V0] Dado PUT single_dpp='1.234,56' · Então grava 1234.56, nunca ×100k (num_uf,
  // ProductController.php:1102-1106). Mesmo par 1.234,56 / 204.99605 do UC-PCAD-04.
  await page.goto('/products');
});
