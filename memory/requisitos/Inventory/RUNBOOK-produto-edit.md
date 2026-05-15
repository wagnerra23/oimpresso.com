---
slug: inventory-runbook-produto-edit
title: "Produto — Runbook da tela Editar produto (migração MWART)"
type: runbook
module: Inventory
status: active
date: 2026-05-15
---

# RUNBOOK — Editar produto (`/products/{id}/edit`)

> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
> **Origem:** Blade `view('product.edit')` via [ProductController@edit](../../../app/Http/Controllers/ProductController.php#L610)
> **Alvo:** `Pages/Produto/Edit.tsx`
> **Blueprint Cowork:** [`produto-cockpit/`](../../../prototipo-ui/prototipos/produto-cockpit/) — mesma família visual do Create (form full-width AppShellV2)

## 1. Estado final esperado

| Verificação | Como conferir |
|---|---|
| `/products/{id}/edit` retorna Inertia | `curl -H "X-Inertia: true"` → `"component":"Produto/Edit"` |
| Permissão `product.update` | sem permissão → 403 |
| `business_id` isolation | cross-tenant → 404 |
| Form preenchido com dados atuais | inputs têm `defaultValue` correto |

## 2. Pré-condições

- [ ] Permissão `product.update`
- [ ] Produto existe + `business_id` bate
- [ ] Charter: `Pages/Produto/Edit.charter.md`

## 3. Passo-a-passo

### 3.1 Branch dual em `edit($id)`

ANTES de `return view('product.edit')`:

```php
if (request()->header('X-Inertia')) {
    return Inertia::render('Produto/Edit', [
        'product' => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'type' => $product->type,
            'brandId' => $product->brand_id,
            'unitId' => $product->unit_id,
            'subUnitIds' => $product->sub_unit_ids,
            'categoryId' => $product->category_id,
            'subCategoryId' => $product->sub_category_id,
            'tax' => $product->tax,
            'taxType' => $product->tax_type,
            'barcodeType' => $product->barcode_type,
            'enableStock' => (bool) $product->enable_stock,
            'alertQuantity' => $alert_quantity,
            'weight' => $product->weight,
            'productDescription' => $product->product_description,
            'productLocations' => $product->product_locations->pluck('id')->toArray(),
            'image' => $product->image_url,
            'warrantyId' => $product->warranty_id,
            'productCustomField1' => $product->product_custom_field1,
            'productCustomField2' => $product->product_custom_field2,
            'productCustomField3' => $product->product_custom_field3,
            'productCustomField4' => $product->product_custom_field4,
        ],
        'categories' => $categories,
        'brands' => $brands,
        'units' => $units,
        'subUnits' => $sub_units,
        'taxes' => $taxes,
        'taxAttributes' => $tax_attributes,
        'barcodeTypes' => $barcode_types,
        'subCategories' => $sub_categories,
        'businessLocations' => $business_locations,
        'rackDetails' => $rack_details,
        'productTypes' => $product_types,
        'warranties' => $warranties,
        'permissions' => [
            'opening_stock' => auth()->user()->can('product.opening_stock'),
            'delete' => auth()->user()->can('product.delete'),
        ],
    ]);
}
return view('product.edit')->with(compact(/* ... legacy ... */));
```

### 3.2 Page Inertia

`Pages/Produto/Edit.tsx`:
- Form full-width AppShellV2
- PageHeader "Editar produto" + nome + SKU mono + ações
- Mesma estrutura Create.tsx (Identificação | Preço & Imposto | Estoque | Localizações | Avançado)
- `useForm` inicializado com `product` props
- Botão "Salvar alterações" + "Cancelar"

### 3.3 Anti-padrões

- ❌ NÃO mexer no método `update()` PHP (out of scope; manter intacto)
- ❌ NÃO recriar lógica de SKU server-side no client

## 4. Testes

```bash
vendor/bin/pest tests/Feature/Produto/Wave2EditBaselineTest.php
vendor/bin/pest tests/Feature/Produto/Wave2EditInertiaTest.php
```

## 5. Refs

- Visual comparison: [`produto-edit-visual-comparison.md`](produto-edit-visual-comparison.md)
- ADR 0149

## 6. Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Runbook criado em Wave 2 B4 Produto. |
