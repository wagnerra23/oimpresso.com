<?php

declare(strict_types=1);

use App\Business;
use App\Role;
use App\User;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Pest GUARDs — GET /settings/payment-gateways/{id}/webhook-events (Onda 4e.UI #2).
 *
 * 4 GUARDs:
 *   1) retorna 200 + events[] no shape esperado pra credencial do biz da sessão
 *   2) NÃO expõe payload (PII guard — só metadados)
 *   3) Tier 0 IRREVOGÁVEL: credencial de outro business → 404
 *   4) limita a 50 events DESC por created_at + filtra por credential_id
 *
 * Gap fechado: estado-da-arte 2026-05-23 catalogou "Webhook delivery log invisível"
 * como #2 P0. Tabela gateway_webhook_events existia desde Onda 2 mas UI não consumia.
 *
 * Refs: ADR 0093 (Tier 0), ADR 0170 Onda 4e.UI.
 */

beforeEach(function () {
    setPermissionsTeamId(1);

    $this->business = Business::query()->firstOrCreate(
        ['id' => 1],
        ['name' => 'Test HQ', 'currency_id' => 1],
    );

    $role = Role::firstOrCreate(
        ['name' => "Admin#{$this->business->id}", 'business_id' => $this->business->id, 'guard_name' => 'web'],
    );

    $this->user = User::factory()->create([
        'business_id' => $this->business->id,
        'username' => 'gwe_test_'.uniqid(),
    ]);
    $this->user->assignRole($role);

    $this->credential = PaymentGatewayCredential::create([
        'business_id'   => $this->business->id,
        'gateway_key'   => 'asaas',
        'ambiente'      => 'sandbox',
        'nome_display'  => 'Asaas Sandbox Test',
        'config_json'   => ['api_key' => 'fake'],
        'ativo'         => true,
        'health_status' => 'unknown',
    ]);
});

it('retorna 200 + events[] no shape esperado', function () {
    GatewayWebhookEvent::create([
        'business_id'                   => $this->business->id,
        'payment_gateway_credential_id' => $this->credential->id,
        'gateway_key'                   => 'asaas',
        'evento'                        => 'PAYMENT_RECEIVED',
        'gateway_event_id'              => 'evt_abc123',
        'cobranca_id'                   => null,
        'payload'                       => ['payment' => ['id' => 'pay_xyz'], 'event' => 'PAYMENT_RECEIVED'],
        'signature_valid'               => true,
        'processed_at'                  => now(),
        'error_message'                 => null,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession([
            'user.business_id' => $this->business->id,
            'business.id'      => $this->business->id,
        ])
        ->getJson("/settings/payment-gateways/{$this->credential->id}/webhook-events");

    $response->assertOk()
        ->assertJsonStructure([
            'events' => [
                '*' => ['id', 'when', 'when_iso', 'evento', 'gateway_event_id', 'signature_valid', 'processed_at', 'error_message', 'cobranca_id'],
            ],
            'total',
        ])
        ->assertJsonPath('events.0.evento', 'PAYMENT_RECEIVED')
        ->assertJsonPath('events.0.signature_valid', true);
});

it('NÃO expõe payload completo (LGPD/PCI guard)', function () {
    GatewayWebhookEvent::create([
        'business_id'                   => $this->business->id,
        'payment_gateway_credential_id' => $this->credential->id,
        'gateway_key'                   => 'asaas',
        'evento'                        => 'PAYMENT_RECEIVED',
        'gateway_event_id'              => 'evt_secret_payload',
        'payload'                       => ['secret_data' => 'must_not_leak', 'cpf' => '12345678900'],
        'signature_valid'               => true,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession([
            'user.business_id' => $this->business->id,
            'business.id'      => $this->business->id,
        ])
        ->getJson("/settings/payment-gateways/{$this->credential->id}/webhook-events");

    $response->assertOk();
    $body = $response->getContent();
    expect($body)->not->toContain('must_not_leak');
    expect($body)->not->toContain('12345678900');
    expect($body)->not->toContain('payload'); // Field name não deve aparecer
});

it('Tier 0: credencial de outro business → 404', function () {
    $otherBiz = Business::query()->firstOrCreate(
        ['id' => 2],
        ['name' => 'Other HQ', 'currency_id' => 1],
    );
    $otherCred = PaymentGatewayCredential::withoutGlobalScopes()->create([
        'business_id'   => $otherBiz->id,
        'gateway_key'   => 'asaas',
        'ambiente'      => 'sandbox',
        'config_json'   => ['api_key' => 'fake_other'],
        'ativo'         => true,
        'health_status' => 'unknown',
    ]);

    // Cria evento em outro business pra garantir que nem com query direta vaza
    GatewayWebhookEvent::withoutGlobalScopes()->create([
        'business_id'                   => $otherBiz->id,
        'payment_gateway_credential_id' => $otherCred->id,
        'gateway_key'                   => 'asaas',
        'evento'                        => 'PAYMENT_RECEIVED',
        'gateway_event_id'              => 'evt_other',
        'payload'                       => ['event' => 'PAYMENT_RECEIVED'],
        'signature_valid'               => true,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession([
            'user.business_id' => $this->business->id,
            'business.id'      => $this->business->id,
        ])
        ->getJson("/settings/payment-gateways/{$otherCred->id}/webhook-events");

    $response->assertNotFound()
        ->assertJson(['events' => [], 'total' => 0]);
});

it('limita a 50 events DESC por created_at + filtra por credential_id', function () {
    // 60 eventos pra esta credencial
    for ($i = 0; $i < 60; $i++) {
        GatewayWebhookEvent::create([
            'business_id'                   => $this->business->id,
            'payment_gateway_credential_id' => $this->credential->id,
            'gateway_key'                   => 'asaas',
            'evento'                        => 'PAYMENT_RECEIVED',
            'gateway_event_id'              => "evt_{$i}",
            'payload'                       => ['i' => $i],
            'signature_valid'               => true,
        ]);
    }

    // Outra credencial mesmo biz com 1 evento — não deve aparecer
    $otherCred = PaymentGatewayCredential::create([
        'business_id'   => $this->business->id,
        'gateway_key'   => 'inter',
        'ambiente'      => 'sandbox',
        'config_json'   => ['client_id' => 'fake'],
        'ativo'         => true,
        'health_status' => 'unknown',
    ]);
    GatewayWebhookEvent::create([
        'business_id'                   => $this->business->id,
        'payment_gateway_credential_id' => $otherCred->id,
        'gateway_key'                   => 'inter',
        'evento'                        => 'NAO_DEVE_APARECER',
        'gateway_event_id'              => 'evt_outra_cred',
        'payload'                       => [],
        'signature_valid'               => true,
    ]);

    $response = $this->actingAs($this->user)
        ->withSession([
            'user.business_id' => $this->business->id,
            'business.id'      => $this->business->id,
        ])
        ->getJson("/settings/payment-gateways/{$this->credential->id}/webhook-events");

    $response->assertOk();
    $events = $response->json('events');
    expect(count($events))->toBe(50);
    // Garante que NÃO contém evento de outra credential
    expect(collect($events)->pluck('evento')->contains('NAO_DEVE_APARECER'))->toBeFalse();
});
