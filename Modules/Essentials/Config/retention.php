<?php

declare(strict_types=1);

/**
 * Política de retenção de dados pessoais — Módulo Essentials (D7 LGPD compliance).
 *
 * Declara explicitamente o tempo de retenção de cada entidade que armazena PII
 * (CPF/email/telefone via Document/Reminder/EssentialsLeave/KnowledgeBase) no módulo
 * Essentials. LGPD Art. 16: dados pessoais devem ser eliminados após o término do
 * tratamento.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **Append-only contrato:**
 * `activity_log` é AUDITORIA (LogsActivity) — NUNCA purgada, mesmo que dado-fonte seja.
 * Retention abaixo é pro dado vivo na tabela origem, não pro audit trail.
 *
 * Valores em DIAS. Defaults conservadores (HRM costuma pedir 5 anos por força CLT
 * Art. 11 — afastamento/atestados ficam 5 anos arquivados; CLT Art. 74 §3 ponto
 * eletrônico 5 anos também).
 *
 * **Status atual (2026-05-16 Wave 12):** declaração canônica. Jobs
 * `essentials:retention-purge` que aplicam efetivamente a política ficam em backlog
 * pra próxima onda Governance (ADR 0105 sinal qualificado — só ativa quando cliente
 * pedir).
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
    | Default false até job `essentials:retention-purge` estar implementado +
    | aprovado por Wagner em canary (regra ADR 0105 — sinal qualificado).
    */
    'enabled' => env('ESSENTIALS_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | ToDo: 3 anos (histórico operacional + audit interno)
    | EssentialsLeave: 1825 (5 anos — CLT Art. 11 prazo prescricional trabalhista)
    | Reminder: 1 ano (lembrete pessoal — PII baixa, expira rápido)
    | Document: 5 anos (anexos contratuais/CNH/RG — janela fiscal + CLT)
    | DocumentShare: herda de Document (FK cascade)
    | KnowledgeBase: indefinido (manual interno sem PII bruta)
    | EssentialsMessage: 2 anos (mensagens internas — LGPD interesse legítimo)
    | EssentialsAttendance: 1825 (5 anos — CLT Art. 74 §3 ponto eletrônico)
    | EssentialsHoliday: indefinido (feriado é dado público — sem PII)
    */
    'entities' => [
        'todo'                              => 1095,   // 3 anos
        'essentials_leave'                  => 1825,   // 5 anos (CLT Art. 11)
        'reminder'                          => 365,    // 1 ano
        'document'                          => 1825,   // 5 anos
        'document_share'                    => 1825,   // herda Document
        'knowledge_base'                    => null,   // indefinido
        'essentials_message'                => 730,    // 2 anos
        'essentials_attendance'             => 1825,   // 5 anos (CLT Art. 74 §3)
        'essentials_holiday'                => null,   // indefinido (dado público)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at` (recuperável via timestamps)
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI — direito eliminação)
    | 'anonymize'   = mantém registro mas substitui PII por placeholder via PiiRedactor
    |
    | Default 'anonymize' preserva métricas HRM agregadas (count de leaves aprovadas,
    | dias de atestado por mês) sem reter dado pessoal — alinha LGPD com necessidade
    | operacional + folha de pagamento histórica.
    */
    'strategy' => env('ESSENTIALS_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD Art. 18 §VI sugere aviso prévio antes de eliminação. Job de purge
    | dispara notificação ao colaborador N dias antes do delete real.
    */
    'notice_period_days' => 30,
];
