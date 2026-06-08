<?php

declare(strict_types=1);

/**
 * Política de retenção de dados — Módulo Officeimpresso (D7 LGPD compliance).
 *
 * Officeimpresso = módulo legacy de gestão de licenças por computador (sistema
 * Delphi WR Sistemas / OfficeImpresso pré-Laravel). Mantém PII em:
 * - `licenca_computador`: identificação máquina (hostname, MAC, hardware ID) +
 *   vínculo cliente/business — IP/host são dados pessoais sob LGPD quando
 *   identificáveis
 * - `licenca_log`: append-only audit trail de uso/ativação licença
 *
 * LGPD Art. 16: dados pessoais devem ser eliminados após o término do tratamento.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **Append-only contrato:**
 * `licenca_log` é AUDITORIA append-only (originalmente com triggers MySQL — ver
 * `2026_04_23_200100_create_licenca_log_triggers.php` e drop em
 * `2026_04_24_000000_drop_licenca_log_triggers.php`). Conteúdo histórico de
 * licenciamento serve evidência contratual + auditoria fiscal de software.
 *
 * Valores em DIAS. Defaults longos refletem natureza fiscal/contratual de
 * licença de software (relação comercial CCB Art. 206 §5 III + evidência
 * compliance Lei do Software 9.609/98).
 *
 * **Status atual (2026-05-16):** declaração canônica. Jobs
 * `officeimpresso:retention-purge` em backlog. Esta config É a fonte da verdade
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
    'enabled' => env('OFFICEIMPRESSO_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por tabela (em DIAS)
    |--------------------------------------------------------------------------
    | licenca_computador: 1825d (5y) — relação contratual licença + evidência
    |                     compliance Lei Software 9.609/98; após expiração da
    |                     licença ainda manter window legal
    | licenca_log: 1825d (5y) — audit append-only de uso/ativação; alinha com
    |              janela fiscal contábil Brasil e evidência contratual
    */
    'tabelas' => [
        'licenca_computador'    => 1825,   // 5 anos (contratual + Lei Software)
        'licenca_log'           => 1825,   // 5 anos (audit + fiscal)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | Default 'anonymize' preserva métricas agregadas (count licenças ativas,
    | volume ativações por período) sem reter identificador de máquina pessoal
    | — alinha LGPD com observabilidade comercial do produto.
    */
    'strategy' => env('OFFICEIMPRESSO_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    */
    'notice_period_days' => 30,
];
