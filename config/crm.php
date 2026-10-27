<?php

/*
 * Configuração do CRM que depende de ambiente. O config do módulo
 * (Modules/Crm/Config/config.php) é mesclado na mesma chave `crm`; o env() fica
 * aqui porque fora de config/ ele volta nulo com o config em cache.
 */
return [
    // Chamado de garantia com SLA (US-CRM-094). Desligado por padrão.
    'garantia_sla' => [
        'enabled' => (bool) env('CRM_GARANTIA_SLA_ENABLED', false),
        // Prazo quando a empresa não cadastrou política em crm_sla_politicas.
        'prazo_padrao_horas' => (int) env('CRM_GARANTIA_SLA_PRAZO_PADRAO_HORAS', 48),
    ],
];
