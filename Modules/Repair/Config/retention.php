<?php

declare(strict_types=1);

/**
 * Política de retenção de dados pessoais — Módulo Repair (D7 LGPD compliance).
 *
 * Declara explícitamente o tempo de retenção de cada entidade que armazena PII
 * (nome cliente, contato, IMEI/serial dispositivo, defeito reportado, diagnóstico
 * técnico) no módulo Repair (Kanban de Ordem de Serviço — shared infrastructure
 * entre verticais Modules/<X>).
 *
 * LGPD Art. 16: dados pessoais devem ser eliminados após o término do tratamento.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **FSM Pipeline canônico** ([ADR 0143] — LIVE prod biz=1 desde 2026-05-12):
 * `repair_job_sheets` participa do FSM (13 stages × ~15 actions × 6 roles).
 * Audit append-only via `sale_stage_history` NUNCA é purgado, mesmo que JobSheet
 * vivo seja anonimizado.
 *
 * **Append-only contrato:**
 * `sale_stage_history` (FSM) é AUDITORIA — NUNCA purgada. Retention abaixo aplica
 * APENAS ao dado vivo na tabela origem, não ao audit trail FSM.
 *
 * Valores em DIAS. Defaults conservadores alinhados com:
 * - Código Civil Art. 206 §5 III (5 anos prescrição relação comercial)
 * - LGPD Art. 16 (eliminação após término tratamento)
 * - CDC Art. 26 (garantia 90 dias produto durável — OS pós-entrega)
 *
 * **Status atual (2026-05-16):** declaração canônica. Jobs `repair:retention-purge`
 * que aplicam efetivamente a política ficam em backlog pra próxima onda Governance.
 * Esta config É a fonte da verdade pra auditoria LGPD (sub-item D7.c rubrica
 * governance v3).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Quando true, jobs de purge consultam estas configs antes de deletar.
    | Default false até job `repair:retention-purge` estar implementado +
    | aprovado por Wagner em canary (regra ADR 0105 — sinal qualificado).
    */
    'enabled' => env('REPAIR_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por tabela (em DIAS)
    |--------------------------------------------------------------------------
    | repair_job_sheets: 1825d (5y) — OS = relação comercial (CCB Art. 206 §5 III)
    |                    + garantia CDC + necessidade fiscal contábil
    | repair_device_models: null (indefinido) — catálogo modelo (iPhone X, Galaxy
    |                    S22) sem PII direta; reutilizado entre OS de clientes
    |                    distintos
    | repair_statuses: null (indefinido) — taxonomia per-business (config),
    |                    sem PII
    */
    'tabelas' => [
        'repair_job_sheets'     => 1825,   // 5 anos (CCB Art. 206 + CDC + fiscal)
        'repair_device_models'  => null,   // indefinido (catálogo sem PII)
        'repair_statuses'       => null,   // indefinido (config sem PII)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at` (recuperável via timestamps)
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI — direito eliminação)
    | 'anonymize'   = mantém registro mas substitui PII por placeholder via
    |                 PiiRedactor (nome cliente, IMEI, telefone)
    |
    | Default 'anonymize' preserva métricas operacionais (lead time médio,
    | reincidência defeito, MTBF por modelo) sem reter dado pessoal — alinha
    | LGPD com observabilidade SRE da operação.
    */
    'strategy' => env('REPAIR_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD Art. 18 §VI sugere aviso prévio antes de eliminação. Job de purge
    | dispara notificação ao cliente (email/WhatsApp via Contact opt-in)
    | N dias antes do anonymize real.
    */
    'notice_period_days' => 30,
];
