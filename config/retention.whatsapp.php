<?php

declare(strict_types=1);

/**
 * Retenção LGPD — Modules/Whatsapp.
 *
 * Cumpre LGPD Art. 16 (eliminação após cumprimento da finalidade) +
 * dimensão D7.c da rubrica module-grade-v3 (ADR 0155).
 *
 * Cron diário `whatsapp:retention-purge` (TODO US-WA-RET-001) varre cada
 * tabela e remove rows mais antigas que `retention_days` aplicando soft-delete
 * → hard-delete pipeline. PiiRedactor é aplicado em logs ANTES da purge.
 *
 * Multi-tenant Tier 0: purge cross-tenant respeita business_id global scope.
 * Não há retenção compartilhada entre businesses.
 *
 * Valores parametrizáveis via env pra clientes com requisitos diferenciados
 * (ex: cliente regulado financeiro pode pedir 180d em vez de 90d).
 *
 * @see memory/decisions/0155-module-grade-rubrica-v3.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Mensagens (whatsapp_messages)
    |--------------------------------------------------------------------------
    | Conteúdo de mensagem é dado pessoal (LGPD Art. 5º I). Default 90 dias
    | balanceia (a) histórico operacional pra atendimento recorrente vs
    | (b) minimização de dados.
    */
    'messages_retention_days' => (int) env('WHATSAPP_MESSAGES_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Mídia (storage/app/whatsapp/media/{biz}/...)
    |--------------------------------------------------------------------------
    | Áudios/imagens/PDFs vinculados a mensagens. Default 30 dias —
    | mais agressivo que mensagens (volume grande, custo storage).
    */
    'media_retention_days' => (int) env('WHATSAPP_MEDIA_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Audit log (mcp_audit_log filtrado por module=whatsapp)
    |--------------------------------------------------------------------------
    | Auditoria de ações operacionais (envios, falhas, bans). Default 365d
    | atende fiscalização interna + Marco Civil Art. 15 (180d mínimo logs).
    */
    'audit_log_retention_days' => (int) env('WHATSAPP_AUDIT_LOG_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Snapshots métricas (whatsapp_conversation_metricas)
    |--------------------------------------------------------------------------
    | Agregações por dia/canal. Default 730d (2 anos) pra comparativo YoY.
    | Sem dado pessoal — só counts + médias.
    */
    'metrics_retention_days' => (int) env('WHATSAPP_METRICS_RETENTION_DAYS', 730),

    /*
    |--------------------------------------------------------------------------
    | CSAT (whatsapp_csat_responses)
    |--------------------------------------------------------------------------
    | Score + comentário cliente. Default 365d pra trend NPS-style anual.
    */
    'csat_retention_days' => (int) env('WHATSAPP_CSAT_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Memory do cliente (whatsapp_customer_memory)
    |--------------------------------------------------------------------------
    | Perfil consolidado. Default 90d alinha com mensagens — sem mensagens
    | recentes, não há razão pra manter perfil enriquecido.
    */
    'customer_memory_retention_days' => (int) env('WHATSAPP_CUSTOMER_MEMORY_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Retention enabled (kill-switch)
    |--------------------------------------------------------------------------
    | Quando false, comando whatsapp:retention-purge é no-op (log only).
    | Default true em prod; pode ser false em homolog/dev.
    */
    'enabled' => (bool) env('WHATSAPP_RETENTION_ENABLED', true),
];
