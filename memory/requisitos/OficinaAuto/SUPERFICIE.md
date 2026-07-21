---
name: "SUPERFÍCIE — OficinaAuto"
description: "Índice GERADO dos artefatos do módulo OficinaAuto reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: OficinaAuto
---

# 🗺️ Superfície de código — OficinaAuto

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs OficinaAuto --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/OficinaAuto/**` + `resources/js/Pages/OficinaAuto/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 161 arquivos em 17 papéis.

## Controllers — 9

- [DataController.php](../../../Modules/OficinaAuto/Http/Controllers/DataController.php)
- [DviInspectionController.php](../../../Modules/OficinaAuto/Http/Controllers/DviInspectionController.php)
- [InstallController.php](../../../Modules/OficinaAuto/Http/Controllers/InstallController.php)
- [ProducaoOficinaController.php](../../../Modules/OficinaAuto/Http/Controllers/ProducaoOficinaController.php)
- [AprovacaoOsController.php](../../../Modules/OficinaAuto/Http/Controllers/Public/AprovacaoOsController.php)
- [ServiceOrderController.php](../../../Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php)
- [ServiceOrderItemController.php](../../../Modules/OficinaAuto/Http/Controllers/ServiceOrderItemController.php)
- [ServiceOrderPhotoController.php](../../../Modules/OficinaAuto/Http/Controllers/ServiceOrderPhotoController.php)
- [VehicleController.php](../../../Modules/OficinaAuto/Http/Controllers/VehicleController.php)

## Requests (validação) — 11

- [StoreDviRequest.php](../../../Modules/OficinaAuto/Http/Requests/StoreDviRequest.php)
- [StoreServiceOrderItemRequest.php](../../../Modules/OficinaAuto/Http/Requests/StoreServiceOrderItemRequest.php)
- [StoreServiceOrderPhotoRequest.php](../../../Modules/OficinaAuto/Http/Requests/StoreServiceOrderPhotoRequest.php)
- [StoreServiceOrderRequest.php](../../../Modules/OficinaAuto/Http/Requests/StoreServiceOrderRequest.php)
- [StoreVehicleRequest.php](../../../Modules/OficinaAuto/Http/Requests/StoreVehicleRequest.php)
- [UpdateDviRequest.php](../../../Modules/OficinaAuto/Http/Requests/UpdateDviRequest.php)
- [UpdateServiceOrderItemRequest.php](../../../Modules/OficinaAuto/Http/Requests/UpdateServiceOrderItemRequest.php)
- [UpdateServiceOrderPhotoLabelRequest.php](../../../Modules/OficinaAuto/Http/Requests/UpdateServiceOrderPhotoLabelRequest.php)
- [UpdateServiceOrderRequest.php](../../../Modules/OficinaAuto/Http/Requests/UpdateServiceOrderRequest.php)
- [UpdateVehicleRequest.php](../../../Modules/OficinaAuto/Http/Requests/UpdateVehicleRequest.php)
- [UploadDviPhotoRequest.php](../../../Modules/OficinaAuto/Http/Requests/UploadDviPhotoRequest.php)

## Services — 14

- [AprovacaoOsService.php](../../../Modules/OficinaAuto/Services/AprovacaoOsService.php)
- [DviInspectionService.php](../../../Modules/OficinaAuto/Services/DviInspectionService.php)
- [HttpPlacaProvider.php](../../../Modules/OficinaAuto/Services/PlacaLookup/HttpPlacaProvider.php)
- [PlacaLookupException.php](../../../Modules/OficinaAuto/Services/PlacaLookup/PlacaLookupException.php)
- [PlacaLookupResult.php](../../../Modules/OficinaAuto/Services/PlacaLookup/PlacaLookupResult.php)
- [PlacaProvider.php](../../../Modules/OficinaAuto/Services/PlacaLookup/PlacaProvider.php)
- [StubPlacaProvider.php](../../../Modules/OficinaAuto/Services/PlacaLookup/StubPlacaProvider.php)
- [CapacidadeService.php](../../../Modules/OficinaAuto/Services/Producao/CapacidadeService.php)
- [ServiceOrderItemService.php](../../../Modules/OficinaAuto/Services/ServiceOrderItemService.php)
- [ServiceOrderPipelineStarter.php](../../../Modules/OficinaAuto/Services/ServiceOrderPipelineStarter.php)
- [ServiceOrderSummaryService.php](../../../Modules/OficinaAuto/Services/ServiceOrderSummaryService.php)
- [StageGateEvaluator.php](../../../Modules/OficinaAuto/Services/StageGateEvaluator.php)
- [VehicleLookupService.php](../../../Modules/OficinaAuto/Services/VehicleLookupService.php)
- [VehicleQueryService.php](../../../Modules/OficinaAuto/Services/VehicleQueryService.php)

## Models / Entities — 4

- [OaInspectionItem.php](../../../Modules/OficinaAuto/Entities/OaInspectionItem.php)
- [ServiceOrder.php](../../../Modules/OficinaAuto/Entities/ServiceOrder.php)
- [ServiceOrderItem.php](../../../Modules/OficinaAuto/Entities/ServiceOrderItem.php)
- [Vehicle.php](../../../Modules/OficinaAuto/Entities/Vehicle.php)

## Observers — 1

- [ServiceOrderObserver.php](../../../Modules/OficinaAuto/Observers/ServiceOrderObserver.php)

## Jobs — 1

- [EnviarLinkAprovacaoWhatsappJob.php](../../../Modules/OficinaAuto/Jobs/EnviarLinkAprovacaoWhatsappJob.php)

## Console / Commands — 5

- [ImportFirebirdMartinhoCommand.php](../../../Modules/OficinaAuto/Console/Commands/ImportFirebirdMartinhoCommand.php)
- [OficinaAutoCleanupMigratedClientCommand.php](../../../Modules/OficinaAuto/Console/Commands/OficinaAutoCleanupMigratedClientCommand.php)
- [OficinaAutoMigrationReportCommand.php](../../../Modules/OficinaAuto/Console/Commands/OficinaAutoMigrationReportCommand.php)
- [OficinaAutoSanityCheckCommand.php](../../../Modules/OficinaAuto/Console/Commands/OficinaAutoSanityCheckCommand.php)
- [OficinaBoardDemoCommand.php](../../../Modules/OficinaAuto/Console/Commands/OficinaBoardDemoCommand.php)

## Providers — 2

- [OficinaAutoServiceProvider.php](../../../Modules/OficinaAuto/Providers/OficinaAutoServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/OficinaAuto/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/OficinaAuto/Routes/web.php)

## Migrations (schema) — 20

- [2026_05_11_000010_create_vehicles_table.php](../../../Modules/OficinaAuto/Database/Migrations/2026_05_11_000010_create_vehicles_table.php)
- [2026_05_11_000020_create_service_orders_table.php](../../../Modules/OficinaAuto/Database/Migrations/2026_05_11_000020_create_service_orders_table.php)
- [2026_05_12_220001_add_cacamba_fields_to_vehicles.php](../../../Modules/OficinaAuto/Database/Migrations/2026_05_12_220001_add_cacamba_fields_to_vehicles.php)
- [2026_05_12_220002_add_rental_fields_to_service_orders.php](../../../Modules/OficinaAuto/Database/Migrations/2026_05_12_220002_add_rental_fields_to_service_orders.php)
- [2026_05_12_230001_add_transaction_sell_line_id_to_service_orders.php](../../../Modules/OficinaAuto/Database/Migrations/2026_05_12_230001_add_transaction_sell_line_id_to_service_orders.php)
- [2026_05_13_010001_add_current_stage_id_to_service_orders.php](../../../Modules/OficinaAuto/Database/Migrations/2026_05_13_010001_add_current_stage_id_to_service_orders.php)
- [2026_05_13_010002_add_contact_id_to_service_orders.php](../../../Modules/OficinaAuto/Database/Migrations/2026_05_13_010002_add_contact_id_to_service_orders.php)
- [2026_05_17_000010_create_oficina_service_order_items_table.php](../../../Modules/OficinaAuto/Database/Migrations/2026_05_17_000010_create_oficina_service_order_items_table.php)
- [2026_05_26_120001_add_box_and_assigned_user_to_service_orders.php](../../../Modules/OficinaAuto/Database/Migrations/2026_05_26_120001_add_box_and_assigned_user_to_service_orders.php)
- [2026_05_26_120002_create_oa_inspection_items_table.php](../../../Modules/OficinaAuto/Database/Migrations/2026_05_26_120002_create_oa_inspection_items_table.php)
- [2026_05_27_000010_add_client_decision_to_oa_inspection_items.php](../../../Modules/OficinaAuto/Database/Migrations/2026_05_27_000010_add_client_decision_to_oa_inspection_items.php)
- [2026_06_02_000001_add_mecanica_to_service_orders_order_type.php](../../../Modules/OficinaAuto/Database/Migrations/2026_06_02_000001_add_mecanica_to_service_orders_order_type.php)
- [2026_06_02_000010_add_checkin_fields_to_service_orders.php](../../../Modules/OficinaAuto/Database/Migrations/2026_06_02_000010_add_checkin_fields_to_service_orders.php)
- [2026_06_09_000001_erradica_locacao_from_order_type.php](../../../Modules/OficinaAuto/Database/Migrations/2026_06_09_000001_erradica_locacao_from_order_type.php)
- [2026_06_09_000002_backfill_order_type_mecanica_legacy.php](../../../Modules/OficinaAuto/Database/Migrations/2026_06_09_000002_backfill_order_type_mecanica_legacy.php)
- [2026_06_09_000003_rename_cacamba_locacao_stage_labels.php](../../../Modules/OficinaAuto/Database/Migrations/2026_06_09_000003_rename_cacamba_locacao_stage_labels.php)
- [2026_06_09_130001_add_approval_tracking_to_service_orders.php](../../../Modules/OficinaAuto/Database/Migrations/2026_06_09_130001_add_approval_tracking_to_service_orders.php)
- [2026_06_10_000000_seed_oficina_mecanica_os_process_existing_businesses.php](../../../Modules/OficinaAuto/Database/Migrations/2026_06_10_000000_seed_oficina_mecanica_os_process_existing_businesses.php)
- [2026_06_10_000001_repoint_orphan_service_orders_from_legacy_pipelines.php](../../../Modules/OficinaAuto/Database/Migrations/2026_06_10_000001_repoint_orphan_service_orders_from_legacy_pipelines.php)
- [2026_06_10_000002_rename_cacamba_locacao_action_labels.php](../../../Modules/OficinaAuto/Database/Migrations/2026_06_10_000002_rename_cacamba_locacao_action_labels.php)

## Seeders — 4

- [OficinaAutoDatabaseSeeder.php](../../../Modules/OficinaAuto/Database/Seeders/OficinaAutoDatabaseSeeder.php)
- [OficinaAutoDemoSeeder.php](../../../Modules/OficinaAuto/Database/Seeders/OficinaAutoDemoSeeder.php)
- [OficinaAutoFsmSeeder.php](../../../Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php)
- [RepairSettingsSeeder.php](../../../Modules/OficinaAuto/Database/Seeders/RepairSettingsSeeder.php)

## Config — 2

- [config.php](../../../Modules/OficinaAuto/Config/config.php)
- [retention.php](../../../Modules/OficinaAuto/Config/retention.php)

## Telas (Inertia/React) — 9

- [AprovacaoPublica.tsx](../../../resources/js/Pages/OficinaAuto/AprovacaoPublica.tsx)
- [Board.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx)
- [Create.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/Create.tsx)
- [Edit.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/Edit.tsx)
- [Show.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/Show.tsx)
- [Create.tsx](../../../resources/js/Pages/OficinaAuto/Vehicles/Create.tsx)
- [Edit.tsx](../../../resources/js/Pages/OficinaAuto/Vehicles/Edit.tsx)
- [Index.tsx](../../../resources/js/Pages/OficinaAuto/Vehicles/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/OficinaAuto/Vehicles/Show.tsx)

## Componentes / apoio de tela — 21

- [DragConfirmDialog.tsx](../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/DragConfirmDialog.tsx)
- [DviInlineEditor.tsx](../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/DviInlineEditor.tsx)
- [DviPhotoGrid.tsx](../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/DviPhotoGrid.tsx)
- [KanbanDndProvider.tsx](../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/KanbanDndProvider.tsx)
- [LaudoPhotoSection.tsx](../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/LaudoPhotoSection.tsx)
- [ServiceOrderRichSheet.tsx](../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/ServiceOrderRichSheet.tsx)
- [ApprovalGateCard.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/ApprovalGateCard.tsx)
- [DviBudgetSection.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/DviBudgetSection.tsx)
- [EntryCheckinFields.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/EntryCheckinFields.tsx)
- [FiscalSplitCard.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/FiscalSplitCard.tsx)
- [ServiceOrderFsmActionPanel.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderFsmActionPanel.tsx)
- [ServiceOrderItemFormSheet.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderItemFormSheet.tsx)
- [ServiceOrderItemRow.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderItemRow.tsx)
- [ServiceOrderStageGate.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderStageGate.tsx)
- [ServiceOrderStagePipeline.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderStagePipeline.tsx)
- [ServiceOrderStatusBadge.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderStatusBadge.tsx)
- [ServiceOrderTimeline.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderTimeline.tsx)
- [BoardKpiCard.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/board/BoardKpiCard.tsx)
- [ServiceOrderKanbanCard.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/board/ServiceOrderKanbanCard.tsx)
- [ServiceOrderKanbanColumn.tsx](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/board/ServiceOrderKanbanColumn.tsx)
- [VehicleStatusBadge.tsx](../../../resources/js/Pages/OficinaAuto/Vehicles/_components/VehicleStatusBadge.tsx)

## Charters (lei da tela) — 9

- [AprovacaoPublica.charter.md](../../../resources/js/Pages/OficinaAuto/AprovacaoPublica.charter.md)
- [Board.charter.md](../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.charter.md)
- [Create.charter.md](../../../resources/js/Pages/OficinaAuto/ServiceOrders/Create.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/OficinaAuto/ServiceOrders/Edit.charter.md)
- [Show.charter.md](../../../resources/js/Pages/OficinaAuto/ServiceOrders/Show.charter.md)
- [Create.charter.md](../../../resources/js/Pages/OficinaAuto/Vehicles/Create.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/OficinaAuto/Vehicles/Edit.charter.md)
- [Index.charter.md](../../../resources/js/Pages/OficinaAuto/Vehicles/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/OficinaAuto/Vehicles/Show.charter.md)

## Casos (contrato UC) — 4

- [Board.casos.md](../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.casos.md)
- [Create.casos.md](../../../resources/js/Pages/OficinaAuto/ServiceOrders/Create.casos.md)
- [Edit.casos.md](../../../resources/js/Pages/OficinaAuto/ServiceOrders/Edit.casos.md)
- [Show.casos.md](../../../resources/js/Pages/OficinaAuto/ServiceOrders/Show.casos.md)

## Testes (Pest) — 44

- 44 arquivos em [Modules/OficinaAuto/Tests/Feature/](../../../Modules/OficinaAuto/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 2

- [ServiceOrderPolicy.php](../../../Modules/OficinaAuto/Policies/ServiceOrderPolicy.php)
- [VehiclePolicy.php](../../../Modules/OficinaAuto/Policies/VehiclePolicy.php)

