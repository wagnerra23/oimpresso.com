<?php

declare(strict_types=1);

use Modules\PaymentGateway\Models\GatewayWebhookEvent;

uses(Tests\TestCase::class);

/**
 * Onda 3 — ADR 0170.
 *
 * Smoke dos 4 endpoints de webhook + idempotência.
 *
 * Conserva pattern RB AsaasWebhookIdempotencyTest:
 *   - 1ª chamada cria evento + retorna duplicate=false
 *   - 2ª chamada mesmo gateway_event_id retorna duplicate=true sem inserir
 *   - Cross-gateway mesmo gateway_event_id OK (não UNIQUE violation)
 *
 * business_id vem do path — TestCase não usa session (webhooks externos).
 *
 * ADR 0101: business_id = 1 (nunca cliente real).
 */

it('Inter webhook cria GatewayWebhookEvent na 1ª chamada', function () {
    $response = $this->postJson('/paymentgateway/webhooks/inter/1', [
        'evento' => 'cobranca.paga',
        'txid'   => 'inter-tx-001',
        'valor'  => 100.00,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'duplicate' => false]);

    $event = GatewayWebhookEvent::query()->withoutGlobalScopes()->first();
    expect($event)->not->toBeNull();
    expect($event->business_id)->toBe(1);
    expect($event->gateway_key)->toBe('inter');
    expect($event->evento)->toBe('cobranca.paga');
    expect($event->gateway_event_id)->toBe('inter-tx-001');
    expect($event->signature_valid)->toBeFalse(); // Onda 4 driver valida
    expect($event->processed_at)->toBeNull();
});

it('Inter webhook duplicado retorna duplicate=true sem inserir 2x', function () {
    $payload = ['evento' => 'cobranca.paga', 'txid' => 'inter-dup-001'];

    $this->postJson('/paymentgateway/webhooks/inter/1', $payload)->assertOk();
    $this->postJson('/paymentgateway/webhooks/inter/1', $payload)
        ->assertOk()
        ->assertJson(['ok' => true, 'duplicate' => true]);

    expect(GatewayWebhookEvent::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('Mesmo event_id em gateways DIFERENTES NÃO colide (UNIQUE inclui gateway_key)', function () {
    $this->postJson('/paymentgateway/webhooks/inter/1', [
        'evento' => 'cobranca.paga',
        'txid'   => 'shared-id',
    ])->assertOk();

    $this->postJson('/paymentgateway/webhooks/asaas/1', [
        'event'   => 'PAYMENT_RECEIVED',
        'id'      => 'shared-id',
        'payment' => ['id' => 'p1'],
    ])->assertOk()->assertJson(['duplicate' => false]);

    expect(GatewayWebhookEvent::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('Mesmo gateway_event_id em businesses DIFERENTES NÃO colide (UNIQUE inclui business_id)', function () {
    $payload = ['evento' => 'cobranca.paga', 'txid' => 'cross-biz-id'];

    $this->postJson('/paymentgateway/webhooks/inter/1', $payload)->assertOk();
    $this->postJson('/paymentgateway/webhooks/inter/99', $payload)
        ->assertOk()
        ->assertJson(['duplicate' => false]);

    expect(GatewayWebhookEvent::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('Asaas webhook extrai event_id de id ou payment.id', function () {
    // Sem `id` top-level — usa fallback `event:payment.id`
    $this->postJson('/paymentgateway/webhooks/asaas/1', [
        'event'   => 'PAYMENT_CONFIRMED',
        'payment' => ['id' => 'p123'],
    ])->assertOk();

    $event = GatewayWebhookEvent::query()->withoutGlobalScopes()->first();
    expect($event->gateway_key)->toBe('asaas');
    expect($event->gateway_event_id)->toBe('PAYMENT_CONFIRMED:p123');
});

it('C6 webhook usa transactionId', function () {
    $this->postJson('/paymentgateway/webhooks/c6/1', [
        'eventType'     => 'PAYMENT_OK',
        'transactionId' => 'c6-tx-999',
    ])->assertOk();

    $event = GatewayWebhookEvent::query()->withoutGlobalScopes()->first();
    expect($event->gateway_key)->toBe('c6');
    expect($event->gateway_event_id)->toBe('c6-tx-999');
});

it('BCB Pix webhook (novo) cria evento corretamente', function () {
    $this->postJson('/paymentgateway/webhooks/bcb-pix/1', [
        'evento' => 'PIX_RECEBIDO',
        'txid'   => 'bcb-tx-001',
    ])->assertOk();

    $event = GatewayWebhookEvent::query()->withoutGlobalScopes()->first();
    expect($event->gateway_key)->toBe('bcb_pix');
    expect($event->evento)->toBe('PIX_RECEBIDO');
});

it('Webhook sem id/txid usa fallback md5 (não trava)', function () {
    $this->postJson('/paymentgateway/webhooks/inter/1', [
        'evento' => 'cobranca.paga',
        // sem id, sem txid, sem nossoNumero
    ])->assertOk()->assertJson(['duplicate' => false]);

    expect(GatewayWebhookEvent::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('Webhook routes NÃO exigem auth (sem auth middleware)', function () {
    // Sem login, sem session — deve passar.
    auth()->logout();

    $this->postJson('/paymentgateway/webhooks/inter/1', [
        'evento' => 'test',
        'txid'   => 'no-auth-test',
    ])->assertOk(); // se exigisse auth, retornaria 302 ou 401
});
