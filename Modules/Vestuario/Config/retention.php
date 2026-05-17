<?php

declare(strict_types=1);

/**
 * Política de retenção de dados pessoais — Módulo Vestuario (D7 LGPD compliance).
 *
 * RESTAURADO Wave 18 (regressão Wave 17 — Config dir foi perdido).
 *
 * Declara explícitamente o tempo de retenção dos settings vertical Vestuario
 * (ROTA LIVRE biz=4 + futuros clientes CNAE 4781-4/00).
 *
 * Embora `vestuario_settings` armazene CONFIG (não PII direta de cliente),
 * o LogsActivity em VestuarioSetting captura mudanças com user_id + timestamp
 * — esse audit trail é dado pessoal sob LGPD Art. 5 §I e tem que respeitar
 * retenção declarada.
 *
 * Cliente piloto: ROTA LIVRE (Larissa) — settings ativos: format_date_shift_hours=3
 * ([ADR 0066]). Settings mudam raramente (<10 alterações/ano por business),
 * então retention conservadora (10 anos) preserva audit fiscal sem custo storage.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * Append-only contrato:
 * `activity_log` (Spatie/LogsActivity) é AUDITORIA — NUNCA purgado, mesmo que
 * settings vivo seja deletado. Audit append-only sobrevive purge.
 *
 * Status atual (2026-05-16): declaração canônica. Job `vestuario:retention-purge`
 * fica em backlog pra próxima onda Governance (sinal qualificado ADR 0105).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md §P7
 * @see memory/decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md
 * @see Modules\Vestuario\Entities\VestuarioSetting
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Default false até job `vestuario:retention-purge` estar implementado +
    | aprovado por Wagner em canary (regra ADR 0105 — sinal qualificado).
    */
    'enabled' => env('VESTUARIO_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | vestuario_settings: 3650d (10y) — settings per-business com audit trail
    |                     que sustenta auditoria fiscal/contábil (Lei 10.165/2000)
    |                     + comprovação de quirks aplicados por cliente
    | activity_log (settings):  NUNCA purgar (append-only contrato Spatie)
    */
    'entities' => [
        'vestuario_settings' => 3650, // 10 anos (audit fiscal)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at` (recuperável)
    | 'hard_delete' = DELETE definitivo
    | 'anonymize'   = preserva estrutura (business_id, created_at) e zera settings JSON
    |
    | Default 'anonymize' preserva histórico de business adoção do vertical
    | sem expor settings antigas (que podem indicar quirks específicos cliente).
    */
    'strategy' => env('VESTUARIO_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD Art. 18 §VI sugere aviso antes da eliminação.
    | Business administrator é notificado via Jana Inbox + email opt-in.
    */
    'notice_period_days' => 30,
];
