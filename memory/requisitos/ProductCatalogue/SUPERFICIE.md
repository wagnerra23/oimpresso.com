---
name: "SUPERFÍCIE — ProductCatalogue"
description: "Índice GERADO dos artefatos do módulo ProductCatalogue reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: ProductCatalogue
---

# 🗺️ Superfície de código — ProductCatalogue

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs ProductCatalogue --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/ProductCatalogue/**` + `resources/js/Pages/ProductCatalogue/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 35 arquivos em 12 papéis.

## Controllers — 3

- [DataController.php](../../../Modules/ProductCatalogue/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/ProductCatalogue/Http/Controllers/InstallController.php)
- [ProductCatalogueController.php](../../../Modules/ProductCatalogue/Http/Controllers/ProductCatalogueController.php)

## Requests (validação) — 5

- [GenerateQrRequest.php](../../../Modules/ProductCatalogue/Http/Requests/GenerateQrRequest.php)
- [ShowProductRequest.php](../../../Modules/ProductCatalogue/Http/Requests/ShowProductRequest.php)
- [ShowPublicCatalogueRequest.php](../../../Modules/ProductCatalogue/Http/Requests/ShowPublicCatalogueRequest.php)
- [StoreProductCatalogueRequest.php](../../../Modules/ProductCatalogue/Http/Requests/StoreProductCatalogueRequest.php)
- [UpdateProductCatalogueRequest.php](../../../Modules/ProductCatalogue/Http/Requests/UpdateProductCatalogueRequest.php)

## Services — 2

- [CatalogueQrService.php](../../../Modules/ProductCatalogue/Services/CatalogueQrService.php)
- [CatalogueService.php](../../../Modules/ProductCatalogue/Services/CatalogueService.php)

## Console / Commands — 1

- [ProductCatalogueHealthCommand.php](../../../Modules/ProductCatalogue/Console/Commands/ProductCatalogueHealthCommand.php)

## Providers — 2

- [ProductCatalogueServiceProvider.php](../../../Modules/ProductCatalogue/Providers/ProductCatalogueServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/ProductCatalogue/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/ProductCatalogue/Routes/api.php)
- [web.php](../../../Modules/ProductCatalogue/Routes/web.php)

## Migrations (schema) — 1

- [2020_09_29_184909_add_product_catalogue_version.php](../../../Modules/ProductCatalogue/Database/Migrations/2020_09_29_184909_add_product_catalogue_version.php)

## Seeders — 1

- [ProductCatalogueDatabaseSeeder.php](../../../Modules/ProductCatalogue/Database/Seeders/ProductCatalogueDatabaseSeeder.php)

## Config — 2

- [config.php](../../../Modules/ProductCatalogue/Config/config.php)
- [retention.php](../../../Modules/ProductCatalogue/Config/retention.php)

## Views (Blade) — 8

- 8 arquivos em [Modules/ProductCatalogue/Resources/views/catalogue/](../../../Modules/ProductCatalogue/Resources/views/catalogue) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Testes (Pest) — 7

- 7 arquivos em [Modules/ProductCatalogue/Tests/Feature/](../../../Modules/ProductCatalogue/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 1

- [ProductCatalogueRepository.php](../../../Modules/ProductCatalogue/Repositories/ProductCatalogueRepository.php)
