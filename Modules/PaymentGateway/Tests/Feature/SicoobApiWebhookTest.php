<?php

declare(strict_types=1);

use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class);

/**
 * US-FIN-044 PR4 — Onda 4f.sicoob_api webhook receiver.
 *
 * Cobre:
 *   1. Signature HMAC `x-sicoob-signature` válida → 200 + GatewayWebhookEvent
 *   2. Signature inválida → 401 + nada gravado
 *   3. Credential inexistente pra business_id → 404
 *   4. Credential sem webhook_secret cadastrado → 401 (fail-secure)
 *   5. Idempotência: 2x mesmo eventId → 1 linha só (UNIQUE)
 *   6. business_id isolado biz=4 vs biz=99 (multi-tenant Tier 0)
 *
 * Multi-tenant Tier 0: biz=1 padrão (ADR 0101 — nunca cliente real).
 */

beforeEach(function () {
    $this->credential = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'sicoob_api',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Sicoob API Test',
        'config_json'  => [
            'client_id'         => 'fake-client',
            'client_secret'     => 'fake-secret',
            'numero_cliente'    => 12345,
            'codigo_modalidade' => 1,
            'numero_conta'      => 1234567,
            'webhook_secret'    => 'super-secret-sicoob-hmac',
        ],
    ]);
});

function postSicoobWebhook($test, int $businessId, array $payload, string $signature)
{
    return $test->call(
        method: 'POST',
        uri: "/paymentgateway/webhooks/sicoob-api/{$businessId}",
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X_SICOOB_SIGNATURE' => $signature,
            'HTTP_ACCEPT'              => 'application/json',
        ],
        content: json_encode($payload),
    );
}

function postSicoobWebhookSigned($test, int $businessId, array $payload, string $secret = 'super-secret-sicoob-hmac')
{
    $raw = json_encode($payload);
    $signature = hash_hmac('sha256', $raw, $secret);

    return postSicoobWebhook($test, $businessId, $payload, $signature);
}

it('aceita webhook com HMAC válido e grava GatewayWebhookEvent', function () {
    $payload = [
        'evento'      => 'cobranca.liquidada',
        'nossoNumero' => '12345678',
        'boleto'      => [
            'situacaoBoleto' => 'LIQUIDADO',
            'valorRecebido'  => 150.00,
            'dataLiquidacao' => '2026-05-27T10:00:00-03:00',
        ],
    ];

    postSicoobWebhookSigned($this, 1, $payload)->assertStatus(200);

    expect(GatewayWebhookEvent::query()->withoutGlobalScopes()->count())->toBe(1);
    $event = GatewayWebhookEvent::query()->withoutGlobalScopes()->first();
    expect($event->gateway_key)->toBe('sicoob_api')
        ->and($event->business_id)->toBe(1)
        ->and($event->evento)->toBe('cobranca.liquidada')
        ->and($event->gateway_event_id)->toBe('12345678')
        ->and($event->signature_valid)->toBeTrue();
});

it('rejeita 401 quando signature HMAC inválida', function () {
    postSicoobWebhook($this, 1, ['evento' => 'x', 'nossoNumero' => '1'], 'assinatura-falsa-deadbeef')
        ->assertStatus(401)
        ->assertJsonFragment(['error' => 'signature_invalid']);

    expect(GatewayWebhookEvent::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('rejeita 404 quando business_id não tem credential sicoob_api ativa', function () {
    postSicoobWebhookSigned($this, 9999, ['evento' => 'x', 'nossoNumero' => '1'])
        ->assertStatus(404)
        ->assertJsonFragment(['error' => 'credential_not_found']);
});

it('rejeita 401 quando credential sem webhook_secret cadastrado (fail-secure)', function () {
    $this->credential->update([
        'config_json' => array_merge($this->credential->config_json, ['webhook_secret' => '']),
    ]);

    postSicoobWebhookSigned($this, 1, ['evento' => 'x', 'nossoNumero' => '1'])
        ->assertStatus(401);
});

it('idempotente — 2x mesmo eventId/nossoNumero NÃO duplica em DB', function () {
    $payload = [
        'evento'      => 'cobranca.liquidada',
        'nossoNumero' => '999888',
    ];

    postSicoobWebhookSigned($this, 1, $payload)->assertStatus(200);
    postSicoobWebhookSigned($this, 1, $payload)->assertStatus(200);

    // UNIQUE (business_id, gateway_key, gateway_event_id) → só 1 linha.
    expect(GatewayWebhookEvent::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('multi-tenant Tier 0: biz=4 e biz=99 webhooks ISOLADOS', function () {
    $secret4 = 'secret-biz-4';
    $secret99 = 'secret-biz-99';

    PaymentGatewayCredential::query()->create([
        'business_id'  => 4,
        'gateway_key'  => 'sicoob_api',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'client_id'         => 'c4',
            'client_secret'     => 's4',
            'numero_cliente'    => 4,
            'codigo_modalidade' => 1,
            'numero_conta'      => 4,
            'webhook_secret'    => $secret4,
        ],
    ]);
    PaymentGatewayCredential::query()->create([
        'business_id'  => 99,
        'gateway_key'  => 'sicoob_api',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'client_id'         => 'c99',
            'client_secret'     => 's99',
            'numero_cliente'    => 99,
            'codigo_modalidade' => 1,
            'numero_conta'      => 99,
            'webhook_secret'    => $secret99,
        ],
    ]);

    // biz=4 com secret correto → 200
    postSicoobWebhookSigned($this, 4, ['evento' => 'x', 'nossoNumero' => 'biz4-001'], $secret4)
        ->assertStatus(200);

    // biz=99 usando SECRET DO biz=4 → 401 (não vaza segredo entre tenants)
    postSicoobWebhookSigned($this, 99, ['evento' => 'x', 'nossoNumero' => 'biz99-001'], $secret4)
        ->assertStatus(401);

    // biz=99 com secret correto → 200
    postSicoobWebhookSigned($this, 99, ['evento' => 'x', 'nossoNumero' => 'biz99-001'], $secret99)
        ->assertStatus(200);

    // Eventos separados por business_id
    expect(GatewayWebhookEvent::query()->withoutGlobalScopes()->where('business_id', 4)->count())->toBe(1)
        ->and(GatewayWebhookEvent::query()->withoutGlobalScopes()->where('business_id', 99)->count())->toBe(1);
});

it('rota nomeada paymentgateway.webhooks.sicoob-api existe', function () {
    expect(route('paymentgateway.webhooks.sicoob-api', ['businessId' => 1]))
        ->toContain('/paymentgateway/webhooks/sicoob-api/1');
});
