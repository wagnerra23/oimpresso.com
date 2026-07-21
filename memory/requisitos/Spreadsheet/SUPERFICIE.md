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
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Spreadsheet/**` + `resources/js/Pages/Spreadsheet/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 41 arquivos em 13 papéis.

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

- 7 arquivos em [Modules/Spreadsheet/Resources/views/](../../../Modules/Spreadsheet/Resources/views) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Testes (Pest) — 9

- 9 arquivos em [Modules/Spreadsheet/Tests/Feature/](../../../Modules/Spreadsheet/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 1

- [SpreadsheetShared.php](../../../Modules/Spreadsheet/Notifications/SpreadsheetShared.php)
