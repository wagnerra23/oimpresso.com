<?php

declare(strict_types=1);

use App\Business;
use App\Role;
use App\User;
use Carbon\CarbonImmutable;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Pest GUARDs — GET /settings/payment-gateways/{id}/quota (Onda 4e gap #3).
 *
 * 3 GUARDs:
 *   1) retorna 200 + counts agrupados por tipo quando credencial tem cobranças no mês
 *   2) Tier 0 IRREVOGÁVEL: credencial de outro business → 404 (zero vaza count cross-tenant)
 *   3) mês anterior NÃO conta (só created_at do mês corrente)
 *
 * Gap fechado: estado-da-arte 2026-05-23 catalogou "Quota tracking" como gap P1 —
 * Wagner descobre quota Inter (250 grátis) ou C6 (200 grátis) estourada só quando
 * vê tarifa cobrada. MVP é só contagem real-time (query agregada, sem contador
 * persistido).
 *
 * Refs: ADR 0093 (Tier 0), ADR 0170 (PaymentGateway), audit 2026-05-23.
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
        'username'    => 'gwq_test_'.uniqid(),
    ]);
    $this->user->assignRole($role);

    $this->credential = PaymentGatewayCredential::create([
        'business_id'   => $this->business->id,
        'gateway_key'   => 'inter',
        'ambiente'      => 'sandbox',
        'nome_display'  => 'Inter Sandbox Quota Test',
        'config_json'   => ['client_id' => 'fake', 'client_secret' => 'fake'],
        'ativo'         => true,
        'health_status' => 'unknown',
    ]);
});

/**
 * Helper: cria cobranças mockadas no DB pra credencial dada.
 * Bypassa requirement de gateway real — Cobranca::create() não chama API.
 */
function mkCobranca(int $businessId, int $credentialId, string $tipo, ?CarbonImmutable $createdAt = null): Cobranca
{
    $cob = Cobranca::create([
        'business_id'                   => $businessId,
        'payment_gateway_credential_id' => $credentialId,
        'tipo'                          => $tipo,
        'status'                        => 'emitida',
        'valor_centavos'                => 10000,
        'vencimento'                    => CarbonImmutable::now()->addDays(7)->toDateString(),
        'descricao'                     => 'Teste quota tracking',
        'idempotency_key'               => 'quota-test-'.uniqid('', true),
    ]);

    if ($createdAt !== null) {
        // Forçar created_at em mês anterior — bypassa $timestamps automáticos
        Cobranca::query()->where('id', $cob->id)->update(['created_at' => $createdAt]);
    }

    return $cob;
}

it('retorna 200 + counts agrupados por tipo quando credencial tem cobranças no mês', function () {
    mkCobranca($this->business->id, $this->credential->id, 'boleto');
    mkCobranca($this->business->id, $this->credential->id, 'boleto');
    mkCobranca($this->business->id, $this->credential->id, 'boleto');
    mkCobranca($this->business->id, $this->credential->id, 'pix_cob');
    mkCobranca($this->business->id, $this->credential->id, 'pix_cob');

    $response = $this->actingAs($this->user)
        ->withSession([
            'user.business_id' => $this->business->id,
            'business.id'      => $this->business->id,
        ])
        ->getJson("/settings/payment-gateways/{$this->credential->id}/quota");

    $response->assertOk()
        ->assertJsonStructure(['month', 'counts', 'total', 'gateway_key']);

    $payload = $response->json();
    expect($payload['gateway_key'])->toBe('inter');
    expect($payload['month'])->toBe(CarbonImmutable::now()->format('Y-m'));
    expect($payload['total'])->toBe(5);
    expect($payload['counts']['boleto'] ?? 0)->toBe(3);
    expect($payload['counts']['pix_cob'] ?? 0)->toBe(2);
});

it('Tier 0: credencial de outro business → 404 (não vaza count cross-tenant)', function () {
    // Cria business 2 + credencial nele
    $otherBiz = Business::query()->firstOrCreate(
        ['id' => 2],
        ['name' => 'Other HQ', 'currency_id' => 1],
    );

    $otherCred = PaymentGatewayCredential::withoutGlobalScopes()->create([
        'business_id'   => $otherBiz->id,
        'gateway_key'   => 'c6',
        'ambiente'      => 'sandbox',
        'config_json'   => ['api_key' => 'fake_other'],
        'ativo'         => true,
        'health_status' => 'unknown',
    ]);

    // Cria cobranças no business 2 sem global scope
    Cobranca::withoutGlobalScopes()->create([
        'business_id'                   => $otherBiz->id,
        'payment_gateway_credential_id' => $otherCred->id,
        'tipo'                          => 'boleto',
        'status'                        => 'emitida',
        'valor_centavos'                => 10000,
        'vencimento'                    => CarbonImmutable::now()->addDays(7)->toDateString(),
        'descricao'                     => 'biz2 cobranca',
        'idempotency_key'               => 'quota-cross-'.uniqid('', true),
    ]);

    // User do biz=1 tenta acessar quota da credencial do biz=2
    $response = $this->actingAs($this->user)
        ->withSession([
            'user.business_id' => $this->business->id,
            'business.id'      => $this->business->id,
        ])
        ->getJson("/settings/payment-gateways/{$otherCred->id}/quota");

    $response->assertNotFound()
        ->assertJson(['counts' => [], 'total' => 0, 'gateway_key' => null]);
});

it('mês anterior NÃO conta (só created_at do mês corrente)', function () {
    // 2 cobranças no mês corrente
    mkCobranca($this->business->id, $this->credential->id, 'boleto');
    mkCobranca($this->business->id, $this->credential->id, 'pix_cob');

    // 5 cobranças backdated pro mês anterior — NÃO devem contar
    $lastMonth = CarbonImmutable::now()->subMonthNoOverflow()->setDay(15)->setTime(12, 0);
    for ($i = 0; $i < 5; $i++) {
        mkCobranca($this->business->id, $this->credential->id, 'boleto', $lastMonth);
    }

    $response = $this->actingAs($this->user)
        ->withSession([
            'user.business_id' => $this->business->id,
            'business.id'      => $this->business->id,
        ])
        ->getJson("/settings/payment-gateways/{$this->credential->id}/quota");

    $response->assertOk();
    $payload = $response->json();
    expect($payload['total'])->toBe(2); // só as 2 do mês corrente
    expect($payload['counts']['boleto'] ?? 0)->toBe(1);
    expect($payload['counts']['pix_cob'] ?? 0)->toBe(1);
});
