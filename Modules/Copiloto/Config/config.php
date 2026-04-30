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
    | MCP server (ADR 0053 / MEM-MCP-1)
    |--------------------------------------------------------------------------
    | Configuração do MCP server da empresa — governança de memória
    | compartilhada com auth, RBAC, audit log e quotas.
    */
    'mcp' => [
        // Token shared-secret entre GitHub webhook e endpoint sync-memory.
        // Setar em .env: COPILOTO_MCP_SYNC_TOKEN=...
        'sync_webhook_token' => env('COPILOTO_MCP_SYNC_TOKEN'),

        // Quanto tempo manter audit log antes de purgar (LGPD: mínimo 1 ano)
        'audit_retention_days' => env('COPILOTO_MCP_AUDIT_RETENTION_DAYS', 365),

        // Pricing pra calcular custo_brl em mcp_audit_log (snapshot abr/2026)
        'pricing_per_million' => [
            'opus'   => ['input' => 15.00, 'output' => 75.00, 'cache_read' => 1.50,  'cache_write' => 18.75],
            'sonnet' => ['input' =>  3.00, 'output' => 15.00, 'cache_read' => 0.30,  'cache_write' =>  3.75],
            'haiku'  => ['input' =>  1.00, 'output' =>  5.00, 'cache_read' => 0.10,  'cache_write' =>  1.25],
        ],
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

    /*
    |--------------------------------------------------------------------------
    | MEM-MEM-WIRE Phase 2 — HyDE Query Expansion (ADR 0054 / Sprint 10)
    |--------------------------------------------------------------------------
    | Gera "documento hipotético" que responderia a pergunta do user e usa
    | esse doc (não a query original) pra busca semântica — bridge phrasing gap.
    | Ganho esperado: +15% Recall@10 (literatura 2026).
    | Custo: ~80 tokens gpt-4o-mini por expand (cache 1h).
    |
    | Desabilitado por default — habilitar via env COPILOTO_HYDE_ENABLED=true.
    */
    /*
    |--------------------------------------------------------------------------
    | MEM-FASE6 — Hit tracking + core_memory promotion
    |--------------------------------------------------------------------------
    | hits_count >= threshold → fato promovido a core_memory (injetado direto
    | no system prompt sem passar pelo recall). Padrão 5 hits.
    */
    'hits' => [
        'core_memory_threshold' => (int) env('COPILOTO_HITS_THRESHOLD', 5),
    ],

    'hyde' => [
        'enabled' => env('COPILOTO_HYDE_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | MEM-MEM-WIRE Phase 2 — LLM Reranker (ADR 0054 / Sprint 10)
    |--------------------------------------------------------------------------
    | Após retrieval BM25/vector, pede pra gpt-4o-mini reordenar candidatos
    | por relevância à query. Substitui cross-encoder (precisaria GPU).
    | Ganho esperado: +5pp recall@5 (literatura RAG 2026).
    | Custo: ~150 tokens por rerank (cache 5min).
    |
    | Desabilitado por default — habilitar via env COPILOTO_RERANKER_ENABLED=true.
    */
    'reranker' => [
        'enabled' => env('COPILOTO_RERANKER_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | MEM-MEM-WIRE Phase 2 — Negative Cache (ADR 0054)
    |--------------------------------------------------------------------------
    | Queries que retornam 0 resultados são marcadas por TTL segundos.
    | Chamadas subsequentes da mesma query retornam [] sem hit Scout ou LLM.
    |
    | Desabilitado por default — habilitar via env COPILOTO_NEGATIVE_CACHE_ENABLED=true.
    */
    'negative_cache' => [
        'enabled'      => env('COPILOTO_NEGATIVE_CACHE_ENABLED', false),
        'ttl_segundos' => (int) env('COPILOTO_NEGATIVE_CACHE_TTL', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | MEM-CACHE-1 — Cache semântico de respostas LLM (ADR 0037 Sprint 8)
    |--------------------------------------------------------------------------
    | Antes de chamar OpenAI: busca query similar no cache. Hit → retorna
    | resposta cacheada (zero token cost). Miss → chama LLM + grava resposta.
    |
    | Estado-da-arte 2026: -68.8% tokens em produção.
    */
    'cache' => [
        'enabled'             => env('COPILOTO_CACHE_ENABLED', true),
        'ttl_segundos'        => env('COPILOTO_CACHE_TTL', 3600),       // 1h default
        'threshold_jaccard'   => env('COPILOTO_CACHE_THRESHOLD', 0.85), // similaridade mínima
    ],

    /*
    |--------------------------------------------------------------------------
    | MEM-S8-2 — ConversationSummarizer (ADR 0037 Sprint 8)
    |--------------------------------------------------------------------------
    | Comprime histórico de conversas longas (>15 turnos): resume msgs antigas
    | em ~200 tokens via LLM, mantém últimas 8 msgs íntegras. -40-70% tokens
    | hot window em conversas longas.
    */
    'summarizer' => [
        'enabled'           => env('COPILOTO_SUMMARIZER_ENABLED', true),
        'threshold_turnos'  => env('COPILOTO_SUMMARIZER_THRESHOLD', 15),
        'msgs_recentes'     => env('COPILOTO_SUMMARIZER_RECENT', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | MEM-MEM-MCP-1 — MCP server como fonte única de memória (ADR 0056)
    |--------------------------------------------------------------------------
    | Copiloto chat web (Laravel) consulta MCP server pra recall de memória.
    | Mesma camada usada pelo Claude Code do Wagner — governança unificada.
    |
    | Pra ativar: COPILOTO_MEMORIA_DRIVER=mcp + COPILOTO_MCP_SYSTEM_TOKEN=mcp_xxx
    | Token system gerado via /copiloto/admin/team (1× pra Wagner).
    |
    | Fallback: se MCP indisponível, degrada pra MeilisearchDriver direto
    | (configurado no provider). Sem indisponibilidade do chat.
    */
    'mcp' => [
        'url'              => env('COPILOTO_MCP_URL', 'https://mcp.oimpresso.com/api/mcp'),
        'system_token'     => env('COPILOTO_MCP_SYSTEM_TOKEN', ''),
        'timeout_seconds'  => env('COPILOTO_MCP_TIMEOUT', 5),
    ],
];
