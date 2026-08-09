---
name: "SUPERFÍCIE — Spreadsheet"
description: "Índice GERADO dos artefatos do módulo Spreadsheet reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Spreadsheet
---

# 🗺️ Superfície de código — Spreadsheet

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Spreadsheet --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o inventário completo das raízes `Modules/Spreadsheet/**` + `resources/js/Pages/Spreadsheet/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](../../../Modules/Spreadsheet/SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 79 arquivos em 13 papéis.

## Controllers — 3

- [DataController.php](../../../Modules/Spreadsheet/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Spreadsheet/Http/Controllers/InstallController.php)
- [SpreadsheetController.php](../../../Modules/Spreadsheet/Http/Controllers/SpreadsheetController.php)

## Requests (validação) — 5

- [MoveToFolderRequest.php](../../../Modules/Spreadsheet/Http/Requests/MoveToFolderRequest.php)
- [ShareSpreadsheetRequest.php](../../../Modules/Spreadsheet/Http/Requests/ShareSpreadsheetRequest.php)
- [StoreFolderRequest.php](../../../Modules/Spreadsheet/Http/Requests/StoreFolderRequest.php)
- [StoreSpreadsheetRequest.php](../../../Modules/Spreadsheet/Http/Requests/StoreSpreadsheetRequest.php)
- [UpdateSpreadsheetRequest.php](../../../Modules/Spreadsheet/Http/Requests/UpdateSpreadsheetRequest.php)

## Services — 1

- [SpreadsheetService.php](../../../Modules/Spreadsheet/Services/SpreadsheetService.php)

## Models / Entities — 2

- [Spreadsheet.php](../../../Modules/Spreadsheet/Entities/Spreadsheet.php)
- [SpreadsheetShare.php](../../../Modules/Spreadsheet/Entities/SpreadsheetShare.php)

## Console / Commands — 1

- [SpreadsheetHealthCommand.php](../../../Modules/Spreadsheet/Console/Commands/SpreadsheetHealthCommand.php)

## Providers — 2

- [RouteServiceProvider.php](../../../Modules/Spreadsheet/Providers/RouteServiceProvider.php)
- [SpreadsheetServiceProvider.php](../../../Modules/Spreadsheet/Providers/SpreadsheetServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Spreadsheet/Routes/api.php)
- [web.php](../../../Modules/Spreadsheet/Routes/web.php)

## Migrations (schema) — 4

- [2020_12_23_125610_add_spreadsheet_version_to_system_table.php](../../../Modules/Spreadsheet/Database/Migrations/2020_12_23_125610_add_spreadsheet_version_to_system_table.php)
- [2020_12_23_153255_create_spreadsheets_table.php](../../../Modules/Spreadsheet/Database/Migrations/2020_12_23_153255_create_spreadsheets_table.php)
- [2021_03_12_175416_create_spreadsheet_shares_table.php](../../../Modules/Spreadsheet/Database/Migrations/2021_03_12_175416_create_spreadsheet_shares_table.php)
- [2023_01_16_124948_add_folder_id_column_to_sheet_spreadsheets_table.php](../../../Modules/Spreadsheet/Database/Migrations/2023_01_16_124948_add_folder_id_column_to_sheet_spreadsheets_table.php)

## Seeders — 1

- [SpreadsheetDatabaseSeeder.php](../../../Modules/Spreadsheet/Database/Seeders/SpreadsheetDatabaseSeeder.php)

## Config — 3

- [config.php](../../../Modules/Spreadsheet/Config/config.php)
- [retention.php](../../../Modules/Spreadsheet/Config/retention.php)
- [retention.spreadsheet.php](../../../Modules/Spreadsheet/Config/retention.spreadsheet.php)

## Views (Blade) — 7

- 1 em [Modules/Spreadsheet/Resources/views/](../../../Modules/Spreadsheet/Resources/views)
- 2 em [Modules/Spreadsheet/Resources/views/layouts/](../../../Modules/Spreadsheet/Resources/views/layouts)
- 3 em [Modules/Spreadsheet/Resources/views/sheet/](../../../Modules/Spreadsheet/Resources/views/sheet)
- 1 em [Modules/Spreadsheet/Resources/views/sheet/partials/](../../../Modules/Spreadsheet/Resources/views/sheet/partials)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Testes (Pest) — 9

- 9 em [Modules/Spreadsheet/Tests/Feature/](../../../Modules/Spreadsheet/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 39

- [CHANGELOG.md](../../../Modules/Spreadsheet/CHANGELOG.md)
- [.gitkeep](../../../Modules/Spreadsheet/Config/.gitkeep)
- [.gitkeep](../../../Modules/Spreadsheet/Console/.gitkeep)
- [.gitkeep](../../../Modules/Spreadsheet/Database/Migrations/.gitkeep)
- [.gitkeep](../../../Modules/Spreadsheet/Database/Seeders/.gitkeep)
- [.gitkeep](../../../Modules/Spreadsheet/Database/factories/.gitkeep)
- [.gitkeep](../../../Modules/Spreadsheet/Entities/.gitkeep)
- [.gitkeep](../../../Modules/Spreadsheet/Http/Controllers/.gitkeep)
- [.gitkeep](../../../Modules/Spreadsheet/Http/Middleware/.gitkeep)
- [.gitkeep](../../../Modules/Spreadsheet/Http/Requests/.gitkeep)
- [SpreadsheetShared.php](../../../Modules/Spreadsheet/Notifications/SpreadsheetShared.php)
- [.gitkeep](../../../Modules/Spreadsheet/Providers/.gitkeep)
- [.gitkeep](../../../Modules/Spreadsheet/Resources/assets/.gitkeep)
- [app.js](../../../Modules/Spreadsheet/Resources/assets/js/app.js)
- [app.scss](../../../Modules/Spreadsheet/Resources/assets/sass/app.scss)
- [.gitkeep](../../../Modules/Spreadsheet/Resources/lang/.gitkeep)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/ar/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/ce/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/de/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/en/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/es/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/fr/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/hi/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/id/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/lo/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/nl/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/ps/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/pt/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/ro/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/sq/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/tr/lang.php)
- [lang.php](../../../Modules/Spreadsheet/Resources/lang/vi/lang.php)
- [.gitkeep](../../../Modules/Spreadsheet/Resources/views/.gitkeep)
- [SCOPE.md](../../../Modules/Spreadsheet/SCOPE.md)
- [.gitkeep](../../../Modules/Spreadsheet/Tests/.gitkeep)
- [composer.json](../../../Modules/Spreadsheet/composer.json)
- [module.json](../../../Modules/Spreadsheet/module.json)
- [package.json](../../../Modules/Spreadsheet/package.json)
- [webpack.mix.js](../../../Modules/Spreadsheet/webpack.mix.js)
