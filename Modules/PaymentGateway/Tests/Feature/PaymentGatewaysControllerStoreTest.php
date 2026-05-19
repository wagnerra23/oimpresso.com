<?php

declare(strict_types=1);

use App\Account;
use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class);

/**
 * Pest — POST /settings/payment-gateways store endpoint (Onda 5 completar wizard).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS + PaymentGateway.');
    }
    if (!Schema::hasTable('payment_gateway_credentials') || !Schema::hasTable('accounts')) {
        $this->markTestSkipped('Schema PaymentGateway/accounts ausente.');
    }
});

it('store cria credencial Inter sandbox com sucesso', function () {
    $user = User::factory()->create(['business_id' => 1]);

    $payload = [
        'gateway_key' => 'inter',
        'ambiente'    => 'sandbox',
        'nome_display' => 'Inter Sandbox Test ' . uniqid(),
        'conta_bancaria_id' => null,
        'config_json' => [
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-secret',
        ],
        'ativo' => true,
    ];

    $response = $this->actingAs($user)
        ->withSession(['user.business_id' => 1])
        ->postJson('/settings/payment-gateways', $payload);

    $response->assertCreated();
    $response->assertJsonStructure(['success', 'credential_id', 'gateway_key']);

    $credId = $response->json('credential_id');
    $cred = PaymentGatewayCredential::withoutGlobalScopes()->find($credId);
    expect($cred)->not->toBeNull();
    expect($cred->business_id)->toBe(1);
    expect($cred->gateway_key)->toBe('inter');
    expect($cred->ambiente)->toBe('sandbox');
    expect($cred->config_json['client_id'])->toBe('test-client-id');

    PaymentGatewayCredential::withoutGlobalScopes()->where('id', $credId)->forceDelete();
});

it('store rejeita duplicate (business_id, gateway_key, ambiente)', function () {
    $user = User::factory()->create(['business_id' => 1]);
    $existing = PaymentGatewayCredential::create([
        'business_id' => 1,
        'gateway_key' => 'asaas',
        'ambiente' => 'sandbox',
        'nome_display' => 'pre-existing',
        'config_json' => ['api_key' => 'pre'],
        'ativo' => true,
        'health_status' => 'unknown',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['user.business_id' => 1])
        ->postJson('/settings/payment-gateways', [
            'gateway_key' => 'asaas',
            'ambiente' => 'sandbox',
            'nome_display' => 'dup test',
            'config_json' => ['api_key' => 'dup'],
        ]);

    $response->assertStatus(422);

    $existing->forceDelete();
});

it('store rejeita conta_bancaria_id de outro business (Tier 0)', function () {
    $user = User::factory()->create(['business_id' => 1]);
    $otherBiz = Business::firstOrCreate(['id' => 99999], ['name' => 'Outro Tenant', 'currency_id' => 1]);
    $otherAccount = Account::create([
        'business_id' => 99999,
        'name' => 'Conta de outro biz',
        'account_number' => 'DUMMY-' . uniqid(),
        'created_by' => 1,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['user.business_id' => 1])
        ->postJson('/settings/payment-gateways', [
            'gateway_key' => 'inter',
            'ambiente' => 'sandbox',
            'nome_display' => 'tier-0 cross-tenant attempt',
            'conta_bancaria_id' => $otherAccount->id,
            'config_json' => ['client_id' => 'x', 'client_secret' => 'y'],
        ]);

    $response->assertStatus(403);

    $otherAccount->forceDelete();
});

it('store valida gateway_key enum', function () {
    $user = User::factory()->create(['business_id' => 1]);

    $response = $this->actingAs($user)
        ->withSession(['user.business_id' => 1])
        ->postJson('/settings/payment-gateways', [
            'gateway_key' => 'foobar_invalid',
            'ambiente' => 'sandbox',
            'config_json' => ['x' => 'y'],
        ]);

    $response->assertStatus(422);
});
