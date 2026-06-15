---
slug: inventory-runbook-produto-create
title: "Produto — Runbook da tela Novo produto (migração MWART)"
type: runbook
module: Inventory
status: active
date: 2026-05-15
---

# RUNBOOK — Novo produto (`/products/create`)

> **Tipo:** runbook de migração Blade → Inertia/React (MWART)
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
> **Estado origem:** Blade legacy `view('product.create')` via [ProductController@create](../../../app/Http/Controllers/ProductController.php#L358)
> **Estado alvo:** `Pages/Produto/Create.tsx` (Inertia v3 + React 19 + AppShellV2)
> **Persona alvo:** Larissa (ROTA LIVRE biz=4) — cadastra ~3-8 produtos/semana
> **Blueprint Cowork:** [`prototipo-ui/prototipos/produto-cockpit/`](../../../prototipo-ui/prototipos/produto-cockpit/) — pattern visual canon. Create deriva do Index (mesma família visual AppShellV2).

## 1. Estado final esperado

| Verificação | Como conferir |
|---|---|
| Rota `/products/create` renderiza Page React | `curl -H "X-Inertia: true" /products/create` retorna `"component":"Produto/Create"` |
| Permissão `product.create` | sem permissão → 403 |
| Quota produtos checada | `isQuotaAvailable('products',$biz)` false → redirect |
| Subscription ativa | expirada → `expiredResponse()` |
| Multi-tenant scopado | `business_id` aplicado em Category/Brand/Unit/TaxRate forDropdown |
| Duplicate via `?d=N` | preserve UX legacy (carrega produto com `(copy)` no nome) |

## 2. Pré-condições

- [ ] Permissão `product.create` atribuída
- [ ] Subscription ativa + quota produtos disponível
- [ ] Categorias + Brands + Units + Taxes cadastrados (dropdowns)
- [ ] Charter ao lado: `Pages/Produto/Create.charter.md`
- [ ] Skill `multi-tenant-patterns` carregada

## 3. Passo-a-passo

### 3.1 Branch dual no controller `create()`

Adicionar após `$pos_module_data = $this->moduleUtil->getModuleData('get_product_screen_top_view');` ANTES do `return view('product.create')`:

```php
if (request()->header('X-Inertia')) {
    return Inertia::render('Produto/Create', [
        'categories' => $categories,
        'brands' => $brands,
        'units' => $units,
        'taxes' => $taxes,
        'taxAttributes' => $tax_attributes,
        'barcodeTypes' => $barcode_types,
        'barcodeDefault' => $barcode_default,
        'defaultProfitPercent' => $default_profit_percent,
        'businessLocations' => $business_locations,
        'duplicateProduct' => $duplicate_product,
        'subCategories' => $sub_categories,
        'rackDetails' => $rack_details,
        'sellingPriceGroupCount' => $selling_price_group_count,
        'productTypes' => $product_types,
        'warranties' => $warranties,
        'commonSettings' => $common_settings,
        'enableExpiry' => session('business.enable_product_expiry') == 1,
        'enableLot' => $common_settings['enable_lot_number'] ?? false,
        'enableRacks' => session('business.enable_racks') ?? false,
    ]);
}
return view('product.create')->with(compact(/* ... legacy ... */));
```

### 3.2 Page Inertia

`resources/js/Pages/Produto/Create.tsx` — form full-width AppShellV2 (não Cockpit 3-col — form não tem "conversa em foco"):

- PageHeader "Novo produto" + ações "Cancelar"+"Salvar"
- Cards seções: Identificação | Preço & Imposto | Estoque | Localizações | Avançado (`<details>`)
- 8 campos sempre visíveis: name, sku, type, unit, category, brand, tax, alert_quantity
- Avançado colapsável: weight, custom_fields 1-20, racks, expiry, sr_no, sub_units
- TypeScript estrito — sem `any`
- `useForm` com defaults conservadores (type='single', enable_stock=true)

### 3.3 Anti-padrões

- ❌ NÃO migrar lógica de geração SKU pro client (continua server-side em `store()`)
- ❌ Cor crua
- ❌ `auth()->user()->business_id` — usar `session('user.business_id')` canon UPOS

## 4. Testes

```bash
vendor/bin/pest tests/Feature/Produto/Wave2CreateBaselineTest.php
vendor/bin/pest tests/Feature/Produto/Wave2CreateInertiaTest.php
```

## 5. Rollback

Remover header `X-Inertia` no client — controller cai pra Blade legacy.

## 6. Refs

- Blueprint Cowork: [`produto-cockpit/`](../../../prototipo-ui/prototipos/produto-cockpit/)
- Visual comparison: [`produto-create-visual-comparison.md`](produto-create-visual-comparison.md)
- ADR 0149 screen-pattern reuse

## 7. Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Runbook criado em Wave 2 B4 Produto. |
