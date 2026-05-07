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
];
