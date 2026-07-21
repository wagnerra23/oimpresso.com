---
name: "SUPERFÍCIE — Repair"
description: "Índice GERADO dos arquivos que moram no módulo Repair, agrupado por papel. Responde 'quais arquivos são deste contexto'. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Repair
---

# 🗺️ Superfície de código — Repair

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Repair --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o código que MORA em `Modules/Repair/**` + `resources/js/Pages/Repair/**` — a porta pra "quais arquivos". **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 148 arquivos em 15 papéis.

## Controllers — 11

- [CustomerRepairStatusController.php](../../../Modules/Repair/Http/Controllers/CustomerRepairStatusController.php)
- [DashboardController.php](../../../Modules/Repair/Http/Controllers/DashboardController.php)
- [DataController.php](../../../Modules/Repair/Http/Controllers/DataController.php)
- [DeviceModelController.php](../../../Modules/Repair/Http/Controllers/DeviceModelController.php)
- [InstallController.php](../../../Modules/Repair/Http/Controllers/InstallController.php)
- [JobSheetController.php](../../../Modules/Repair/Http/Controllers/JobSheetController.php)
- [ProducaoOficinaController.php](../../../Modules/Repair/Http/Controllers/ProducaoOficinaController.php)
- [RepairController.php](../../../Modules/Repair/Http/Controllers/RepairController.php)
- [RepairFsmActionController.php](../../../Modules/Repair/Http/Controllers/RepairFsmActionController.php)
- [RepairSettingsController.php](../../../Modules/Repair/Http/Controllers/RepairSettingsController.php)
- [RepairStatusController.php](../../../Modules/Repair/Http/Controllers/RepairStatusController.php)

## Requests (validação) — 6

- [CancelJobSheetRequest.php](../../../Modules/Repair/Http/Requests/CancelJobSheetRequest.php)
- [ExecuteRepairFsmActionRequest.php](../../../Modules/Repair/Http/Requests/ExecuteRepairFsmActionRequest.php)
- [ReopenJobSheetRequest.php](../../../Modules/Repair/Http/Requests/ReopenJobSheetRequest.php)
- [StartFsmActionRequest.php](../../../Modules/Repair/Http/Requests/StartFsmActionRequest.php)
- [StoreJobSheetRequest.php](../../../Modules/Repair/Http/Requests/StoreJobSheetRequest.php)
- [UpdateJobSheetRequest.php](../../../Modules/Repair/Http/Requests/UpdateJobSheetRequest.php)

## Services — 1

- [KanbanProductionService.php](../../../Modules/Repair/Services/KanbanProductionService.php)

## Models / Entities — 3

- [DeviceModel.php](../../../Modules/Repair/Entities/DeviceModel.php)
- [JobSheet.php](../../../Modules/Repair/Entities/JobSheet.php)
- [RepairStatus.php](../../../Modules/Repair/Entities/RepairStatus.php)

## Observers — 1

- [JobSheetObserver.php](../../../Modules/Repair/Observers/JobSheetObserver.php)

## Events / Listeners — 1

- [RepairStatusChanged.php](../../../Modules/Repair/Events/RepairStatusChanged.php)

## Providers — 2

- [RepairServiceProvider.php](../../../Modules/Repair/Providers/RepairServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Repair/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Repair/Routes/api.php)
- [web.php](../../../Modules/Repair/Routes/web.php)

## Migrations (schema) — 18

