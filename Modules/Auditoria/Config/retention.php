<?php

declare(strict_types=1);

/**
 * Declaracao de janelas de guarda — Modulo Auditoria (D7.c LGPD + audit fiscal).
 *
 * Estes valores nao tem efeito: nenhum codigo os aplica e nao existe comando de
 * expurgo neste modulo. Registrado em 2026-11-09. Nao criar um a partir daqui:
 * num ERP o dado tem dever de guarda e o controle e por permissao de acesso
 * (decisao [W] de 2026-07-27, memory/proibicoes.md §5).
 *
 * Janela conservadora 2555d (7 anos), referencia da guarda:
 *   - CONFAZ SINIEF 07/2005 Art. 26 (guarda fiscal 5 anos + extensao
 *     pos-prescricao 2 anos = 7 anos defensivos)
 *   - Codigo Tributario Nacional Art. 174 (prescricao 5 anos)
 *
 * Este modulo nao gerencia a retencao de `activity_log` core (Spatie shared).
 * A unica tabela propria declarada e auditoria_audit_notes (D7.b).
 *
 * @see Modules\Auditoria\Entities\AuditNote
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0127-modules-auditoria-undo-activity-log.md
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar politica de retencao
    |--------------------------------------------------------------------------
    | Nenhum codigo le esta chave (2026-11-09). Fica false.
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
    |       desta tabela.
    */
    'entities' => [
        'audit_note'           => 2555, // 7 anos
        'activity_log_shared'  => null, // intocavel pelo modulo Auditoria
    ],

    /*
    |--------------------------------------------------------------------------
    | Estrategia declarada
    |--------------------------------------------------------------------------
    | Valor sem consumidor em 2026-11-09 (ver cabecalho).
    */
    'strategy' => env('AUDITORIA_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso previo ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | Valor sem consumidor em 2026-11-09 (ver cabecalho).
    */
    'notice_period_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Override per-business
    |--------------------------------------------------------------------------
    | Valor sem consumidor em 2026-11-09 (ver cabecalho).
    */
    'allow_per_business_override' => false,
];
