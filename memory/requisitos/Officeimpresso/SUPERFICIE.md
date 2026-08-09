---
name: "SUPERFÍCIE — Officeimpresso"
description: "Índice GERADO dos artefatos do módulo Officeimpresso reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Officeimpresso
---

# 🗺️ Superfície de código — Officeimpresso

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Officeimpresso --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o inventário completo das raízes `Modules/Officeimpresso/**` + `resources/js/Pages/Officeimpresso/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](../../../Modules/Officeimpresso/SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 108 arquivos em 15 papéis.

## Controllers — 7

- [AuditController.php](../../../Modules/Officeimpresso/Http/Controllers/AuditController.php)
- [ClientController.php](../../../Modules/Officeimpresso/Http/Controllers/ClientController.php)
- [DataController.php](../../../Modules/Officeimpresso/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Officeimpresso/Http/Controllers/InstallController.php)
- [LicencaComputadorController.php](../../../Modules/Officeimpresso/Http/Controllers/LicencaComputadorController.php)
- [LicencaLogController.php](../../../Modules/Officeimpresso/Http/Controllers/LicencaLogController.php)
- [OfficeimpressoController.php](../../../Modules/Officeimpresso/Http/Controllers/OfficeimpressoController.php)

## Requests (validação) — 5

- [BulkRevokeLicencaRequest.php](../../../Modules/Officeimpresso/Http/Requests/BulkRevokeLicencaRequest.php)
- [RevokeLicencaRequest.php](../../../Modules/Officeimpresso/Http/Requests/RevokeLicencaRequest.php)
- [StoreLicencaRequest.php](../../../Modules/Officeimpresso/Http/Requests/StoreLicencaRequest.php)
- [UpdateEmpresaConfigRequest.php](../../../Modules/Officeimpresso/Http/Requests/UpdateEmpresaConfigRequest.php)
- [UpdateLicencaRequest.php](../../../Modules/Officeimpresso/Http/Requests/UpdateLicencaRequest.php)

## Middleware — 3

- [CheckDemo.php](../../../Modules/Officeimpresso/Http/Middleware/CheckDemo.php)
- [LogDelphiAccess.php](../../../Modules/Officeimpresso/Http/Middleware/LogDelphiAccess.php)
- [LogDesktopAccess.php](../../../Modules/Officeimpresso/Http/Middleware/LogDesktopAccess.php)

## Services — 4

- [FirebirdConnector.php](../../../Modules/Officeimpresso/Services/FirebirdImporter/FirebirdConnector.php)
- [OfficeimpressoImporterService.php](../../../Modules/Officeimpresso/Services/FirebirdImporter/OfficeimpressoImporterService.php)
- [LicencaAuditService.php](../../../Modules/Officeimpresso/Services/LicencaAuditService.php)
- [LicencaService.php](../../../Modules/Officeimpresso/Services/LicencaService.php)

## Models / Entities — 2

- [LicencaLog.php](../../../Modules/Officeimpresso/Entities/LicencaLog.php)
- [Licenca_Computador.php](../../../Modules/Officeimpresso/Entities/Licenca_Computador.php)

## Events / Listeners — 1

- [LogPassportAccessToken.php](../../../Modules/Officeimpresso/Listeners/LogPassportAccessToken.php)

## Console / Commands — 4

- [ImportOfficeimpressoCommand.php](../../../Modules/Officeimpresso/Console/Commands/ImportOfficeimpressoCommand.php)
- [OfficeimpressoHealthCommand.php](../../../Modules/Officeimpresso/Console/Commands/OfficeimpressoHealthCommand.php)
- [InspectDelphiApiCommand.php](../../../Modules/Officeimpresso/Console/InspectDelphiApiCommand.php)
- [ParseLicencaLogCommand.php](../../../Modules/Officeimpresso/Console/ParseLicencaLogCommand.php)

## Providers — 2

- [OfficeimpressoServiceProvider.php](../../../Modules/Officeimpresso/Providers/OfficeimpressoServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Officeimpresso/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Officeimpresso/Routes/api.php)
- [web.php](../../../Modules/Officeimpresso/Routes/web.php)

## Migrations (schema) — 8

- [2024_11_05_101935_create_licenca_computador_table.php](../../../Modules/Officeimpresso/Database/Migrations/2024_11_05_101935_create_licenca_computador_table.php)
- [2024_11_07_083505_update_licenca_computador_table.php](../../../Modules/Officeimpresso/Database/Migrations/2024_11_07_083505_update_licenca_computador_table.php)
- [2025_02_07_184909_add_officeimpresso_version.php](../../../Modules/Officeimpresso/Database/Migrations/2025_02_07_184909_add_officeimpresso_version.php)
- [2026_04_23_200000_create_licenca_log_table.php](../../../Modules/Officeimpresso/Database/Migrations/2026_04_23_200000_create_licenca_log_table.php)
- [2026_04_23_200100_create_licenca_log_triggers.php](../../../Modules/Officeimpresso/Database/Migrations/2026_04_23_200100_create_licenca_log_triggers.php)
- [2026_04_23_200200_add_indexes_to_licenca_computador.php](../../../Modules/Officeimpresso/Database/Migrations/2026_04_23_200200_add_indexes_to_licenca_computador.php)
- [2026_04_24_000000_drop_licenca_log_triggers.php](../../../Modules/Officeimpresso/Database/Migrations/2026_04_24_000000_drop_licenca_log_triggers.php)
- [2026_04_24_100500_add_business_location_id_to_licenca_log.php](../../../Modules/Officeimpresso/Database/Migrations/2026_04_24_100500_add_business_location_id_to_licenca_log.php)

## Seeders — 1

- [OfficeimpressoDatabaseSeeder.php](../../../Modules/Officeimpresso/Database/Seeders/OfficeimpressoDatabaseSeeder.php)

## Config — 2

- [config.php](../../../Modules/Officeimpresso/Config/config.php)
- [retention.php](../../../Modules/Officeimpresso/Config/retention.php)

## Views (Blade) — 18

- 1 em [Modules/Officeimpresso/Resources/views/](../../../Modules/Officeimpresso/Resources/views)
- 3 em [Modules/Officeimpresso/Resources/views/catalogue/](../../../Modules/Officeimpresso/Resources/views/catalogue)
- 3 em [Modules/Officeimpresso/Resources/views/catalogue/partials/](../../../Modules/Officeimpresso/Resources/views/catalogue/partials)
- 1 em [Modules/Officeimpresso/Resources/views/clients/](../../../Modules/Officeimpresso/Resources/views/clients)
- 2 em [Modules/Officeimpresso/Resources/views/layouts/](../../../Modules/Officeimpresso/Resources/views/layouts)
- 1 em [Modules/Officeimpresso/Resources/views/layouts/partials/](../../../Modules/Officeimpresso/Resources/views/layouts/partials)
- 4 em [Modules/Officeimpresso/Resources/views/licenca_computador/](../../../Modules/Officeimpresso/Resources/views/licenca_computador)
- 2 em [Modules/Officeimpresso/Resources/views/licenca_log/](../../../Modules/Officeimpresso/Resources/views/licenca_log)
- 1 em [Modules/Officeimpresso/Resources/views/licencas_log/](../../../Modules/Officeimpresso/Resources/views/licencas_log)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Testes (Pest) — 14

- 14 em [Modules/Officeimpresso/Tests/Feature/](../../../Modules/Officeimpresso/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 35

- [CHANGELOG.md](../../../Modules/Officeimpresso/CHANGELOG.md)
- [.gitkeep](../../../Modules/Officeimpresso/Config/.gitkeep)
- [.gitkeep](../../../Modules/Officeimpresso/Console/.gitkeep)
- [.gitkeep](../../../Modules/Officeimpresso/Database/Migrations/.gitkeep)
- [.gitkeep](../../../Modules/Officeimpresso/Database/Seeders/.gitkeep)
- [.gitkeep](../../../Modules/Officeimpresso/Database/factories/.gitkeep)
- [.gitkeep](../../../Modules/Officeimpresso/Entities/.gitkeep)
- [.gitkeep](../../../Modules/Officeimpresso/Http/Controllers/.gitkeep)
- [.gitkeep](../../../Modules/Officeimpresso/Http/Middleware/.gitkeep)
- [.gitkeep](../../../Modules/Officeimpresso/Http/Requests/.gitkeep)
- [.gitkeep](../../../Modules/Officeimpresso/Providers/.gitkeep)
- [README.md](../../../Modules/Officeimpresso/README.md)
- [.gitkeep](../../../Modules/Officeimpresso/Resources/assets/.gitkeep)
- [easy.qrcode.min.js](../../../Modules/Officeimpresso/Resources/assets/plugins/easy.qrcode.min.js)
- [app.scss](../../../Modules/Officeimpresso/Resources/assets/sass/app.scss)
- [.gitkeep](../../../Modules/Officeimpresso/Resources/lang/.gitkeep)
- [lang.php](../../../Modules/Officeimpresso/Resources/lang/en/lang.php)
- [lang.php](../../../Modules/Officeimpresso/Resources/lang/pt/lang.php)
- [topnav.php](../../../Modules/Officeimpresso/Resources/menus/topnav.php)
- [.gitkeep](../../../Modules/Officeimpresso/Resources/views/.gitkeep)
- [SCOPE.md](../../../Modules/Officeimpresso/SCOPE.md)
- [.gitkeep](../../../Modules/Officeimpresso/Tests/.gitkeep)
- [BusinessLocationResource.php](../../../Modules/Officeimpresso/Transformers/BusinessLocationResource.php)
- [BusinessResource.php](../../../Modules/Officeimpresso/Transformers/BusinessResource.php)
- [CommonResource.php](../../../Modules/Officeimpresso/Transformers/CommonResource.php)
- [ExpenseResource.php](../../../Modules/Officeimpresso/Transformers/ExpenseResource.php)
- [ProductResource.php](../../../Modules/Officeimpresso/Transformers/ProductResource.php)
- [SellResource.php](../../../Modules/Officeimpresso/Transformers/SellResource.php)
- [SellTransactionResource.php](../../../Modules/Officeimpresso/Transformers/SellTransactionResource.php)
- [TypesOfServiceResource.php](../../../Modules/Officeimpresso/Transformers/TypesOfServiceResource.php)
- [VariationResource.php](../../../Modules/Officeimpresso/Transformers/VariationResource.php)
- [composer.json](../../../Modules/Officeimpresso/composer.json)
- [module.json](../../../Modules/Officeimpresso/module.json)
- [package.json](../../../Modules/Officeimpresso/package.json)
- [webpack.mix.js](../../../Modules/Officeimpresso/webpack.mix.js)
