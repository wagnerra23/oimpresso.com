<?php

return [
    'name' => 'Copiloto',

    /*
    |--------------------------------------------------------------------------
    | Identificação do módulo na UI / instalador
    |--------------------------------------------------------------------------
    */
    'module_label'       => 'Copiloto',
    'module_description' => 'Copiloto de IA do negócio — chat + metas + monitoramento',
    'module_icon'        => 'fa fa-compass',
    'module_version'     => '0.1',
    'pid'                => null,

    /*
    |--------------------------------------------------------------------------
    | Adapter de IA — verdade canônica ADR 0035
    |--------------------------------------------------------------------------
    | 'auto'             — detecta laravel/ai instalado, fallback OpenAiDirect (legado)
    | 'laravel_ai_sdk'   — força Laravel AI SDK oficial (CANÔNICO, fev/2026)
    | 'openai_direct'    — LEGADO, depende de openai-php/laravel (não instalado)
    */
    'ai_adapter' => env('COPILOTO_AI_ADAPTER', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Modelo default pra OpenAI direto
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'model_chat'         => env('COPILOTO_OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'model_suggestions'  => env('COPILOTO_OPENAI_SUGGEST_MODEL', 'gpt-4o'),
        'max_tokens_chat'    => 2000,
        'max_tokens_suggest' => 4000,
        'temperature'        => 0.7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custo da IA — pricing por modelo + câmbio (US-COPI-070)
    |--------------------------------------------------------------------------
    | Preços em USD por 1k tokens (input/output). Modelo default usado quando
    | o registro de mensagem não identifica o modelo. Câmbio configurável em
    | env (default 5.50 BRL/USD) — pode evoluir pra fonte cotação automática.
    |
    | Referência: https://openai.com/api/pricing/ (snapshot 2026-04-27).
    */
    'ai' => [
        'pricing_default_model' => env('COPILOTO_PRICING_DEFAULT_MODEL', 'gpt-4o-mini'),
        'pricing' => [
            'gpt-4o-mini' => [
                'input'  => 0.00015,  // USD / 1k tokens
                'output' => 0.0006,
            ],
            'gpt-4o' => [
                'input'  => 0.0025,
                'output' => 0.01,
            ],
            'gpt-4-turbo' => [
                'input'  => 0.01,
                'output' => 0.03,
            ],
        ],
        'cambio_brl_usd' => (float) env('COPILOTO_CAMBIO_BRL_USD', 5.50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dry-run (propostas fixtures, sem chamada de API) — útil em dev
    |--------------------------------------------------------------------------
    */
    'dry_run' => env('COPILOTO_AI_DRY_RUN', false),

    /*
    |--------------------------------------------------------------------------
    | Memória (camada C) — verdade canônica ADR 0036
    |--------------------------------------------------------------------------
    | 'auto'         — usa MeilisearchDriver (default)
    | 'meilisearch'  — força Scout + Meilisearch self-hosted (CANÔNICO)
    | 'null'         — fixtures em memória, dev/CI
    | 'mem0_rest'    — sprint 8+ condicional (não implementado ainda)
    */
    'memoria' => [
        'driver' => env('COPILOTO_MEMORIA_DRIVER', 'auto'),
        // Sprint 5 — bridge memória↔chat (ADR 0036)
        'recall_enabled' => env('COPILOTO_MEMORIA_RECALL', true),
        'write_enabled'  => env('COPILOTO_MEMORIA_WRITE', true),
        'meilisearch' => [
            'index'          => env('COPILOTO_MEMORIA_INDEX', 'copiloto_memoria_facts'),
            'top_k_default'  => 5,
            // ADR 0047 / MEM-HOT-1 — defaults batem com o que está deployado em prod
            // (2026-04-28): embedder name = 'openai' (chave JSON do PATCH), ratio 0.7
            // (sweet spot Meilisearch hybrid pra cross-phrasing PT-BR).
            // RRF tuning A/B (0.3 vs 0.7) é uma task futura — MEM-P2-2 / Cycle 02.
            'semantic_ratio' => (float) env('COPILOTO_MEMORIA_SEMANTIC_RATIO', 0.7),
            'embedder'       => env('COPILOTO_MEMORIA_EMBEDDER', 'openai'),
        ],
        // 'mem0_rest' fica reservado pra sprint 8+ (ver triggers em ADR 0036)
    ],

    /*
    |--------------------------------------------------------------------------
    | Apuração
    |--------------------------------------------------------------------------
    */
    'apuracao' => [
        'sql_timeout_seconds'  => 10,
        'http_timeout_seconds' => 15,
        'http_retry_times'     => 3,
        'historico_dias_max'   => 730, // 2 anos; mover pra arquivo frio depois
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache do snapshot de contexto (ContextSnapshotService)
    |--------------------------------------------------------------------------
    */
    'context_cache_ttl_minutes' => 10,

    /*
    |--------------------------------------------------------------------------
    | Alertas
    |--------------------------------------------------------------------------
    */
    'alertas' => [
        'desvio_threshold_default' => 10,   // percentual
        'canais_default'           => ['in_app'], // ['in_app', 'email', 'whatsapp']
        'cadencia_avaliacao'       => 'everyFifteenMinutes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta da plataforma (seed) — ver memory/decisions/0022 e memory/11-metas-negocio.md
    |--------------------------------------------------------------------------
    */
    'meta_plataforma' => [
        'habilitada'   => true,
        'slug'         => 'faturamento_oimpresso_anual',
        'nome'         => 'Faturamento anual oimpresso',
        'valor_alvo'   => 5000000, // R$ 5 milhões
        'unidade'      => 'R$',
    ],
];
