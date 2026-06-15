---
slug: inventory-runbook-produto-stock-history
title: "Produto — Runbook da tela Histórico de estoque (migração MWART)"
type: runbook
module: Inventory
status: active
date: 2026-05-15
---

# RUNBOOK — Histórico de estoque (`/products/stock-history/{id}`)

> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
> **Origem:** Blade `view('product.stock_history')` via [ProductController@productStockHistory](../../../app/Http/Controllers/ProductController.php#L2292)
> **Alvo:** `Pages/Produto/StockHistory.tsx`
> **Blueprint Cowork:** Família AppShellV2 + tokens. **Divergência declarada (ADR 0149):** timeline movimento por variação — pattern visual de tabela cronológica com filtros location/period distinto de Index. Mantém header + KPIs + filter bar canon Cowork.

## 1. Estado final esperado

| Verificação | Como conferir |
|---|---|
| `/products/stock-history/{id}` Inertia | `curl -H "X-Inertia: true"` → `"component":"Produto/StockHistory"` |
| Permissão `product.view` | sem permissão → 403 |
| `business_id` isola | produto cross-tenant → 404 |
| Variations renderizadas | dropdown variation_id |
| Locations dropdown | filtro location_id |

## 2. Pré-condições

- [ ] Permissão `product.view`
- [ ] Produto existe + `business_id` bate
- [ ] Charter: `Pages/Produto/StockHistory.charter.md`

## 3. Passo-a-passo

### 3.1 Branch dual em `productStockHistory($id)`

A request ajax continua retornando `view('product.stock_history_details')` parcial (HTML fragment). Mantém intacto. Adiciona branch Inertia na response principal:

```php
public function productStockHistory($id)
{
    if (! auth()->user()->can('product.view')) {
        abort(403, 'Unauthorized action.');
    }

    $business_id = request()->session()->get('user.business_id');

    if (request()->ajax()) {
        // mantém legacy intacto — ajax retorna HTML partial pra Blade legacy
        // ...
        return view('product.stock_history_details')->with(compact('stock_details', 'stock_history'));
    }

    $product = Product::where('business_id', $business_id)
        ->with(['variations', 'variations.product_variation'])
        ->findOrFail($id);

    $business_locations = BusinessLocation::forDropdown($business_id);

    if (request()->header('X-Inertia')) {
        return Inertia::render('Produto/StockHistory', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'type' => $product->type,
                'unit' => $product->unit?->actual_name,
            ],
            'variations' => $product->variations->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'subSku' => $v->sub_sku,
            ]),
            'businessLocations' => $business_locations,
            'permissions' => [
                'view' => true,
            ],
        ]);
    }

    return view('product.stock_history')->with(compact('product', 'business_locations'));
}
```

### 3.2 Page Inertia

`Pages/Produto/StockHistory.tsx`:
- AppShellV2
- PageHeader "Histórico de estoque · {nome produto}"
- Toolbar filtros: variation_id select + location_id select
- Hero KPIs (deferred via fetch após seleção): Estoque atual · Entrada 30d · Saída 30d
- Tabela cronológica (deferred): data · operação · qty · before · after · ref (OS/Compra/Venda)
- Fetch via `axios.get('/products/stock-history/{variation_id}?location_id=N')` retorna HTML partial — parse e renderiza, OR migra endpoint pra JSON futuro

### 3.3 Divergência blueprint

Charter declara `divergence_from_blueprint: "timeline movimento por variação tem filtros location/variation próprios — não é list cockpit padrão"`. Mantém família visual + tokens canon.

## 4. Testes

```bash
vendor/bin/pest tests/Feature/Produto/Wave2StockHistoryBaselineTest.php
vendor/bin/pest tests/Feature/Produto/Wave2StockHistoryInertiaTest.php
```

## 5. Refs

- Visual comparison: [`produto-stock-history-visual-comparison.md`](produto-stock-history-visual-comparison.md)
- ADR 0149

## 6. Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Runbook criado em Wave 2 B4 Produto. |
