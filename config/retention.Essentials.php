<?php

declare(strict_types=1);

/**
 * Retention policy — Modules/Essentials (Wave 11 LGPD D7.c).
 *
 * Declarado conforme LGPD Art. 16 (eliminação após cumprida finalidade) +
 * Constituição v2 §4 (Princípio Loop fechado por métrica). Base legal de
 * conservação prolongada cita CC Art. 206 (prescrição quinquenal de
 * documentos) e CLT (registros de jornada/leave).
 *
 * Unidades: dias. Backfill via comando `php artisan retention:cleanup`
 * (RetentionCleanupCommand existente em Modules/Arquivos pode ser ampliado
 * em Wave futura — por enquanto este file declara a policy de governança
 * detectada pela rubrica `ModuleGradeService::dim7LgpdCompliance D7.c`).
 *
 * @see memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md
 * @see Modules/Governance/Services/ModuleGradeService.php dim7LgpdCompliance
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Retention por entidade (dias após "soft completion")
    |--------------------------------------------------------------------------
    |
    | "soft completion" = condição de transição pra eligível-pra-purge.
    |
    |   todos        — 365 dias após `completed_at` (status='completed')
    |   reminders    — 730 dias após `date` (passou data + 2 anos)
    |   documents    — 1825 dias (5 anos — CC Art. 206 §5º I prescrição)
    |   leaves       — 1825 dias (5 anos — CLT Art. 11 conservação registros)
    |   attendance   — 1825 dias (CLT Art. 74 §2º registro jornada — 5 anos)
    |   activity_log — 1095 dias (3 anos — LGPD Art. 37 audit trail)
    |
    */
    'todos' => [
        'days'       => 365,
        'after'      => 'completed_at',
        'rationale'  => 'tarefa concluída — após 1 ano não é mais útil pra retrospectiva',
        'legal_base' => 'LGPD Art. 16 (eliminação após finalidade cumprida)',
    ],

    'reminders' => [
        'days'       => 730,
        'after'      => 'date',
        'rationale'  => 'lembrete expirou há 2 anos — sem valor operacional',
        'legal_base' => 'LGPD Art. 16',
    ],

    'documents' => [
        'days'       => 1825,
        'after'      => 'created_at',
        'rationale'  => 'documentos jurídicos/RH podem ter prescrição quinquenal',
        'legal_base' => 'CC Art. 206 §5º I (prescrição 5 anos) + LGPD Art. 16',
    ],

    'leaves' => [
        'days'       => 1825,
        'after'      => 'end_date',
        'rationale'  => 'férias/ausências contam pra fiscalização trabalhista',
        'legal_base' => 'CLT Art. 11 (conservação 5 anos) + LGPD Art. 16',
    ],

    'attendance' => [
        'days'       => 1825,
        'after'      => 'clock_out_time',
        'rationale'  => 'registro de ponto exige guarda quinquenal',
        'legal_base' => 'CLT Art. 74 §2º + Portaria MTP 671/2021 Anexo I',
    ],

    'activity_log' => [
        'days'       => 1095,
        'after'      => 'created_at',
        'rationale'  => 'audit trail Spatie ActivityLog — 3 anos cobre auditorias LGPD',
        'legal_base' => 'LGPD Art. 37 (registro de operações de tratamento)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Modo de purge
    |--------------------------------------------------------------------------
    |
    | `soft`  — soft-delete (deleted_at) — recoverable até purge físico
    | `hard`  — DELETE físico imediato após `days` expirar
    | `anon`  — anonimizar (preservar linha + redactar PII com PiiRedactor)
    |
    */
    'mode' => env('ESSENTIALS_RETENTION_MODE', 'soft'),

    /*
    |--------------------------------------------------------------------------
    | Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093)
    |--------------------------------------------------------------------------
    |
    | Toda execução de purge DEVE escopar por business_id. Comando artisan
    | aceita flag `--business=<id>` ou itera business ativos. NUNCA truncate.
    |
    */
    'multi_tenant_scoped' => true,
];
