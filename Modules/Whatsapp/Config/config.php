<?php

/**
 * Configuração do módulo Whatsapp.
 *
 * Decisão arquitetural mãe: ADR 0096 (memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)
 * Espelha o que está em memory/requisitos/Whatsapp/ARCHITECTURE.md §10.
 */

return [
    'name' => 'Whatsapp',

    /*
     * Driver default global (pode ser sobrescrito per-business via whatsapp_business_configs.driver).
     *
     * Valores válidos: 'zapi' | 'meta_cloud' | 'null'
     * Valor 'evolution' é PROIBIDO Tier 0 (FormRequest rejeita 422).
     */
    'default_driver' => env('WHATSAPP_DEFAULT_DRIVER', 'zapi'),

    'zapi' => [
        'base_url' => env('WHATSAPP_ZAPI_BASE_URL', 'https://api.z-api.io'),
        'request_timeout' => env('WHATSAPP_ZAPI_TIMEOUT', 15),
    ],

    'meta' => [
        'api_version' => env('WHATSAPP_META_API_VERSION', 'v21.0'),
        'base_url' => env('WHATSAPP_META_BASE_URL', 'https://graph.facebook.com'),
        'request_timeout' => env('WHATSAPP_META_TIMEOUT', 10),
    ],

    'health_check' => [
        'interval_seconds' => env('WHATSAPP_HEALTH_INTERVAL', 21600), // 6h
        'consecutive_failures_to_degrade' => 5,
        'consecutive_failures_to_disconnect' => 10,
        'cross_tenant_ban_alarm_threshold' => 3, // 3 businesses banidos em 24h = alarme Wagner
    ],

    'fallback' => [
        'enabled' => env('WHATSAPP_FALLBACK_ENABLED', true),
        'auto_switch_after_status' => 'degraded', // healthy|degraded|disconnected|banned
        'mandatory_for_drivers' => ['zapi'], // drivers que EXIGEM fallback configurado
    ],

    /*
     * Drivers proibidos (FormRequest rejeita 422 se tentar salvar).
     * Reabrir só via nova ADR explícita Wagner-aceita.
     */
    'forbidden_drivers' => ['evolution', 'baileys', 'whatsapp_web_js'],

    'queue' => env('WHATSAPP_QUEUE', 'whatsapp'),

    'webhook' => [
        'rate_limit_per_minute' => 600,
    ],
];
