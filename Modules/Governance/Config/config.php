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
     * D1 heurística hardening (ADR 0158 — aceita Wave 12, ATIVADA Wave 14 2026-05-16).
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
     * Default `true` desde Wave 14 — Wagner aprovou após smoke run + diff por módulo
     * mostrar ajustes esperados (Jana/Entities/Mcp/* sobe, jobs Asaas/Inter qualificam).
     *
     * Quando desativar (`GOVERNANCE_D1_HARDENED=false` no .env):
     *   - Regressão massiva (>3 módulos perdem ≥3pts D1) detectada em prod
     *   - Investigação de bug exige isolar heurística legacy vs hardened
     *   - Rollback emergencial de ADR 0158 (via ADR nova de reversão)
     *
     * @see memory/decisions/0158-module-grade-v3-d1-heuristica-hardening.md
     */
    'd1_hardened' => env('GOVERNANCE_D1_HARDENED', true),
];