- [2019_03_07_155813_make_repair_statuses_table.php](../../../Modules/Repair/Database/Migrations/2019_03_07_155813_make_repair_statuses_table.php)
- [2019_03_08_120634_add_repair_columns_to_transactions_table.php](../../../Modules/Repair/Database/Migrations/2019_03_08_120634_add_repair_columns_to_transactions_table.php)
- [2019_03_14_182704_add_repair_permissions.php](../../../Modules/Repair/Database/Migrations/2019_03_14_182704_add_repair_permissions.php)
- [2019_03_29_110241_add_repair_version_column_to_system_table.php](../../../Modules/Repair/Database/Migrations/2019_03_29_110241_add_repair_version_column_to_system_table.php)
- [2019_04_12_113901_add_repair_settings_column_to_business_table.php](../../../Modules/Repair/Database/Migrations/2019_04_12_113901_add_repair_settings_column_to_business_table.php)
- [2020_05_05_125008_create_device_models_table.php](../../../Modules/Repair/Database/Migrations/2020_05_05_125008_create_device_models_table.php)
- [2020_05_06_103135_add_repair_model_id_column_to_products_table.php](../../../Modules/Repair/Database/Migrations/2020_05_06_103135_add_repair_model_id_column_to_products_table.php)
- [2020_07_11_120308_add_columns_to_repair_statuses_table.php](../../../Modules/Repair/Database/Migrations/2020_07_11_120308_add_columns_to_repair_statuses_table.php)
- [2020_07_31_130737_create_job_sheets_table.php](../../../Modules/Repair/Database/Migrations/2020_07_31_130737_create_job_sheets_table.php)
- [2020_08_07_124241_add_job_sheet_id_to_transactions_table.php](../../../Modules/Repair/Database/Migrations/2020_08_07_124241_add_job_sheet_id_to_transactions_table.php)
- [2020_08_22_104640_add_email_template_field_to_repair_status_table.php](../../../Modules/Repair/Database/Migrations/2020_08_22_104640_add_email_template_field_to_repair_status_table.php)
- [2020_10_19_131934_add_job_sheet_custom_fields_to_repair_job_sheets_table.php](../../../Modules/Repair/Database/Migrations/2020_10_19_131934_add_job_sheet_custom_fields_to_repair_job_sheets_table.php)
- [2020_11_25_111050_add_parts_column_to_repair_job_sheets_table.php](../../../Modules/Repair/Database/Migrations/2020_11_25_111050_add_parts_column_to_repair_job_sheets_table.php)
- [2020_12_30_101842_add_use_for_repair_column_to_brands_table.php](../../../Modules/Repair/Database/Migrations/2020_12_30_101842_add_use_for_repair_column_to_brands_table.php)
- [2021_02_16_190423_add_repair_module_indexing.php](../../../Modules/Repair/Database/Migrations/2021_02_16_190423_add_repair_module_indexing.php)
- [2022_12_23_162847_add_repair_jobsheet_settings_column_to_business_table.php](../../../Modules/Repair/Database/Migrations/2022_12_23_162847_add_repair_jobsheet_settings_column_to_business_table.php)
- [2026_05_06_180000_add_repair_listing_indexes.php](../../../Modules/Repair/Database/Migrations/2026_05_06_180000_add_repair_listing_indexes.php)
- [2026_05_12_050001_add_current_stage_id_to_job_sheets.php](../../../Modules/Repair/Database/Migrations/2026_05_12_050001_add_current_stage_id_to_job_sheets.php)

## Seeders — 1

- [RepairDatabaseSeeder.php](../../../Modules/Repair/Database/Seeders/RepairDatabaseSeeder.php)

## Config — 2

- [config.php](../../../Modules/Repair/Config/config.php)
- [retention.php](../../../Modules/Repair/Config/retention.php)

## Views (Blade) — 52

- 52 arquivos em [Modules/Repair/Resources/views/customer_repair/](../../../Modules/Repair/Resources/views/customer_repair) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Telas (Inertia/React) — 13

- [Index.tsx](../../../resources/js/Pages/Repair/Dashboard/Index.tsx)
- [Create.tsx](../../../resources/js/Pages/Repair/DeviceModels/Create.tsx)
- [Edit.tsx](../../../resources/js/Pages/Repair/DeviceModels/Edit.tsx)
- [Index.tsx](../../../resources/js/Pages/Repair/DeviceModels/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Repair/Index.tsx)
- [AddParts.tsx](../../../resources/js/Pages/Repair/JobSheet/AddParts.tsx)
- [Create.tsx](../../../resources/js/Pages/Repair/JobSheet/Create.tsx)
- [Edit.tsx](../../../resources/js/Pages/Repair/JobSheet/Edit.tsx)
- [Index.tsx](../../../resources/js/Pages/Repair/JobSheet/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/Repair/JobSheet/Show.tsx)
- [Index.tsx](../../../resources/js/Pages/Repair/ProducaoOficina/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/Repair/Show.tsx)
- [Index.tsx](../../../resources/js/Pages/Repair/Status/Index.tsx)

## Charters (lei da tela) — 13

- [Index.charter.md](../../../resources/js/Pages/Repair/Dashboard/Index.charter.md)
- [Create.charter.md](../../../resources/js/Pages/Repair/DeviceModels/Create.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/Repair/DeviceModels/Edit.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Repair/DeviceModels/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Repair/Index.charter.md)
- [AddParts.charter.md](../../../resources/js/Pages/Repair/JobSheet/AddParts.charter.md)
- [Create.charter.md](../../../resources/js/Pages/Repair/JobSheet/Create.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/Repair/JobSheet/Edit.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Repair/JobSheet/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Repair/JobSheet/Show.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Repair/ProducaoOficina/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Repair/Show.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Repair/Status/Index.charter.md)

## Testes (Pest) — 22

- 22 arquivos em [Modules/Repair/Tests/Feature/](../../../Modules/Repair/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 3

- [LogsWithPiiRedactor.php](../../../Modules/Repair/Concerns/LogsWithPiiRedactor.php)
- [RepairStatusUpdated.php](../../../Modules/Repair/Notifications/RepairStatusUpdated.php)
- [RepairUtil.php](../../../Modules/Repair/Utils/RepairUtil.php)

