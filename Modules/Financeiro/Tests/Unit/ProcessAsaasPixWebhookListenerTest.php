<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Listeners\ProcessAsaasPixWebhookListener;
use Modules\Financeiro\Services\FinanceiroAuditLogger;
use Modules\Financeiro\Services\Integrations\AsaasPixAutomaticoService;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Wave 28-5 — sanity tests do listener Pix Automático.
 *
 * **Foco unit** — testa só os caminhos sem DB (filtros + idempotency lock + skip).
 * Path completo (criar ExtratoLancamento + atualizar Subscription) cai em Feature
 * test futuro com DB real (W28-5b).
 *
 * Rodar local:
 *   vendor/bin/pest Modules/Financeiro/Tests/Unit/ProcessAsaasPixWebhookListenerTest.php
 */

beforeEach(function () {
    config()->set('financeiro.asaas.pix_automatico_enabled', true);
    Cache::flush();

    $this->listener = new ProcessAsaasPixWebhookListener(
        new FinanceiroAuditLogger(new PiiRedactor()),
        new AsaasPixAutomaticoService(
            new FinanceiroAuditLogger(new PiiRedactor()),
            apiKey: 'fake',
            environment: 'sandbox',
        ),
    );
});

function makeEvent(int $bizId, array $payload): object {
    return new class($bizId, $payload) {
        public function __construct(public int $businessId, public array $payload) {}
    };
}

test('ignora eventos que não são PAYMENT_RECEIVED/CONFIRMED', function () {
    Log::spy();

    $this->listener->handle(makeEvent(99, [
        'event' => 'PAYMENT_CREATED',
        'payment' => ['id' => 'pay_001', 'billingType' => 'PIX'],
    ]));

    // Espera log debug "event_skipped" e nada mais
    Log::shouldHaveReceived('debug')
        ->withArgs(fn ($msg) => str_contains($msg, 'event_skipped'))
        ->once();
});

test('ignora pagamentos billingType != PIX (ex: BOLETO)', function () {
    Log::spy();

    $this->listener->handle(makeEvent(99, [
        'event' => 'PAYMENT_RECEIVED',
        'payment' => ['id' => 'pay_001', 'billingType' => 'BOLETO'],
    ]));

    // Não deve logar processed nem skipped específico — só retorna
    Log::shouldNotHaveReceived('info', function ($msg) {
        return str_contains($msg ?? '', 'pix_received_processed');
    });

    expect(true)->toBeTrue();
});

test('idempotency lock impede processamento duplo do mesmo payment.id', function () {
    Log::spy();

    $payload = [
        'event' => 'PAYMENT_RECEIVED',
        'payment' => [
            'id' => 'pay_duplicate_001',
            'billingType' => 'PIX',
            'value' => 199.90,
            'netValue' => 195.00,
            'paymentDate' => '2026-05-17',
            'subscription' => 'sub_xxx',
        ],
    ];

    // Marca já processado
    Cache::add('financeiro:asaas:pix:processed:99:pay_duplicate_001', 'now', 60);

    $this->listener->handle(makeEvent(99, $payload));

    // Espera log idempotency_hit
    Log::shouldHaveReceived('info')
        ->withArgs(fn ($msg) => str_contains($msg, 'idempotency_hit'))
        ->once();
});

test('warning quando payment.id ausente', function () {
    Log::spy();

    $this->listener->handle(makeEvent(99, [
        'event' => 'PAYMENT_RECEIVED',
        'payment' => ['billingType' => 'PIX'],
    ]));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($msg) => str_contains($msg, 'missing_payment_id'))
        ->once();
});
