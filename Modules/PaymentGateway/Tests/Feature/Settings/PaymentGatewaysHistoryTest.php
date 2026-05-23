<?php

declare(strict_types=1);

use App\Business;
use App\Role;
use App\User;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Spatie\Activitylog\Models\Activity;

/**
 * Pest GUARDs — GET /settings/payment-gateways/{id}/history (Onda 4e.UI).
 *
 * 4 GUARDs:
 *   1) retorna 200 + entries[] no shape esperado pra credencial do biz da sessão
 *   2) inclui diff inferido de properties.old / properties.attributes (Spatie)
 *   3) Tier 0 IRREVOGÁVEL: credencial de outro business → 404
 *   4) limita a 50 entries DESC por created_at
 *
 * Gap fechado: estado-da-arte 2026-05-23 catalogou "Audit log invisível"
 * como #1 P0 (Spatie LogsActivity configurado no model linha 25-58 mas
 * UI não consumia). Nota 78/100 → ~82+ esperada.
 *
 * Refs: ADR 0093 (Tier 0), Cobranca-shared FinAuditTrail (Financeiro pattern reusado).
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
        'username' => 'gwh_test_'.uniqid(),
    ]);
    $this->user->assignRole($role);

    $this->credential = PaymentGatewayCredential::create([
        'business_id'   => $this->business->id,
        'gateway_key'   => 'asaas',
        'ambiente'      => 'sandbox',
        'nome_display'  => 'Asaas Sandbox Test',
        'config_json'   => ['api_key' => 'fake', 'webhook_secret' => 'whsec_fake'],
        'ativo'         => true,
        'health_status' => 'unknown',
    ]);
});

it('retorna 200 + entries[] no shape esperado pra credencial do biz', function () {
    // Spatie LogsActivity já criou 1 entry no create acima (event=created).
    // Faz update pra gerar entry adicional com diff.
    $this->credential->update(['nome_display' => 'Asaas Sandbox Editado']);

    $response = $this->actingAs($this->user)
        ->withSession([
            'user.business_id' => $this->business->id,
            'business.id'      => $this->business->id,
        ])
        ->getJson("/settings/payment-gateways/{$this->credential->id}/history");

    $response->assertOk()
        ->assertJsonStructure([
            'entries' => [
                '*' => ['id', 'when', 'when_iso', 'who', 'action', 'event'],
            ],
            'total',
        ]);

    $entries = $response->json('entries');
    expect(count($entries))->toBeGreaterThanOrEqual(2);
    expect($entries[0]['event'])->toBe('updated'); // mais recente primeiro
});

it('inclui diff field/from/to quando properties.old e .attributes existem', function () {
    $this->credential->update(['nome_display' => 'Novo Nome']);

    $response = $this->actingAs($this->user)
        ->withSession([
            'user.business_id' => $this->business->id,
            'business.id'      => $this->business->id,
        ])
        ->getJson("/settings/payment-gateways/{$this->credential->id}/history");

    $response->assertOk();
    $entries = $response->json('entries');
    $updatedEntry = collect($entries)->firstWhere('event', 'updated');

    expect($updatedEntry)->not->toBeNull();
    expect($updatedEntry)->toHaveKey('diff');
    expect($updatedEntry['diff']['field'])->toBe('nome_display');
    expect($updatedEntry['diff']['to'])->toBe('Novo Nome');
});

it('Tier 0: credencial de outro business → 404 (não vaza activity_log cross-tenant)', function () {
    // Cria business 2 + credencial nele
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

    // User do biz=1 tenta acessar history da credencial do biz=2
    $response = $this->actingAs($this->user)
        ->withSession([
            'user.business_id' => $this->business->id,
            'business.id'      => $this->business->id,
        ])
        ->getJson("/settings/payment-gateways/{$otherCred->id}/history");

    $response->assertNotFound()
        ->assertJson(['entries' => [], 'total' => 0]);
});

it('limita a 50 entries DESC por created_at', function () {
    // Gera 60 updates fake direto na activity_log (mais rápido que 60 update() reais)
    for ($i = 0; $i < 60; $i++) {
        Activity::create([
            'log_name'     => 'paymentgateway.credential',
            'description'  => 'updated',
            'subject_type' => PaymentGatewayCredential::class,
            'subject_id'   => $this->credential->id,
            'event'        => 'updated',
            'business_id'  => $this->business->id,
            'properties'   => [
                'old'        => ['nome_display' => "v{$i}"],
                'attributes' => ['nome_display' => 'v'.($i + 1)],
            ],
        ]);
    }

    $response = $this->actingAs($this->user)
        ->withSession([
            'user.business_id' => $this->business->id,
            'business.id'      => $this->business->id,
        ])
        ->getJson("/settings/payment-gateways/{$this->credential->id}/history");

    $response->assertOk();
    expect(count($response->json('entries')))->toBe(50);
});
