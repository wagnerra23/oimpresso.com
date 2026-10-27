<?php

return [
    'name' => 'Crm',
    'module_version' => '2.1',
    'pid' => 7,

    /*
     * Segmentação RFM (US-CRM-098). Desligada por padrão: ligada, o cálculo roda todo dia às 04:30.
     */
    'rfm' => [
        'enabled' => (bool) env('CRM_RFM_ENABLED', false),
    ],
];
