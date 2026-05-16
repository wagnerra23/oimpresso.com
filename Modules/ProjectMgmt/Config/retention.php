<?php

declare(strict_types=1);

/**
 * Política de retenção de dados — Módulo ProjectMgmt (D7 LGPD compliance).
 *
 * Declara explicitamente o tempo de retenção das entidades manipuladas pelo
 * módulo Jira-style (Board/Backlog/MyWork/Roadmap/Activity). ProjectMgmt
 * **não possui Entities próprias** — opera sobre tabelas `mcp_projects`,
 * `mcp_tasks`, `mcp_task_comments`, `mcp_task_events`, `mcp_inbox_notifications`
 * (canônicas em Modules/Jana/Entities/Mcp/*).
 *
 * PII potencial nestes dados:
 *   - `mcp_task_comments.body` — texto livre, pode conter CPF/email/telefone
 *     mencionado por usuário em comentário (@mentions + descrição operacional)
 *   - `mcp_tasks.description` — texto livre, mesmo risco do comentário
 *   - `mcp_inbox_notifications.body` — copia trecho do comentário (mesmo risco)
 *   - `mcp_task_events.note` — audit log, pode incluir descrição livre
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **Append-only contrato:**
 * `mcp_task_events` é AUDITORIA — entries são preservadas mesmo após purge da task
 * (LGPD Art. 16 §I: cumprimento de obrigação legal).
 *
 * Valores em DIAS. Defaults alinhados ao Crm (Wave 9 pattern canônico).
 *
 * **Status atual (2026-05-16, Wave 16):** declaração canônica.
 * Job `project-mgmt:retention-purge` fica em backlog próxima onda Governance.
 * Esta config É a fonte da verdade pra auditoria LGPD (sub-item D7.c).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0070-jira-style-task-management-current-md-removed.md
 * @see Modules\Crm\Config\retention.php (pattern Wave 9)
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Quando true, jobs de purge consultam estas configs antes de deletar.
    | Default false até job `project-mgmt:retention-purge` estar implementado
    | + aprovado por Wagner em canary (regra ADR 0105 — sinal qualificado).
    */
    'enabled' => env('PROJECT_MGMT_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | project_mgmt_projects: 5 anos (histórico estratégico — Código Civil
    |   Art. 206 §5 III: relação operacional preserva 5 anos)
    | project_mgmt_tasks: 3 anos (operação produto — histórico velocidade,
    |   retro, post-mortem; menor risco PII direto)
    | project_mgmt_task_comments: 2 anos (texto livre com risco PII;
    |   purge mais agressiva — LGPD Art. 16 §V)
    | project_mgmt_task_events: 5 anos (audit append-only; preservação por
    |   obrigação legal LGPD Art. 16 §I)
    | project_mgmt_inbox_notifications: 365 dias (transient, baixa utilidade
    |   após 1 ano — notif mention/assigned/etc)
    | project_mgmt_task_dependencies: null (sem PII — grafo de deps puro,
    |   herda lifecycle de tasks via FK cascade)
    | project_mgmt_task_watchers: null (sem PII — user_id pivot, herda task)
    */
    'entities' => [
        'project_mgmt_projects'              => 1825, // 5 anos
        'project_mgmt_tasks'                 => 1095, // 3 anos
        'project_mgmt_task_comments'         => 730,  // 2 anos
        'project_mgmt_task_events'           => 1825, // 5 anos (audit append-only)
        'project_mgmt_inbox_notifications'   => 365,  // 1 ano (transient)
        'project_mgmt_task_dependencies'     => null, // herda task (FK cascade)
        'project_mgmt_task_watchers'         => null, // herda task (FK cascade)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at` (recuperável)
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI — direito eliminação)
    | 'anonymize'   = substitui PII por placeholder via PiiRedactor
    |
    | Default 'anonymize' preserva métricas de produtividade (velocity, throughput)
    | sem reter texto livre potencialmente sensível em comments/descriptions.
    */
    'strategy' => env('PROJECT_MGMT_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio (em DIAS)
    |--------------------------------------------------------------------------
    | Diferente do Crm (notifica titular externo), ProjectMgmt notifica owner
    | da task interna N dias antes da anonimização — pra exportar evidência
    | se precisar.
    */
    'notice_period_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Campos PII-relevantes por entidade (mapping pro PiiRedactor)
    |--------------------------------------------------------------------------
    | Quando strategy='anonymize', o job aplica PiiRedactor::redact() em cada
    | campo listado abaixo, preservando demais colunas pra métricas agregadas.
    */
    'pii_fields' => [
        'project_mgmt_tasks'               => ['description'],
        'project_mgmt_task_comments'       => ['body'],
        'project_mgmt_inbox_notifications' => ['body'],
        'project_mgmt_task_events'         => ['note'],
    ],
];
