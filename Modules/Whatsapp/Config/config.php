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
        // Daemon Node próprio CT 100 (ADR 0096 emenda 4).
        // US-WA-022: daemon_url + api_key são GLOBAIS (server secrets), não
        // per-tenant. Multi-tenancy é via business_uuid no path do webhook +
        // baileys_instance_id auto-gerado (prefix "biz{business_id}-").
        // Tenant só cadastra telefone E.164 na UI Settings.
        'daemon_url' => env('WHATSAPP_BAILEYS_DAEMON_URL', 'https://whatsapp-baileys.oimpresso.local'),
        'api_key' => env('WHATSAPP_BAILEYS_API_KEY'),
        'request_timeout' => (int) env('WHATSAPP_BAILEYS_TIMEOUT', 15),
        'instance_id_prefix' => env('WHATSAPP_BAILEYS_INSTANCE_PREFIX', 'biz'),
        // Rate limit anti-abuse (US-WA-022 §Rate limit) — 3 connect/business/dia
        'connect_rate_limit_per_day' => (int) env('WHATSAPP_BAILEYS_CONNECT_RATE_LIMIT', 3),
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

    /*
     * Caixa Unificada v4 (RUNBOOK-inbox-caixa-unificada-v4.md §4).
     *
     * Filas de atendimento APENAS VISUAIS (sem persistência DB nesta passada).
     * Cada conversation recebe um campo derivado `queue` via heurística no
     * InboxController::convToListArray (tags `financeiro`/`cobranca` → financeiro;
     * resto → comercial). UI filtra client-side.
     *
     * Migration + roteamento automático ficam pra outra passada quando Wagner
     * decidir persistir (RUNBOOK §3 Non-Goals: "NÃO criar migrations").
     */
    'queues' => [
        'comercial' => [
            'label' => 'Comercial',
            'hue' => 220,
            'sla' => '1h',
            // tags que disparam essa fila (heurística OR)
            'trigger_tags' => [],  // fallback default — sem trigger explícito
        ],
        'financeiro' => [
            'label' => 'Financeiro',
            'hue' => 280,
            'sla' => '4h',
            'trigger_tags' => ['financeiro', 'cobranca'],
        ],
    ],
    'default_queue' => 'comercial',

    'webhook' => [
        'rate_limit_per_minute' => 600,
    ],

    /*
     * US-WA-084 — Backpressure protetiva no webhook receiver Baileys.
     *
     * Quando a fila `whatsapp-history` (queue:database) acumula mais do que
     * `queue_max_depth` jobs pendentes, o middleware EnforceWebhookBackpressure
     * retorna 429 com Retry-After. Daemon Node respeita (já tem 429 no
     * RETRYABLE_STATUS) e dropa carga, evitando saturar PHP-FPM.
     *
     * Drop policy: 429 é melhor que 500/timeout porque daemon faz exponential
     * backoff e a msg fica preservada no SQLite local até receiver normalizar.
     *
     * Cleanup stale: jobs presos >`stale_job_max_age_hours` ficam órfãos
     * (worker crashed mid-flight, reserved_at sem tocar há horas). Command
     * `whatsapp:jobs-cleanup-stale` purga + loga.
     */
    'backpressure' => [
        'enabled' => (bool) env('WHATSAPP_BACKPRESSURE_ENABLED', true),
        'queue_max_depth' => (int) env('WHATSAPP_QUEUE_MAX_DEPTH', 2000),
        'queue_name' => env('WHATSAPP_BACKPRESSURE_QUEUE', 'whatsapp-history'),
        'retry_after_seconds' => (int) env('WHATSAPP_BACKPRESSURE_RETRY_AFTER', 30),
        'stale_job_max_age_hours' => (int) env('WHATSAPP_STALE_JOB_MAX_AGE_HOURS', 6),
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

    /*
     * US-WA-VOZ-001 — Customer Memory (perfil persistente do cliente final).
     *
     * Wagner 2026-05-15: "onde vai ficar as memórias? estruture. vai precisar
     * achar o telefone e o cliente no banco de dados do servidor."
     *
     * Schema: `customer_memory` table (1 row per (business_id, customer_external_id)).
     * Listener `TouchCustomerMemoryOnMessage` atualiza last_interaction_at em
     * tempo real; Job RebuildCustomerMemoryJob recompila stats em background
     * quando `last_rebuilt_at < NOW() - rebuild_after_hours`.
     *
     * Default `enabled=true` — Tier 0 sempre ON pra capturar memória mesmo
     * sem IA. Wagner desliga via env só se houver problema operacional.
     *
     * @see Modules/Whatsapp/Entities/CustomerMemory.php
     * @see Modules/Whatsapp/Services/CustomerMemory/CustomerMemoryRebuilder.php
     */
    'customer_memory' => [
        'enabled' => (bool) env('WHATSAPP_CUSTOMER_MEMORY_ENABLED', true),
        // Threshold pra dispatcha rebuild assíncrono (heavy path) no listener.
        // < default 6h pra refrescar várias vezes ao dia sem encher queue.
        'rebuild_after_hours' => (int) env('WHATSAPP_CUSTOMER_MEMORY_REBUILD_AFTER_HOURS', 6),
        // Stale threshold pra cron daily (default 24h).
        'cron_stale_hours' => (int) env('WHATSAPP_CUSTOMER_MEMORY_CRON_STALE_HOURS', 24),
        // Cap de jobs dispatched por business no cron daily (anti-overload).
        'cron_limit_per_business' => (int) env('WHATSAPP_CUSTOMER_MEMORY_CRON_LIMIT', 1000),
    ],

    /*
     * US-WA-072 — Áudio (Whisper transcription) + Mídia (upload outbound).
     *
     * Provider único Whisper nesta fase. Fallback Ollama whisper-local
     * (CT 100 self-host) fica em US separada. OPENAI_API_KEY reusa env
     * já existente (Jana Camada A — laravel/ai SDK, ADR 0035).
     */
    'audio' => [
        'transcription' => [
            'provider' => env('WHATSAPP_AUDIO_TRANSCRIPTION_PROVIDER', 'openai'),
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('WHATSAPP_AUDIO_TRANSCRIPTION_MODEL', 'whisper-1'),
            'endpoint' => env('WHATSAPP_AUDIO_TRANSCRIPTION_ENDPOINT', 'https://api.openai.com/v1/audio/transcriptions'),
            'timeout' => (int) env('WHATSAPP_AUDIO_TRANSCRIPTION_TIMEOUT', 60),
            // Rate limit anti-abuse: 100min/business/dia (~R$1,80/dia max)
            'rate_limit_minutes_per_day' => (int) env('WHATSAPP_AUDIO_RATE_LIMIT_MIN_DAY', 100),
        ],
    ],

    /*
     * US-WA-072 — Upload de mídia (image/audio/document) outbound.
     * Disk default `public` em Hostinger; S3 em US separada. Max 16MB =
     * limite Meta Cloud (Baileys aceita 100MB mas alinhamos no menor pra
     * Tier 0 multi-provider consistency).
     */
    'media' => [
        'disk' => env('WHATSAPP_MEDIA_DISK', 'public'),
        'max_size_bytes' => (int) env('WHATSAPP_MEDIA_MAX_SIZE_BYTES', 16 * 1024 * 1024),
        // URL assinada TTL — `Storage::temporaryUrl()` SOMENTE em S3/GCS.
        // Disk `public` retorna `Storage::url()` direto (sem TTL).
        'signed_url_ttl_seconds' => (int) env('WHATSAPP_MEDIA_SIGNED_TTL', 86400),
    ],

    /*
     * PR-6 CYCLE-07 — CSAT (pesquisa pós-resolução 1-5 estrelas).
     *
     * `enabled=true` dispara mensagem CSAT automática quando atendente marca
     * conv como `resolved` E parser tenta extrair score do próximo inbound.
     * Default true em todos ambientes — Wagner desliga via env por business
     * se cliente reclamar (opt-out simples por enquanto; template per-business
     * + opt-out granular em PR futuro — ver follow-ups no commit).
     */
    'csat' => [
        'enabled' => (bool) env('WHATSAPP_CSAT_ENABLED', true),
    ],

    /*
     * Wagner request 2026-05-14: botão "Importar Histórico" na UI Channels
     * com feature flag por business_id. Default: DESABILITADO (lista vazia).
     * Wagner libera manual via .env quando cliente paga plano alto:
     *
     *   WHATSAPP_HISTORY_IMPORT_ENABLED_BIZ=1,7,42
     *
     * Motivo do gating restritivo:
     * - Re-pareamento custa: cliente desconecta sessão WhatsApp atual
     * - Burst de 10k+ msgs históricas satura PHP-FPM (mesmo com queue)
     * - Anti-ban risk: cada re-pair WhatsApp pode flagar conta nova
     * - Cliente PME free não precisa (só msgs novas chegam normal)
     * - Cliente Enterprise pagante: vale a pena pra populate Inbox histórico
     */
    'history_import' => [
        'enabled_business_ids' => array_values(array_filter(
            array_map('intval', explode(',', (string) env('WHATSAPP_HISTORY_IMPORT_ENABLED_BIZ', ''))),
            fn ($id) => $id > 0,
        )),
    ],

    /*
     * D7 LGPD — Retention policy canônica (Wave 9 governance push).
     *
     * Valores espelhados de memory/requisitos/Whatsapp/COMPLIANCE.md §2.
     * Consumidos pelos 3 comandos artisan retention (§3 do COMPLIANCE,
     * implementação pós-aprovação Wagner):
     *   - whatsapp:redact-old-messages  (body+payload → PII redact após `body_redact_days`)
     *   - whatsapp:purge-old-media       (delete arquivo storage após `media_purge_days`)
     *   - whatsapp:anonymize-inactive-contacts (phone+name → anonymize após `contact_anonymize_months`)
     *
     * Bases legais (LGPD Art. 7º) — execução de contrato (transacional) +
     * consentimento (marketing) + cumprimento obrigação legal (NFe Lei 8.846/94).
     *
     * Override via .env: ajuste fino quando cliente exige retention diferenciada
     * (ex: setor financeiro PCI-DSS exige 7 anos, vestuário Termas pode 90d).
     *
     * @see memory/requisitos/Whatsapp/COMPLIANCE.md
     * @see memory/requisitos/Whatsapp/PII-REDACTION.md
     */
    'retention' => [
        'body_redact_days' => (int) env('WHATSAPP_RETENTION_BODY_DAYS', 180),
        'media_purge_days' => (int) env('WHATSAPP_RETENTION_MEDIA_DAYS', 365),
        'contact_anonymize_months' => (int) env('WHATSAPP_RETENTION_CONTACT_MONTHS', 24),
        // Activity log Spatie (LogsActivity em Channel/Conversation) — 5 anos alinha
        // com NFe SEFAZ Art. 23 Lei 8.846/94 (Brasil exige guarda fiscal 5 anos).
        'activity_log_years' => (int) env('WHATSAPP_RETENTION_ACTIVITY_LOG_YEARS', 5),
    ],
];
