<?php

declare(strict_types=1);

/**
 * Retenção LGPD — Modules/ADS (Autonomic Decision System).
 *
 * Cumpre LGPD Art. 16 (eliminação após cumprimento da finalidade) +
 * dimensão D7.c da rubrica module-grade-v3 (ADR 0155).
 *
 * Cron diário `ads:retention-purge` (TODO US-ADS-RET-001) varre tabelas
 * de decision/learning e remove rows mais antigas que `retention_days`.
 * PiiRedactor aplicado em files_affected/metadata ANTES da purge.
 *
 * Multi-tenant Tier 0: purge respeita business_id global scope.
 *
 * @see memory/decisions/0155-module-grade-rubrica-v3.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Decisions (mcp_dual_brain_decisions)
    |--------------------------------------------------------------------------
    | Decisions resolvidas/canceladas/blocked. 180d cobre auditoria semestral
    | + análise de pattern learning. Decisions parent de subtasks ativas
    | NÃO são purgadas (preserva cascade).
    */
    'decisions_retention_days' => (int) env('ADS_DECISIONS_RETENTION_DAYS', 180),

    /*
    |--------------------------------------------------------------------------
    | Outcomes (mcp_decision_outcomes / mcp_pattern_outcomes)
    |--------------------------------------------------------------------------
    | Histórico de resultado por decision (success/fail/wagner_modified).
    | 365d alimenta Wilson Score com janela ampla pra trend stability.
    */
    'outcomes_retention_days' => (int) env('ADS_OUTCOMES_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Confidence scores (mcp_confidence_scores)
    |--------------------------------------------------------------------------
    | Janela rolante 20 outcomes; histórico antigo não acrescenta sinal.
    | 90d permite recálculo se algoritmo mudar (replay outcomes).
    */
    'confidence_scores_retention_days' => (int) env('ADS_CONFIDENCE_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Audit log (mcp_audit_log filtrado por module=ads)
    |--------------------------------------------------------------------------
    | Auditoria de roteamento + brain calls + policy applied. Default 365d
    | atende fiscalização interna + Marco Civil Art. 15 (180d mínimo).
    */
    'audit_log_retention_days' => (int) env('ADS_AUDIT_LOG_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Retention enabled (kill-switch)
    |--------------------------------------------------------------------------
    | Quando false, comando ads:retention-purge é no-op (log only).
    | Default true em prod; false em dev pra preservar dados de teste.
    */
    'enabled' => (bool) env('ADS_RETENTION_ENABLED', true),
];
