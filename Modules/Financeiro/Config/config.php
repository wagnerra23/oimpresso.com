<?php

return [
    'name' => 'Financeiro',
    'module_version' => '0.1.0',

    /**
     * DDA — boletos emitidos contra o CNPJ da empresa viram contas a pagar
     * SUGERIDAS (US-FIN-078). Desligado: a fonte dos boletos (API do banco ou
     * provedor de DDA) depende de decisão e credencial do [W] — ver BRIEFING.
     */
    'dda' => [
        'enabled' => env('FINANCEIRO_DDA_ENABLED', false), // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    ],

    /**
     * Juros de mora padrão BR (config por business pode override).
     * 0,033% ao dia (~1% ao mês) + 2% multa pós-vencimento.
     */
    'juros_mora_diario' => 0.0033,
    'multa_atraso' => 0.02,

    /**
     * Pagar título a pagar pelo banco — boleto e Pix (US-FIN-079). Desligado:
     * exige escopos de pagamento na integração do banco (ação do [W] — BRIEFING).
     * Mesmo ligado, cada pagamento é aprovado pelo usuário; nunca automático.
     */
    'pagamento_banco' => [
        'enabled' => env('FINANCEIRO_PAGAMENTO_BANCO_ENABLED', false), // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    ],

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

    /**
     * US-FIN-081 — link para o cliente pagar o título a receber (Pix ou boleto).
     *
     * Desligado por padrão. Desligado, a rota pública responde 404, o operador não gera link e a
     * baixa automática segue só na empresa dona do SaaS, como antes.
     */
    'link_pagamento' => [
        'enabled' => env('FINANCEIRO_LINK_PAGAMENTO_ENABLED', false),
        'dias_validade' => 30,
    ],
];
