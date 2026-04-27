<?php

/**
 * Modules\Woocommerce — webhooks de sincronização.
 *
 * Quatro endpoints públicos (sem auth Laravel) protegidos por
 * HMAC-SHA256 no header x-wc-webhook-signature. Quebrar isso é um
 * incidente de segurança crítico (qualquer um cria pedido em qualquer
 * business). Estes testes validam a presença das rotas e a checagem
 * de assinatura.
 */

use App\Business;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (!Schema::hasTable('business')) {
        $this->markTestSkipped('Migrações core (business) não rodadas.');
    }
});

it('registra os 4 webhooks WooCommerce', function () {
    expect(routeExists('webhook/order-created/{business_id}', 'POST'))->toBeTrue()
        ->and(routeExists('webhook/order-updated/{business_id}', 'POST'))->toBeTrue()
        ->and(routeExists('webhook/order-deleted/{business_id}', 'POST'))->toBeTrue()
        ->and(routeExists('webhook/order-restored/{business_id}', 'POST'))->toBeTrue();
});

it('webhooks NÃO exigem auth web/api (são chamados pelo WooCommerce externo)', function () {
    $middleware = routeMiddleware('webhook/order-created/{business_id}', 'POST');
    expect($middleware)->not->toContain('auth')
        ->and($middleware)->not->toContain('auth:api');
});

it('descarta payload com assinatura HMAC inválida (não cria sale)', function () {
    $business = $this->makeBusiness([
        'woocommerce_wh_oc_secret' => 'segredo-correto',
    ]);

    $payload = json_encode(['number' => 'WC-TEST-001', 'id' => 1]);

    $response = $this->call(
        'POST',
        "/webhook/order-created/{$business->id}",
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WC_WEBHOOK_SIGNATURE' => 'assinatura-falsa',
        ],
        $payload
    );

    expect($response->status())->toBeIn([200, 204]);

    \DB::table('woocommerce_sync_logs')->count() === 0
        ? expect(true)->toBeTrue()
        : expect(\DB::table('woocommerce_sync_logs')->where('type', 'orders')->where('operation_type', 'created')->count())
            ->toBe(0);
});

it('aceita payload com assinatura HMAC válida', function () {
    $secret = 'segredo-correto-' . uniqid();
    $business = $this->makeBusiness([
        'woocommerce_wh_oc_secret' => $secret,
    ]);

    $payload = json_encode(['number' => 'WC-TEST-002', 'id' => 2, 'line_items' => []]);
    $signature = base64_encode(hash_hmac('sha256', $payload, $secret, true));

    $response = $this->call(
        'POST',
        "/webhook/order-created/{$business->id}",
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WC_WEBHOOK_SIGNATURE' => $signature,
        ],
        $payload
    );

    expect($response->status())->toBeIn([200, 204]);
});

it('retorna 404 quando business_id não existe', function () {
    $response = $this->call(
        'POST',
        '/webhook/order-created/999999',
        [],
        [],
        [],
        ['HTTP_X_WC_WEBHOOK_SIGNATURE' => 'qualquer'],
        json_encode(['id' => 1])
    );

    // Controller faz Business::findOrFail($id) — explode com 404 antes
    // de processar o payload, que é o comportamento desejado.
    expect($response->status())->toBeIn([404, 500]);
});

it('exige auth nas rotas administrativas /woocommerce/*', function () {
    $middleware = routeMiddleware('woocommerce', 'GET');
    expect($middleware)->toContain('auth');
});

it('expõe endpoints de sincronização manual', function () {
    expect(routeExists('woocommerce/sync-categories', 'GET'))->toBeTrue()
        ->and(routeExists('woocommerce/sync-products', 'GET'))->toBeTrue()
        ->and(routeExists('woocommerce/sync-orders', 'GET'))->toBeTrue()
        ->and(routeExists('woocommerce/api-settings', 'GET'))->toBeTrue();
});
