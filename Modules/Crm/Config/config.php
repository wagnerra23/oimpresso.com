<?php

return [
    'name' => 'Crm',
    'module_version' => '2.1',
    'pid' => 7,

    // Chamado de garantia com SLA (US-CRM-094). Desligado por padrão.
    'garantia_sla' => [
        'enabled' => (bool) env('CRM_GARANTIA_SLA_ENABLED', false),
        // Prazo quando a empresa não cadastrou política em crm_sla_politicas.
        'prazo_padrao_horas' => (int) env('CRM_GARANTIA_SLA_PRAZO_PADRAO_HORAS', 48),
        // Quantas horas antes do vencimento o responsável é avisado (uma vez por chamado).
        'alerta_antes_horas' => (int) env('CRM_GARANTIA_SLA_ALERTA_ANTES_HORAS', 4),
    ],
];
