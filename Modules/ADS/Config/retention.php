<?php

declare(strict_types=1);

/**
 * Política de retenção de dados — Módulo ADS (D7 LGPD compliance).
 *
 * ADS armazena saídas do Brain B (Claude API) + escalações HiTL + auditoria de
 * decisões automatizadas. Embora ADS NÃO trabalhe diretamente com PII de cliente
 * final (CPF/CNPJ vivem em Crm/Sells/Contacts), os outputs do Brain B podem
 * conter:
 *   - Trechos de código colado pelo usuário (potencial PII em comentários/strings)
 *   - Mensagens de erro (`$e->getMessage()`) que vazam dados de fixtures reais
 *   - Decisões `pending_wagner` com `event_metadata` JSON que carrega payload livre
 *   - Instruções geradas (`instruction_generated`) — texto livre LLM
 *
 * Por isso a política de retenção declarada aqui é canônica (D7.c). PiiRedactor
 * roda nos pontos de log/persistência (D7.a). Audit log Spatie roda nos Eloquent
 * Models quando existirem (D7.b — atualmente ADS é query-builder puro via DB::table).
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **Append-only contrato:**
 * `mcp_decision_patterns` é APRENDIZADO agregado (Wilson Score) — NUNCA purgado.
 * Retention abaixo é pro dado vivo da decisão individual, não pro padrão aprendido.
 *
 * Valores em DIAS. Defaults conservadores (janela fiscal Brasil mínima).
 *
 * **Status atual (2026-05-16):** declaração canônica. Jobs `ads:retention-purge`
 * que aplicam efetivamente ficam em backlog Wave seguinte Governance.
 * Esta config É a fonte da verdade pra auditoria LGPD (sub-item D7.c).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Quando true, jobs de purge consultam estas configs antes de deletar.
    | Default false até job `ads:retention-purge` estar implementado +
    | aprovado por Wagner em canary (regra ADR 0105 — sinal qualificado).
    */
    'enabled' => env('ADS_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | brain_b_outputs (= mcp_dual_brain_decisions com brain_used=brain_b):
    |   2 anos — texto livre LLM com potencial PII; janela LGPD operacional
    |
    | escalations (= mcp_dual_brain_decisions com destination=pending_wagner):
    |   3 anos — escalações humanas têm valor de evidência audit (Wagner aprovou/rejeitou)
    |
    | tool_executions (= mcp_tool_executions): 1 ano (logs op + diagnostic)
    |
    | governance_rules: indefinido (regras declarativas — sem PII direto)
    | decision_patterns: indefinido (Wilson Score aprendizado agregado — append-only)
    | confidence_scores: 2 anos (snapshot histórico calibração)
    | project_parts: 5 anos (entrega comercial — Código Civil Art. 206)
    */
    'entities' => [
        'ads_brain_b_outputs'     => 730,    // 2 anos
        'ads_escalations'         => 1095,   // 3 anos
        'ads_tool_executions'     => 365,    // 1 ano
        'ads_confidence_scores'   => 730,    // 2 anos
        'ads_project_parts'       => 1825,   // 5 anos
        'ads_governance_rules'    => null,   // indefinido (sem PII)
        'ads_decision_patterns'   => null,   // append-only Wilson Score
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at` (recuperável)
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI direito eliminação)
    | 'anonymize'   = mantém registro mas substitui PII via PiiRedactor
    |
    | Default 'anonymize' preserva métricas agregadas (Wilson Score, taxas)
    | sem reter conteúdo livre — alinha LGPD com necessidade operacional.
    */
    'strategy' => env('ADS_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio
    |--------------------------------------------------------------------------
    | ADS não notifica titular (não é dado pessoal direto). Mas preserva
    | janela operacional pra Wagner reverter purge equivocado.
    */
    'notice_period_days' => 30,
];
