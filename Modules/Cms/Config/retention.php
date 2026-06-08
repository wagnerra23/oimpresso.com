<?php

/**
 * Retention LGPD — Modules/Cms (D7.c canon — Wave 11 Onda).
 *
 * Política de retenção declarada (não aplicada automaticamente — RUNBOOK separado
 * via comando artisan agendado faz o purge respeitando estes valores).
 *
 * Base legal LGPD Art. 16: dados pessoais devem ser eliminados após o término do
 * tratamento, exceto quando necessário para cumprimento de obrigação legal,
 * exercício regular de direitos em processo judicial, ou anonimização.
 *
 * Valores em DIAS (não meses) — fácil parametrizar via .env quando preciso.
 *
 * Cross-ref:
 * - ADR 0093 (multi-tenant Tier 0) — purge sempre scopado por business_id
 * - ADR 0094 §4 (custo IA tracking) — purge não toca LLM (zero custo)
 * - memory/requisitos/Cms/PII-REDACTION.md — pontos de uso PiiRedactor
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Leads de formulário público (`postContactForm`)
    |--------------------------------------------------------------------------
    |
    | Capturados via `Modules\Cms\Http\Controllers\CmsController@postContactForm`
    | + `NewLeadGeneratedNotification` (envio mail admin).
    |
    | Hoje não persistimos em tabela própria (só envia mail) — quando US futura
    | adicionar `cms_leads` table, o purge job usa este valor.
    |
    | Default: 730 dias (~24 meses) — janela típica de prospecção B2B.
    */
    'leads_days' => env('CMS_RETENTION_LEADS_DAYS', 730),

    /*
    |--------------------------------------------------------------------------
    | Mensagens de contato direto (futuro)
    |--------------------------------------------------------------------------
    |
    | Reservado pra quando houver tabela `cms_contact_messages` persistente
    | (hoje a mensagem só vai por mail). Default: 1095 dias (~36 meses)
    | porque pode virar lead qualificado depois.
    */
    'contacts_days' => env('CMS_RETENTION_CONTACTS_DAYS', 1095),

    /*
    |--------------------------------------------------------------------------
    | Comentários de blog (futuro)
    |--------------------------------------------------------------------------
    |
    | Reservado pra quando módulo Cms ganhar comments. Default: 1825 dias
    | (~5 anos) — limite legal CLT/Marco Civil pra preservar audit chain
    | em conteúdo público com identificação de autor.
    */
    'blog_comments_days' => env('CMS_RETENTION_BLOG_COMMENTS_DAYS', 1825),

    /*
    |--------------------------------------------------------------------------
    | Activity log retenção (spatie/laravel-activitylog)
    |--------------------------------------------------------------------------
    |
    | Audit trail D7.b dos Models CmsPage + CmsSiteDetail + CmsPageMeta.
    | Default: 2555 dias (~7 anos) — alinhado com prazo prescricional civil
    | BR (Código Civil Art. 205) pra disputas sobre conteúdo publicado.
    */
    'activity_log_days' => env('CMS_RETENTION_ACTIVITY_LOG_DAYS', 2555),

];
