<?php

declare(strict_types=1);

/**
 * Política de retenção de dados pessoais — Módulo OficinaAuto (D7 LGPD compliance).
 *
 * Declara explícitamente o tempo de retenção de cada entidade que armazena PII
 * (placa, chassi, RENAVAM, contact_id linkando dono) no módulo OficinaAuto.
 * LGPD Art. 16: dados pessoais devem ser eliminados após o término do tratamento.
 *
 * **Particularidade vertical automotiva:**
 * Veículo + OS guardam dados que linkam a `contacts` (dono/locatário). PII real
 * está em `contacts` (CPF/CNPJ/endereço) mas os identificadores aqui (placa,
 * RENAVAM) são considerados PII no contexto LGPD pois identificam pessoa via
 * lookup público SENATRAN.
 *
 * **Janela fiscal:** vendas com NFe (transaction_id linkado) seguem retention
 * CONFAZ — XML/PDF guardados 5 anos. ServiceOrder cancelada ou concluída
 * acompanha esse mínimo legal (não pode purgar antes da venda associada).
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **Append-only contrato:**
 * `activity_log` é AUDITORIA (LogsActivity) — NUNCA purgada, mesmo que dado-fonte seja.
 * Retention abaixo é pro dado vivo na tabela origem, não pro audit trail.
 *
 * Valores em DIAS. Defaults conservadores (5 anos = janela fiscal Brasil NFe).
 * Override per-business via `oficinaauto_business_settings.retention_overrides` (TODO).
 *
 * **Status atual (2026-05-16):** declaração canônica. Jobs `oficinaauto:retention-purge`
 * que aplicam efetivamente a política ficam em backlog pra próxima onda Governance.
 * Esta config É a fonte da verdade pra auditoria LGPD (sub-item D7.c).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0137-modulo-oficina-auto-qualificado.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Quando true, jobs de purge consultam estas configs antes de deletar.
    | Default false até job `oficinaauto:retention-purge` estar implementado +
    | aprovado por Wagner em canary (regra ADR 0105 — sinal qualificado).
    */
    'enabled' => env('OFICINAAUTO_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | Vehicle: 5 anos (NFe da venda associada — CONFAZ SINIEF mínimo)
    | ServiceOrder: 5 anos (idem — relação com transaction NFe/boleto)
    | Vehicle inativo sem OS: 730 dias (2 anos — cliente "frio" sem operação)
    | ServiceOrder cancelada (sem NFe): 1095 dias (3 anos — audit operacional)
    */
    'entities' => [
        'vehicle'                       => 1825,   // 5 anos (janela NFe)
        'vehicle_inactive_no_orders'    => 730,    // 2 anos (sem OS associada)
        'service_order'                 => 1825,   // 5 anos (janela NFe)
        'service_order_cancelled'       => 1095,   // 3 anos (cancelada sem NFe)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at` (recuperável via timestamps)
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI — direito eliminação)
    | 'anonymize'   = mantém registro mas substitui PII por placeholder via PiiRedactor
    |
    | Default 'anonymize' preserva métricas agregadas (KPIs frota, locação ativa)
    | sem reter placa/RENAVAM nominais — alinha LGPD com necessidade operacional.
    */
    'strategy' => env('OFICINAAUTO_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD Art. 18 §VI sugere aviso prévio antes de eliminação. Job de purge
    | dispara notificação ao dono do veículo N dias antes do delete real.
    */
    'notice_period_days' => 30,
];
