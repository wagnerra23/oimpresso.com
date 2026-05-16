<?php

return [
    'name' => 'Governance',

    /*
     * ActionGate enforcement mode (Constituição Art. 8):
     * - 'off':    middleware loaded mas não checa nada
     * - 'warn':   loga warnings em mcp_audit_log mas não bloqueia (default MVP)
     * - 'strict': BLOCK_ALWAYS força reject quando rule decide
     */
    'actiongate_mode' => env('GOVERNANCE_ACTIONGATE_MODE', 'warn'),

    /*
     * Quarterly review schedule (Art. 10 §10.4 + Enforcement #7).
     * Próxima review em 2026-08-05 (3 meses pós-ratificação v1.0.0).
     */
    'next_review_at' => env('GOVERNANCE_NEXT_REVIEW_AT', '2026-08-05'),

    /*
     * D1 heurística hardening (ADR 0158 proposto) — Wave 12 2026-05-16.
     *
     * Quando true, ModuleGradeService aplica 3 fixes na heurística D1 multi-tenant:
     *
     *   (1) phpFiles() recursivo em Entities/ + Models/ + Jobs/
     *       — captura subdiretórios (ex Jana/Entities/Mcp/*.php)
     *   (2) Regex isCrossTenantTestFile aceita `withoutGlobalScope` singular E plural
     *       (s? = 0 ou 1 ocorrência) — back-compat preservado
     *   (3) D1.c fallback regex: Job constructor `__construct(int $entityId)` +
     *       body referencia `->business_id` qualifica como multi-tenant safe
     *
     * Default false: módulos atuais que passavam D1 continuam com mesmo score.
     * Wagner aprova ativar via .env após smoke run + diff de score por módulo.
     *
     * @see memory/decisions/0158-module-grade-v3-d1-heuristica-hardening.md (proposto)
     */
    'd1_hardened' => env('GOVERNANCE_D1_HARDENED', false),
];
