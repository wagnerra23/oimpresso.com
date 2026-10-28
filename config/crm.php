<?php

declare(strict_types=1);

/**
 * Config do módulo Crm lida do `.env` (raiz `config/crm.php`).
 *
 * Por que raiz: `env()` só é legítimo em `config/` — fora daqui o Larastan barra
 * (`larastan.noEnvCallsOutsideOfConfig`, retorna null com `config:cache`). O
 * `Modules/Crm/Config/config.php` guarda os defaults internos do módulo e é
 * mesclado por baixo deste arquivo (`mergeConfigFrom`: as chaves daqui vencem).
 */
return [
    /*
     * Segmentação RFM (US-CRM-098). Desligada por padrão: ligada, o cálculo roda
     * todo dia às 04:30 (`crm:rfm-calcular`).
     */
    'rfm' => [
        'enabled' => (bool) env('CRM_RFM_ENABLED', false),
    ],
];
