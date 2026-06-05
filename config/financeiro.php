<?php

declare(strict_types=1);

/**
 * Config canônica do módulo Financeiro (raiz `config/financeiro.php`).
 *
 * Por que raiz (e não `Modules/Financeiro/Config/`)?
 *   - `Modules/Financeiro/Config/` (config.php, retention.php) cuida de
 *     concerns INTERNOS do módulo (nome/cor/sidebar, retention LGPD).
 *   - `config/financeiro.php` cuida de INTEGRAÇÕES EXTERNAS expostas via
 *     `config('financeiro.*')`, padrão Laravel — Pluggy é o primeiro consumer.
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093 + ADR 0094):
 *   - `enabled => false` default (Wagner habilita por business no .env)
 *   - Credentials NUNCA no git — só env var
 *   - Webhook signature HMAC obrigatória pra aceitar callback
 *
 * @see Modules\Financeiro\Services\Integrations\PluggyClient
 * @see Modules\Financeiro\Services\Integrations\PluggyBankSyncService
 * @see W27 estado-da-arte 2026-05-17 — Pluggy padrão Open Banking BR
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Pluggy — Open Banking Brasil (estado-da-arte W27)
    |--------------------------------------------------------------------------
    |
    | Pluggy.ai é a plataforma B2B padrão de Open Finance no BR (Itaú,
    | Bradesco, BB, Nubank, C6, Inter via connectors regulados + scraping).
    |
    | Fluxo:
    |   1. Backend autentica (POST /auth com CLIENT_ID + CLIENT_SECRET) → API key 2h
    |   2. Backend cria connect_token (POST /connect_token) — limited 30min
    |   3. Frontend mostra Pluggy Connect widget com token → user conecta banco
    |   4. Pluggy retorna `item_id` (representa conexão user-instituição)
    |   5. Backend lista accounts/transactions e importa pra ExtratoLancamento
    |   6. Webhook (POST /webhooks/pluggy) avisa de syncs delta
    |
    | Custo prod estimado: ~R$ 0,30-1,00/conta/mês (varia connector).
    */
    'pluggy' => [
        'enabled'         => env('PLUGGY_ENABLED', false),
        'client_id'       => env('PLUGGY_CLIENT_ID'),
        'client_secret'   => env('PLUGGY_CLIENT_SECRET'),
        'base_url'        => env('PLUGGY_BASE_URL', 'https://api.pluggy.ai'),
        'webhook_secret'  => env('PLUGGY_WEBHOOK_SECRET'),

        // HTTP timeouts (segundos). Pluggy SLA p95 < 2s pra leitura.
        'timeout'         => (int) env('PLUGGY_TIMEOUT', 15),
        'retry_times'     => (int) env('PLUGGY_RETRY_TIMES', 2),
        'retry_sleep_ms'  => (int) env('PLUGGY_RETRY_SLEEP_MS', 250),

        // Cache TTL da API key (Pluggy expira em 2h; mantemos 100min de folga).
        'api_key_cache_ttl_sec' => (int) env('PLUGGY_API_KEY_CACHE_TTL_SEC', 6000),

        // Sync incremental — janela default em dias pra puxar transactions.
        'sync_window_days' => (int) env('PLUGGY_SYNC_WINDOW_DAYS', 30),

        // Mock mode pra Pest local sem chamar Pluggy real (ZERO custo).
        'force_mock'       => env('PLUGGY_FORCE_MOCK', false),
    ],

];
