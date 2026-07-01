import { test, expect } from '@playwright/test';

// E2E de comportamento — VENDA BALCÃO A PRAZO (Onda Q2 do mandato ONDAS-QUALIDADE,
// ADR 0264 G-3). É o primeiro elo do fio que [W] protege: venda → faturamento → caixa
// (o encadeamento backend CU-3→CU-5 é provado por tests/Feature/TravaSegunda/
// RetencaoLoopE2ETest.php; aqui provamos o LADO DA TELA que a Larissa usa).
//
// Contrato: resources/js/Pages/Sells/Create.casos.md
//   UC-S01 · venda balcão a prazo — adicionar produto, salvar SEM pagamento,
//            sistema acusa saldo devedor (payment_status=due no backend).
//
// Harness: tela V2 Inertia (flag useV2SellsCreate cai no fallback default TRUE no CI
// sem GrowthBook — FeatureFlagService::$fallbackDefaults). Produto E2E-0001 vem do
// VisregTenantSeeder (enable_stock=0 → sem exigir saldo de estoque). Cliente default
// Walk-In e location Matriz já chegam pré-selecionados (props do controller).
// Locators RESILIENTES — role/label/placeholder, nunca classe CSS (L-24).

test('UC-S01 · venda balcão a prazo — produto no carrinho, salvar sem pagamento', async ({ page }) => {
  await page.goto('/sells/create');
  await page.waitForLoadState('networkidle');

  // Tela V2 carregada ("Adicionar venda" é o H1; o submit é "Salvar venda",
  // que nasce DISABLED até ter produto — run 27367899441 pegou a confusão).
  await expect(page.getByRole('heading', { name: 'Adicionar venda' })).toBeVisible({ timeout: 15_000 });
  const salvar = page.getByRole('button', { name: /Salvar venda/i });
  await expect(salvar).toBeDisabled();

  // Busca o produto do seed e adiciona ao carrinho (dropdown role=option).
  const busca = page.getByPlaceholder(/Buscar por nome, SKU/i);
  await busca.fill('E2E-0001');
  await page.getByRole('option', { name: /Produto E2E Balcão/i }).first().click();

  // Linha entrou na tabela de produtos (nome + SKU visíveis).
  await expect(page.getByText('Produto E2E Balcão').first()).toBeVisible();
  await expect(page.getByText(/SKU E2E-0001/i)).toBeVisible();

  // Venda a prazo: NENHUM pagamento informado → indicador de saldo devedor
  // (decisão [W] 2026-05-27: fiado permitido, backend grava payment_status=due).
  await expect(page.getByText(/Venda a prazo — saldo devedor/i)).toBeVisible();

  // Salvar — POST /pos com is_direct_sale=1 (sem exigir caixa aberto).
  // Com produto no carrinho o submit habilita (canSubmit).
  await expect(salvar).toBeEnabled();
  await salvar.click();

  // Sucesso REAL = saiu do formulário (redirect Inertia) sem erro de venda.
  await expect(page).not.toHaveURL(/\/sells\/create/, { timeout: 20_000 });
  await expect(page.getByText(/Falha|Erro ao|não pôde/i)).toHaveCount(0);
});

// UC-S11 · da lista, iniciar a devolução de uma venda (Index.casos.md).
// Roda DEPOIS do UC-S01 (mesma suíte): a venda balcão criada acima garante ≥1
// linha na lista. Defende a regressão do #1032 (menu de Ações sumiu no rewrite
// Cowork) — remover o menu/Devolução volta a quebrar o CI. Locators por
// role/nome (L-24, sem classe CSS).
test('UC-S11 · menu de Ações da venda oferece Devolução → /sell-return/add', async ({ page }) => {
  await page.goto('/sells');
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: 'Vendas' })).toBeVisible({ timeout: 15_000 });

  // Abre o menu ⋮ da primeira linha (kebab restaurado — #3494).
  const acoes = page.getByRole('button', { name: 'Ações da venda' }).first();
  await expect(acoes).toBeVisible({ timeout: 15_000 });
  await acoes.click();

  // O item Devolução existe e aponta pro formulário de retorno da venda (→ estoque).
  const devolucao = page.getByRole('menuitem', { name: 'Devolução' });
  await expect(devolucao).toBeVisible();
  await expect(devolucao).toHaveAttribute('href', /\/sell-return\/add\/\d+/);
});
