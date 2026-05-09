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
     * Valores válidos Sprint 1: 'zapi' | 'meta_cloud' | 'null'
     * Valores válidos Sprint 3: + 'baileys' (driver custom oimpresso — daemon Node CT 100,
     *   ADR 0096 emenda 4: estrutura customizada de atendimento, dor de observabilidade)
     * Valor 'evolution' é PROIBIDO permanente (FormRequest rejeita 422 — bans Wagner +
     *   schema não atende + falta observabilidade).
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

    'baileys' => [
        // Sprint 3 — daemon Node próprio CT 100 (ADR 0096 emenda 4)
        // base_url é per-business (whatsapp_business_configs.baileys_daemon_url);
        // este default só aparece na UI Settings ao cadastrar instance nova.
        'daemon_url_default' => env('WHATSAPP_BAILEYS_DAEMON_URL', 'https://whatsapp-baileys.oimpresso.local'),
        'request_timeout' => env('WHATSAPP_BAILEYS_TIMEOUT', 15),
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
        // drivers que EXIGEM fallback Meta Cloud configurado (gating duro FormRequest).
        // Sprint 3: 'baileys' entra nessa lista junto com 'zapi'.
        'mandatory_for_drivers' => ['zapi', 'baileys'],

        // Lista de business_id que escapam do gate Meta-fallback (ADR 0111 — emenda 5 ao 0096).
        // Per-tenant bypass cirúrgico — preserva Tier 0 multi-tenant em todos outros businesses.
        // LGPD continua exigido; drivers proibidos continuam proibidos.
        // Formato env: lista CSV de IDs inteiros, ex: WHATSAPP_BYPASS_META_FALLBACK_BUSINESS_IDS=1,7
        // Default vazio = gate Tier 0 ativo em todos.
        'bypass_business_ids' => array_values(array_filter(
            array_map('intval', explode(',', (string) env('WHATSAPP_BYPASS_META_FALLBACK_BUSINESS_IDS', ''))),
            fn ($id) => $id > 0,
        )),
    ],

    /*
     * Drivers proibidos (FormRequest rejeita 422 se tentar salvar).
     * Reabrir só via nova ADR explícita Wagner-aceita.
     *
     * Histórico:
     * - 'evolution' — PROIBIDO permanente (ADR 0096 emenda 4): bans em produção Wagner +
     *   schema de banco não atende estrutura customizada + falta de observabilidade
     * - 'whatsapp_web_js' — sobreposição funcional com BaileysDriver custom Sprint 3
     * - 'baileys' SAIU dessa lista em ADR 0096 emenda 4 — autorizado como BaileysDriver
     *   custom Sprint 3 com daemon Node próprio CT 100 (resolve as 3 dores do Evolution)
     */
    'forbidden_drivers' => ['evolution', 'whatsapp_web_js'],

    'queue' => env('WHATSAPP_QUEUE', 'whatsapp'),

    'webhook' => [
        'rate_limit_per_minute' => 600,
    ],

    'centrifugo' => [
        // ADR 0058 — Centrifugo CT 100 substituiu Reverb (Hostinger HTTP-only não roda daemons).
        // Subdomain canônico ADR 0058 = `realtime.oimpresso.com` (NÃO `centrifugo.*` que é só
        // o nome do binary). Deploy step-by-step em
        // `memory/requisitos/Infra/RUNBOOK-deploy-centrifugo.md`.
        // API HTTP: POST {url}/api com header X-API-Key + body {"method":"publish",...}
        'url' => env('WHATSAPP_CENTRIFUGO_URL', 'https://realtime.oimpresso.com'),
        'api_key' => env('WHATSAPP_CENTRIFUGO_API_KEY', null),
        'request_timeout' => env('WHATSAPP_CENTRIFUGO_TIMEOUT', 5),
        'enabled' => env('WHATSAPP_CENTRIFUGO_ENABLED', true),
        // WebSocket URL pro frontend connect (default = mesmo host com /connection/websocket).
        // Centrifugo aceita ws:// em dev, wss:// em prod via Traefik.
        'ws_url' => env('WHATSAPP_CENTRIFUGO_WS_URL', 'wss://realtime.oimpresso.com/connection/websocket'),
        // HMAC secret pra emitir JWT HS256 de subscribe (CentrifugoTokenIssuer).
        // No Centrifugo CT 100 config.json, configurar `client.token.hmac_secret_key` igual a este valor.
        'token_hmac_secret' => env('WHATSAPP_CENTRIFUGO_TOKEN_HMAC_SECRET', null),
        'token_ttl_seconds' => (int) env('WHATSAPP_CENTRIFUGO_TOKEN_TTL', 3600),
    ],

    'bot' => [
        // Sprint 3 — DispatchToJanaBot listener encaminha pro PolicyEngine ADS via decide().
        // Disabled até Sprint 3 ativar ADS Universal (ADR 0096 emenda 4 + ads-route skill).
        'enabled' => env('WHATSAPP_BOT_ENABLED', false),
    ],
];
