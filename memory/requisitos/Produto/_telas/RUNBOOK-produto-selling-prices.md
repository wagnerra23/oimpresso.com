---
slug: inventory-runbook-produto-selling-prices
title: "Produto — Runbook da tela Tabelas de preço (migração MWART)"
type: runbook
module: Inventory
status: active
date: 2026-05-15
---

# RUNBOOK — Tabelas de preço (`/products/add-selling-prices/{id}`)

> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
> **Origem:** Blade `view('product.add-selling-prices')` via [ProductController@addSellingPrices](../../../app/Http/Controllers/ProductController.php#L1697)
> **Alvo:** `Pages/Produto/SellingPrices.tsx`
> **Blueprint Cowork:** Reusa pattern visual `produto-cockpit/` família AppShellV2. **Divergência:** matriz de preços `variation × price_group` é tabela densa específica — divergence_from_blueprint documentada no charter.

## 1. Estado final esperado

| Verificação | Como conferir |
|---|---|
| `/products/add-selling-prices/{id}` Inertia | `curl -H "X-Inertia: true"` → `"component":"Produto/SellingPrices"` |
| Permissão `product.create` | sem permissão → 403 |
| `business_id` isola | cross-tenant → 404 |
| Matriz variação × price_group renderiza | linhas = variações ativas, colunas = price_groups ativos |
| Tipo preço por célula | dropdown `fixed`/`percentage` |

## 2. Pré-condições

- [ ] Permissão `product.create`
- [ ] Produto tem pelo menos 1 variation
- [ ] Pelo menos 1 SellingPriceGroup ativo no business
- [ ] Charter: `Pages/Produto/SellingPrices.charter.md`

## 3. Passo-a-passo

### 3.1 Branch dual em `addSellingPrices($id)`

```php
if (request()->header('X-Inertia')) {
    return Inertia::render('Produto/SellingPrices', [
        'product' => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'type' => $product->type,
        ],
        'variations' => $product->variations->map(fn ($v) => [
            'id' => $v->id,
            'name' => $v->name,
            'subSku' => $v->sub_sku,
            'defaultSellPrice' => (float) $v->default_sell_price_inc_tax,
        ]),
        'priceGroups' => $price_groups->map(fn ($pg) => [
            'id' => $pg->id,
            'name' => $pg->name,
            'description' => $pg->description,
        ]),
        'variationPrices' => $variation_prices,
        'permissions' => [
            'save' => auth()->user()->can('product.create'),
        ],
    ]);
}
return view('product.add-selling-prices')->with(compact('product', 'price_groups', 'variation_prices'));
```

### 3.2 Page Inertia

`Pages/Produto/SellingPrices.tsx`:
- AppShellV2
- PageHeader "Tabelas de preço · {nome produto}" + ações
- Tabela densa: linhas = variações; colunas = price_groups
- Por célula: input numérico + dropdown tipo (fixed/percentage)
- Submit POST `/products/save-selling-prices`

## 4. Divergência blueprint

Charter explicita `divergence_from_blueprint: "matriz variation × price_group é tabela densa específica — não cabe no pattern drawer/list do Cowork blueprint"`. ADR 0149 §"Casos que NÃO se qualificam" exige Cowork próprio pra patterns visuais distintos; nesta tela mantemos família AppShellV2 + tokens + header pattern, divergimos no conteúdo central da tabela densa (justificado).

## 5. Testes

```bash
vendor/bin/pest tests/Feature/Produto/Wave2SellingPricesBaselineTest.php
vendor/bin/pest tests/Feature/Produto/Wave2SellingPricesInertiaTest.php
```

## 6. Refs

- Visual comparison: [`produto-selling-prices-visual-comparison.md`](produto-selling-prices-visual-comparison.md)
- ADR 0149

## 7. Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Runbook criado em Wave 2 B4 Produto. |
