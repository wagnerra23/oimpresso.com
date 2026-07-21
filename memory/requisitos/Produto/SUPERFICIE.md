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
> **O que isto é:** o módulo `Produto` é CLASSE B — o código mora no núcleo UltimatePOS (`app/`), sem diretório modular homônimo. A membership vem de uma **semente curada** de paths do core declarada em `module-surface.mjs::CORE_APP_MODULES` (revisável no diff) + `resources/js/Pages/Produto/**`. **O que NÃO é:** cobertura/nota/status (donos: `screen-coverage-map.mjs` + `casos-gate`). As **tabelas do domínio** (`products`, `variations`, `product_variations`, `variation_location_details`) são metadado-ÂNCORA declarado, **não** o derivador (derivar por tabela over-inclui — medido 2026-07-21).

**Total mapeado:** 82 arquivos em 7 papéis.

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

- 43 arquivos em [resources/views/brand/](../../../resources/views/brand) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Telas (Inertia/React) — 8

- [BulkEdit.tsx](../../../resources/js/Pages/Produto/BulkEdit.tsx)
- [Create.tsx](../../../resources/js/Pages/Produto/Create.tsx)
- [Edit.tsx](../../../resources/js/Pages/Produto/Edit.tsx)
- [Index.tsx](../../../resources/js/Pages/Produto/Index.tsx)
- [SellingPrices.tsx](../../../resources/js/Pages/Produto/SellingPrices.tsx)
- [Show.tsx](../../../resources/js/Pages/Produto/Show.tsx)
- [StockHistory.tsx](../../../resources/js/Pages/Produto/StockHistory.tsx)
- [Index.tsx](../../../resources/js/Pages/Produto/Unificado/Index.tsx)

## Charters (lei da tela) — 8

- [BulkEdit.charter.md](../../../resources/js/Pages/Produto/BulkEdit.charter.md)
- [Create.charter.md](../../../resources/js/Pages/Produto/Create.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/Produto/Edit.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Produto/Index.charter.md)
- [SellingPrices.charter.md](../../../resources/js/Pages/Produto/SellingPrices.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Produto/Show.charter.md)
- [StockHistory.charter.md](../../../resources/js/Pages/Produto/StockHistory.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Produto/Unificado/Index.charter.md)

## Casos (contrato UC) — 2

- [Create.casos.md](../../../resources/js/Pages/Produto/Create.casos.md)
- [SellingPrices.casos.md](../../../resources/js/Pages/Produto/SellingPrices.casos.md)
