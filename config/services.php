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

];
