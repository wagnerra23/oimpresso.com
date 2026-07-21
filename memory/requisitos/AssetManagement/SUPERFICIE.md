---
name: "SUPERFÍCIE — AssetManagement"
description: "Índice GERADO dos artefatos do módulo AssetManagement reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: AssetManagement
---

# 🗺️ Superfície de código — AssetManagement

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs AssetManagement --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/AssetManagement/**` + `resources/js/Pages/AssetManagement/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 64 arquivos em 13 papéis.

## Controllers — 7

- [AssetAllocationController.php](../../../Modules/AssetManagement/Http/Controllers/AssetAllocationController.php)
- [AssetController.php](../../../Modules/AssetManagement/Http/Controllers/AssetController.php)
- [AssetMaitenanceController.php](../../../Modules/AssetManagement/Http/Controllers/AssetMaitenanceController.php)
- [AssetSettingsController.php](../../../Modules/AssetManagement/Http/Controllers/AssetSettingsController.php)
- [DataController.php](../../../Modules/AssetManagement/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/AssetManagement/Http/Controllers/InstallController.php)
- [RevokeAllocatedAssetController.php](../../../Modules/AssetManagement/Http/Controllers/RevokeAllocatedAssetController.php)

## Requests (validação) — 5

- [StoreAssetAllocationRequest.php](../../../Modules/AssetManagement/Http/Requests/StoreAssetAllocationRequest.php)
- [StoreAssetMaintenanceRequest.php](../../../Modules/AssetManagement/Http/Requests/StoreAssetMaintenanceRequest.php)
- [StoreAssetRequest.php](../../../Modules/AssetManagement/Http/Requests/StoreAssetRequest.php)
- [UpdateAssetMaintenanceRequest.php](../../../Modules/AssetManagement/Http/Requests/UpdateAssetMaintenanceRequest.php)
- [UpdateAssetRequest.php](../../../Modules/AssetManagement/Http/Requests/UpdateAssetRequest.php)

## Services — 4

- [AssetAllocationService.php](../../../Modules/AssetManagement/Services/AssetAllocationService.php)
- [AssetMaintenanceService.php](../../../Modules/AssetManagement/Services/AssetMaintenanceService.php)
- [AssetService.php](../../../Modules/AssetManagement/Services/AssetService.php)
- [AssetWarrantyService.php](../../../Modules/AssetManagement/Services/AssetWarrantyService.php)

## Models / Entities — 4

- [Asset.php](../../../Modules/AssetManagement/Entities/Asset.php)
- [AssetMaintenance.php](../../../Modules/AssetManagement/Entities/AssetMaintenance.php)
- [AssetTransaction.php](../../../Modules/AssetManagement/Entities/AssetTransaction.php)
- [AssetWarranty.php](../../../Modules/AssetManagement/Entities/AssetWarranty.php)

## Console / Commands — 1

- [AssetManagementHealthCommand.php](../../../Modules/AssetManagement/Console/Commands/AssetManagementHealthCommand.php)

## Providers — 2

- [AssetManagementServiceProvider.php](../../../Modules/AssetManagement/Providers/AssetManagementServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/AssetManagement/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/AssetManagement/Routes/api.php)
- [web.php](../../../Modules/AssetManagement/Routes/web.php)

## Migrations (schema) — 7

- [2020_08_19_175842_add_asset_management_module_version_to_system_table.php](../../../Modules/AssetManagement/Database/Migrations/2020_08_19_175842_add_asset_management_module_version_to_system_table.php)
- [2020_08_20_114339_create_assets_table.php](../../../Modules/AssetManagement/Database/Migrations/2020_08_20_114339_create_assets_table.php)
- [2020_08_20_173031_create_asset_transactions_table.php](../../../Modules/AssetManagement/Database/Migrations/2020_08_20_173031_create_asset_transactions_table.php)
- [2020_08_21_180138_add_asset_settings_column_to_business_table.php](../../../Modules/AssetManagement/Database/Migrations/2020_08_21_180138_add_asset_settings_column_to_business_table.php)
- [2021_10_29_110841_create_asset_warranties_table.php](../../../Modules/AssetManagement/Database/Migrations/2021_10_29_110841_create_asset_warranties_table.php)
- [2022_03_26_062215_create_asset_maintenances_table.php](../../../Modules/AssetManagement/Database/Migrations/2022_03_26_062215_create_asset_maintenances_table.php)
- [2022_05_11_070711_add_maintenance_note_column_to_asset_maintenances_table.php](../../../Modules/AssetManagement/Database/Migrations/2022_05_11_070711_add_maintenance_note_column_to_asset_maintenances_table.php)

## Seeders — 1

- [AssetManagementDatabaseSeeder.php](../../../Modules/AssetManagement/Database/Seeders/AssetManagementDatabaseSeeder.php)

## Config — 2

- [config.php](../../../Modules/AssetManagement/Config/config.php)
- [retention.php](../../../Modules/AssetManagement/Config/retention.php)

## Views (Blade) — 17

- 17 arquivos em [Modules/AssetManagement/Resources/views/asset/](../../../Modules/AssetManagement/Resources/views/asset) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Testes (Pest) — 9

- 9 arquivos em [Modules/AssetManagement/Tests/Feature/](../../../Modules/AssetManagement/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 3

- [AssetAssignedForMaintenance.php](../../../Modules/AssetManagement/Notifications/AssetAssignedForMaintenance.php)
- [AssetSentForMaintenance.php](../../../Modules/AssetManagement/Notifications/AssetSentForMaintenance.php)
- [AssetUtil.php](../../../Modules/AssetManagement/Utils/AssetUtil.php)
