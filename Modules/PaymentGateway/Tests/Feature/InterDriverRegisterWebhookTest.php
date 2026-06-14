<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\InterDriver;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Cobertura do gap descoberto 2026-06-03 — `InterDriver` faltava método
 * pra registrar URL do webhook no Inter (PUT /cobranca/v3/cobrancas/webhook).
 *
 * Sem ele, `Webhooks/InterWebhookController` ficava órfão (Inter não sabia
 * pra onde mandar). Ver session log 2026-06-03 + ADR 0170.
 *
 * Pattern: Http::fake captura request, valida URL+body+headers; mTLS é bypass
 * em test conforme convenção da Onda 4a (ver InterDriver::mtlsOptions).
 */

beforeEach(function () {
    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter Test',
        'config_json'  => [
            'client_id'     => 'fake-client',
            'client_secret' => 'fake-secret',
        ],
    ]);
});

it('chama PUT /cobranca/v3/cobrancas/webhook com webhookUrl no body', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
        '*/cobranca/v3/cobrancas/webhook' => Http::response('', 204),
    ]);

    $driver = app(InterDriver::class);
    $url = 'https://oimpresso.com/paymentgateway/webhooks/inter/1';

    $ok = $driver->registerWebhook($this->cred, $url);

    expect($ok)->toBeTrue();
    Http::assertSent(function ($request) use ($url) {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/cobranca/v3/cobrancas/webhook')
            && $request->data() === ['webhookUrl' => $url];
    });
});

it('retorna false quando Inter responde 4xx', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
        '*/cobranca/v3/cobrancas/webhook' => Http::response(['error' => 'invalid_scope'], 403),
    ]);

    $driver = app(InterDriver::class);

    $ok = $driver->registerWebhook($this->cred, 'https://oimpresso.com/paymentgateway/webhooks/inter/1');

    expect($ok)->toBeFalse();
});

it('rejeita credential de outro gateway', function () {
    $asaasCred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'asaas',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Asaas Test',
        'config_json'  => ['client_id' => 'x', 'client_secret' => 'y'],
    ]);

    $driver = app(InterDriver::class);

    expect(fn () => $driver->registerWebhook($asaasCred, 'https://x'))
        ->toThrow(\Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException::class);
});

it('consultarWebhook retorna URL atual quando Inter responde 200', function () {
    $url = 'https://oimpresso.com/paymentgateway/webhooks/inter/1';
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
        '*/cobranca/v3/cobrancas/webhook' => Http::response(['webhookUrl' => $url], 200),
    ]);

    $driver = app(InterDriver::class);

    expect($driver->consultarWebhook($this->cred))->toBe($url);
});

it('consultarWebhook retorna null quando Inter responde 404 (sem webhook registrado)', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
        '*/cobranca/v3/cobrancas/webhook' => Http::response('', 404),
    ]);

    $driver = app(InterDriver::class);

    expect($driver->consultarWebhook($this->cred))->toBeNull();
});
