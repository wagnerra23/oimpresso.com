<?php

declare(strict_types=1);

/**
 * Política de retenção LGPD — Modules/Arquivos (Wave 25 D7.c).
 *
 * Arquivos é backbone DMS multi-purpose (NFe XML, contratos, fotos OS, anexos ticket).
 * Cada `sub_destination` tem prazo próprio baseado em lei BR — declarado de forma
 * unificada aqui pra consumo pelo `RetentionCleanupCommand`, jobs de purge e auditoria
 * compliance LGPD Art. 16.
 *
 * NOTA: este arquivo é UM SHIM canônico de retenção. A fonte da verdade operacional
 * é `config.php` chave `retention_days_policy` (consultada pelo Service ao fazer upload
 * — preenche `arquivos.retention_days` per-row). Este shim agrupa todos prazos em
 * formato D7.c-compliant pra rubrica governance + facilita auditoria estado-arte.
 *
 * Bases legais (Brasil):
 *  - **Lei 8.846/94 Art. 23** — XMLs NFe/NFSe autorizados: 5 anos (1825d).
 *  - **SINIEF 07/2005 Art. 8** — guarda do XML autorizado SEFAZ: 5 anos.
 *  - **CTN Art. 173** — prescrição tributária: 5 anos (1825d).
 *  - **CDC Art. 27** — prescrição reparação consumo: 5 anos (1825d).
 *  - **CPC Art. 205** — prescrição decenal contratos cíveis (ajuste per-contrato).
 *  - **LGPD Art. 16** — eliminação após término do tratamento (override per-titular
 *    via pedido formal LGPD Art. 18 §VI).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * Append-only contrato:
 * `arquivos_audit_log` é AUDITORIA (Sprint 1 ADR 0123) — NUNCA purgada, mesmo que
 * dado-fonte seja. Retention abaixo é pro arquivo vivo, não pro audit trail.
 *
 * @see Modules/Arquivos/Config/config.php (chave retention_days_policy — operacional)
 * @see Modules/Arquivos/Console/Commands/RetentionCleanupCommand.php
 * @see Modules/Jana/Services/Privacy/PiiRedactor (varredura best-effort em export)
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | `arquivos:retention-cleanup` consulta tanto config.php (operacional) quanto
    | esta config (auditoria/documental). Quando false, RetentionCleanupCommand
    | só faz dry-run + log (não deleta).
    */
    'enabled' => env('ARQUIVOS_RETENTION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Retenção por sub_destination (em DIAS)
    |--------------------------------------------------------------------------
    | Espelho de config.php['retention_days_policy'] — fonte da verdade aqui
    | (auditoria D7.c rubrica governance v3). Mudança real DEVE atualizar ambos.
    */
    'entities' => [
        'nfe-xml'            => 1825,  // 5 anos — Lei 8.846/94 Art. 23 + SINIEF 07/2005 Art. 8
        'nfse-xml'           => 1825,  // 5 anos — idem NFe
        'documentos-fiscais' => 1825,  // 5 anos — CTN Art. 173 (prescrição tributária)
        'contratos'          => 1825,  // 5 anos — CDC Art. 27 (default; cíveis decenais via override per-row)
        'repair-foto'        => 730,   // 2 anos — pós-encerramento OS (evidência reparo)
        'os-anexo'           => 730,   // 2 anos — anexos de OS
        'ticket-anexo'       => 365,   // 1 ano — pós-fechamento ticket
        'default'            => 90,    // 90 dias — fallback LGPD Art. 15-16 (eliminação tempestiva)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = só marca `deleted_at` (recuperável durante grace period 30d)
    | 'hard_delete' = DELETE definitivo após grace period (recomendado pós-retenção)
    | 'anonymize'   = mantém metadados (counts, timestamps) mas zera storage_path
    |                 + roda PiiRedactor sobre metadata_json + descarta arquivo físico
    |
    | Default 'hard_delete' alinha com LGPD Art. 18 §VI (direito de eliminação).
    | Override per-business via .env ARQUIVOS_RETENTION_STRATEGY.
    */
    'strategy' => env('ARQUIVOS_RETENTION_STRATEGY', 'hard_delete'),

    /*
    |--------------------------------------------------------------------------
    | Grace period após retention vencer (em DIAS)
    |--------------------------------------------------------------------------
    | Janela entre `retention_days` expirar e hard_delete real. Permite restauração
    | em caso de purge acidental ou pedido de cliente. HealthCheckCommand alerta
    | WARN quando algo passou retention + grace ainda não deletado (check #4).
    */
    'grace_period_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD Art. 18 §VI sugere aviso antes da eliminação para dado pessoal.
    | Para arquivos sensitive (bucket=sensitive) com vínculo a user_id explícito,
    | RetentionCleanupCommand pode emitir notificação via Notification channel.
    */
    'notice_period_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Bucket override
    |--------------------------------------------------------------------------
    | Arquivos com bucket=sensitive recebem retention MAIS curta default (mitiga
    | exposição de PII). Override por sub_destination acima ainda vale (NFe XML
    | é sensitive mas 5 anos por força de lei tributária).
    */
    'bucket_override' => [
        'sensitive' => env('ARQUIVOS_SENSITIVE_DEFAULT_DAYS', 365),  // 1 ano default pra PII
        'public'    => null,  // sem override — usa sub_destination
        'common'    => null,
    ],
];
