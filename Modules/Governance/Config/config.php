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
];
