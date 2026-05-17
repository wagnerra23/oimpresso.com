<?php

return [
    'name' => 'Financeiro',
    'module_version' => '0.1.0',

    /**
     * Juros de mora padrão BR (config por business pode override).
     * 0,033% ao dia (~1% ao mês) + 2% multa pós-vencimento.
     */
    'juros_mora_diario' => 0.0033,
    'multa_atraso' => 0.02,

    /**
     * Asaas — gateway de cobrança SaaS BR.
     *
     * Wave 28-5: Pix Automático (subscriptions billingType=PIX). Default
     * disabled — Wagner habilita por business após validação sandbox.
     *
     * @see Modules\Financeiro\Services\Integrations\AsaasPixAutomaticoService
     */
    'asaas' => [
        'pix_automatico_enabled' => env('ASAAS_PIX_AUTOMATICO_ENABLED', false),
        'api_key' => env('ASAAS_API_KEY'),
        'webhook_secret' => env('ASAAS_WEBHOOK_SECRET'),
        'environment' => env('ASAAS_ENV', 'sandbox'),
    ],
];
