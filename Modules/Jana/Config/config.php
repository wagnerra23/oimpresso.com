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
        // Default gpt-4o-mini: único modelo a que o projeto OpenAI atual tem acesso
        // (gpt-4o → 403 "does not have access" — mesma razão de clarify/advisor/PrUiJudgeAgent).
        // Subir via env COPILOTO_OPENAI_SUGGEST_MODEL quando houver acesso frontier (ou Sonnet) — #2270.
        'model_suggestions'  => env('COPILOTO_OPENAI_SUGGEST_MODEL', 'gpt-4o-mini'),
        'max_tokens_chat'    => 2000,
        'max_tokens_suggest' => 4000,
        'temperature'        => 0.7,
    ],

    /*
    |--------------------------------------------------------------------------
    | PR UI Judge — self-consistency (robustez do juiz · 2026-06-23)
    |--------------------------------------------------------------------------
    | samples       — quantas amostras do PrUiJudgeAgent por PR. A mediana de N
    |                 mata o single-shot que alucina "ok". 3-5 (pesquisa VLM-judge).
    |                 Custo e latência crescem ×N (gpt-4o-mini ~$0.002/amostra).
    | abstain_below — confiança geral (0-1) abaixo da qual o juiz ABSTÉM: rebaixa
    |                 um "approve" pra "comment" (zona cinza · defer humano). Wagner
    |                 calibra contra a série de 4 semanas (EVAL_PROTOCOL Onda 2).
    */
    'ui_judge' => [
        // Hardcoded (não env): a regra larastan noEnvCallsOutsideOfConfig conta as
        // chamadas env() deste arquivo num baseline fixo — knobs de tuning de um juiz
        // de CI não valem furar esse teto. Wagner calibra editando aqui + config:clear.
        'samples'       => 3,
        'abstain_below' => 0.6,
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
    |
    | ⚠️ Esta é a ÚNICA chave de pricing canônica: `copiloto.ai.pricing.*`.
    | NÃO existe `copiloto.openai.pricing` — consumidores (ex.: McpAuthMiddleware
    | via estimarCustoBrl()) DEVEM ler daqui. Unidade = USD por 1k tokens.
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
            'index'          => env('COPILOTO_MEMORIA_INDEX', 'jana_memoria_facts'),
            'top_k_default'  => 5,
            // Sprint 9b (US-COPI-083, 2026-05-04) — qwen3-embedding:0.6b + stopwords PT-BR
            // + ratio=0.6 venceu eval matrix:
            //   ratio=0.4 → 0.637 RAGAS
            //   ratio=0.5 → 0.642 RAGAS
            //   ratio=0.6 → 0.692 RAGAS ← vencedor
            //   ratio=0.0 (MySQL FT bypass) → 0.700 RAGAS (comparativo)
            // qwen3 PT-BR cosine ~0.55 (vs nomic ~0.97 uniforme). Infra pronta pra
            // ganho real quando reranker (US-COPI-087) entrar.
            'semantic_ratio' => (float) env('COPILOTO_MEMORIA_SEMANTIC_RATIO', 0.6),
            'embedder'       => env('COPILOTO_MEMORIA_EMBEDDER', 'qwen3_local'),
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
        // === MCP Client (ADR 0056) — Copiloto/Laravel chama mcp.oimpresso.com ===
        'url'              => env('COPILOTO_MCP_URL', 'https://mcp.oimpresso.com/api/mcp'),
        'system_token'     => env('COPILOTO_MCP_SYSTEM_TOKEN', ''),
        'timeout_seconds'  => env('COPILOTO_MCP_TIMEOUT', 5),

        // === Webhook GitHub → endpoint sync-memory (TeamMcp/SyncMemoryWebhookController) ===
        // Token shared-secret entre GitHub webhook e endpoint sync-memory.
        // Setar em .env: COPILOTO_MCP_SYNC_TOKEN=...
        'sync_webhook_token' => env('COPILOTO_MCP_SYNC_TOKEN'),

        // === Drift sentinel (ADR 0256) — token dedicado do endpoint /api/mcp/version ===
        // A sentinela externa (mcp-drift-sentinel.yml) lê o commit servido com este token.
        // SEM user/RBAC: se vazar, só revela o SHA. Setar em .env: MCP_DRIFT_TOKEN=...
        'drift_token' => env('MCP_DRIFT_TOKEN'),

        // === Audit log governança ===
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
        'valor_alvo'   => 5000000, // R$ [redacted Tier 0] milhões
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
    /*
    |--------------------------------------------------------------------------
    | Reranker canônico (GAP-A — AUDITORIA 2026-05-13 §5 G3)
    |--------------------------------------------------------------------------
    | Driver options:
    |   'rrf'  — Reciprocal Rank Fusion (default MVP, zero custo, ~1ms latência)
    |   'llm'  — LLM-as-judge gpt-4o-mini (Sprint 10 — +5pp recall, ~200ms, R$ [redacted Tier 0]/rerank)
    |   'bge'  — Cross-encoder BGE-v2-m3 self-host CT 100 (Onda 4 R1 P0 — +6pp NDCG@10,
    |            100-300ms CPU, zero custo cloud, fallback RRF se HTTP falha).
    |            Pré-req: container `bge-reranker` no CT 100 — ver
    |            memory/requisitos/Infra/RUNBOOK-bge-reranker-ct100.md
    |   'null' — passthrough top-K (debug / disable)
    |
    | Compat: env legado COPILOTO_RERANKER_ENABLED ainda funciona (true=usa driver, false=null).
    | Novo env JANA_RERANKER_ENABLED + JANA_RERANKER_DRIVER. Default = habilitado RRF.
    */
    /*
    |--------------------------------------------------------------------------
    | MCP search tools — pipeline bom vs FULLTEXT (gap #2)
    |--------------------------------------------------------------------------
    | SPEC-retrieval-tools-mcp-unificado. As tools MCP de busca usam FULLTEXT; o
    | pipeline estado-da-arte (hybrid+HyDE+RRF+decay+Peso Real+rerank) só serve o chat.
    |
    | memoria_pipeline: liga a tool memoria-search a usar MeilisearchDriver::buscarBusiness
    | (BUSINESS-scoped — memória da empresa, sem user_id). DEFAULT OFF = FULLTEXT atual
    | byte-a-byte. Fallback gracioso pro FULLTEXT em erro/vazio/driver-incompatível.
    | NÃO ligar default sem validar recall@5 com golden set (US-RET-003).
    |
    | Nota: decisions-search/kb-answer (corpus MCP global mcp_memory_documents) NÃO entram
    | aqui ainda — dependem de verificar o embedder do índice no CT 100 (US-RET-001).
    */
    'mcp_search' => [
        // memoria-search → jana_memoria_facts (corpus Jana, BUSINESS-scoped). US-RET-002.
        'memoria_pipeline' => (bool) env('JANA_MCP_SEARCH_PIPELINE_MEMORIA', false),
        // decisions-search + kb-answer → mcp_memory_documents (corpus MCP GLOBAL, sem
        // filtro tenant). US-RET-001. Embedder qwen3_local + filterable status/type/module
        // verificados no índice live (CT 100). Default OFF; só caminho ativo (não archived).
        'docs_pipeline' => (bool) env('JANA_MCP_SEARCH_PIPELINE_DOCS', false),
        // Embedder do índice mcp_memory_documents (qwen3_local OU nomic_local — ambos
        // existem no índice). Separado da config do chat (que resolve 'openai', inexistente
        // neste índice). Verificado live CT 100 2026-05-29.
        'docs_embedder' => env('JANA_MCP_DOCS_EMBEDDER', 'qwen3_local'),
        // Instrução de query pro embedder instruction-aware (qwen3) — ADR 0322. O qwen3
        // embedda query ASSIMÉTRICA (com prefixo) vs documento (sem); mandar a query raw
        // inverte a similaridade (causa-raiz medida da ADR 0312). buscarHybrid pré-computa
        // o embedding de `instrução + query` no Ollama e envia como `vector` — o `q` segue
        // raw pro lado lexical. String vazia desliga o prefixo (hybrid volta ao q raw).
        // Config-as-code SEM env() de propósito (adendo "vira máquina" 2026-07-04):
        // mudar a instrução = PR medido contra o golden set, nunca ajuste manual de .env.
        'docs_query_instruction' => "Instruct: Given a search query in Portuguese, retrieve the most relevant architecture decision record or governance document.\nQuery: ",
    ],

    /*
    |--------------------------------------------------------------------------
    | Meilisearch index settings (embedders) — CONFIG-AS-CODE
    |--------------------------------------------------------------------------
    | Codifica o que antes era setado MANUAL via curl (Sprint 9b 2026-05-04) e
    | SE PERDEU (jana_memoria_facts voltou a embedders {} → recall do chat degradou).
    | Aplicado idempotente por `jana:meilisearch-setup`. Embedder ollama qwen3_local
    | (qwen3-embedding:0.6b, 1024d) — venceu nomic (inútil PT-BR) no eval Sprint 9.
    | URL interna CT 100. Ver INFRA-ACESSO-CANON + RUNBOOK-ragas-canary.
    */
    'meilisearch_indexes' => [
        'jana_memoria_facts' => [
            'embedders' => [
                'qwen3_local' => [
                    'source'                  => 'ollama',
                    'model'                   => env('JANA_OLLAMA_EMBED_MODEL', 'qwen3-embedding:0.6b'),
                    'dimensions'              => 1024,
                    'documentTemplate'        => '{{doc.fato}}',
                    'documentTemplateMaxBytes' => 400,
                    'url'                     => env('JANA_OLLAMA_EMBED_URL', 'http://ollama-embedder:11434/api/embeddings'),
                ],
            ],
            'filterableAttributes' => ['business_id', 'user_id', 'valid_until'],
        ],
        'mcp_memory_documents' => [
            'embedders' => [
                'qwen3_local' => [
                    'source'                  => 'ollama',
                    'model'                   => env('JANA_OLLAMA_EMBED_MODEL', 'qwen3-embedding:0.6b'),
                    'dimensions'              => 1024,
                    'documentTemplate'        => '{{doc.title}}. {{doc.content_excerpt}}',
                    'documentTemplateMaxBytes' => 400,
                    'url'                     => env('JANA_OLLAMA_EMBED_URL', 'http://ollama-embedder:11434/api/embeddings'),
                ],
            ],
            // corpus MCP é GLOBAL — NÃO inclui business_id (ADR 0093 não se aplica: docs de programação)
            'filterableAttributes' => ['status', 'type', 'module', 'slug'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconcilers — jana:reconcile loop único (ADR 0237)
    |--------------------------------------------------------------------------
    | Cada Reconciler garante sincronia de UMA faceta (git == índice == MCP ==
    | settings == deploy). `jana:reconcile` itera esta lista — padrão idêntico ao
    | `governance.drift_checkers` do ADR 0216. O orquestrador usa class_exists-guard
    | (tolera reconciler ainda-não-criado). Filtra via `--only=index,settings`.
    | Este config é merged como `copiloto.*` (JanaServiceProvider) → o orquestrador
    | lê via config('copiloto.reconcilers').
    */
    'reconcilers' => [
        \Modules\Jana\Services\Reconcile\Reconcilers\IndexReconciler::class,    // cura poluição dos índices (ADR 0237 P0)
        \Modules\Jana\Services\Reconcile\Reconcilers\SettingsReconciler::class, // embedder Meilisearch (perdido 2×)
        \Modules\Jana\Services\Reconcile\Reconcilers\ContentReconciler::class,  // git→DB mcp_memory_documents
        \Modules\Jana\Services\Reconcile\Reconcilers\DeployReconciler::class,   // SHA deployado vs main
        \Modules\Jana\Services\Reconcile\Reconcilers\EvalReconciler::class,     // RAGAS pass-rate threshold
        \Modules\Jana\Services\Reconcile\Reconcilers\TasksReconciler::class,    // detect-only: doing órfã / done sem acceptance_ref / blocked_by resolvido (ADR 0237 + 0278)
    ],

    'reranker' => [
        'enabled' => env('JANA_RERANKER_ENABLED', env('COPILOTO_RERANKER_ENABLED', true)),
        'driver'  => env('JANA_RERANKER_DRIVER', env('COPILOTO_RERANKER_DRIVER', 'rrf')),

        // BGE-v2-m3 self-host (CT 100). Endpoint default = LAN Tailscale hostname.
        // Pra prod via Traefik: JANA_RERANKER_BGE_ENDPOINT=https://bge-reranker.ct100.oimpresso.com/rerank
        'bge' => [
            'endpoint' => env('JANA_RERANKER_BGE_ENDPOINT', 'http://bge-reranker.ct100:8080/rerank'),
            'timeout'  => (int) env('JANA_RERANKER_BGE_TIMEOUT', 5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | GAP D7 #2 — Freshness Pipeline (auditoria memoria-senior 2026-05-15)
    |--------------------------------------------------------------------------
    | Pipeline ativo de frescura sobre `mcp_memory_documents`. Complementa o
    | time_decay (query-time score) com observability de pipeline (index-time
    | health). 4 níveis: FRESH (<=1d) / WARM (<7d) / STALE (<30d) / CRITICAL (>=30d).
    |
    | `jana:freshness-check` (cron daily 04:30 BRT em app/Console/Kernel.php)
    | classifica + detecta drift DB↔git + alerta CRITICAL (idempotente por dia)
    | + dispatcha ReindexarDocumentoJob pra stale/drift (max 50/execução).
    |
    | Ajustar thresholds via env JANA_FRESHNESS_THRESHOLD_*. Desabilitar todo
    | pipeline via JANA_FRESHNESS_PIPELINE=false.
    |
    | Métricas alvo (alvo 2026 dossier auditoria): >=80% FRESH+WARM combinados.
    */
    'freshness' => [
        'enabled'      => env('JANA_FRESHNESS_PIPELINE', true),
        'auto_reindex' => env('JANA_FRESHNESS_AUTO_REINDEX', false),
        // Semântica dos cutoffs (alinhada com StalenessDetectorService::staleness):
        //   FRESH    → age <= fresh (1d)
        //   WARM     → fresh < age < warm (1d–7d)
        //   STALE    → warm <= age < stale (7d–30d)   ← faixa warning
        //   CRITICAL → age >= critical (>=30d)         ← alerta mcp_alertas_eventos
        // `detectStale()` usa `warm` como cutoff (pega tudo >= 7d, inclui CRITICAL);
        // `detectCritical()` usa `critical` (pega só >= 30d). A faixa 7-30d isolada
        // = detectStale − detectCritical. Chave `stale` mantida pro método
        // `staleness()` (fronteira STALE→CRITICAL). BUG-2 fix 2026-05-29.
        'thresholds_days' => [
            'fresh'    => (int) env('JANA_FRESHNESS_THRESHOLD_FRESH', 1),
            'warm'     => (int) env('JANA_FRESHNESS_THRESHOLD_WARM', 7),
            'stale'    => (int) env('JANA_FRESHNESS_THRESHOLD_STALE', 30),
            'critical' => (int) env('JANA_FRESHNESS_THRESHOLD_CRITICAL', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | K1 — Time-decay weighting recall (Onda 5 — ADR 0061 + dossier 2026-05-13)
    |--------------------------------------------------------------------------
    | Half-life decay aplicado pós-recall, pré-rerank em MeilisearchDriver::buscar.
    | Fórmula canônica (TDS Temporal Layer 2026):
    |   score_final = score_base × (
    |       (1 - temporal_weight)
    |     + temporal_weight × 0.5^(age_days / half_life_days)
    |   ) × status_multiplier
    |
    | half_life_days por doc_type (default ADR=365, SPEC=180, session=30, handoff=14).
    | status_multipliers per lifecycle (accepted=1.2, proposed=1.0, historical=0.5,
    | superseded=0.3) — Wagner aprovou 2026-05-13.
    |
    | Per-doc-type/status: lê via metadata['doc_type'] e metadata['status'] do
    | MemoriaFato. Doc sem date / sem metadata → score base preservado (fallback).
    |
    | Desabilitar via JANA_TIME_DECAY_ENABLED=false (back-compat).
    */
    'time_decay' => [
        'enabled'         => env('JANA_TIME_DECAY_ENABLED', true),
        'temporal_weight' => (float) env('JANA_TIME_DECAY_TEMPORAL_WEIGHT', 0.4),

        // Meia-vida em dias por tipo de doc (sem entrada → default 180d).
        'half_life' => [
            'adr'     => (int) env('JANA_TIME_DECAY_HL_ADR', 365),
            'spec'    => (int) env('JANA_TIME_DECAY_HL_SPEC', 180),
            'session' => (int) env('JANA_TIME_DECAY_HL_SESSION', 30),
            'handoff' => (int) env('JANA_TIME_DECAY_HL_HANDOFF', 14),
            'default' => (int) env('JANA_TIME_DECAY_HL_DEFAULT', 180),
        ],

        // Multiplicadores por status (lifecycle) — accepted boost, historical pena.
        'status_multipliers' => [
            'accepted'   => (float) env('JANA_TIME_DECAY_MULT_ACCEPTED', 1.2),
            'proposed'   => (float) env('JANA_TIME_DECAY_MULT_PROPOSED', 1.0),
            'historical' => (float) env('JANA_TIME_DECAY_MULT_HISTORICAL', 0.5),
            'superseded' => (float) env('JANA_TIME_DECAY_MULT_SUPERSEDED', 0.3),
            'default'    => (float) env('JANA_TIME_DECAY_MULT_DEFAULT', 1.0),
        ],
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
    | JANA ADVISOR — Metade A: Clarify reativo (Modo Consultor, proposta §10.4)
    |--------------------------------------------------------------------------
    | Cascata Decidir → Clarificar → Responder no chat. Decoupla AMBIGUIDADE-DE-
    | INTENÇÃO (perguntar) de FALTA-DE-DADO (buscar) — INTENT-SIM (NAACL 2025) +
    | Active Task Disambiguation (ICLR 2025): "fazer perguntas melhores, não só
    | dar respostas melhores".
    |
    | Cascata por latência:
    |   1a. heurística local (zero LLM) resolve ~80% direto → responde.
    |   1b. disambiguador FRONTIER (só no ~20% cinza) decide e gera a pergunta de
    |       maior ganho de informação.
    |
    | DEFAULT OFF (toca o coração do chat — mesma postura de contextual_retrieval /
    | peso_real): com a flag OFF o pipeline é byte-idêntico ao legado. Wagner liga
    | em homolog após validação ([W] soberania ADR 0238).
    |
    | Roteamento de modelo (custo-consciente): raciocínio difícil → frontier. Default
    | gpt-4o (mais forte que o gpt-4o-mini do chat), mas só dispara no cinza. Wagner
    | pode trocar p/ um modelo de raciocínio estendido via JANA_CLARIFY_MODEL.
    |
    | Medição: log channel copiloto-ai → evento `clarify_event` (gray-hit, taxa de
    | clarify, false-clarify proxy). Sem isso é fé, não engenharia.
    |
    | CONTROLE POR AMBIENTE: o flag/modelo/provider são env-driven (homolog liga, prod
    | espera) — ADR 0245. As 3 chaves env() entram na contagem baselined do Larastan
    | (noEnvCallsOutsideOfConfig) deste arquivo, igual reranker/freshness. As demais são
    | constantes de tuning (valores diretos). Default OFF: com a flag OFF o pipeline de
    | chat é byte-idêntico ao legado.
    */
    'clarify' => [
        'enabled'  => (bool) env('JANA_CLARIFY_ENABLED', false),   // homolog liga; prod espera (ADR 0245)
        'provider' => env('JANA_CLARIFY_PROVIDER'),                // null → config('ai.default') (provider do chat)
        // Default gpt-4o-mini: é o ÚNICO modelo a que o projeto OpenAI atual tem acesso
        // (gpt-4o → 403 "does not have access"; validado E2E no staging CT 100). Pra subir pro
        // frontier de verdade: conceder gpt-4o ao projeto OU provider=anthropic (claude-sonnet) —
        // ambos via env JANA_CLARIFY_MODEL/JANA_CLARIFY_PROVIDER, sem code change.
        'model'    => env('JANA_CLARIFY_MODEL', 'gpt-4o-mini'),
        // Confiança mínima do disambiguador p/ realmente perguntar (anti false-clarify).
        'min_confianca'          => 0.6,
        // Heurística 1a (zero-custo) — limites do "cinza".
        'gray_max_chars'         => 140,
        'gray_max_words'         => 8,
        // Quantos turnos recentes (PII-redigidos) alimentam o disambiguador.
        'historico_turnos'       => 4,
        // Anti-loop: TTL (s) do marcador "turno anterior foi clarify" (não pergunta 2x seguidas).
        'anti_loop_ttl_segundos' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat tools — tool use READ-ONLY no ChatCopilotoAgent (US-COPI-141)
    |--------------------------------------------------------------------------
    |
    | Até 2026-07 o chat era o único caminho conversacional da Jana e era
    | single-shot: 1 de 14 agents declarava tools, e não era este. O LLM recebia
    | ContextoNegocio pré-cozido e só formatava. Com a flag ON, o
    | ChatCopilotoAgent declara as 5 tools READ-ONLY do BriefDiarioAgent e o LLM
    | decide se busca número vivo (ADR 0141 — pattern "Claude Code").
    |
    | Default OFF (ADR 0245 — homolog liga, prod espera): com a flag OFF
    | `tools()` devolve [] e o SDK omite a chave `tools` do request
    | (BuildsTextRequests: `if (filled($tools))`) → pipeline byte-idêntico ao
    | legado. Ligar muda custo/latência por mensagem (tool call = round-trip
    | extra), então o flip é decisão [W] com medição antes/depois.
    |
    | Tier 0: a flag NÃO afeta isolamento — o business_id das tools vem sempre do
    | `conversa->business_id` (constructor), nunca do LLM (ADR 0093 + 0141).
    |
    | A chave env() abaixo entra na contagem baselined do Larastan
    | (noEnvCallsOutsideOfConfig) deste arquivo — igual clarify/reranker/freshness.
    */
    'chat_tools' => [
        'enabled' => (bool) env('JANA_CHAT_TOOLS_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | JANA ADVISOR — Metade B: Próxima-melhor-pergunta proativa (ADR 0245)
    |--------------------------------------------------------------------------
    | A Jana surfa, por persona, as perguntas que [W]/a equipe deveriam estar
    | fazendo AGORA — já com a resposta. Estende o brief diário (o snapshot do
    | BriefDiarioService é o gancho). Salto "ferramenta que responde" → "consultor
    | que pauta". Active Task Disambiguation (ICLR 2025): pergunta de maior ganho.
    |
    | VALORES DIRETOS, SEM env() — Larastan barra env() fora de config/ raiz (mesma
    | razão do bloco peso_real). Default OFF (Wagner liga depois de validar via config
    | runtime). Roda 1×/dia por business (junto do brief) → custo frontier trivial.
    |
    | Medição: log copiloto-ai → `advisor_questions_event`.
    */
    'advisor_questions' => [
        'enabled'  => false,            // default OFF — Wagner liga via config runtime após validar
        'provider' => null,             // null → config('ai.default') (provider do chat)
        'model'    => 'gpt-4o-mini',    // projeto OpenAI atual só tem gpt-4o-mini (gpt-4o → 403); subir via config quando houver acesso frontier
        'max_por_persona' => 2,         // menos é mais — só a(s) de maior valor
        // Cada persona recebe a pergunta do TRABALHO dela (ADR UI-0016 personas reais).
        'personas' => [
            ['key' => 'larissa', 'label' => 'Balcão / velocidade de venda', 'foco' => 'vendas, atendimento (tickets)'],
            ['key' => 'eliana',  'label' => 'Fiscal / financeiro',          'foco' => 'inadimplência, NF-e/rejeições'],
            ['key' => 'tecnico', 'label' => 'Operação / oficina',           'foco' => 'oportunidades, reativação'],
            ['key' => 'gestor',  'label' => 'Gestão',                       'foco' => 'visão geral: receita, risco, oportunidade'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Onda 5 — A1 Auto-summary docs longos (dossier 2026-05-13 §6)
    |--------------------------------------------------------------------------
    | AutoSummarizerService (Modules/Jana/Services/Summarizer/) comprime
    | responses > threshold das tools MCP `decisions-fetch`, `tasks-detail`,
    | `kb-answer` via map-reduce gpt-4o-mini + cache MySQL 24h.
    |
    | Cap mensal hard-enforce: ao exceder JANA_SUMMARIZER_MAX_COST_BRL,
    | service fail-open (retorna texto truncado, NÃO bloqueia tool).
    |
    | Anthropic prompt caching: prompts incluem sentinels
    | `<!--JANA_CACHE_BREAKPOINT_*-->` que serão traduzidos pra
    | cache_control breakpoints `{"type": "ephemeral"}` quando provider
    | trocar pra Anthropic direto (laravel/ai 0.6 não expõe ainda).
    |
    | Surpresa estratégica (dossier §9): economia ~R$ [redacted Tier 0]/mês quando agente
    | Jana migrar pra Sonnet com ADR canon cacheado 1h.
    */
    'auto_summarizer' => [
        'enabled'           => env('JANA_AUTO_SUMMARIZER_ENABLED', true),
        'threshold_chars'   => (int) env('JANA_AUTO_SUMMARIZER_THRESHOLD_CHARS', 8000),
        'target_tokens'     => (int) env('JANA_AUTO_SUMMARIZER_TARGET_TOKENS', 1500),
        'chunk_size_chars'  => (int) env('JANA_AUTO_SUMMARIZER_CHUNK_SIZE_CHARS', 5000),
        'cache_ttl_hours'   => (int) env('JANA_AUTO_SUMMARIZER_CACHE_TTL_HOURS', 24),
        'model'             => env('JANA_AUTO_SUMMARIZER_MODEL', 'gpt-4o-mini'),
        'max_cost_brl'      => (float) env('JANA_SUMMARIZER_MAX_COST_BRL', 10),
        'anthropic_cache_breakpoints' => (bool) env('JANA_SUMMARIZER_ANTHROPIC_CACHE_BREAKPOINTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | GAP D3 #1 — Contextual Retrieval Anthropic (2024-09-19 / oimpresso 2026-05-15)
    |--------------------------------------------------------------------------
    | Anthropic blog "Introducing Contextual Retrieval": LLM cheap (Haiku) gera
    | contexto curto (50-100 tokens) descrevendo doc de origem ANTES de embed/BM25.
    |   - Embeddings: -49% failed retrievals
    |   - + Contextual BM25 + reranking: -67% failed retrievals
    |
    | Custo: ~$1.02/1M tokens cached (Haiku 4.5). Pra ~1.500 docs oimpresso,
    | steady state estimado <$1/dia.
    |
    | Default DESLIGADO. Wagner ativa em homolog após validação.
    |
    | Backfill canônico:
    |   php artisan jana:contextualize-backfill --limit=100
    |
    | Feature flag IRREVOGÁVEL pra reverter sem migration rollback:
    |   JANA_CONTEXTUAL_RETRIEVAL=false  → service no-op, content_md raw indexado
    |
    | Mock mode pra Pest local (não chama API, custo zero):
    |   CONTEXTUAL_RETRIEVAL_FORCE_MOCK=true
    |
    | @see https://www.anthropic.com/news/contextual-retrieval
    | @see memory/requisitos/Jana/CONTEXTUAL-RETRIEVAL-ANTHROPIC.md
    */
    'contextual_retrieval' => [
        'enabled'             => (bool) env('JANA_CONTEXTUAL_RETRIEVAL', false),
        'force_mock'          => (bool) env('CONTEXTUAL_RETRIEVAL_FORCE_MOCK', false),
        'cheap_model'         => env('JANA_CHEAP_MODEL', 'claude-haiku-4-5-20251001'),
        'api_key'             => env('ANTHROPIC_API_KEY', ''),
        'max_chunk_chars'     => (int) env('JANA_CONTEXTUAL_MAX_CHUNK_CHARS', 3200),  // ~800 tokens
        'context_max_tokens'  => (int) env('JANA_CONTEXTUAL_CONTEXT_MAX_TOKENS', 100),
        'max_doc_chars'       => (int) env('JANA_CONTEXTUAL_MAX_DOC_CHARS', 200_000), // ~50k tokens
    ],

    /*
    |--------------------------------------------------------------------------
    | D8 gap #3 — OTel GenAI retrieval spans (2026-05-15, +2pp 86→88)
    |--------------------------------------------------------------------------
    | Quando `retrieval_spans_enabled=true`, MemoriaContrato é wrappado por
    | RetrievalTelemetryDecorator que emite spans OTel GenAI canônicos pra
    | cada query do pipeline retrieval Jana. Atributos seguem semantic
    | conventions https://opentelemetry.io/docs/specs/semconv/gen-ai/.
    |
    | Spans canônicos emitidos:
    |   jana.retrieval.query (root) + sub-spans prontos no SpanBuilder
    |   (negative_cache, hyde, embedding, bm25, merge, time_decay, rerank,
    |    context_select) — wireados em PR Onda 7 quando MeilisearchDriver
    |    expor hooks de instrumentação.
    |
    | Default DESLIGADO em prod — Wagner liga JANA_RETRIEVAL_SPANS=true após
    | validar overhead (~5-15ms/query) em homolog.
    |
    | redact_query=true (default): query vai como sha256 hash em span
    | attribute `gen_ai.retrieval.query`, NUNCA raw (PII Tier 0 LGPD ADR 0093).
    |
    | audit_log_enabled=true (default): persiste linha em mcp_audit_log
    | endpoint=jana.retrieval com payload_summary (latência, candidates_count,
    | top_k, query_hash, rerank_driver, embedder).
    |
    | Consumers: Langfuse self-host CT 100 (LangfuseClient::recordSpan),
    | Log channel default (debug local), mcp_audit_log (governança ADR 0053).
    |
    | @see Modules/Jana/Services/Memoria/Telemetry/RetrievalSpanBuilder.php
    | @see memory/requisitos/Jana/OTEL-RETRIEVAL-SPANS.md
    | @see memory/decisions/0051-jana-schema-proprio-adapter-otel-genai.md
    */
    'telemetry' => [
        'retrieval_spans_enabled' => (bool) env('JANA_RETRIEVAL_SPANS', false),
        'redact_query'            => (bool) env('JANA_REDACT_QUERY_IN_SPANS', true),
        'audit_log_enabled'       => (bool) env('JANA_RETRIEVAL_AUDIT_LOG', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Peso Real — Modelo de classificação por meta (ADR 0232)
    |--------------------------------------------------------------------------
    | Mapas da heurística de `relevancia_meta` (0-100) — quanto um item
    | move/protege a meta R$ [redacted Tier 0]M/ano (ADR 0022). Fonte do RelevanciaMetaInferer
    | (Área B), consumido pelo PesoRealService (Área A).
    |
    | Régua canônica ADR 0232:
    |   0-25 indireto · 26-50 habilitador · 51-75 alavanca · 76-100 receita direta.
    |
    | VALORES DIRETOS, SEM env() — Larastan barra env fora de config/ raiz.
    | Ranking de módulos espelha memory/NORTE-ROI.md (Tier 1 vende > 2 > 3).
    |
    | @see Modules/Jana/Services/Peso/RelevanciaMetaInferer.php
    | @see memory/decisions/0232-modelo-peso-real-classificacao-por-meta.md
    | @see memory/NORTE-ROI.md
    */
    'peso_real' => [
        'relevancia' => [
            // Tags Tier 0 — proteção/meta direta (topo da régua). Maior peso vence.
            'tags' => [
                'tier-0'       => 95,
                'multi-tenant' => 95,
                'meta'         => 95,
                'seguranca'    => 90,
                'security'     => 90,
                'peso-real'    => 70,
                'roi'          => 60,
                'governance'   => 45,
                'meta-processo' => 45,
                'feature-wish' => 25,
            ],

            // Módulos — ranking NORTE-ROI (Tier 1 vende/validado > Tier 2 > Tier 3).
            'modules' => [
                // Tier 1 — cliente pagante / validado / compliance obrigatório.
                'Financeiro'       => 88,
                'Vestuario'        => 88,
                'RecurringBilling' => 85,
                'NfeBrasil'        => 82,

                // Tier 2 — diferencial, sem cliente pagante dedicado ainda.
                // 'Copiloto' removido: módulo renomeado pra Jana (ADR 0088). O nome morto
                // pesava o recall (audit SDD 2026-06-18 risco KL); o peso vivo é a linha Jana.
                'Jana'              => 62,
                'LaravelAI'         => 62,
                'Whatsapp'          => 60,
                'ComunicacaoVisual' => 58,

                // Tier 3 — governança/infra (meio) e sem sinal (espera).
                'governance' => 45,

                // Tier 3 — sem cliente pagando (ADR 0105: feature-wish até sinal).
                'OficinaAuto' => 28,
            ],

            // Tipo de documento — fallback fraco quando módulo/tag não casam.
            'types' => [
                'adr'       => 55,
                'spec'      => 55,
                'handoff'   => 45,
                'session'   => 40,
                'reference' => 45,
            ],
        ],

        // ÁREA C / ETAPA 5 IAOS — feature-flag do passo de reordenação por Peso
        // Real dentro do MeilisearchDriver::buscar (pós-time-decay, pré-reranker).
        //
        // ⚠️ FALSE POR DEFAULT (segurança máxima): com a flag OFF o pipeline de
        // retrieval é BYTE-IDÊNTICO ao legado. Toca o coração da busca em prod —
        // só ligar conscientemente em homolog após validação (Wagner aprova).
        //
        // Valor DIRETO (sem env) — Larastan barra env() fora de config/ raiz,
        // mesmo padrão que a Área A adotou pro resto deste bloco.
        //
        // NOTA de namespace (CORRIGIDA — KL-C1 2026-06-12, fim do "duplo-OFF"):
        // este arquivo é merged como `copiloto.*` via JanaServiceProvider::
        // registerConfig → mergeConfigFrom(config.php, 'copiloto'). A chave
        // config('copiloto.peso_real.retrieval_enabled') resolve NÃO-NULL
        // tanto em boot normal quanto sob `config:cache` (verificado via tinker
        // 2026-06-12). O kill-switch é ESTA chave, funcional nos dois sentidos.
        //
        // LIGADO 2026-07-01 (Wagner, P12-5b): default flipado false→true após
        // validação do mecanismo. Como é uma config.php única (env() barrado pelo
        // Larastan neste bloco — ver nota "Valor DIRETO" acima), o flip é GLOBAL:
        // vale em todo ambiente após deploy. Pra DESLIGAR (kill-switch), voltar
        // esta linha pra false. O guard do driver mantém default explícito false
        // (resiliente a config/copiloto.php publicado stale).
        'retrieval_enabled' => true,

        // (a) DECISÃO/ADR — multiplicador por lifecycle (não decai por tempo).
        //
        // KL-C1 (plano SDD 2026-06-12): vocabulário ALINHADO ao que o dado real
        // carrega — antes a tabela só tinha o vocabulário ADR 0232 (accepted-
        // historical/sunsetting), que NADA produz, e todo doc real caía no
        // fallback 0.1 (aceito == superseded → viola ADR 0270 D-4 "o morto não
        // volta no top-K com o mesmo peso do vigente"). Fontes do vocabulário:
        //   - metadata['status'] EN normalizado (SeedAdrsCommand::normalizeStatus):
        //     accepted | proposed | historical | superseded — mesmo vocabulário do
        //     time_decay.status_multipliers (Wagner aprovou 2026-05-13).
        //   - frontmatter canônico (scripts/memory-schemas/adr.schema.json, FONTE
        //     ÚNICA): status proposto|aceito|...; lifecycle ativo|arquivado|
        //     substituido|historical (applyPesoReal prefere metadata['lifecycle']).
        // Pesos: vigente = 1.0 · historical = 0.5 · superseded/substituido/
        // arquivado = 0.3. Desconhecido → fallback conservador 0.1 (não infla).
        'lifecycle_mult' => [
            // vigente — peso cheio
            'accepted'            => 1.0,
            'aceito'              => 1.0,
            'ativo'               => 1.0,
            'proposed'            => 1.0,
            'proposto'            => 1.0,
            // morto — decai por lifecycle (nunca por idade)
            'historical'          => 0.5,
            'superseded'          => 0.3,
            'substituido'         => 0.3,
            'arquivado'           => 0.3,
            // legacy ADR 0232 (back-compat com fatos antigos)
            'accepted-historical' => 0.8,
            'sunsetting'          => 0.4,
            'deprecated'          => 0.1,
        ],

        // (b) MEMÓRIA — meia-vida default do decay exponencial, em dias (ADR 0195).
        'half_life' => 60,

        // (b) MEMÓRIA — piso crítico (fração de relevancia_meta). Memória que
        // protege cliente pagante não cai abaixo deste piso, mesmo velha.
        'piso_critico' => 0.5,

        // (c) INICIATIVA — sinal de cliente (ADR 0105).
        'sinal' => [
            'paga_reporta' => 1.0,
            'qualificado'  => 0.5,
            'hipotese'     => 0.2,
        ],

        // (c) INICIATIVA — time_criticality (Cost of Delay / WSJF).
        'time_criticality' => [
            'normal'     => 1.0,
            'compliance' => 1.5,
        ],
    ],
];
