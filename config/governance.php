<?php

declare(strict_types=1);

/**
 * Config canônico do módulo Governance.
 *
 * Centraliza flags de behavior switch sem precisar tocar Service. Cada flag
 * mapeada pra env var permite ativação por ambiente (dev/staging/prod) sem
 * deploy de código.
 *
 * @see Modules/Governance/Services/ModuleGradeService.php
 * @see memory/decisions/0157-module-grade-v3-d2-detection-hardening.md
 * @see memory/decisions/0158-module-grade-v3-d1-heuristica-hardening.md
 */

return [

    /*
    |--------------------------------------------------------------------------
    | V4 Scoped Scorecards (ADR 0160 — Wave 19+21+22 rollout)
    |--------------------------------------------------------------------------
    |
    | Quando true, `ModuleGradeService::gradeV4()` carrega bucket declarado em
    | `Modules/<X>/module.json.governance.bucket` + avalia YAML
    | `memory/scorecards/<bucket>.yaml` em vez de aplicar rubrica v3
    | monolítica.
    |
    | Default `false` (Wave 21) — dual-mode preserva v3 enquanto:
    |   1. Wave 22 fecha cobertura Pest pro ScopedScorecardEvaluator
    |   2. Wave 23 implementa detection types pendentes (ast_scan, ci_health, otel_query)
    |   3. Wave 24 ativa paired_indicators cap 50% anti-gaming
    |
    | Ativar gradualmente (.env `GOVERNANCE_V4_ENABLED=true`):
    |   - staging primeiro → comparar v3 vs v4 (módulo a módulo)
    |   - prod só após Wagner aprovar diff de pontuação por módulo
    |
    | Mesmo com flag true, módulos legados sem `governance.bucket` em
    | module.json caem em fallback_v3 automático (`v4_mode='fallback_v3'`
    | no retorno).
    |
    | @see memory/decisions/0160-scoped-scorecards-v4-bucket-yaml.md
    | @see Modules/Governance/Services/ScopedScorecardEvaluator.php
    */
    'v4_enabled' => env('GOVERNANCE_V4_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | D2 detection hardening (ADR 0157 — aceita Wave 12, ATIVADA Wave 14)
    |--------------------------------------------------------------------------
    |
    | Endurecimento da heurística D2 (Pest cobertura) — 3 frentes:
    |   - D2.a: conta apenas test files em pastas REGISTRADAS no phpunit.xml
    |   - D2.b: além do NOME do arquivo, exige asserção real no corpo
    |   - D2.c: parser XML estruturado (SimpleXMLElement) com granularidade
    |           parcial (1 dir = 2 pts) vs integral (2+ dirs = 4 pts)
    |
    | Default `true` desde Wave 14 (2026-05-16) — Wagner aprovou ativação após
    | ADRs 0157+0158 aceitas e tests Pest cobrindo dual-mode com cenários reais.
    |
    | Quando desativar (`GOVERNANCE_D2_HARDENED=false` no .env):
    |   - Bug catastrófico zera D2 de >5 módulos simultaneamente em prod
    |   - Investigação de regressão exige comparar runs hardened vs legacy
    |   - Rollback temporário de ADR 0157 via emergência (ADR nova de reversão)
    */
    'd2_hardened' => env('GOVERNANCE_D2_HARDENED', true),

    /*
    |--------------------------------------------------------------------------
    | Observability — D9.b query failed_jobs (ADR 0159 Wave 18 ready-mode)
    |--------------------------------------------------------------------------
    |
    | Quando true (default Wave 18), ModuleGradeService::dim9Observability D9.b
    | consulta a tabela `failed_jobs` nas últimas 24h e pontua 3/3 se <5 fails.
    | Antes (placeholder) zerava ~todos módulos em 2/3 + bloqueava meta 97.75.
    |
    | Desativar (.env `OBSERVABILITY_QUERY_FAILED_JOBS=false`) quando:
    |   - DB Hostinger sob carga e essa query somar latência percebida
    |   - Investigação de regressão exige isolar D9.b sem DB hit
    |
    | @see memory/decisions/0159-module-grade-v3-errata-meta-97-realismo.md
    */
    'observability' => [
        'query_failed_jobs' => env('OBSERVABILITY_QUERY_FAILED_JOBS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | D7 LGPD — PII redaction (Wave 18 saturate)
    |--------------------------------------------------------------------------
    |
    | Quando true (default prod), ActionGate::logViolation roda PiiRedactor
    | (Modules\Jana\Services\Privacy\PiiRedactor) sobre strings do payload
    | ANTES de Log::warning. Garante CPF/CNPJ/email/phone redacted em
    | storage/logs/laravel.log + audit downstream.
    |
    | Desativar (.env GOVERNANCE_PII_REDACTION=false) APENAS em debug local
    | quando precisar reproduzir incident com PII real (NUNCA em prod).
    |
    | @see Modules/Governance/Config/retention.php
    | @see memory/proibicoes.md §"Multi-tenant Tier 0 IRREVOGÁVEL" (PII nunca em log)
    */
    'pii_redaction_enabled' => env('GOVERNANCE_PII_REDACTION', true),

    /*
    |--------------------------------------------------------------------------
    | D7 LGPD — Retention (Wave 18 saturate, delegado pra Config/retention.php)
    |--------------------------------------------------------------------------
    |
    | Carrega defaults declarados em Modules/Governance/Config/retention.php.
    | Permite override via env sem precisar republicar config.
    */
    'retention' => [
        'audit_log_days'              => env('GOVERNANCE_RETENTION_AUDIT_DAYS', 1825),
        'module_grades_days'          => env('GOVERNANCE_RETENTION_MODULE_GRADES_DAYS', 90),
        'action_gate_violations_days' => env('GOVERNANCE_RETENTION_VIOLATIONS_DAYS', 365),
        'charter_metrics_days'        => env('GOVERNANCE_RETENTION_CHARTER_METRICS_DAYS', 180),
    ],

];
