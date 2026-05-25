<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT', '/auth/google/callback'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT', '/auth/microsoft/callback'),
        'tenant' => env('MICROSOFT_TENANT_ID', 'common'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Asaas — flags de segurança financeira
    |--------------------------------------------------------------------------
    |
    | Credenciais Asaas (api_key, ambiente) vivem em `rb_boleto_credentials`
    | criptografadas por tenant — NÃO ficam aqui.
    |
    | Aqui só vive a flag global pra refund (estorno de cobrança JÁ PAGA).
    | Default FALSE — em prod o estorno mexe com dinheiro real do pagador
    | e exige aprovação humana. Ativado apenas após validação em homologação
    | (US futura com botão admin + auditoria).
    |
    | Quando false, RefundCobrancaAsaasJob:
    |   - NÃO chama POST /api/v3/payments/{id}/refund
    |   - Loga "TODO ativar ASAAS_REFUND_ENABLED" + retorna
    |   - Garante que ambiente prod fica seguro até Wagner liberar
    */
    'asaas' => [
        'refund_enabled' => (bool) env('ASAAS_REFUND_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inter PJ — shared infra Banking API + PIX (US-RB-045..047, US-RB-050..051)
    |--------------------------------------------------------------------------
    |
    | ⚠️ Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): credenciais por tenant
    | (client_id/client_secret/cert paths/secret_webhook/chave_pix) ficam em
    | `rb_boleto_credentials.config_json` criptografadas. NÃO use estas envs
    | pra credencial real de produção.
    |
    | As envs aqui só atendem (a) sandbox local de dev (Wagner cobrança
    | imediata smoke), (b) ambientes onde só existe 1 tenant (single-instance
    | self-host), e (c) defaults compartilhados (api_base_url, webhook_hmac
    | de validação assinatura quando o tenant não cadastrou o seu).
    |
    | InterPixCobrancaService (US-RB-050) e InterWebhookController (US-RB-051)
    | priorizam SEMPRE config_json do BoletoCredential do tenant; estes valores
    | são fallback dev-only.
    */
    'inter' => [
        'client_id' => env('INTER_CLIENT_ID'),
        'client_secret' => env('INTER_CLIENT_SECRET'),
        'certificate_path' => env('INTER_CERT_PATH'),
        'private_key_path' => env('INTER_KEY_PATH'),
        'api_base_url' => env('INTER_API_BASE', 'https://cdpj.partners.bancointer.com.br'),
        'webhook_hmac' => env('INTER_WEBHOOK_HMAC'),
        'pix_chave' => env('INTER_PIX_CHAVE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack — webhooks de alerta operacional (gap Onda 6 Sells smoke)
    |--------------------------------------------------------------------------
    |
    | Incoming Webhook URL pra notificar time MCP quando smoke automatizado
    | detecta drift. NÃO usar pra credencial Slack OAuth (App-level token);
    | aqui é simples HTTP POST sem auth — Slack valida via URL secreta.
    |
    | Default null = notificação Slack desabilitada (smoke ainda loga ALERT
    | em Log::channel('single')->error). Ativar em prod via .env Hostinger:
    |
    |     SLACK_SMOKE_WEBHOOK_URL=https://hooks.slack.com/services/T.../B.../...
    |
    | Compatível com Mattermost e Discord (formato Slack-compatible block kit).
    | Comando caller: app/Console/Commands/Sells/SmokeDailyCommand.php
    */
    'slack' => [
        'smoke_webhook_url' => env('SLACK_SMOKE_WEBHOOK_URL'),
    ],

    /*
    | Consent banner LGPD (ADR 0191) — pré-req Microsoft Clarity / GA4 / Pixel.
    | Versionar `cookie_name` (_v1 → _v2) força reapresentação do banner.
    */
    'consent' => [
        'cookie_name'     => 'oimpresso_consent_v1',
        'cookie_ttl_days' => (int) env('CONSENT_COOKIE_TTL_DAYS', 365),
        'categories'      => ['necessary', 'analytics', 'marketing'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Microsoft Clarity (session replay + heatmaps) — ADR 0191 LGPD-compliant
    |--------------------------------------------------------------------------
    |
    | Setup: criar projeto em https://clarity.microsoft.com → anotar PROJECT_ID
    | Ativação: CLARITY_ENABLED=true + CLARITY_PROJECT_ID=xxx em .env produção
    | Default OFF — sem ativação manual Wagner, snippet NÃO carrega em lugar
    | nenhum (zero risco de tracking sem opt-in).
    |
    | Multi-tenant: 1 projeto Clarity global + custom tag `business_id` no JS
    | (filtragem nativa no dashboard). NUNCA criar 1 projeto por business.
    |
    | mask_strategy:
    |   - 'mask-all' (default LGPD-safe) → mascarariza TODO texto/input;
    |     unmask seletivo via `data-clarity-unmask="True"` em elementos seguros.
    |   - 'mask-none' → captura tudo (NÃO usar em prod com PII de cliente).
    */
    'clarity' => [
        'enabled'       => (bool) env('CLARITY_ENABLED', false),
        'project_id'    => env('CLARITY_PROJECT_ID'),
        'mask_strategy' => env('CLARITY_MASK_STRATEGY', 'mask-all'),
    ],

];
