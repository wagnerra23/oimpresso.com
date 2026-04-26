<?php

/**
 * Modulo: Woocommerce — sync hooks (webhook + sync admin).
 *
 * Endpoints publicos (sem middleware web/auth):
 *   - POST /webhook/order-created/{business_id}
 *   - POST /webhook/order-updated/{business_id}
 *   - POST /webhook/order-deleted/{business_id}
 *   - POST /webhook/order-restored/{business_id}
 *
 * Cobertura:
 *   - assinatura HMAC invalida -> webhook ignora silenciosamente (200/4xx, sem 500)
 *   - business_id inexistente -> nao crasha
 *   - JSON malformado -> nao crasha
 *
 * Endpoints admin (/woocommerce/*) usam stack web+auth -> redirect 302 sem login.
 *
 * NAO tentamos exercitar sync real contra Woo externo — testes ficam em payload-shape.
 */

beforeEach(function () {
    session()->flush();
    auth()->logout();
});

it('POST /webhook/order-created/{biz} sem assinatura nao crasha (4xx ou silenciado)', function () {
    $r = $this->postJson('/webhook/order-created/1', ['id' => 12345]);

    // WoocommerceWebhookController::isValidWebhookRequest valida HMAC do header
    // X-WC-Webhook-Signature; sem isso, request eh rejeitada cedo. Aceitamos
    // qualquer status que nao seja 500 (server error indicaria bug).
    expect($r->getStatusCode())->toBeLessThan(500);
});

it('POST /webhook/order-updated/{biz} com payload vazio nao crasha', function () {
    $r = $this->postJson('/webhook/order-updated/1', []);
    expect($r->getStatusCode())->toBeLessThan(500);
});

it('POST /webhook/order-deleted/{biz} aceita JSON malformado sem 500', function () {
    $r = $this->call('POST', '/webhook/order-deleted/1', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT'  => 'application/json',
    ], '{not-json}');

    expect($r->getStatusCode())->toBeLessThan(500);
});

it('POST /webhook/order-restored/{biz} com business_id inexistente nao crasha', function () {
    $r = $this->postJson('/webhook/order-restored/9999999', ['id' => 1]);
    expect($r->getStatusCode())->toBeLessThan(500);
});

it('rotas admin /woocommerce/* exigem auth (302 sem login)', function () {
    foreach (['/woocommerce', '/woocommerce/api-settings', '/woocommerce/sync-products'] as $url) {
        $r = $this->get($url);
        expect($r->getStatusCode())->toBeIn([302, 401, 403]);
    }
});

it('POST /woocommerce/update-api-settings exige auth (302 sem login)', function () {
    $r = $this->post('/woocommerce/update-api-settings', [
        'woocommerce_app_url' => 'https://exemplo.com',
        'woocommerce_consumer_key' => 'ck_invalido',
        'woocommerce_consumer_secret' => 'cs_invalido',
    ]);
    expect($r->getStatusCode())->toBeIn([302, 401, 403, 419]);
});

it('GET /woocommerce/get-log-details/{id} inexistente devolve 302 sem auth', function () {
    $r = $this->get('/woocommerce/get-log-details/9999999');
    expect($r->getStatusCode())->toBeIn([302, 401, 403, 404]);
});
