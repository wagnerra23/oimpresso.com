<?php

return [
    'name' => 'Crm',
    'module_version' => '2.1',
    'pid' => 7,

    // US-CRM-096 — formulário público de captura de lead. Nasce desligado.
    'captura_lead_enabled' => (bool) env('CRM_CAPTURA_LEAD_ENABLED', false),
];
