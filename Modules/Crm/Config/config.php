<?php

return [
    'name' => 'Crm',
    'module_version' => '2.1',
    'pid' => 7,

    /*
     * Segmentação RFM (US-CRM-098). Desligada por padrão: ligada, o cálculo roda todo dia às 04:30.
     * Sem `env()` aqui (Larastan `noEnvCallsOutsideOfConfig`): o liga/desliga pelo `.env`
     * (`CRM_RFM_ENABLED`) mora em `config/crm.php`, que é mesclado por cima deste.
     */
    'rfm' => [
        'enabled' => false,
    ],
];
