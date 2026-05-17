<?php

declare(strict_types=1);

/**
 * D7 LGPD — Retenção de dados Governance.
 *
 * Wave 18 saturate (88 → 100). Tabelas Governance (audit log, module grades
 * history, action gate violations) seguem retenção declarada aqui.
 *
 * Tier 0 IRREVOGÁVEL ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 *   - Audit log é APPEND-ONLY (trigger MySQL ADR 0084) — retenção via
 *     purge command scoped por business_id, NUNCA `TRUNCATE` global.
 *
 * LGPD Art. 16 (eliminação após fim do tratamento) + Art. 37 (registro
 * operações). Audit log oficial = 5 anos (mesma regra Receita Federal pra
 * documentos fiscais — folga jurisprudencial).
 *
 * @see Modules/Governance/Console/Commands/PurgeRetentionCommand.php (futuro)
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §3 LGPD
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Audit log (mcp_audit_log)
    |--------------------------------------------------------------------------
    |
    | Append-only via trigger MySQL (ADR 0084). Retenção CONSERVADORA:
    | 5 anos = LGPD Art. 37 + Receita Federal docs fiscais (jurisprudência).
    | Purge command futuro DELETE WHERE ts < NOW() - INTERVAL 5 YEAR scoped
    | por business_id (cross-tenant Tier 0).
    */
    'audit_log_days' => env('GOVERNANCE_RETENTION_AUDIT_DAYS', 1825), // 5 anos

    /*
    |--------------------------------------------------------------------------
    | Module grades history (mcp_module_grades_history)
    |--------------------------------------------------------------------------
    |
    | Sparkline 7d na UI Show + cron daily 06:05 BRT snapshot. Retenção 90d
    | suficiente pra trending; rollup mensal pra histórico longo (futuro).
    */
    'module_grades_days' => env('GOVERNANCE_RETENTION_MODULE_GRADES_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | ActionGate violations (log channel)
    |--------------------------------------------------------------------------
    |
    | ActionGate em modo `warn` loga violações no channel `single` (storage/logs/
    | laravel.log). Logs em filesystem têm rotação separada (config/logging.php
    | daily channel — 14 dias default). Aqui declaramos a intenção pra cron
    | de purge alinhar quando virar tabela própria (Fase 5+1).
    */
    'action_gate_violations_days' => env('GOVERNANCE_RETENTION_VIOLATIONS_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Charter metrics (mcp_charter_metrics)
    |--------------------------------------------------------------------------
    |
    | Métricas health/drift de Page Charters. Histórico 180d cobre análise
    | semestral de degradação. Rollup trimestral pra histórico longo (futuro).
    */
    'charter_metrics_days' => env('GOVERNANCE_RETENTION_CHARTER_METRICS_DAYS', 180),

    /*
    |--------------------------------------------------------------------------
    | PII Redaction (Tier 0 LGPD)
    |--------------------------------------------------------------------------
    |
    | Sempre TRUE em prod. CPF/CNPJ/email/phone redacted em logs ActionGate
    | violations + audit log entries que carregam payload. Reusa
    | Modules\Jana\Services\Privacy\PiiRedactor (canônico do projeto).
    */
    'pii_redaction_enabled' => env('GOVERNANCE_PII_REDACTION', true),
];
