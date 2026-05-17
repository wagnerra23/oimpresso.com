<?php

declare(strict_types=1);

/**
 * D7 LGPD — Shim canônico que delega pro config Module-owned (W27 Governance).
 *
 * Pattern espelha config/retention.ads.php + config/retention.whatsapp.php — facilita
 * `config('retention.governance.audit_log_days')` por outros módulos sem precisar
 * conhecer caminho interno do Modules/Governance/Config/retention.php.
 *
 * Tier 0 ([ADR 0094](memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §3):
 * Module owns sua retenção, raiz só re-exporta pra discovery uniforme.
 *
 * @see Modules/Governance/Config/retention.php (canônico)
 * @see config/retention.ads.php (mesmo padrão)
 * @see config/retention.whatsapp.php (mesmo padrão)
 */

$moduleConfig = __DIR__ . '/../Modules/Governance/Config/retention.php';

if (file_exists($moduleConfig)) {
    return require $moduleConfig;
}

// Fallback fail-safe se Modules/Governance ainda não está publicado (CI fresh / dev).
return [
    'audit_log_days'                  => env('GOVERNANCE_RETENTION_AUDIT_DAYS', 1825),
    'module_grades_days'              => env('GOVERNANCE_RETENTION_MODULE_GRADES_DAYS', 90),
    'action_gate_violations_days'     => env('GOVERNANCE_RETENTION_VIOLATIONS_DAYS', 365),
    'charter_metrics_days'            => env('GOVERNANCE_RETENTION_CHARTER_METRICS_DAYS', 180),
    'pii_redaction_enabled'           => env('GOVERNANCE_PII_REDACTION', true),
];
