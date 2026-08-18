---
name: "SUPERFÍCIE — Produto"
description: "Índice GERADO dos artefatos do módulo Produto reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Produto
tabelas_dominio: ["products", "variations", "product_variations", "variation_location_details"]
---

# 🗺️ Superfície de código — Produto

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Produto --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o módulo `Produto` é CLASSE B — o código mora no núcleo UltimatePOS (`app/`), sem diretório modular homônimo. A membership vem de uma **semente curada** de paths do core declarada em `module-surface.mjs::CORE_APP_MODULES` (revisável no diff) + `resources/js/Pages/Produto/**`. **O que NÃO é:** cobertura/nota/status (donos: `screen-coverage-map.mjs` + `casos-gate`) nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve). As **tabelas do domínio** (`products`, `variations`, `product_variations`, `variation_location_details`) são metadado-ÂNCORA declarado, **não** o derivador (derivar por tabela over-inclui — medido 2026-07-21).

**Total mapeado:** 96 arquivos em 9 papéis.

## Controllers — 8

- [BarcodeController.php](../../../app/Http/Controllers/BarcodeController.php)
- [BrandController.php](../../../app/Http/Controllers/BrandController.php)
- [ImportProductsController.php](../../../app/Http/Controllers/ImportProductsController.php)
- [LabelsController.php](../../../app/Http/Controllers/LabelsController.php)
- [ProductController.php](../../../app/Http/Controllers/ProductController.php)
- [SellingPriceGroupController.php](../../../app/Http/Controllers/SellingPriceGroupController.php)
- [UnitController.php](../../../app/Http/Controllers/UnitController.php)
- [VariationTemplateController.php](../../../app/Http/Controllers/VariationTemplateController.php)

## Motor (Utils/Domínio) — 1

- [ProductUtil.php](../../../app/Utils/ProductUtil.php)

## Models / Entities — 12

- [Barcode.php](../../../app/Barcode.php)
- [Brands.php](../../../app/Brands.php)
- [Product.php](../../../app/Product.php)
- [ProductRack.php](../../../app/ProductRack.php)
- [ProductVariation.php](../../../app/ProductVariation.php)
- [SellingPriceGroup.php](../../../app/SellingPriceGroup.php)
- [Unit.php](../../../app/Unit.php)
- [Variation.php](../../../app/Variation.php)
- [VariationGroupPrice.php](../../../app/VariationGroupPrice.php)
- [VariationLocationDetails.php](../../../app/VariationLocationDetails.php)
- [VariationTemplate.php](../../../app/VariationTemplate.php)
- [VariationValueTemplate.php](../../../app/VariationValueTemplate.php)

## Views (Blade) — 43

- [create.blade.php](../../../resources/views/brand/create.blade.php)
- [edit.blade.php](../../../resources/views/brand/edit.blade.php)
- [index.blade.php](../../../resources/views/brand/index.blade.php)
- [index.blade.php](../../../resources/views/import_products/index.blade.php)
- [preview.blade.php](../../../resources/views/labels/partials/preview.blade.php)
- [preview_2.blade.php](../../../resources/views/labels/partials/preview_2.blade.php)
- [show_table_rows.blade.php](../../../resources/views/labels/partials/show_table_rows.blade.php)
- [show.blade.php](../../../resources/views/labels/show.blade.php)
- [add-selling-prices.blade.php](../../../resources/views/product/add-selling-prices.blade.php)
- [bulk-edit.blade.php](../../../resources/views/product/bulk-edit.blade.php)
- [create.blade.php](../../../resources/views/product/create.blade.php)
- [edit.blade.php](../../../resources/views/product/edit.blade.php)
- [index.blade.php](../../../resources/views/product/index.blade.php)
- [bulk_edit_product_row.blade.php](../../../resources/views/product/partials/bulk_edit_product_row.blade.php)
- [bulk_edit_variation_row.blade.php](../../../resources/views/product/partials/bulk_edit_variation_row.blade.php)
- [combo_product_details.blade.php](../../../resources/views/product/partials/combo_product_details.blade.php)
- [combo_product_entry_row.blade.php](../../../resources/views/product/partials/combo_product_entry_row.blade.php)
- [combo_product_form_part.blade.php](../../../resources/views/product/partials/combo_product_form_part.blade.php)
- [edit_product_location_modal.blade.php](../../../resources/views/product/partials/edit_product_location_modal.blade.php)
- [edit_product_variation_row.blade.php](../../../resources/views/product/partials/edit_product_variation_row.blade.php)
- [edit_single_product_form_part.blade.php](../../../resources/views/product/partials/edit_single_product_form_part.blade.php)
- [edit_variable_product_form_part.blade.php](../../../resources/views/product/partials/edit_variable_product_form_part.blade.php)
- [product_list.blade.php](../../../resources/views/product/partials/product_list.blade.php)
- [product_stock_details.blade.php](../../../resources/views/product/partials/product_stock_details.blade.php)
- [product_variation_row.blade.php](../../../resources/views/product/partials/product_variation_row.blade.php)
- [product_variation_template.blade.php](../../../resources/views/product/partials/product_variation_template.blade.php)
- [quick_add_product.blade.php](../../../resources/views/product/partials/quick_add_product.blade.php)
- [quick_product_opening_stock.blade.php](../../../resources/views/product/partials/quick_product_opening_stock.blade.php)
- [single_product_details.blade.php](../../../resources/views/product/partials/single_product_details.blade.php)
- [single_product_form_part.blade.php](../../../resources/views/product/partials/single_product_form_part.blade.php)
- [toggle_woocommerce_sync_modal.blade.php](../../../resources/views/product/partials/toggle_woocommerce_sync_modal.blade.php)
- [variable_product_details.blade.php](../../../resources/views/product/partials/variable_product_details.blade.php)
- [variable_product_form_part.blade.php](../../../resources/views/product/partials/variable_product_form_part.blade.php)
- [variation_value_row.blade.php](../../../resources/views/product/partials/variation_value_row.blade.php)
- [show.blade.php](../../../resources/views/product/show.blade.php)
- [stock_history.blade.php](../../../resources/views/product/stock_history.blade.php)
- [stock_history_details.blade.php](../../../resources/views/product/stock_history_details.blade.php)
- [view-modal.blade.php](../../../resources/views/product/view-modal.blade.php)
- [view-product-group-prices.blade.php](../../../resources/views/product/view-product-group-prices.blade.php)
- [create.blade.php](../../../resources/views/selling_price_group/create.blade.php)
- [edit.blade.php](../../../resources/views/selling_price_group/edit.blade.php)
- [index.blade.php](../../../resources/views/selling_price_group/index.blade.php)
- [update_product_price.blade.php](../../../resources/views/selling_price_group/update_product_price.blade.php)

