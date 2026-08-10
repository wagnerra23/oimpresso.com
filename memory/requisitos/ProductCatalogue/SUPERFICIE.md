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
> **O que isto é:** o inventário completo das raízes `Modules/ProductCatalogue/**` + `resources/js/Pages/ProductCatalogue/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](../../../Modules/ProductCatalogue/SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 59 arquivos em 12 papéis.

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

- [generate_qr.blade.php](../../../Modules/ProductCatalogue/Resources/views/catalogue/generate_qr.blade.php)
- [index.blade.php](../../../Modules/ProductCatalogue/Resources/views/catalogue/index.blade.php)
- [combo_product_details.blade.php](../../../Modules/ProductCatalogue/Resources/views/catalogue/partials/combo_product_details.blade.php)
- [single_product_details.blade.php](../../../Modules/ProductCatalogue/Resources/views/catalogue/partials/single_product_details.blade.php)
- [variable_product_details.blade.php](../../../Modules/ProductCatalogue/Resources/views/catalogue/partials/variable_product_details.blade.php)
- [show.blade.php](../../../Modules/ProductCatalogue/Resources/views/catalogue/show.blade.php)
- [index.blade.php](../../../Modules/ProductCatalogue/Resources/views/index.blade.php)
- [master.blade.php](../../../Modules/ProductCatalogue/Resources/views/layouts/master.blade.php)

## Testes (Pest) — 7

- 7 arquivos em [Modules/ProductCatalogue/Tests/Feature/](../../../Modules/ProductCatalogue/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Demais arquivos (manifestos, docs, assets e misc) — 25

- [CHANGELOG.md](../../../Modules/ProductCatalogue/CHANGELOG.md)
- [.gitkeep](../../../Modules/ProductCatalogue/Config/.gitkeep)
- [.gitkeep](../../../Modules/ProductCatalogue/Console/.gitkeep)
- [.gitkeep](../../../Modules/ProductCatalogue/Database/Migrations/.gitkeep)
- [.gitkeep](../../../Modules/ProductCatalogue/Database/Seeders/.gitkeep)
- [.gitkeep](../../../Modules/ProductCatalogue/Database/factories/.gitkeep)
- [.gitkeep](../../../Modules/ProductCatalogue/Entities/.gitkeep)
- [.gitkeep](../../../Modules/ProductCatalogue/Http/Controllers/.gitkeep)
- [.gitkeep](../../../Modules/ProductCatalogue/Http/Middleware/.gitkeep)
- [.gitkeep](../../../Modules/ProductCatalogue/Http/Requests/.gitkeep)
- [.gitkeep](../../../Modules/ProductCatalogue/Providers/.gitkeep)
- [README.md](../../../Modules/ProductCatalogue/README.md)
- [ProductCatalogueRepository.php](../../../Modules/ProductCatalogue/Repositories/ProductCatalogueRepository.php)
- [.gitkeep](../../../Modules/ProductCatalogue/Resources/assets/.gitkeep)
- [easy.qrcode.min.js](../../../Modules/ProductCatalogue/Resources/assets/plugins/easy.qrcode.min.js)
- [app.scss](../../../Modules/ProductCatalogue/Resources/assets/sass/app.scss)
- [.gitkeep](../../../Modules/ProductCatalogue/Resources/lang/.gitkeep)
- [lang.php](../../../Modules/ProductCatalogue/Resources/lang/en/lang.php)
- [.gitkeep](../../../Modules/ProductCatalogue/Resources/views/.gitkeep)
- [SCOPE.md](../../../Modules/ProductCatalogue/SCOPE.md)
- [.gitkeep](../../../Modules/ProductCatalogue/Tests/.gitkeep)
- [composer.json](../../../Modules/ProductCatalogue/composer.json)
- [module.json](../../../Modules/ProductCatalogue/module.json)
- [package.json](../../../Modules/ProductCatalogue/package.json)
- [webpack.mix.js](../../../Modules/ProductCatalogue/webpack.mix.js)
