<?php

return [
    'name' => 'KB',

    /*
    |--------------------------------------------------------------------------
    | Identificação do módulo na UI / instalador
    |--------------------------------------------------------------------------
    */
    'module_label'       => 'Knowledge Base',
    'module_description' => 'Knowledge Base — biblioteca compartilhada de ADRs, sessions, runbooks, comparativos.',
    'module_icon'        => 'fa fa-book-open',
    'module_version'     => '0.1',
    'pid'                => null,

    /*
    |--------------------------------------------------------------------------
    | LGPD — Retention policy canônica (Wave 11 — boost D7 KB)
    |--------------------------------------------------------------------------
    | Retenção por tipo de dado conforme LGPD Art. 16 (descarte após fim da
    | finalidade). Wave 11 alinhou KB com Modules/Arquivos retention pattern
    | + Modules/Jana retention config (Wave 9).
    |
    | Política Wagner 2026-05-16:
    |  - Artigos editáveis (kb_nodes is_editable=true): 5 anos (operacional Larissa)
    |  - Bridge canon (is_editable=false): 0 dias (espelha mcp_memory_documents)
    |  - Comments/favorites: 5 anos (audit interno)
    |  - Logs de query/search (kb_search_logs futuro): 90 dias (LGPD strict)
    |
    | Hard-delete em soft-deleted após retention_days. Job mensal canônico:
    |   php artisan kb:retention-cleanup --dry-run
    |   php artisan kb:retention-cleanup --bucket=articles
    |
    | Conforme padrão Arquivos (retention_days_default=90 ADR 0123).
    */
    'retention' => [
        'articles_days'   => (int) env('KB_RETENTION_ARTICLES_DAYS', 1825), // 5 anos operacional
        'bridges_days'    => (int) env('KB_RETENTION_BRIDGES_DAYS', 0),     // espelha source
        'comments_days'   => (int) env('KB_RETENTION_COMMENTS_DAYS', 1825), // 5 anos audit
        'favorites_days'  => (int) env('KB_RETENTION_FAVORITES_DAYS', 1825),
        'queries_days'    => (int) env('KB_RETENTION_QUERIES_DAYS', 90),    // LGPD strict
        'audit_log_days'  => (int) env('KB_RETENTION_AUDIT_LOG_DAYS', 730), // 2 anos
    ],

    /*
    |--------------------------------------------------------------------------
    | LGPD — PII redaction enforcement (Wave 11)
    |--------------------------------------------------------------------------
    | Habilita defense-in-depth via Modules/Jana/Services/Privacy/PiiRedactor
    | em todos os pontos onde KB persiste/loga query ou body do user.
    |
    | Pontos cobertos atualmente:
    |  - KbRagService::ask        (query → cache + log)
    |  - KbRagService::summarize  (body do node → LLM)
    |  - KbRagService::suggestMeta (body_blocks rascunho → LLM)
    |
    | Default true em produção. Desabilitar via env apenas em dev local
    | quando precisar reproduzir bug com dado real (NUNCA em prod).
    */
    'pii_redaction' => [
        'enabled' => (bool) env('KB_PII_REDACTION_ENABLED', true),
        'mode'    => env('KB_PII_REDACTION_MODE', 'placeholder'), // placeholder|hash|remove
    ],

    /*
    |--------------------------------------------------------------------------
    | LGPD — Activity log (audit trail Spatie ActivityLog)
    |--------------------------------------------------------------------------
    | Quando true, KbNode + KbComment registram mudanças em `activity_log`
    | tabela (Spatie). Coberto pelo trait LogsActivity nos Models.
    |
    | LGPD Art. 37 — Registro de operações de tratamento. Wagner regra
    | 2026-05-16: artigos editáveis + comments precisam audit trail
    | per-user pra rastrear quem mudou o quê (Tier 0 LGPD).
    */
    'activity_log' => [
        'enabled' => (bool) env('KB_ACTIVITY_LOG_ENABLED', true),
    ],
];
