<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Caminho da memória do projeto
    |--------------------------------------------------------------------------
    | Diretório raiz que contém os .md indexáveis (ADRs, SPECs, etc).
    | Pode ser sobrescrito em testes via config(['evolution.memory_path' => ...]).
    |
    */
    'memory_path' => env('EVOLUTION_MEMORY_PATH', base_path('memory')),

    /*
    |--------------------------------------------------------------------------
    | Modelos LLM (Fase 1c+ — quando Vizra ADK estiver instalado)
    |--------------------------------------------------------------------------
    | Ver memory/requisitos/EvolutionAgent/adr/tech/0001-prism-php-claude-padrao.md
    |
    */
    'default_model' => env('EVOLUTION_DEFAULT_MODEL', 'anthropic.claude-sonnet-4-6'),
    'judge_model' => env('EVOLUTION_JUDGE_MODEL', 'anthropic.claude-opus-4-7'),
    'extractor_model' => env('EVOLUTION_EXTRACTOR_MODEL', 'anthropic.claude-haiku-4-5'),

    /*
    |--------------------------------------------------------------------------
    | Embeddings (Fase 1b)
    |--------------------------------------------------------------------------
    */
    'embedding_provider' => env('EVOLUTION_EMBEDDING_PROVIDER', 'voyage'),
    'embedding_model' => env('EVOLUTION_EMBEDDING_MODEL', 'voyage-3-lite'),

    /*
    |--------------------------------------------------------------------------
    | Cap mensal em USD (alarme em 80%)
    |--------------------------------------------------------------------------
    */
    'monthly_cap_usd' => (float) env('EVOLUTION_MONTHLY_CAP_USD', 30.0),

    /*
    |--------------------------------------------------------------------------
    | Toggles de autonomia (Fase 3)
    |--------------------------------------------------------------------------
    */
    'pr_comment_enabled' => env('EVOLUTION_PR_COMMENT_ENABLED', false),
    'auto_pr_enabled' => env('EVOLUTION_AUTO_PR_ENABLED', false),

];
