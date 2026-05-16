<?php

declare(strict_types=1);

/**
 * Política de retenção de dados pessoais — Módulo RecurringBilling (D7 LGPD compliance).
 *
 * Declara explicitamente o tempo de retenção de cada entidade que armazena PII
 * (CPF/CNPJ do contato, telefone, email cobrança, dados bancários cifrados, payloads
 * de webhook gateway) no Módulo RecurringBilling. LGPD Art. 16: dados pessoais devem
 * ser eliminados após o término do tratamento.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **Append-only contrato:**
 * `activity_log` é AUDITORIA (LogsActivity) — NUNCA purgada, mesmo que dado-fonte seja.
 * `rb_subscription_events` é timeline append-only (regra cultural) — também NUNCA purgado.
 * `rb_charge_attempts` é append-only (`UPDATED_AT = null`) — também preservado.
 * Retention abaixo é pro dado vivo na tabela origem, não pro audit trail.
 *
 * Valores em DIAS. Defaults conservadores reconhecem Código Civil Art. 206 §5 III
 * (prazo 5 anos pra dívida líquida) — cobranças e faturas ficam 5 anos.
 * Boletos pagos: tempo de NF emitida (5 anos Receita Federal CTN Art. 174).
 *
 * **Status atual (2026-05-16 Wave 14):** declaração canônica D7. Jobs
 * `rb:retention-purge` que aplicam efetivamente a política ficam em backlog
 * pra próxima onda Governance (ADR 0105 sinal qualificado — só ativa quando
 * Eliana/financeiro pedir).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 * @see Modules\Crm\Config\retention.php (módulo referência D7)
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Quando true, jobs de purge consultam estas configs antes de deletar.
    | Default false até job `rb:retention-purge` estar implementado +
    | aprovado por Wagner em canary (regra ADR 0105 — sinal qualificado).
    */
    'enabled' => env('RB_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | subscription:        5 anos (Código Civil Art. 206 §5 III prescrição)
    | invoice:             5 anos (CTN Art. 174 — Receita Federal NFe vinculada)
    | charge_attempt:      append-only (preservar reconciliação financeira)
    | plan:                indefinido (catálogo de plano sem PII direta)
    | boleto_credential:   cifrado em vida; revogar credencial = deletar registro
    | subscription_event:  append-only (timeline cultural; histórico financeiro)
    | subscription_note:   3 anos (nota humana sobre cliente — PII contextual)
    | subscription_favorite: indefinido (apenas FK user/subscription, sem PII)
    */
    'entities' => [
        'subscription'          => 1825,   // 5 anos (Código Civil Art. 206)
        'invoice'               => 1825,   // 5 anos (CTN Art. 174)
        'charge_attempt'        => null,   // append-only (audit financeiro)
        'plan'                  => null,   // indefinido (catálogo, sem PII)
        'boleto_credential'     => 1825,   // 5 anos (vínculo bancário histórico)
        'subscription_event'    => null,   // append-only (timeline cultural)
        'subscription_note'     => 1095,   // 3 anos
        'subscription_favorite' => null,   // sem PII direta
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at` (recuperável via timestamps)
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI — direito eliminação)
    | 'anonymize'   = mantém registro mas substitui PII por placeholder via PiiRedactor
    |
    | Default 'anonymize' preserva métricas agregadas (MRR histórico, churn anual)
    | sem reter dado pessoal — alinha LGPD com necessidade financeira recorrente.
    */
    'strategy' => env('RB_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD Art. 18 §VI sugere aviso prévio antes de eliminação. Job de purge
    | dispara notificação ao contato N dias antes do delete real.
    */
    'notice_period_days' => 30,
];
