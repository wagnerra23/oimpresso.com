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
> **O que isto é:** o inventário completo das raízes `Modules/AssetManagement/**` + `resources/js/Pages/AssetManagement/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 99 arquivos em 13 papéis.

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

- [create.blade.php](../../../Modules/AssetManagement/Resources/views/asset/create.blade.php)
- [dashboard.blade.php](../../../Modules/AssetManagement/Resources/views/asset/dashboard.blade.php)
- [edit.blade.php](../../../Modules/AssetManagement/Resources/views/asset/edit.blade.php)
- [index.blade.php](../../../Modules/AssetManagement/Resources/views/asset/index.blade.php)
- [create.blade.php](../../../Modules/AssetManagement/Resources/views/asset_allocation/create.blade.php)
- [edit.blade.php](../../../Modules/AssetManagement/Resources/views/asset_allocation/edit.blade.php)
- [index.blade.php](../../../Modules/AssetManagement/Resources/views/asset_allocation/index.blade.php)
- [create.blade.php](../../../Modules/AssetManagement/Resources/views/asset_maintenance/create.blade.php)
- [edit.blade.php](../../../Modules/AssetManagement/Resources/views/asset_maintenance/edit.blade.php)
- [index.blade.php](../../../Modules/AssetManagement/Resources/views/asset_maintenance/index.blade.php)
- [create.blade.php](../../../Modules/AssetManagement/Resources/views/asset_revocation/create.blade.php)
- [index.blade.php](../../../Modules/AssetManagement/Resources/views/asset_revocation/index.blade.php)
- [index.blade.php](../../../Modules/AssetManagement/Resources/views/index.blade.php)
- [nav.blade.php](../../../Modules/AssetManagement/Resources/views/layouts/nav.blade.php)
- [index.blade.php](../../../Modules/AssetManagement/Resources/views/settings/index.blade.php)
- [notification_settings.blade.php](../../../Modules/AssetManagement/Resources/views/settings/notification_settings.blade.php)
- [prefix_settings.blade.php](../../../Modules/AssetManagement/Resources/views/settings/prefix_settings.blade.php)

## Testes (Pest) — 9

- 9 em [Modules/AssetManagement/Tests/Feature/](../../../Modules/AssetManagement/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 38

- [.gitkeep](../../../Modules/AssetManagement/Config/.gitkeep)
- [.gitkeep](../../../Modules/AssetManagement/Console/.gitkeep)
- [.gitkeep](../../../Modules/AssetManagement/Database/Migrations/.gitkeep)
- [.gitkeep](../../../Modules/AssetManagement/Database/Seeders/.gitkeep)
- [.gitkeep](../../../Modules/AssetManagement/Database/factories/.gitkeep)
- [.gitkeep](../../../Modules/AssetManagement/Entities/.gitkeep)
- [.gitkeep](../../../Modules/AssetManagement/Http/Controllers/.gitkeep)
- [.gitkeep](../../../Modules/AssetManagement/Http/Middleware/.gitkeep)
- [.gitkeep](../../../Modules/AssetManagement/Http/Requests/.gitkeep)
- [AssetAssignedForMaintenance.php](../../../Modules/AssetManagement/Notifications/AssetAssignedForMaintenance.php)
- [AssetSentForMaintenance.php](../../../Modules/AssetManagement/Notifications/AssetSentForMaintenance.php)
- [.gitkeep](../../../Modules/AssetManagement/Providers/.gitkeep)
- [.gitkeep](../../../Modules/AssetManagement/Resources/assets/.gitkeep)
- [assetmanagement.js](../../../Modules/AssetManagement/Resources/assets/js/assetmanagement.js)
- [assetmanagement.css](../../../Modules/AssetManagement/Resources/assets/sass/assetmanagement.css)
- [.gitkeep](../../../Modules/AssetManagement/Resources/lang/.gitkeep)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/ar/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/ce/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/de/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/en/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/es/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/fr/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/hi/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/id/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/lo/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/nl/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/ps/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/pt/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/ro/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/sq/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/tr/lang.php)
- [lang.php](../../../Modules/AssetManagement/Resources/lang/vi/lang.php)
- [.gitkeep](../../../Modules/AssetManagement/Resources/views/.gitkeep)
- [.gitkeep](../../../Modules/AssetManagement/Tests/.gitkeep)
- [AssetUtil.php](../../../Modules/AssetManagement/Utils/AssetUtil.php)
- [composer.json](../../../Modules/AssetManagement/composer.json)
- [module.json](../../../Modules/AssetManagement/module.json)
- [SCOPE.md](../../../memory/requisitos/AssetManagement/SCOPE.md)
