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
];
