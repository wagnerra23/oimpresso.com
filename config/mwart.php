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

return [
    'repair_index' => [
        'enabled'      => env('MWART_REPAIR_INDEX', false),
        'business_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('MWART_REPAIR_INDEX_BIZ', ''))
        ))),
    ],
];
