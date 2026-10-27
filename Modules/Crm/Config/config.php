<?php

return [
    'name' => 'Crm',
    'module_version' => '2.1',
    'pid' => 7,

    /*
     * Cashback por regra (US-CRM-093). Desligado por padrão: com a flag ligada, a venda paga
     * de cliente cadastrado gera crédito conforme a regra da empresa (crm_cashback_regras).
     */
    'cashback' => [
        'enabled' => (bool) env('CRM_CASHBACK_ENABLED', false),
    ],
];
