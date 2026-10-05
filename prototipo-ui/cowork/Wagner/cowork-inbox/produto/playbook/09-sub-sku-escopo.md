---
sessao: "09"
titulo: sub_sku sem escopo
dono: "[CL]"
base: 99e6fa3e08f0
---
# 09 · P0 — mesma falha do import de preço, em outras 5 chamadas

O `_saida-06` deixou o P0 do `import()` de preço para [W]. **Ele já foi consertado no `main`** (`SellingPriceGroupController.php` busca o SKU só dentro de `products.business_id`, comentário "P0 Tier 0 (2026-10-01)"). O mesmo padrão `Variation::where('sub_sku', …)` aparece em mais 5 lugares em `99e6fa3e08f0`:

- `ImportOpeningStockController.php:123`
- `ImportSalesController.php:229`
- `ProductController.php:1740` e `:1785`
- `PurchaseController.php:1639`

**Não verifiquei** se as linhas seguintes de cada um já escopam pelo negócio. Leia cada chamada inteira. Onde já escopa: registre no recibo e não mexa. Onde não escopa: aplique o mesmo filtro do `import()` de preço (`whereIn('product_id', produtos do negócio)` + `orderBy('variations.id')`) e escreva o teste (SKU igual em outro negócio → "não encontrado", nada gravado).

**PARAR SE** a mudança alterar o que uma importação legítima do próprio negócio grava.
