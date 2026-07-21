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
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Officeimpresso/**` + `resources/js/Pages/Officeimpresso/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 81 arquivos em 15 papéis.

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

- 18 arquivos em [Modules/Officeimpresso/Resources/views/catalogue/](../../../Modules/Officeimpresso/Resources/views/catalogue) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Testes (Pest) — 13

- 13 arquivos em [Modules/Officeimpresso/Tests/Feature/](../../../Modules/Officeimpresso/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 9

- [BusinessLocationResource.php](../../../Modules/Officeimpresso/Transformers/BusinessLocationResource.php)
- [BusinessResource.php](../../../Modules/Officeimpresso/Transformers/BusinessResource.php)
- [CommonResource.php](../../../Modules/Officeimpresso/Transformers/CommonResource.php)
- [ExpenseResource.php](../../../Modules/Officeimpresso/Transformers/ExpenseResource.php)
- [ProductResource.php](../../../Modules/Officeimpresso/Transformers/ProductResource.php)
- [SellResource.php](../../../Modules/Officeimpresso/Transformers/SellResource.php)
- [SellTransactionResource.php](../../../Modules/Officeimpresso/Transformers/SellTransactionResource.php)
- [TypesOfServiceResource.php](../../../Modules/Officeimpresso/Transformers/TypesOfServiceResource.php)
- [VariationResource.php](../../../Modules/Officeimpresso/Transformers/VariationResource.php)
