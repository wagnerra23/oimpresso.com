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
];
