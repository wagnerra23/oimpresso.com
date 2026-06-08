<?php

/**
 * Langfuse — observability LLM canônica oimpresso (ADR 0132, ADR 0037 §GAP-1).
 *
 * Self-host CT 100 docker-host: https://langfuse.oimpresso.com (LIVE 2026-05-10).
 * Stack: postgres + clickhouse + minio + redis + langfuse-web + langfuse-worker (~1.76GB RAM).
 *
 * Cliente PHP: Modules/Jana/Services/Telemetry/LangfuseClient.php.
 * Emite traces async via queue pra não bloquear request principal.
 *
 * Eventos canônicos:
 *  - trace          — operação completa (chat, brief, kb-answer, handoff-summary, weekly-digest)
 *  - generation     — chamada LLM (tokens/cost/model)
 *  - span           — sub-operação (retrieval, rerank, judge)
 *  - score          — métrica numérica (RAGAS faithfulness/relevancy/precision/recall)
 *
 * Multi-tenant: business_id sempre incluído em trace metadata (Tier 0 IRREVOGÁVEL).
 * Server NÃO precisa de scope (telemetria é repo-wide), mas trace metadata sim.
 *
 * Fail-open: se Langfuse down/timeout, loga warning e segue request normal.
 * Default LANGFUSE_ENABLED=false até Wagner ativar via .env CT 100 deploy.
 *
 * @see memory/decisions/0132-langfuse-self-host-ct100.md
 * @see memory/requisitos/Infra/RUNBOOK-langfuse-ct100.md
 * @see memory/requisitos/Infra/RUNBOOK-langfuse-operacional.md
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Habilitar emissão de traces
    |--------------------------------------------------------------------------
    | Default false — só ativa após Wagner gerar keys no painel
    | https://langfuse.oimpresso.com e popular .env CT 100 + Hostinger.
    | Local dev: liga via .env LANGFUSE_ENABLED=true.
    */
    'enabled' => env('LANGFUSE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Host (Langfuse self-host CT 100)
    |--------------------------------------------------------------------------
    | URL pública via Traefik com IP-whitelist (ADR 0132).
    | Endpoint OTLP: $host/api/public/otel/v1/traces (alternativo).
    | Endpoint ingestion canônico: $host/api/public/ingestion (batch events).
    */
    'host' => env('LANGFUSE_HOST', 'https://langfuse.oimpresso.com'),

    /*
    |--------------------------------------------------------------------------
    | API Keys (gerar no painel Langfuse → Settings → API Keys)
    |--------------------------------------------------------------------------
    | public_key  — usado em Basic Auth username
    | secret_key  — usado em Basic Auth password (NUNCA logar/commitar)
    |
    | Convenção: keys vivem no Vaultwarden vault.oimpresso.com → Langfuse → API.
    | Hostinger .env e CT 100 .env recebem cópia local (gitignore protege .env).
    */
    'public_key' => env('LANGFUSE_PUBLIC_KEY', ''),
    'secret_key' => env('LANGFUSE_SECRET_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Dispatch mode
    |--------------------------------------------------------------------------
    | 'queue' (default)    — push via Bus::dispatch pra fila (não bloqueia request)
    | 'sync'               — HTTP síncrono (uso em CI/tests/comandos artisan curtos)
    | 'log'                — só loga payload em channel 'langfuse' (dry-run / debug)
    */
    'dispatch' => env('LANGFUSE_DISPATCH', 'queue'),

    /*
    |--------------------------------------------------------------------------
    | Connection (queue name pra LangfuseTraceJob)
    |--------------------------------------------------------------------------
    | Default fila 'default' — telemetria é low-priority. Quando volume crescer,
    | criar fila dedicada 'langfuse' com workers separados (Horizon).
    */
    'queue' => env('LANGFUSE_QUEUE', 'default'),
    'queue_connection' => env('LANGFUSE_QUEUE_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | HTTP timeout (segundos)
    |--------------------------------------------------------------------------
    | Ingestion endpoint é leve (batch JSON ~1-5KB). 5s é folga generosa.
    | Em job assíncrono, timeout não bloqueia user — mas evita worker preso.
    */
    'timeout' => env('LANGFUSE_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Sampling (% de chamadas LLM que emitem trace)
    |--------------------------------------------------------------------------
    | 1.0 (100%, default) — sempre emite. Reduzir SE volume explode (>1M traces/mês
    | ultrapassa free tier indicado pela documentação Langfuse self-host).
    | Valor entre 0 (off) e 1 (always).
    */
    'sample_rate' => (float) env('LANGFUSE_SAMPLE_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Release / Environment tags
    |--------------------------------------------------------------------------
    | Tagueia traces pra filtrar no dashboard (prod vs staging vs dev).
    | release = git_sha do deploy (CI seta via env var no workflow).
    */
    'release' => env('LANGFUSE_RELEASE', env('APP_VERSION', 'unknown')),
    'environment' => env('LANGFUSE_ENV', env('APP_ENV', 'production')),

    /*
    |--------------------------------------------------------------------------
    | Redact PII em metadata (default ON pra LGPD)
    |--------------------------------------------------------------------------
    | Quando true: aplica PiiRedactor em campos `input`/`output` antes de enviar.
    | Custo: ~1ms por trace. Default ON — desativar só sob ADR específica.
    */
    'redact_pii' => env('LANGFUSE_REDACT_PII', true),

    /*
    |--------------------------------------------------------------------------
    | Tools instrumentados (referência — emit automático no driver)
    |--------------------------------------------------------------------------
    | Lista canônica de ferramentas LLM que devem emitir trace ao serem chamadas.
    | Útil pra grep + auditoria de cobertura — código não lê esta lista, mas dev
    | pode conferir "minha tool nova está aqui?".
    */
    'instrumented_tools' => [
        'kb-answer',                  // Modules/Jana/Mcp/Tools/KbAnswerTool
        'handoff-fetch-summarized',   // Modules/Jana/Mcp/Tools/HandoffFetchSummarizedTool
        'handoff-diff',               // Modules/Jana/Mcp/Tools/HandoffDiffTool
        'weekly-digest',              // Modules/Jana/Console/Commands/JanaWeeklyDigestCommand
        'ragas-judge',                // Modules/Jana/Services/Ragas/RagasJudgeService
        'brief-fetch',                // Modules/Jana/Services/BriefDiarioService
        'jana-chat',                  // Modules/Jana/Services/Ai/LaravelAiSdkDriver (chat)
        'jana-briefing',              // Modules/Jana/Services/Ai/LaravelAiSdkDriver (briefing)
        'jana-sugestoes-metas',       // Modules/Jana/Services/Ai/LaravelAiSdkDriver (sugerirMetas)
    ],
];
