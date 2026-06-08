<?php

declare(strict_types=1);

/**
 * Política de retenção de dados pessoais — Módulo TeamMcp (D7 LGPD compliance).
 *
 * Declara explicitamente o tempo de retenção das tabelas TeamMcp que armazenam
 * PII / metadados sensíveis de devs e sessões MCP (Identity Mesh + ingest CC).
 * LGPD Art. 16: dados pessoais devem ser eliminados após o término do tratamento.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope quando a tabela tiver business_id.
 * `mcp_actors` é repo-wide (Identity Mesh global) — purge nele é admin-only.
 *
 * **Append-only contrato:**
 * `activity_log` (Spatie LogsActivity em McpActor) é AUDITORIA — NUNCA purgada,
 * mesmo que o registro origem seja anonimizado. Retention abaixo é pro dado vivo.
 *
 * Valores em DIAS. Defaults conservadores (90d sessões / 2 anos identity).
 *
 * **Status atual (Wave 15 governance v3 RESCUE — 2026-05-16):** declaração canônica.
 * Comando artisan `team-mcp:retention-prune` fica em backlog Wave 16 — esta config
 * É a fonte da verdade pra auditoria LGPD (sub-item D7.c).
 *
 * @see memory/decisions/0081-identity-mesh.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Quando true, jobs de purge consultam estas configs antes de deletar.
    | Default false até comando `team-mcp:retention-prune` estar implementado +
    | aprovado por Wagner em canary (regra ADR 0105 — sinal qualificado).
    */
    'enabled' => env('TEAM_MCP_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade/tabela (em DIAS)
    |--------------------------------------------------------------------------
    | mcp_actors        : 1825d (5 anos) — Identity Mesh é histórico longo;
    |                     revoked_at marca soft-revoke, hard-delete só após 5y.
    | mcp_tokens        : 365d (1 ano) — tokens já hash-only, mas metadata
    |                     (last_used_at, name) tem rastro temporal — limpar pós-1y.
    | mcp_cc_sessions   : 180d (6 meses) — sessões Claude Code completas;
    |                     contém project_path (pode revelar org structure).
    | mcp_cc_messages   : 90d (3 meses) — mensagens Claude Code; content pode
    |                     ter PII colada em prompts (CPFs, emails de tickets).
    | mcp_cc_blobs      : 90d (3 meses) — conteúdo grande dedup; mesma janela
    |                     de mensagens (FK orphan cleanup).
    | mcp_audit_log     : 730d (2 anos) — audit calls MCP; user_id + tool nome,
    |                     PII baixo mas usado pra forensics; alinha LGPD janela.
    */
    'entities' => [
        'mcp_actors'        => 1825,   // 5 anos (Identity Mesh histórico)
        'mcp_tokens'        => 365,    // 1 ano (metadata pós-revoke)
        'mcp_cc_sessions'   => 180,    // 6 meses
        'mcp_cc_messages'   => 90,     // 3 meses (PII alta — prompts)
        'mcp_cc_blobs'      => 90,     // 3 meses (FK orphan cascade)
        'mcp_audit_log'     => 730,    // 2 anos (forensics)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at` (recuperável via timestamps)
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI — direito eliminação)
    | 'anonymize'   = mantém registro mas substitui PII por placeholder via PiiRedactor
    |
    | Default 'anonymize' preserva métricas agregadas (call counts, custo total)
    | sem reter dado pessoal — alinha LGPD com necessidade operacional.
    */
    'strategy' => env('TEAM_MCP_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD Art. 18 §VI sugere aviso prévio antes de eliminação. Para devs do
    | time MCP, aviso vai via email do user (mcp_actors.user_id → users.email).
    */
    'notice_period_days' => 30,
];
