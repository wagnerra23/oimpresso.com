<?php

/*
|--------------------------------------------------------------------------
| MWART (Module Web App React Transition)
|--------------------------------------------------------------------------
|
| Cada entrada define uma rota Blade legacy que pode renderizar Inertia/React
| no lugar do Blade quando a flag `enabled` for true. `business_ids` permite
| rollout gradual: vazio = todos os businesses; lista = só os listados.
|
| Padrão de uso no controller:
|
|   if (config('mwart.repair_index.enabled')
|       && (empty(config('mwart.repair_index.business_ids'))
|           || in_array($business_id, config('mwart.repair_index.business_ids'), true))) {
|       return Inertia::render('Repair/Index', $data);
|   }
|   return view('repair::repair.index')->with($data);
|
| Ver `memory/sprints/s2-os-listagem/01-adr-mwart-contract.md` (ADR MWART-0001).
*/

/**
 * Helper inline pra parsear lista de business_ids da env.
 * Vazia = todos liberados.
 */
$parseBizIds = static function (string $envVar): array {
    return array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env($envVar, ''))
    )));
};

return [
    // Sprint 2 — Listagem OS (Repair index) — entregue PR #100
    'repair_index' => [
        'enabled'      => env('MWART_REPAIR_INDEX', false),
        'business_ids' => $parseBizIds('MWART_REPAIR_INDEX_BIZ'),
    ],

    // Sprint 2.5 — Replicar MWART nas 4 telas Repair restantes
    // (Wagner aprovou 2026-05-06 paralelo a S3)

    // Tela 1/4 — Status CRUD (RepairStatusController@index)
    'repair_status_index' => [
        'enabled'      => env('MWART_REPAIR_STATUS_INDEX', false),
        'business_ids' => $parseBizIds('MWART_REPAIR_STATUS_INDEX_BIZ'),
    ],

    // Tela 2/4 — Device Models CRUD (DeviceModelController@index)
    'repair_device_models_index' => [
        'enabled'      => env('MWART_REPAIR_DEVICE_MODELS_INDEX', false),
        'business_ids' => $parseBizIds('MWART_REPAIR_DEVICE_MODELS_INDEX_BIZ'),
    ],

    // Blade T1 Migration C (2026-05-17) — Device Models create + edit
    // Página dedicada substitui modal Blade legacy. Coexiste via flag opt-in.
    'repair_device_models_create' => [
        'enabled'      => env('MWART_REPAIR_DEVICE_MODELS_CREATE', false),
        'business_ids' => $parseBizIds('MWART_REPAIR_DEVICE_MODELS_CREATE_BIZ'),
    ],

    'repair_device_models_edit' => [
        'enabled'      => env('MWART_REPAIR_DEVICE_MODELS_EDIT', false),
        'business_ids' => $parseBizIds('MWART_REPAIR_DEVICE_MODELS_EDIT_BIZ'),
    ],

    // Tela 3/4 — Dashboard Repair (DashboardController@index)
    'repair_dashboard_index' => [
        'enabled'      => env('MWART_REPAIR_DASHBOARD_INDEX', false),
        'business_ids' => $parseBizIds('MWART_REPAIR_DASHBOARD_INDEX_BIZ'),
    ],

    // Tela 4/4 — Job Sheet (JobSheetController@index)
    'repair_job_sheet_index' => [
        'enabled'      => env('MWART_REPAIR_JOB_SHEET_INDEX', false),
        'business_ids' => $parseBizIds('MWART_REPAIR_JOB_SHEET_INDEX_BIZ'),
    ],

    // Wave 3 B6 Repair — 2026-05-15 (MWART massiva): show/edit/create/add-parts
    // + Repair/Show (venda-de-reparo). Cliente piloto canary biz=1 (Wagner WR2).
    // ROTA LIVRE (biz=4) NÃO usa Repair — sem ROI canary lá.

    'repair_job_sheet_show' => [
        'enabled'      => env('MWART_REPAIR_JOB_SHEET_SHOW', false),
        'business_ids' => $parseBizIds('MWART_REPAIR_JOB_SHEET_SHOW_BIZ'),
    ],

    'repair_job_sheet_edit' => [
        'enabled'      => env('MWART_REPAIR_JOB_SHEET_EDIT', false),
        'business_ids' => $parseBizIds('MWART_REPAIR_JOB_SHEET_EDIT_BIZ'),
    ],

    'repair_job_sheet_create' => [
        'enabled'      => env('MWART_REPAIR_JOB_SHEET_CREATE', false),
        'business_ids' => $parseBizIds('MWART_REPAIR_JOB_SHEET_CREATE_BIZ'),
    ],

    'repair_job_sheet_add_parts' => [
        'enabled'      => env('MWART_REPAIR_JOB_SHEET_ADD_PARTS', false),
        'business_ids' => $parseBizIds('MWART_REPAIR_JOB_SHEET_ADD_PARTS_BIZ'),
    ],

    // Wave 3 B6 — Repair/Show (venda-de-reparo, Transaction sub_type=repair)
    'repair_show' => [
        'enabled'      => env('MWART_REPAIR_SHOW', false),
        'business_ids' => $parseBizIds('MWART_REPAIR_SHOW_BIZ'),
    ],

    // Flag pra ativar FsmActionPanel Sells na tela Repair/Show
    // (opcional — sells FSM já LIVE biz=1 desde 2026-05-12 — ADR 0143)
    'repair_show_fsm_panel' => [
        'enabled'      => env('MWART_REPAIR_SHOW_FSM_PANEL', false),
    ],

    // Wave 1 B3 Cliente — 2026-05-15 (W1-B3 retry) — 7 telas Contact/Cliente
    // Default OFF · Rollout canary biz=1 (Wagner WR2 SC); biz=4 (ROTA LIVRE) só após canary.

    'cliente_index' => [
        'enabled'      => env('MWART_CLIENTE_INDEX', false),
        'business_ids' => $parseBizIds('MWART_CLIENTE_INDEX_BIZ'),
    ],

    'cliente_create' => [
        'enabled'      => env('MWART_CLIENTE_CREATE', false),
        'business_ids' => $parseBizIds('MWART_CLIENTE_CREATE_BIZ'),
    ],

    'cliente_show' => [
        'enabled'      => env('MWART_CLIENTE_SHOW', false),
        'business_ids' => $parseBizIds('MWART_CLIENTE_SHOW_BIZ'),
    ],

    'cliente_edit' => [
        'enabled'      => env('MWART_CLIENTE_EDIT', false),
        'business_ids' => $parseBizIds('MWART_CLIENTE_EDIT_BIZ'),
    ],

    'cliente_import' => [
        'enabled'      => env('MWART_CLIENTE_IMPORT', false),
        'business_ids' => $parseBizIds('MWART_CLIENTE_IMPORT_BIZ'),
    ],

    'cliente_ledger' => [
        'enabled'      => env('MWART_CLIENTE_LEDGER', false),
        'business_ids' => $parseBizIds('MWART_CLIENTE_LEDGER_BIZ'),
    ],

    'cliente_map' => [
        'enabled'      => env('MWART_CLIENTE_MAP', false),
        'business_ids' => $parseBizIds('MWART_CLIENTE_MAP_BIZ'),
    ],
];
