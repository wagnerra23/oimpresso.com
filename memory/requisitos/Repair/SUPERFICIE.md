---
name: "SUPERFÍCIE — Repair"
description: "Índice GERADO dos artefatos do módulo Repair reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Repair
---

# 🗺️ Superfície de código — Repair

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Repair --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o inventário completo das raízes `Modules/Repair/**` + `resources/js/Pages/Repair/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 190 arquivos em 16 papéis.

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

- [index.blade.php](../../../Modules/Repair/Resources/views/customer_repair/index.blade.php)
- [repair_activities.blade.php](../../../Modules/Repair/Resources/views/customer_repair/repair_activities.blade.php)
- [repair_details.blade.php](../../../Modules/Repair/Resources/views/customer_repair/repair_details.blade.php)
- [index.blade.php](../../../Modules/Repair/Resources/views/dashboard/index.blade.php)
- [create.blade.php](../../../Modules/Repair/Resources/views/device_model/create.blade.php)
- [edit.blade.php](../../../Modules/Repair/Resources/views/device_model/edit.blade.php)
- [index.blade.php](../../../Modules/Repair/Resources/views/device_model/index.blade.php)
- [device_model_drodown.blade.php](../../../Modules/Repair/Resources/views/device_model/partials/device_model_drodown.blade.php)
- [list_product_filters.blade.php](../../../Modules/Repair/Resources/views/device_model/partials/list_product_filters.blade.php)
- [repair_product_screen.blade.php](../../../Modules/Repair/Resources/views/device_model/partials/repair_product_screen.blade.php)
- [index.blade.php](../../../Modules/Repair/Resources/views/index.blade.php)
- [add_parts.blade.php](../../../Modules/Repair/Resources/views/job_sheet/add_parts.blade.php)
- [create.blade.php](../../../Modules/Repair/Resources/views/job_sheet/create.blade.php)
- [edit.blade.php](../../../Modules/Repair/Resources/views/job_sheet/edit.blade.php)
- [index.blade.php](../../../Modules/Repair/Resources/views/job_sheet/index.blade.php)
- [document_table_view.blade.php](../../../Modules/Repair/Resources/views/job_sheet/partials/document_table_view.blade.php)
- [edit_status.blade.php](../../../Modules/Repair/Resources/views/job_sheet/partials/edit_status.blade.php)
- [edit_status_form.blade.php](../../../Modules/Repair/Resources/views/job_sheet/partials/edit_status_form.blade.php)
- [job_sheet_part_row.blade.php](../../../Modules/Repair/Resources/views/job_sheet/partials/job_sheet_part_row.blade.php)
- [scurity_modal.blade.php](../../../Modules/Repair/Resources/views/job_sheet/partials/scurity_modal.blade.php)
- [print_label.blade.php](../../../Modules/Repair/Resources/views/job_sheet/print_label.blade.php)
- [print_pdf.blade.php](../../../Modules/Repair/Resources/views/job_sheet/print_pdf.blade.php)
- [show.blade.php](../../../Modules/Repair/Resources/views/job_sheet/show.blade.php)
- [tagify_css.blade.php](../../../Modules/Repair/Resources/views/job_sheet/tagify_css.blade.php)
- [upload_doc.blade.php](../../../Modules/Repair/Resources/views/job_sheet/upload_doc.blade.php)
- [master.blade.php](../../../Modules/Repair/Resources/views/layouts/master.blade.php)
- [nav.blade.php](../../../Modules/Repair/Resources/views/layouts/nav.blade.php)
- [header.blade.php](../../../Modules/Repair/Resources/views/layouts/partials/header.blade.php)
- [invoice_layout_settings.blade.php](../../../Modules/Repair/Resources/views/layouts/partials/invoice_layout_settings.blade.php)
- [javascripts.blade.php](../../../Modules/Repair/Resources/views/layouts/partials/javascripts.blade.php)
- [pos_header.blade.php](../../../Modules/Repair/Resources/views/layouts/partials/pos_header.blade.php)
- [plain.blade.php](../../../Modules/Repair/Resources/views/layouts/plain.blade.php)
- [repair_status.blade.php](../../../Modules/Repair/Resources/views/layouts/repair_status.blade.php)
- [create.blade.php](../../../Modules/Repair/Resources/views/repair/create.blade.php)
- [edit.blade.php](../../../Modules/Repair/Resources/views/repair/edit.blade.php)
- [index.blade.php](../../../Modules/Repair/Resources/views/repair/index.blade.php)
- [activities.blade.php](../../../Modules/Repair/Resources/views/repair/partials/activities.blade.php)
- [checklist_modal.blade.php](../../../Modules/Repair/Resources/views/repair/partials/checklist_modal.blade.php)
- [checklists.blade.php](../../../Modules/Repair/Resources/views/repair/partials/checklists.blade.php)
- [edit_repair_status_modal.blade.php](../../../Modules/Repair/Resources/views/repair/partials/edit_repair_status_modal.blade.php)
- [preview_label.blade.php](../../../Modules/Repair/Resources/views/repair/partials/preview_label.blade.php)
- [repair_pos.blade.php](../../../Modules/Repair/Resources/views/repair/partials/repair_pos.blade.php)
- [repair_status.blade.php](../../../Modules/Repair/Resources/views/repair/partials/repair_status.blade.php)
- [security_modal.blade.php](../../../Modules/Repair/Resources/views/repair/partials/security_modal.blade.php)
- [classic.blade.php](../../../Modules/Repair/Resources/views/repair/receipts/classic.blade.php)
- [show.blade.php](../../../Modules/Repair/Resources/views/repair/show.blade.php)
- [index.blade.php](../../../Modules/Repair/Resources/views/settings/index.blade.php)
- [jobsheet_settings_tab.blade.php](../../../Modules/Repair/Resources/views/settings/partials/jobsheet_settings_tab.blade.php)
- [repair_settings_tab.blade.php](../../../Modules/Repair/Resources/views/settings/partials/repair_settings_tab.blade.php)
- [create.blade.php](../../../Modules/Repair/Resources/views/status/create.blade.php)
- [edit.blade.php](../../../Modules/Repair/Resources/views/status/edit.blade.php)
- [index.blade.php](../../../Modules/Repair/Resources/views/status/index.blade.php)

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

- 22 em [Modules/Repair/Tests/Feature/](../../../Modules/Repair/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 42

- [LogsWithPiiRedactor.php](../../../Modules/Repair/Concerns/LogsWithPiiRedactor.php)
- [.gitkeep](../../../Modules/Repair/Config/.gitkeep)
- [.gitkeep](../../../Modules/Repair/Console/.gitkeep)
- [.gitkeep](../../../Modules/Repair/Database/Migrations/.gitkeep)
- [.gitkeep](../../../Modules/Repair/Database/Seeders/.gitkeep)
- [.gitkeep](../../../Modules/Repair/Database/factories/.gitkeep)
- [.gitkeep](../../../Modules/Repair/Entities/.gitkeep)
- [.gitkeep](../../../Modules/Repair/Http/Controllers/.gitkeep)
- [.gitkeep](../../../Modules/Repair/Http/Middleware/.gitkeep)
- [.gitkeep](../../../Modules/Repair/Http/Requests/.gitkeep)
- [RepairListResource.php](../../../Modules/Repair/Http/Resources/RepairListResource.php)
- [RepairStatusUpdated.php](../../../Modules/Repair/Notifications/RepairStatusUpdated.php)
- [.gitkeep](../../../Modules/Repair/Providers/.gitkeep)
- [.gitkeep](../../../Modules/Repair/Resources/assets/.gitkeep)
- [app.js](../../../Modules/Repair/Resources/assets/js/app.js)
- [app.scss](../../../Modules/Repair/Resources/assets/sass/app.scss)
- [.gitkeep](../../../Modules/Repair/Resources/lang/.gitkeep)
- [lang.php](../../../Modules/Repair/Resources/lang/ar/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/ce/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/de/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/en/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/es/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/fr/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/hi/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/id/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/lo/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/nl/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/ps/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/pt/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/ro/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/sq/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/tr/lang.php)
- [lang.php](../../../Modules/Repair/Resources/lang/vi/lang.php)
- [topnav.php](../../../Modules/Repair/Resources/menus/topnav.php)
- [.gitkeep](../../../Modules/Repair/Resources/views/.gitkeep)
- [.gitkeep](../../../Modules/Repair/Tests/.gitkeep)
- [RepairUtil.php](../../../Modules/Repair/Utils/RepairUtil.php)
- [composer.json](../../../Modules/Repair/composer.json)
- [module.json](../../../Modules/Repair/module.json)
- [package.json](../../../Modules/Repair/package.json)
- [webpack.mix.js](../../../Modules/Repair/webpack.mix.js)
- [SCOPE.md](../../../memory/requisitos/Repair/SCOPE.md)
