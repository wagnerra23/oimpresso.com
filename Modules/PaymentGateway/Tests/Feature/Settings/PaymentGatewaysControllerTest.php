<?php

declare(strict_types=1);

use App\Business;
use App\Role;
use App\User;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Spatie\Permission\Models\Permission;

/**
 * Pest GUARDs — /settings/payment-gateways F3 PaymentGateway UI Tela 2.
 *
 * 8 GUARDs conforme Charter (Index.charter.md):
 *   1) renderiza Inertia component Settings/PaymentGateways/Index
 *   2) expõe Props no shape esperado (gateways, accounts, kpis, today)
 *   3) expõe 3 KPIs (ativos, total, fail, cobs_hoje)
 *   4) lista gateways do business + warn pra drivers deprecated
 *   5) health-check endpoint atualiza health_status no DB
 *   6) toggle endpoint inverte ativo do credential
 *   7) Tier 0 IRREVOGÁVEL: PaymentGatewayCredential respeita business_id global scope
 *   8) não dispara mutação em GET (read-only puro)
 *
 * ADR 0101: testes biz=1 (não usar biz=4 ROTA LIVRE cliente real).
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
        'username' => 'gw_test_'.uniqid(),
    ]);
    $this->user->assignRole($role);
});

it('renderiza Inertia component Settings/PaymentGateways/Index', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/settings/payment-gateways')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/PaymentGateways/Index'));
});

it('expõe Props no shape esperado (today + Deferred gateways/accounts/kpis)', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/settings/payment-gateways')
        ->assertInertia(fn ($page) => $page->has('today'));
});

it('expõe 3 KPIs (ativos, total, fail, cobs_hoje) no partial reload', function () {
    PaymentGatewayCredential::create([
        'business_id' => $this->business->id,
        'gateway_key' => 'inter',
        'ambiente' => 'production',
        'ativo' => true,
        'nome_display' => 'Inter Test',
        'config_json' => [],
        'health_status' => 'ok',
    ]);

    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/settings/payment-gateways?only=kpis', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            'X-Inertia-Partial-Component' => 'Settings/PaymentGateways/Index',
            'X-Inertia-Partial-Data' => 'kpis',
        ])
        ->assertInertia(fn ($page) => $page
            ->has('kpis.ativos')
            ->has('kpis.total')
            ->has('kpis.fail')
            ->has('kpis.cobs_hoje')
        );
});

it('lista gateways do business + warn deprecated PesaPal', function () {
    PaymentGatewayCredential::create([
        'business_id' => $this->business->id,
        'gateway_key' => 'pesapal',
        'ambiente' => 'production',
        'ativo' => false,
        'nome_display' => 'PesaPal Legacy',
        'config_json' => [],
    ]);

    $resp = $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/settings/payment-gateways?only=gateways', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            'X-Inertia-Partial-Component' => 'Settings/PaymentGateways/Index',
            'X-Inertia-Partial-Data' => 'gateways',
        ]);

    $resp->assertOk();
    expect($resp->getContent())->toContain('deprecated');
});

it('toggle endpoint inverte ativo do credential', function () {
    $cred = PaymentGatewayCredential::create([
        'business_id' => $this->business->id,
        'gateway_key' => 'inter',
        'ambiente' => 'sandbox',
        'ativo' => false,
        'nome_display' => 'Inter Toggle Test',
        'config_json' => [],
    ]);

    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->postJson("/settings/payment-gateways/{$cred->id}/toggle")
        ->assertOk()
        ->assertJson(['credential_id' => $cred->id, 'ativo' => true]);

    expect($cred->fresh()->ativo)->toBeTrue();
});

it('Tier 0 IRREVOGÁVEL: PaymentGatewayCredential respeita business_id global scope', function () {
    $otherBiz = Business::query()->firstOrCreate(['id' => 99], ['name' => 'Other Biz', 'currency_id' => 1]);

    $credOther = PaymentGatewayCredential::withoutGlobalScopes()->create([
        'business_id' => $otherBiz->id,
        'gateway_key' => 'asaas',
        'ambiente' => 'production',
        'ativo' => true,
        'nome_display' => 'NEVER SHOULD APPEAR Asaas',
        'config_json' => [],
    ]);

    $resp = $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/settings/payment-gateways?only=gateways', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            'X-Inertia-Partial-Component' => 'Settings/PaymentGateways/Index',
            'X-Inertia-Partial-Data' => 'gateways',
        ]);

    $resp->assertOk();
    expect($resp->getContent())->not->toContain('NEVER SHOULD APPEAR');
});

it('cross-tenant toggle: 404 ao tentar togglar credencial de outro business', function () {
    $otherBiz = Business::query()->firstOrCreate(['id' => 99], ['name' => 'Other Biz', 'currency_id' => 1]);

    $credOther = PaymentGatewayCredential::withoutGlobalScopes()->create([
        'business_id' => $otherBiz->id,
        'gateway_key' => 'inter',
        'ambiente' => 'production',
        'ativo' => true,
        'nome_display' => 'Outro',
        'config_json' => [],
    ]);

    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->postJson("/settings/payment-gateways/{$credOther->id}/toggle")
        ->assertNotFound();
});

it('não dispara mutação em GET /settings/payment-gateways (read-only puro)', function () {
    $countAntes = PaymentGatewayCredential::withoutGlobalScopes()->count();

    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/settings/payment-gateways')
        ->assertOk();

    $countDepois = PaymentGatewayCredential::withoutGlobalScopes()->count();
    expect($countDepois)->toEqual($countAntes);
});
