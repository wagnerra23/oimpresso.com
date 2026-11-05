<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;

class VerifyCsrfToken extends BaseVerifier
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        '/install/details',
        '/install/post-details',
        '/install/install-alternate',
        '/api/ecom/customers',
        '/api/ecom/orders',
        '/webhook/*',
        // Webhooks de banco (plural — o '/webhook/*' acima não os cobre). O banco não manda
        // token CSRF; quem autentica é o controller: assinatura HMAC (PaymentGateway) ou segredo
        // compartilhado no header `X-Inter-Webhook-Secret` (RecurringBilling), ambos antes de gravar.
        // Em produção (2026-11-05) as duas famílias abaixo devolviam 419.
        'paymentgateway/webhooks/*', // Modules/PaymentGateway/Routes/web.php — Asaas, Inter, C6, BCB Pix, Pagar.me, Sicoob
        'webhooks/inter/*',          // PaymentGateway InterPixWebhookController + RecurringBilling /webhooks/inter/pix/*
    ];
}
