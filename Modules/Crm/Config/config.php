<?php

return [
    'name' => 'Crm',
    'module_version' => '2.1',
    'pid' => 7,

    // US-CRM-097 — pesquisa NPS (0-10) pós-venda e pós-OS. Nasce desligada.
    'nps_enabled' => (bool) env('CRM_NPS_ENABLED', false),
];
