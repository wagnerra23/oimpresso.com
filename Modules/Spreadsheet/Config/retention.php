<?php

declare(strict_types=1);

/**
 * Política de retenção de dados — Módulo Spreadsheet (D7 LGPD compliance).
 *
 * Spreadsheet = planilhas colaborativas estilo Google Sheets dentro do oimpresso.
 * Conteúdo das células é dado livre do usuário — pode conter PII (lista de
 * contatos, CPF/CNPJ, dados financeiros pessoais) ou dado puramente operacional
 * (KPIs internos, projeções, cálculos).
 *
 * Tabelas:
 * - `sheet_spreadsheets`: metadados + conteúdo serializado da planilha
 * - `sheet_spreadsheet_shares`: ACL (user_id ↔ spreadsheet_id) com permission
 *
 * LGPD Art. 16: dados pessoais devem ser eliminados após o término do tratamento.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **Conteúdo opaco — limitação técnica:**
 * Conteúdo de célula é UGC (user-generated content) opaco — NÃO temos como
 * detectar PII automaticamente sem heurística PII regex. PII Redactor pode
 * passar varredura best-effort nas células texto durante anonymize.
 *
 * Valores em DIAS. Defaults conservadores alinhados com janela fiscal Brasil
 * (5 anos) — planilha frequentemente serve como evidência operacional/contábil.
 *
 * **Status atual (2026-05-16):** declaração canônica. Jobs
 * `spreadsheet:retention-purge` em backlog. Esta config É a fonte da verdade
 * pra auditoria LGPD (sub-item D7.c rubrica governance v3).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    */
    'enabled' => env('SPREADSHEET_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por tabela (em DIAS)
    |--------------------------------------------------------------------------
    | sheet_spreadsheets: 1825d (5y) — janela fiscal Brasil mínima; planilha
    |                     pode ser evidência contábil/operacional
    | sheet_spreadsheet_shares: 1825d (5y) — herda da planilha pai (ACL faz
    |                           sentido apenas enquanto a planilha vive)
    */
    'tabelas' => [
        'sheet_spreadsheets'        => 1825,   // 5 anos (fiscal Brasil)
        'sheet_spreadsheet_shares'  => 1825,   // 5 anos (herda do pai)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | Default 'anonymize' preserva o registro da planilha (count, dono criador)
    | mas faz best-effort pass com PiiRedactor sobre conteúdo das células texto.
    | Para planilha que ultrapassou janela e cliente explicitou direito de
    | eliminação (LGPD Art. 18 §VI), upgrade pra 'hard_delete' via override.
    */
    'strategy' => env('SPREADSHEET_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    */
    'notice_period_days' => 30,
];
