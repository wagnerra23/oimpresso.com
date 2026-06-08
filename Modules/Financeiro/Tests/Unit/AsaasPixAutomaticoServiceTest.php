<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Financeiro\Services\FinanceiroAuditLogger;
use Modules\Financeiro\Services\Integrations\AsaasPixAutomaticoService;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Wave 28-5 — sanity tests do service Asaas Pix Automático.
 *
 * **Sem DB / sem credenciais reais:** unit test puro com `Http::fake` +
 * `PiiRedactor` real. NUNCA bater na API Asaas real (mesmo sandbox) em CI.
 *
 * **Sem PII real:** customer/payment ids são sintéticos.
 *
 * Rodar local:
 *   vendor/bin/pest Modules/Financeiro/Tests/Unit/AsaasPixAutomaticoServiceTest.php
 */

beforeEach(function () {
    config()->set('financeiro.asaas.pix_automatico_enabled', true);
    config()->set('financeiro.asaas.api_key', 'fake_key_sandbox');
    config()->set('financeiro.asaas.environment', 'sandbox');
    config()->set('financeiro.asaas.webhook_secret', 'super_secret_sandbox');

    $this->service = new AsaasPixAutomaticoService(
        new FinanceiroAuditLogger(new PiiRedactor()),
        apiKey: 'fake_key_sandbox',
        environment: 'sandbox',
    );
});

test('criarRecorrencia POST /v3/subscriptions com billingType=PIX', function () {
    Http::fake([
        'api-sandbox.asaas.com/v3/subscriptions' => Http::response([
            'id' => 'sub_test_001',
            'status' => 'ACTIVE',
            'billingType' => 'PIX',
            'dateCreated' => '2026-05-17',
        ], 200),
    ]);

    $response = $this->service->criarRecorrencia(99, [
        'customer' => 'cus_test_001',
        'value' => 199.90,
        'cycle' => 'MONTHLY',
        'nextDueDate' => '2026-06-01',
        'externalReference' => 'oimpresso-sub-uuid-001',
    ]);

    expect($response['id'])->toBe('sub_test_001');
    expect($response['status'])->toBe('ACTIVE');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/v3/subscriptions')
            && ($request->data()['billingType'] ?? null) === 'PIX'
            && ($request->data()['cycle'] ?? null) === 'MONTHLY';
    });
});

test('criarRecorrencia lança quando feature flag desabilitada', function () {
    config()->set('financeiro.asaas.pix_automatico_enabled', false);

    $this->service->criarRecorrencia(99, [
        'customer' => 'cus_001',
        'value' => 10.0,
        'cycle' => 'MONTHLY',
        'nextDueDate' => '2026-06-01',
    ]);
})->throws(RuntimeException::class, 'desabilitado');

test('criarRecorrencia lança quando campo obrigatório falta', function () {
    $this->service->criarRecorrencia(99, [
        'customer' => 'cus_001',
        // value ausente
        'cycle' => 'MONTHLY',
        'nextDueDate' => '2026-06-01',
    ]);
})->throws(RuntimeException::class, "'value' ausente");

test('cancelarRecorrencia DELETE retorna true em sucesso', function () {
    Http::fake([
        'api-sandbox.asaas.com/v3/subscriptions/sub_test_001' => Http::response([
            'deleted' => true,
            'id' => 'sub_test_001',
        ], 200),
    ]);

    expect($this->service->cancelarRecorrencia(99, 'sub_test_001'))->toBeTrue();
});

test('configurarWebhookPix valida URL antes de POST', function () {
    $this->service->configurarWebhookPix(99, 'not-a-url');
})->throws(RuntimeException::class, 'callbackUrl inválido');

test('verifyWebhookSignature aceita token simétrico cru (asaas-access-token)', function () {
    expect($this->service->verifyWebhookSignature('{}', 'super_secret_sandbox'))->toBeTrue();
});

test('verifyWebhookSignature aceita HMAC SHA-256 hex do payload', function () {
    $payload = '{"event":"PAYMENT_RECEIVED"}';
    $hmac = hash_hmac('sha256', $payload, 'super_secret_sandbox');

    expect($this->service->verifyWebhookSignature($payload, $hmac))->toBeTrue();
});

test('verifyWebhookSignature rejeita assinatura inválida em tempo constante', function () {
    expect($this->service->verifyWebhookSignature('{}', 'wrong_signature'))->toBeFalse();
});

test('verifyWebhookSignature rejeita quando secret não configurado', function () {
    config()->set('financeiro.asaas.webhook_secret', '');
    expect($this->service->verifyWebhookSignature('{}', 'any'))->toBeFalse();
});

test('listarPagamentos retorna array data do response Asaas', function () {
    Http::fake([
        'api-sandbox.asaas.com/v3/subscriptions/sub_test_001/payments' => Http::response([
            'data' => [
                ['id' => 'pay_001', 'value' => 199.90, 'status' => 'RECEIVED'],
                ['id' => 'pay_002', 'value' => 199.90, 'status' => 'PENDING'],
            ],
            'hasMore' => false,
        ], 200),
    ]);

    $pagamentos = $this->service->listarPagamentos(99, 'sub_test_001');

    expect($pagamentos)->toHaveCount(2);
    expect($pagamentos[0]['id'])->toBe('pay_001');
});

test('request lança RuntimeException em 5xx Asaas', function () {
    Http::fake([
        'api-sandbox.asaas.com/v3/subscriptions' => Http::response(['error' => 'server'], 503),
    ]);

    $this->service->criarRecorrencia(99, [
        'customer' => 'cus_001',
        'value' => 10.0,
        'cycle' => 'MONTHLY',
        'nextDueDate' => '2026-06-01',
    ]);
})->throws(RuntimeException::class, 'Asaas HTTP 503');
