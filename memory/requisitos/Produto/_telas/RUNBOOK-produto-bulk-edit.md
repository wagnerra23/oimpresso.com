---
slug: inventory-runbook-produto-bulk-edit
title: "Produto — Runbook da tela Edição em massa (migração MWART)"
type: runbook
module: Inventory
status: active
date: 2026-05-15
---

# RUNBOOK — Edição em massa (`/products/mass-edit`)

> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
> **Origem:** Blade `view('product.bulk-edit')` via [ProductController@bulkEdit](../../../app/Http/Controllers/ProductController.php#L2035)
> **Alvo:** `Pages/Produto/BulkEdit.tsx`
> **Blueprint Cowork:** Família AppShellV2 + tokens + header pattern do `produto-cockpit/`. **Divergência declarada (ADR 0149):** datatable multi-row edit não cabe no pattern Cockpit/drawer. `divergence_from_blueprint: "bulk-edit datatable multi-row distinta de Index — exige tabela edit-in-place densa".`

## 1. Estado final esperado

| Verificação | Como conferir |
|---|---|
| `/products/mass-edit` Inertia | `curl -H "X-Inertia: true"` (com `selected_products` query) → `"component":"Produto/BulkEdit"` |
| Permissão `product.update` | sem permissão → 403 |
| `business_id` isola | produtos cross-tenant não aparecem |
| Selected products param | sem `selected_products` → redirect Index |

## 2. Pré-condições

- [ ] Permissão `product.update`
- [ ] `selected_products` query string (CSV de IDs)
- [ ] Charter: `Pages/Produto/BulkEdit.charter.md`

## 3. Passo-a-passo

### 3.1 Branch dual em `bulkEdit(Request $request)`

```php
$selected_products_string = $request->input('selected_products');
if (! empty($selected_products_string)) {
    // ... lógica existente ...

    if ($request->header('X-Inertia')) {
        return Inertia::render('Produto/BulkEdit', [
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'categoryId' => $p->category_id,
                'subCategoryId' => $p->sub_category_id,
                'brandId' => $p->brand_id,
                'tax' => $p->tax,
                'productLocations' => $p->product_locations->pluck('id')->toArray(),
                'variations' => $p->variations->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'subSku' => $v->sub_sku,
                    'defaultPurchasePrice' => (float) $v->default_purchase_price,
                    'defaultSellPrice' => (float) $v->default_sell_price_inc_tax,
                ]),
            ]),
            'categories' => $categories,
            'subCategories' => $sub_categories,
            'brands' => $brands,
            'taxes' => $taxes,
            'taxAttributes' => $tax_attributes,
            'priceGroups' => $price_groups,
            'businessLocations' => $business_locations,
        ]);
    }

    return view('product.bulk-edit')->with(compact(/* ... legacy ... */));
}
```

### 3.2 Page Inertia

`Pages/Produto/BulkEdit.tsx`:
- AppShellV2
- PageHeader "Edição em massa · {N} produtos"
- Tabela densa edit-in-place: cada linha = 1 produto + colunas editáveis (Categoria, Sub-categoria, Brand, Tax, Locations, preços por variação)
- Submit POST `/products/mass-update`
- Aviso: alterações afetam N produtos simultaneamente

### 3.3 Divergência blueprint declarada

Charter `divergence_from_blueprint`: explica que datatable multi-row edit é pattern distinto de Index Cockpit (drawer). ADR 0149 admite divergência caso justificada — mantém família AppShellV2 + tokens canon.

## 4. Testes

```bash
vendor/bin/pest tests/Feature/Produto/Wave2BulkEditBaselineTest.php
vendor/bin/pest tests/Feature/Produto/Wave2BulkEditInertiaTest.php
```

## 5. Refs

- Visual comparison: [`produto-bulk-edit-visual-comparison.md`](produto-bulk-edit-visual-comparison.md)
- ADR 0149

## 6. Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Runbook criado em Wave 2 B4 Produto. |
