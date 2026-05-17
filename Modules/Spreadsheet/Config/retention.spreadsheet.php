<?php

declare(strict_types=1);

/**
 * Shim D7.c — Modules/Spreadsheet retention canon Wave 27 (2026-05-17).
 *
 * Espelha `Config/retention.php` num formato D7.c-compliant pra rubrica
 * governance v3 ({@see ADR 0155}). Permite auditoria estado-da-arte LGPD
 * separada do shape operacional (`retention.php`), seguindo o mesmo
 * padrão consolidado em `Modules/Arquivos/Config/retention.php`
 * (Wave 25 — shim canônico LGPD).
 *
 * **Por que ter os 2 arquivos?**
 *  - `retention.php` = OPERACIONAL (consumido pelo `SpreadsheetService` /
 *    job `spreadsheet:retention-purge` quando entregue). Define `enabled`,
 *    `tabelas[]`, `strategy`, `notice_period_days`.
 *  - `retention.spreadsheet.php` = AUDITORIAL/DOCUMENTAL (fonte da verdade
 *    pra compliance LGPD + facilita auditoria estado-arte). Declara entities
 *    no shape D7.c canônico (entity → days → law_ref → strategy).
 *
 * Mudança real DEVE atualizar AMBOS (acoplamento explícito — ver comments).
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ({@see ADR 0093}): jobs de purge
 * respeitam `business_id` global scope.
 *
 * **Status atual (2026-05-17):** declaração canônica. Job
 * `spreadsheet:retention-purge` em backlog.
 *
 * @see Modules\Spreadsheet\Config\retention.php (operacional)
 * @see Modules\Arquivos\Config\retention.php (mesmo padrão D7.c shim)
 * @see memory/decisions/0155-module-grade-v3.md D7.c
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitação política
    |--------------------------------------------------------------------------
    | Deve espelhar `retention.php['enabled']` (acoplamento explícito).
    */
    'enabled' => env('SPREADSHEET_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Estratégia padrão (LGPD Art. 16 + Art. 18 §VI)
    |--------------------------------------------------------------------------
    | 'anonymize' (default): preserva metadados (count/dono), PiiRedactor
    |   best-effort pass nas células texto. Mantém evidência fiscal sem PII.
    | 'hard_delete': elimina linha (cliente exerceu direito Art. 18 §VI).
    */
    'strategy' => env('SPREADSHEET_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD não fixa janela; 30d é padrão de mercado pra dar chance de export.
    */
    'grace_period_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Entities canônicas (D7.c rubrica governance v3)
    |--------------------------------------------------------------------------
    | Cada entity declara: tabela, retenção em dias, base legal, estratégia.
    | Valores alinhados com `retention.php['tabelas']` (5y = janela fiscal BR).
    */
    'entities' => [
        'sheet_spreadsheets' => [
            'days'     => 1825,                      // 5 anos (CTN Art. 173)
            'law_ref'  => 'CTN Art. 173 + LGPD Art. 16',
            'strategy' => 'anonymize',               // preserva conteúdo após PII pass
            'note'     => 'Planilha pode ser evidência operacional/contábil; anonymize preserva auditoria sem PII.',
        ],

        'sheet_spreadsheet_shares' => [
            'days'     => 1825,                      // herda do pai
            'law_ref'  => 'LGPD Art. 16 (vínculo derivado)',
            'strategy' => 'hard_delete',             // ACL faz sentido só enquanto planilha vive
            'note'     => 'Quando spreadsheet pai é anonymized/deleted, share também é removido.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PII Redactor — limitação técnica documentada
    |--------------------------------------------------------------------------
    | Conteúdo de célula é UGC opaco — não temos como detectar PII
    | automaticamente sem heurística. Redactor faz best-effort regex
    | (CPF/CNPJ/email/telefone) e marca `pii_redacted_at` na linha.
    |
    | Caso cliente reporte PII residual, fluxo Art. 18 §VI: exporta CSV
    | (audit log) → hard_delete da planilha.
    */
    'pii_redactor' => [
        'enabled'      => true,
        'mode'         => 'best_effort',
        'targets'      => ['sheet_data'],            // coluna JSON com células
        'patterns'     => ['cpf', 'cnpj', 'email', 'phone_br'],
    ],
];
