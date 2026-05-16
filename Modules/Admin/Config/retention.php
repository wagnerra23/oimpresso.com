<?php

declare(strict_types=1);

/**
 * Política de retenção de dados — Módulo Admin (D7 LGPD + audit compliance — Wave 14).
 *
 * Declara explicitamente o tempo de retenção das tabelas de audit
 * append-only do Admin Center ([ADR 0122](memory/decisions/0122-admin-center-ct100.md)).
 *
 * **Contexto regulatório:**
 * - **Código Civil Art. 206 §3 V**: pretensão de reparação civil prescreve em
 *   3 anos. Audit log de mutations administrativas (rotação de token, apply
 *   curador, mudança de feature flag) precisa sobreviver esse prazo pra
 *   defesa em juízo + auditoria de governança (Constituição v2 §7
 *   Transparência).
 * - **LGPD Art. 16 §I**: dados podem ser conservados quando necessário pro
 *   cumprimento de obrigação legal. Audit de superadmin entra aqui (defesa
 *   contra alegação de uso indevido de credencial Wagner).
 * - **Feature flags audit indefinido**: histórico de quem ativou rollout
 *   biz-N é evidência de cumprimento de canary protocol — NUNCA apagar
 *   (governança de releases [ADR 0094](memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)).
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * Admin Center é Wagner-only CT 100 (biz=1 superadmin). Mas audit log
 * persiste `business_id` da sessão pra rastreabilidade quando Wagner
 * troca contexto via "vendor mode" pra impersonar outro tenant — purge
 * NUNCA cross-tenant, sempre por business_id quando preenchido.
 *
 * **Zero auto-mem privada** ([ADR 0061](memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)):
 * Esta config É a fonte canônica via git — visível pro time MCP.
 *
 * **PiiRedactor integrado** (Wave 14 D7.a):
 * `AdminAuditLogger::log()` redacta payload via `PiiRedactor->redactArray()`
 * ANTES do INSERT em `mcp_admin_audit_log`. Defesa em profundidade pra
 * casos onde Wagner digita CPF/CNPJ/email no campo `reason` ad-hoc.
 *
 * **Status atual (2026-05-16, Wave 14):** declaração canônica. Job
 * `admin:retention-purge` ficará em backlog Governance (ADR 0105 — sinal
 * qualificado: titular pedir exclusão LGPD via legal@oimpresso.com.br
 * OU auditoria externa demandar). Esta config É a fonte da verdade pra
 * auditoria LGPD/CC do módulo Admin (sub-item D7.c).
 *
 * Valores em DIAS (`null` = retenção indefinida obrigatória).
 *
 * @see memory/decisions/0122-admin-center-ct100.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md
 * @see Modules\Admin\Services\AdminAuditLogger
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Quando true, job `admin:retention-purge` (backlog) consulta estas configs
    | antes de deletar. Default false até job estar implementado + Wagner
    | aprovar canary (ADR 0105). Apenas titular request LGPD pode acionar
    | manual via comando artisan `admin:lgpd-delete --user-id=N`.
    */
    'enabled' => env('ADMIN_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS, null = indefinido)
    |--------------------------------------------------------------------------
    | - admin_actions (mcp_admin_audit_log): 2555d (~7 anos) — Código Civil
    |                                Art. 206 §3 V (pretensão reparação civil
    |                                3 anos) + Constituição v2 §7 transparência
    |                                + margem audit externa CFC/CRC.
    |
    | - feature_flags_history (feature_flag_audits): null (indefinido) — histórico
    |                                de canary protocol é evidência de
    |                                governança de releases; NUNCA apagar.
    |                                Volume baixíssimo (~10-50 rows/mês).
    |
    | - audit_log_mcp (mcp_audit_log tool_or_resource='admin-*'): 365d — já
    |                                configurado em
    |                                `config/copiloto/mcp/audit_retention_days`.
    |                                Telemetria de uso de tools MCP (não audit
    |                                de mutation — esses caem em admin_actions).
    */
    'entities' => [
        'admin_actions'           => 2555,  // 7 anos — CC Art. 206 + margem
        'feature_flags_history'   => null,  // indefinido — governança canary
        'audit_log_mcp'           => 365,   // já em mcp/audit_retention_days
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI — direito eliminação)
    | 'anonymize'   = mantém registro mas substitui PII via PiiRedactor
    |
    | Default 'anonymize' pra admin_actions porque audit de mutation tem
    | valor agregado mesmo sem identificar usuário (métricas de frequência,
    | distribuição por action, detecção de anomalia). PII em payload já foi
    | redactado no INSERT (D7.a Wave 14) — anonymize aqui zera user_id+ip
    | mantendo action+timestamp+business_id pra auditoria longitudinal.
    */
    'strategy' => env('ADMIN_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | PII Redactor integrado no path quente (D7.a Wave 14)
    |--------------------------------------------------------------------------
    | `AdminAuditLogger::log()` chama `PiiRedactor->redactArray()` no payload
    | ANTES de gravar em `mcp_admin_audit_log`. Admin Center é Wagner-only
    | CT 100 (ataque externo zero por design), mas:
    |
    | 1. Audit pode ser exportado pra perícia/legal externo — payload sem
    |    PII facilita LGPD compliance na exportação.
    | 2. Campo `reason` em mutations aceita string ad-hoc — Wagner pode
    |    inadvertidamente digitar email/CPF do cliente.
    | 3. Defesa em profundidade Tier 0 ([ADR 0094](memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §6 LGPD).
    */
    'redact_pii_on_insert' => env('ADMIN_REDACT_PII_ON_INSERT', true),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | Admin Center é Wagner-only. Audit log referencia Wagner (titular único
    | dos dados pessoais nesse contexto). Aviso prévio não aplicável —
    | titular é o próprio operador do painel + autoriza retenção via
    | Constituição v2 (aprovou ADR 0094 + 0122).
    */
    'notice_period_days' => 0,
];
