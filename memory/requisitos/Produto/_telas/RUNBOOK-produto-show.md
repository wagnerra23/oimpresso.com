---
slug: inventory-runbook-produto-show
title: "Produto — Runbook da tela Detalhe do produto (migração MWART)"
type: runbook
module: Inventory
status: active
date: 2026-05-15
---

# RUNBOOK — Detalhe do produto (`/products/{id}`)

> **Tipo:** runbook MWART
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
> **Estado origem:** Blade legacy `view('product.show')` via [ProductController@show](../../../app/Http/Controllers/ProductController.php#L592) — usa `getRackDetails()`
> **Estado alvo:** `Pages/Produto/Show.tsx` (drawer pattern → Page full)
> **Blueprint Cowork:** [`prototipo-ui/prototipos/produto-cockpit/`](../../../prototipo-ui/prototipos/produto-cockpit/) — drawer detalhe canon serve de blueprint pra tela full Show

## 1. Estado final esperado

| Verificação | Como conferir |
|---|---|
| Rota `/products/{id}` renderiza Page React | `curl -H "X-Inertia: true" /products/N` retorna `"component":"Produto/Show"` |
| Permissão `product.view` | sem permissão → 403 |
| `business_id` isola | biz=99 acessando produto biz=1 → 404 |
| Hero KPIs visíveis | Estoque · Custo · Preço varejo · Vendas no mês |
| Tabs renderizadas | Resumo · Composição · Variações · Preços · Movimento · Fiscal |

## 2. Pré-condições

- [ ] Permissão `product.view`
- [ ] Product existe + `business_id` bate
- [ ] Charter: `Pages/Produto/Show.charter.md`

## 3. Passo-a-passo

### 3.1 Branch dual em `show($id)`

```php
public function show($id)
{
    if (! auth()->user()->can('product.view')) {
        abort(403, 'Unauthorized action.');
    }

    $business_id = request()->session()->get('user.business_id');

    if (request()->header('X-Inertia')) {
        $product = Product::where('business_id', $business_id)
            ->with(['variations', 'variations.product_variation', 'category', 'sub_category', 'brand', 'unit'])
            ->findOrFail($id);

        return Inertia::render('Produto/Show', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'type' => $product->type,
                'category' => $product->category?->name,
                'subCategory' => $product->sub_category?->name,
                'brand' => $product->brand?->name,
                'unit' => $product->unit?->actual_name,
                'enableStock' => (bool) $product->enable_stock,
                'alertQuantity' => $product->alert_quantity,
                'productDescription' => $product->product_description,
                'image' => $product->image_url,
            ],
            'rackDetails' => Inertia::defer(fn () => $this->productUtil->getRackDetails($business_id, $id, true)),
            'variations' => Inertia::defer(fn () => $product->variations->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'sku' => $v->sub_sku,
                'defaultPurchasePrice' => $v->default_purchase_price,
                'defaultSellPrice' => $v->default_sell_price_inc_tax,
            ])),
            'permissions' => [
                'update' => auth()->user()->can('product.update'),
                'delete' => auth()->user()->can('product.delete'),
            ],
        ]);
    }

    $details = $this->productUtil->getRackDetails($business_id, $id, true);
    return view('product.show')->with(compact('details'));
}
```

### 3.2 Page Inertia — drawer-blueprint pra Page full

`Pages/Produto/Show.tsx`:
- AppShellV2 wrapper
- Header sticky (h1 nome produto + SKU mono + categoria)
- Hero KPIs strip (Estoque · Custo · Preço varejo · Margem)
- Tabs: Resumo · Composição · Preços · Movimento · Fiscal
- `<Deferred>` em rackDetails e variations
- Botão "Editar" → linka `/products/{id}/edit`

## 4. Testes

```bash
vendor/bin/pest tests/Feature/Produto/Wave2ShowBaselineTest.php
vendor/bin/pest tests/Feature/Produto/Wave2ShowInertiaTest.php
```

## 5. Refs

- Blueprint Cowork drawer: `produto-cockpit/produto-cockpit-page.jsx::DrawerView`
- Visual comparison: [`produto-show-visual-comparison.md`](produto-show-visual-comparison.md)
- ADR 0149

## 6. Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Runbook criado em Wave 2 B4 Produto. |
