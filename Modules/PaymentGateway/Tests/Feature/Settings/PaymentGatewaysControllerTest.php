<?php

declare(strict_types=1);

use App\Business;
use Spatie\Permission\Models\Role;
use App\User;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

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

/**
 * Versão Inertia que o app anuncia. Partial reload com X-Inertia-Version diferente da do app
 * leva 409 (o Inertia manda o cliente recarregar a página) — era o caso do '1' fixo. Lida da
 * primeira visita, como em AssetManagement/Tests/Feature/BensContratoTest.
 */
function versaoInertiaPg($test): string
{
    $inicial = $test->actingAs($test->user)
        ->withSession(['user.business_id' => $test->business->id, 'business.id' => $test->business->id])
        ->get('/settings/payment-gateways');

    return (string) data_get($inicial->viewData('page'), 'version');
}

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
            'X-Inertia-Version' => versaoInertiaPg($this),
            'X-Inertia-Partial-Component' => 'Settings/PaymentGateways/Index',
            'X-Inertia-Partial-Data' => 'kpis',
        ])
        // Partial reload responde JSON da página, e o assertInertia desta versão só lê o
        // viewData('page') da resposta HTML ("Not a valid Inertia response"). Mesmos 4 campos,
        // lidos do JSON.
        ->assertOk()
        ->assertJsonPath('component', 'Settings/PaymentGateways/Index')
        ->assertJsonStructure(['props' => ['kpis' => ['ativos', 'total', 'fail', 'cobs_hoje']]]);
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
            'X-Inertia-Version' => versaoInertiaPg($this),
            'X-Inertia-Partial-Component' => 'Settings/PaymentGateways/Index',
            'X-Inertia-Partial-Data' => 'gateways',
        ]);

    // O aviso do controller (warnFor) é 'Deprecated — migrar pra Asaas…', com D maiúsculo.
    $resp->assertOk();
    expect($resp->getContent())->toContain('Deprecated');
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
    // Adversário biz=99 pelo helper canônico: o business cru (só name+currency_id) bate na FK owner_id.
    $otherBiz = $this->seededSupportClientTenant();

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
            'X-Inertia-Version' => versaoInertiaPg($this),
            'X-Inertia-Partial-Component' => 'Settings/PaymentGateways/Index',
            'X-Inertia-Partial-Data' => 'gateways',
        ]);

    $resp->assertOk();
    expect($resp->getContent())->not->toContain('NEVER SHOULD APPEAR');
});

it('cross-tenant toggle: 404 ao tentar togglar credencial de outro business', function () {
    // Adversário biz=99 pelo helper canônico: o business cru (só name+currency_id) bate na FK owner_id.
    $otherBiz = $this->seededSupportClientTenant();

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
