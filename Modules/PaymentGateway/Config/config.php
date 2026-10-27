<?php

return [
    'name' => 'PaymentGateway',
    'module_version' => '0.1.0',

    /*
    |--------------------------------------------------------------------------
    | Retry de webhooks órfãos — cron paymentgateway:retry-orphan-webhooks
    |--------------------------------------------------------------------------
    | Default OFF (REGRA MESTRE valor/estoque — o Job quita título via
    | CobrancaPaga = mexe em VALOR). Só habilitar APÓS: (1) cutover dos webhooks
    | genéricos Onda 3, (2) linkage cobranca_id no WebhookProcessor (hoje a
    | tabela gateway_webhook_events nasce com cobranca_id NULL → branch de
    | quitação inalcançável), (3) dry-run aprovado pelo Wagner. Ver
    | app/Console/Kernel.php e RetryOrphanWebhookCommand.
    */
    'retry_orphan_webhooks_enabled' => env('PAYMENTGATEWAY_RETRY_ORPHAN_WEBHOOKS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retorno do banco lança o pagamento na venda (US-PCP-025 · UC-FLX-28)
    |--------------------------------------------------------------------------
    | Default OFF (REGRA MESTRE valor). Ligado, a cobrança paga de uma venda
    | (origem_type='sale') vira transaction_payments da venda (LancarPagamentoDaCobrancaNaVenda),
    | e o pagamento libera a OS que espera o sinal. Antes de ligar: conferir se a empresa
    | registra esses pagamentos à mão (o listener recusa o que não cabe no saldo, mas a ordem
    | importa: se o retorno chegar antes do lançamento manual, o manual vira o duplicado).
    */
    'lancar_pagamento_na_venda' => env('PAYMENTGATEWAY_LANCAR_PAGAMENTO_NA_VENDA', false),
    /*
    |--------------------------------------------------------------------------
    | Maquininha integrada ao caixa — TEF (US-SELL-075)
    |--------------------------------------------------------------------------
    | Default OFF. A camada é independente da adquirente: o driver vem de
    | `PAYMENTGATEWAY_TEF_DRIVER` (hoje só `simulador`, para homologação). O driver
    | real depende da escolha do integrador e do contrato, decisão [W] registrada
    | em memory/requisitos/Sells/BRIEFING.md.
    | `PAYMENTGATEWAY_TEF_BIZ` = lista de empresas (vazia = todas, se ligado).
    */
    'tef' => [
        'enabled'      => env('PAYMENTGATEWAY_TEF_ENABLED', false),
        'business_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('PAYMENTGATEWAY_TEF_BIZ', ''))
        ))),
        'driver'       => env('PAYMENTGATEWAY_TEF_DRIVER', 'simulador'),
    ],
];
