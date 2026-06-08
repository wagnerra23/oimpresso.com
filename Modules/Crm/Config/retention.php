<?php

declare(strict_types=1);

/**
 * Política de retenção de dados pessoais — Módulo Crm (D7 LGPD compliance).
 *
 * Declara explícitamente o tempo de retenção de cada entidade que armazena PII
 * (CPF/CNPJ, email, telefone, endereço, gravação de chamada) no módulo Crm.
 * LGPD Art. 16: dados pessoais devem ser eliminados após o término do tratamento.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **Append-only contrato:**
 * `activity_log` é AUDITORIA (LogsActivity) — NUNCA purgada, mesmo que dado-fonte seja.
 * Retention abaixo é pro dado vivo na tabela origem, não pro audit trail.
 *
 * Valores em DIAS. Defaults conservadores (2 anos = janela fiscal Brasil mínima).
 * Override per-business via `crm_business_settings.retention_overrides` JSON (TODO).
 *
 * **Status atual (2026-05-16):** declaração canônica. Jobs `crm:retention-purge`
 * que aplicam efetivamente a política ficam em backlog pra próxima onda Governance.
 * Esta config É a fonte da verdade pra auditoria LGPD (sub-item D7.c).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/requisitos/Crm/PII-REDACTION.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Quando true, jobs de purge consultam estas configs antes de deletar.
    | Default false até job `crm:retention-purge` estar implementado +
    | aprovado por Wagner em canary (regra ADR 0105 — sinal qualificado).
    */
    'enabled' => env('CRM_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | Lead/Contact: 2 anos (cliente potencial inativo após 2y é "frio")
    | Schedule (follow-up): 3 anos (histórico de contato — base relacionamento)
    | ScheduleLog (anotações call): 3 anos (audit operacional + LGPD evidência)
    | CrmCallLog (gravações): 1 ano (PII alta — gravação voz; LGPD recomenda mínimo)
    | Campaign: 5 anos (histórico de marketing — LGPD Art. 16 §V interesse legítimo)
    | Proposal: 5 anos (relação comercial — Código Civil Art. 206 §5 III)
    | ProposalTemplate: indefinido (sem PII direta — template é genérico)
    | CrmMarketplace: 3 anos (atribuição user-marketplace, sem PII bruta)
    | Leaduser (pivot): herda de Lead (Contact) — purge cascade via FK
    | CrmContactPersonCommission: 5 anos (regra comercial + impostos)
    */
    'entities' => [
        'lead'                              => 730,    // 2 anos
        'schedule'                          => 1095,   // 3 anos
        'schedule_log'                      => 1095,   // 3 anos
        'call_log'                          => 365,    // 1 ano (PII alta — gravação)
        'campaign'                          => 1825,   // 5 anos
        'proposal'                          => 1825,   // 5 anos
        'proposal_template'                 => null,   // indefinido (sem PII)
        'crm_marketplace'                   => 1095,   // 3 anos
        'crm_contact_person_commission'     => 1825,   // 5 anos
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at` (recuperável via timestamps)
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI — direito eliminação)
    | 'anonymize'   = mantém registro mas substitui PII por placeholder via PiiRedactor
    |
    | Default 'anonymize' preserva métricas agregadas (count campanhas, taxas)
    | sem reter dado pessoal — alinha LGPD com necessidade operacional.
    */
    'strategy' => env('CRM_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD Art. 18 §VI sugere aviso prévio antes de eliminação. Job de purge
    | dispara notificação ao contato N dias antes do delete real.
    */
    'notice_period_days' => 30,
];
