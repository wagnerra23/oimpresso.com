<?php

declare(strict_types=1);

/**
 * Política de retenção de dados pessoais — Módulo Woocommerce (D7 LGPD compliance — Wave 14).
 *
 * Declara explicitamente o tempo de retenção de cada entidade que armazena PII
 * (CPF/CNPJ, email, telefone, endereço do cliente final) no fluxo Woocommerce ↔ oimpresso.
 *
 * **LGPD Art. 16**: dados pessoais devem ser eliminados após o término do tratamento.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **Append-only contrato:**
 * `activity_log` (Spatie) é AUDITORIA — NUNCA purgada, mesmo que dado-fonte seja.
 * Retention abaixo é pro dado vivo na tabela origem, não pro audit trail.
 *
 * **PiiRedactor integrado** ([Modules\Jana\Services\Privacy\PiiRedactor](../../Jana/Services/Privacy/PiiRedactor.php)):
 * WoocommerceWebhookController redacta payloads (billing.email/phone/address) antes
 * de gravar em `storage/logs/laravel.log` — defesa em profundidade D7.a.
 *
 * **Status atual (2026-05-16, Wave 14):** declaração canônica. Jobs
 * `woocommerce:retention-purge` ficam em backlog Governance (ADR 0105 — sinal
 * qualificado: drift detectado OU titular pedir exclusão LGPD).
 * Esta config É a fonte da verdade pra auditoria LGPD (sub-item D7.c).
 *
 * Valores em DIAS. Defaults conservadores alinhados ao Código Civil Art. 206
 * (prescrição comercial 5 anos) + Lei Complementar 123/2006 (fiscal MEI 5 anos).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 * @see Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Quando true, jobs de purge consultam estas configs antes de deletar.
    | Default false até job `woocommerce:retention-purge` estar implementado +
    | aprovado por Wagner em canary (regra ADR 0105 — sinal qualificado).
    */
    'enabled' => env('WOOCOMMERCE_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | - orders (Transaction.woocommerce_order_id): 1825d (5 anos — Código Civil
    |   Art. 206 §5 III + LC 123/2006 fiscal). Inclui billing.email/phone/address
    |   do cliente final + line items. Retention longa = obrigação fiscal-tributária.
    |
    | - webhook_events (woocommerce_sync_logs): 365d (1 ano — debug operacional +
    |   evidência LGPD de processamento legítimo). Após 1 ano sync log perde
    |   valor (Wcommerce ↔ oimpresso reconciliáveis via Transaction direto).
    |
    | - webhook_events_invalid (signature mismatch / parse error): 90d — só pra
    |   investigação de ataque/integração quebrada. Sem PII de cliente real
    |   (payloads inválidos rejeitados antes do parse).
    */
    'entities' => [
        'orders'                  => 1825,  // 5 anos (fiscal)
        'webhook_events'          => 365,   // 1 ano
        'webhook_events_invalid'  => 90,    // 90 dias
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at` (recuperável via timestamps)
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI — direito eliminação)
    | 'anonymize'   = mantém registro mas substitui PII por placeholder via PiiRedactor
    |
    | Default 'anonymize' preserva métricas agregadas (volume orders, taxa
    | sucesso webhook) sem reter PII do cliente final — alinha LGPD com
    | necessidade fiscal-operacional (NFe já emitida usa CPF/CNPJ canônico
    | em Transaction → NfeBrasil; sync log pode anonimizar sem perder histórico).
    */
    'strategy' => env('WOOCOMMERCE_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Anonimizar PII antes de delete/anonymize
    |--------------------------------------------------------------------------
    | Quando true, PiiRedactor substitui email/phone/CPF/CNPJ/endereço por
    | placeholders [REDACTED:*] em vez de deletar o registro inteiro. Mantém
    | rastreabilidade fiscal (order_id, total, data) sem PII.
    */
    'anonymize_pii' => env('WOOCOMMERCE_ANONYMIZE_PII', true),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD Art. 18 §VI sugere aviso prévio antes de eliminação. Job de purge
    | dispara notificação ao cliente final N dias antes do delete/anonymize real.
    | 30d alinha com pattern Crm/Brief.
    */
    'notice_period_days' => 30,
];
