<?php

declare(strict_types=1);

/**
 * Política de retenção de dados pessoais — Módulo AssetManagement (D7 LGPD compliance).
 *
 * Declara explícitamente o tempo de retenção das entidades patrimoniais que registram
 * vínculo a colaboradores (alocação asset → user), notas de manutenção e garantias.
 * Embora não armazene PII direta de cliente, registra dados patrimoniais do business
 * + vínculos a usuários (allocated_to) sujeitos a LGPD Art. 7º + obrigação fiscal/contábil.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * Append-only contrato:
 * `activity_log` é AUDITORIA (LogsActivity) — NUNCA purgada, mesmo que dado-fonte seja.
 *
 * Valores em DIAS. Defaults conservadores alinhados com prazos fiscais BR
 * (CTN Art. 173 — 5 anos prescrição tributária; Lei 10.165/2000 — 10 anos contábil).
 *
 * Status atual (2026-05-16): declaração canônica. Job `assetmanagement:retention-purge`
 * fica em backlog pra próxima onda Governance.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Default false até job `assetmanagement:retention-purge` estar implementado +
    | aprovado por Wagner em canary (ADR 0105 — sinal qualificado).
    */
    'enabled' => env('ASSETMANAGEMENT_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | assets: 10 anos (patrimônio — bem do business, prazo contábil Lei 10.165)
    | asset_transactions: 5 anos (movimentações allocate/revoke — auditoria fiscal CTN Art. 173)
    | asset_maintenances: 5 anos (notas técnicas — garantia + comprovação despesa)
    | asset_warranties: 7 anos (cobre vida útil + 2 anos pós-vencimento)
    */
    'entities' => [
        'am_assets'             => 3650, // 10 anos fiscal/contábil
        'am_asset_transactions' => 1825, // 5 anos prescrição CTN
        'am_maintenance_logs'   => 1825, // 5 anos
        'am_warranties'         => 2555, // 7 anos
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at` (recuperável)
    | 'hard_delete' = DELETE definitivo
    | 'anonymize'   = preserva agregados, redaciona PII via PiiRedactor
    |
    | Default 'anonymize' preserva métricas (count assets, taxa manutenção)
    | sem reter vínculo pessoal (allocated_to user_id virá NULL após purge).
    */
    'strategy' => env('ASSETMANAGEMENT_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD Art. 18 §VI sugere aviso antes da eliminação.
    */
    'notice_period_days' => 30,
];
