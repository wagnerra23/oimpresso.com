# Patch a aplicar em `routes/web.php` (raiz do app, não Module)

> **Importante:** o domínio "produto" no oimpresso é **UltimatePOS herdado em `app/`**, não modular. A rota vai no `routes/web.php` raiz junto com as rotas de produtos existentes (procure por `/products` no arquivo).

Adicionar no grupo de rotas autenticadas (`Route::middleware([...]) ->group(...)`) **logo abaixo das rotas `/products` existentes**:

```php
        // Catálogo Unificado (Cockpit V2) — 5 sub-telas em uma rota.
        // Persona Larissa [L] · 1280×1024 · ROTA LIVRE.
        Route::get('/products/unificado', [\App\Http\Controllers\ProdutoUnificadoController::class, 'index'])
            ->name('products.unificado.index');
```

> ⚠️ **NÃO** criei rotas POST/PATCH de mutação ainda — `store/toggle-active` reusará as do `ProductController` existente em `app/Http/Controllers/ProductController.php`. Confirmar com [W] se ações inline na unificada (toggle ativo, edição rápida) reusam endpoints atuais ou precisam de novos.

## Models confirmados (lidos de `app/Product.php` no main em 2026-05-09)

- `App\Product` — guarded id, casts sub_unit_ids array, scopes `active()` / `inactive()` / `productForSales()` / `forLocation()`
- Relações: `category()`, `brand()`, `unit()`, `variations()`, `product_locations()`, `media()`, `rack_details()`
- Coluna chave: `business_id`, `is_inactive`, `not_for_selling`, `enable_stock`, `sku`, `category_id`

## Models a confirmar (TODO [CL] antes de mergear)

- `App\Variation` — `default_sell_price_inc_tax`, `default_purchase_price` ✅ esperado UltimatePOS
- `App\SellingPriceGroup` — multiplicador NÃO é nativo, decisão pendente com [W]
- `Modules\Manufacturing\Entities\MfgRecipe` — confirmar caminho exato
- `App\TransactionSellLine` — agregação 30d via join transactions

## Permission

UltimatePOS usa Spatie Permission. Adicionar no middleware da rota:
```php
->middleware('can:product.view')
```
