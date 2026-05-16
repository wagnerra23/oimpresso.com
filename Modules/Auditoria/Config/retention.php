<?php

declare(strict_types=1);

/**
 * Politica de retencao — Modulo Auditoria (D7.c LGPD + audit fiscal).
 *
 * Auditoria registra DECISOES humanas sobre dados sensiveis (revert de
 * activity_log + notas internas). Janela conservadora 2555d (7 anos)
 * alinha:
 *   - CONFAZ SINIEF 07/2005 Art. 26 (guarda fiscal 5 anos + extensao
 *     pos-prescricao 2 anos = 7 anos defensivos)
 *   - LGPD Art. 16 (eliminacao apos termino do tratamento — audit fiscal
 *     conta como legitimo interesse continuado Art. 7 §IX)
 *   - Codigo Tributario Nacional Art. 174 (prescricao 5 anos)
 *
 * Multi-tenant Tier 0 IRREVOGAVEL (ADR 0093): jobs de purge SEMPRE
 * scoped por business_id. NUNCA cross-tenant cleanup.
 *
 * Auditoria-de-auditoria principio: NAO purga `activity_log` core
 * UltimatePOS (Spatie shared). Apenas tabelas proprias do modulo:
 *   - auditoria_audit_notes (D7.b)
 *
 * Status atual (2026-05-16): declaracao canonica. Jobs `auditoria:retention-purge`
 * que aplicam efetivamente ficam em backlog (ADR 0105 — so nasce com
 * sinal qualificado: titular pedir exclusao OU compliance gate detectar drift).
 *
 * @see Modules\Auditoria\Entities\AuditNote
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0127-modules-auditoria-ui-undo.md
 * @see Modules\Jana\Config\retention.php (template pattern)
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar politica de retencao
    |--------------------------------------------------------------------------
    | Default false ate job `auditoria:retention-purge` estar implementado
    | + aprovado por Wagner em canary 7d (ADR 0105 sinal qualificado).
    */
    'enabled' => env('AUDITORIA_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retencao por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | - audit_note (auditoria_audit_notes) : 2555d / 7 anos
    |       Anotacao humana sobre activity_log entry. Janela conservadora
    |       (audit fiscal + LGPD 16 + CTN 174). Append-only.
    |
    | - activity_log_shared (Spatie core) : null (NUNCA purga via Auditoria)
    |       Tabela compartilhada UltimatePOS. Auditoria nao gerencia retencao
    |       desta tabela — fica a cargo do core (Modules/Essentials retention
    |       ou job dedicado fora deste modulo).
    */
    'entities' => [
        'audit_note'           => 2555, // 7 anos
        'activity_log_shared'  => null, // intocavel pelo modulo Auditoria
    ],

    /*
    |--------------------------------------------------------------------------
    | Estrategia de purge
    |--------------------------------------------------------------------------
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI eliminacao)
    | 'anonymize'   = mantem registro mas substitui texto livre por placeholder
    |                 via PiiRedactor (preserva contagem agregada)
    |
    | Default 'anonymize' preserva metrica "quantos reverts foram anotados"
    | sem reter conteudo da nota (que pode conter PII residual mesmo com
    | redaction no momento da escrita — defense-in-depth).
    */
    'strategy' => env('AUDITORIA_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso previo ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD Art. 18 §VI sugere aviso previo. Job de purge dispara notificacao
    | ao owner do business N dias antes do delete real.
    */
    'notice_period_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Override per-business
    |--------------------------------------------------------------------------
    | Mecanismo opt-in pra cliente B2B configurar retencao mais curta
    | (Art. 18 §IV direito de informacao). TODO US-AUDIT-RET-001.
    */
    'allow_per_business_override' => false,
];