## Telas (Inertia/React) — 8

- [BulkEdit.tsx](../../../resources/js/Pages/Produto/BulkEdit.tsx)
- [Create.tsx](../../../resources/js/Pages/Produto/Create.tsx)
- [Edit.tsx](../../../resources/js/Pages/Produto/Edit.tsx)
- [Index.tsx](../../../resources/js/Pages/Produto/Index.tsx)
- [SellingPrices.tsx](../../../resources/js/Pages/Produto/SellingPrices.tsx)
- [Show.tsx](../../../resources/js/Pages/Produto/Show.tsx)
- [StockHistory.tsx](../../../resources/js/Pages/Produto/StockHistory.tsx)
- [Index.tsx](../../../resources/js/Pages/Produto/Unificado/Index.tsx)

## Componentes / apoio de tela — 7

- [Colunas.tsx](../../../resources/js/Pages/Produto/Unificado/_components/Colunas.tsx)
- [DetalheProduto.tsx](../../../resources/js/Pages/Produto/Unificado/_components/DetalheProduto.tsx)
- [Disponibilidade.tsx](../../../resources/js/Pages/Produto/Unificado/_components/Disponibilidade.tsx)
- [FiltroTrigger.tsx](../../../resources/js/Pages/Produto/Unificado/_components/FiltroTrigger.tsx)
- [KpiFiltros.tsx](../../../resources/js/Pages/Produto/Unificado/_components/KpiFiltros.tsx)
- [Mono.tsx](../../../resources/js/Pages/Produto/Unificado/_components/Mono.tsx)
- [SubTelas.tsx](../../../resources/js/Pages/Produto/Unificado/_components/SubTelas.tsx)

## Charters (lei da tela) — 8

- [BulkEdit.charter.md](../../../resources/js/Pages/Produto/BulkEdit.charter.md)
- [Create.charter.md](../../../resources/js/Pages/Produto/Create.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/Produto/Edit.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Produto/Index.charter.md)
- [SellingPrices.charter.md](../../../resources/js/Pages/Produto/SellingPrices.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Produto/Show.charter.md)
- [StockHistory.charter.md](../../../resources/js/Pages/Produto/StockHistory.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Produto/Unificado/Index.charter.md)

## Casos (contrato UC) — 8

- [BulkEdit.casos.md](../../../resources/js/Pages/Produto/BulkEdit.casos.md)
- [Create.casos.md](../../../resources/js/Pages/Produto/Create.casos.md)
- [Edit.casos.md](../../../resources/js/Pages/Produto/Edit.casos.md)
- [Index.casos.md](../../../resources/js/Pages/Produto/Index.casos.md)
- [SellingPrices.casos.md](../../../resources/js/Pages/Produto/SellingPrices.casos.md)
- [Show.casos.md](../../../resources/js/Pages/Produto/Show.casos.md)
- [StockHistory.casos.md](../../../resources/js/Pages/Produto/StockHistory.casos.md)
- [Index.casos.md](../../../resources/js/Pages/Produto/Unificado/Index.casos.md)

## Demais arquivos (manifestos, docs, assets e misc) — 1

- [catalogo.ts](../../../resources/js/Pages/Produto/Unificado/_components/catalogo.ts)
