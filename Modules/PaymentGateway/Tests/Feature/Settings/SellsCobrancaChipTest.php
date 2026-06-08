<?php

declare(strict_types=1);

use App\Business;
use App\Role;
use App\Transaction;
use App\User;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Spatie\Permission\Models\Permission;

/**
 * Pest GUARDs — Sells drawer chip cobrança F3 PaymentGateway UI Tela 3.
 *
 * 4 GUARDs:
 *   1) SellController@sheetData retorna shape cobranca: kind=none quando sem cobrança
 *   2) SellController@sheetData retorna shape cobranca: kind=paid quando cobrança paga
 *   3) SellController@sheetData retorna shape cobranca: kind=overdue quando vencida
 *   4) Tier 0: cobrança de outro business NÃO aparece no shape (cross-tenant)
 *
 * ADR 0144 + ADR 0170 + ADR 0093 + ADR 0101.
 */

beforeEach(function () {
    setPermissionsTeamId(1);

    $this->business = Business::query()->firstOrCreate(
        ['id' => 1],
        ['name' => 'Test HQ', 'currency_id' => 1],
    );

    // direct_sell.view é a permission UPOS pra sheetData
    Permission::firstOrCreate(['name' => 'direct_sell.view', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(
        ['name' => "Admin#{$this->business->id}", 'business_id' => $this->business->id, 'guard_name' => 'web'],
    );
    $role->syncPermissions(['direct_sell.view']);

    $this->user = User::factory()->create([
        'business_id' => $this->business->id,
        'username' => 'sells_cob_test_'.uniqid(),
    ]);
    $this->user->assignRole($role);

    // Venda canônica pro test
    $this->sale = Transaction::create([
        'business_id' => $this->business->id,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'paid',
        'invoice_no' => 'INV-COB-'.uniqid(),
        'transaction_date' => now(),
        'final_total' => 500.00,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'created_by' => $this->user->id,
    ]);
});

it('shape cobranca retorna kind=none quando venda sem cobrança', function () {
    $response = $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->getJson("/sells/{$this->sale->id}/sheet-data");

    $response->assertOk()
        ->assertJsonPath('cobranca.kind', 'none');
});

it('shape cobranca retorna kind=paid quando cobrança paga existe', function () {
    $cred = PaymentGatewayCredential::create([
        'business_id' => $this->business->id,
        'gateway_key' => 'inter',
        'ambiente' => 'production',
        'ativo' => true,
        'nome_display' => 'Inter Test',
        'config_json' => [],
    ]);

    Cobranca::create([
        'business_id' => $this->business->id,
        'payment_gateway_credential_id' => $cred->id,
        'gateway_external_id' => 'ext-paid-'.uniqid(),
        'tipo' => 'boleto',
        'status' => 'paga',
        'valor_centavos' => 50000,
        'valor_pago_centavos' => 50000,
        'vencimento' => now()->subDays(5)->toDateString(),
        'paga_em' => now()->subDays(3),
        'payer_name' => 'Cliente Teste',
        'origem_type' => 'sale',
        'origem_id' => $this->sale->id,
        'idempotency_key' => 'idem-paid-'.uniqid(),
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->getJson("/sells/{$this->sale->id}/sheet-data");

    $response->assertOk()
        ->assertJsonPath('cobranca.kind', 'paid')
        ->assertJsonPath('cobranca.cob.tipo', 'boleto')
        ->assertJsonPath('cobranca.cob.gateway', 'inter')
        ->assertJsonPath('cobranca.cob.valor', 500.0);
});

it('shape cobranca retorna kind=overdue quando emitida e vencimento passou', function () {
    $cred = PaymentGatewayCredential::create([
        'business_id' => $this->business->id,
        'gateway_key' => 'asaas',
        'ambiente' => 'production',
        'ativo' => true,
        'nome_display' => 'Asaas Test',
        'config_json' => [],
    ]);

    Cobranca::create([
        'business_id' => $this->business->id,
        'payment_gateway_credential_id' => $cred->id,
        'gateway_external_id' => 'ext-overdue-'.uniqid(),
        'tipo' => 'pix_cob',
        'status' => 'emitida',
        'valor_centavos' => 30000,
        'vencimento' => now()->subDays(7)->toDateString(),
        'payer_name' => 'Cliente Atrasado',
        'origem_type' => 'sale',
        'origem_id' => $this->sale->id,
        'idempotency_key' => 'idem-overdue-'.uniqid(),
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->getJson("/sells/{$this->sale->id}/sheet-data");

    $response->assertOk()
        ->assertJsonPath('cobranca.kind', 'overdue');
});

// ─── Onda 4d.5 — Wire-up emissão GUARDs ──────────────────────────────────

it('POST /sells/{id}/emitir-cobranca retorna 404 quando venda de outro business', function () {
    $otherBiz = Business::query()->firstOrCreate(['id' => 99], ['name' => 'Other Biz', 'currency_id' => 1]);

    $otherSale = Transaction::create([
        'business_id' => $otherBiz->id,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'paid',
        'invoice_no' => 'INV-CROSS-'.uniqid(),
        'transaction_date' => now(),
        'final_total' => 100.00,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->postJson("/sells/{$otherSale->id}/emitir-cobranca", [
            'tipo' => 'boleto',
            'vencimento' => now()->addDays(7)->toDateString(),
        ])
        ->assertNotFound();
});

it('POST /sells/{id}/emitir-cobranca retorna 422 quando venda sem contact_id', function () {
    // Sale criado em beforeEach NÃO tem contact_id
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->postJson("/sells/{$this->sale->id}/emitir-cobranca", [
            'tipo' => 'boleto',
            'vencimento' => now()->addDays(7)->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('error', 'Venda sem cliente vinculado — emita cobrança avulsa.');
});

it('POST /sells/{id}/emitir-cobranca exige tipo válido', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->postJson("/sells/{$this->sale->id}/emitir-cobranca", [
            'tipo' => 'cripto_btc',
            'vencimento' => now()->addDays(7)->toDateString(),
        ])
        ->assertStatus(422);
});

it('Tier 0 IRREVOGÁVEL: cobrança de outro business NÃO aparece na venda', function () {
    $otherBiz = Business::query()->firstOrCreate(['id' => 99], ['name' => 'Other Biz', 'currency_id' => 1]);

    $credOther = PaymentGatewayCredential::withoutGlobalScopes()->create([
        'business_id' => $otherBiz->id,
        'gateway_key' => 'inter',
        'ambiente' => 'production',
        'ativo' => true,
        'nome_display' => 'Outro',
        'config_json' => [],
    ]);

    // Cobrança apontando pra venda do biz=1 MAS com business_id=99 (impossível em
    // produção, mas defensivo — global scope deve excluir).
    Cobranca::withoutGlobalScopes()->create([
        'business_id' => $otherBiz->id,
        'payment_gateway_credential_id' => $credOther->id,
        'gateway_external_id' => 'ext-cross-'.uniqid(),
        'tipo' => 'boleto',
        'status' => 'paga',
        'valor_centavos' => 99999,
        'vencimento' => now()->toDateString(),
        'paga_em' => now(),
        'payer_name' => 'NEVER SHOULD APPEAR',
        'origem_type' => 'sale',
        'origem_id' => $this->sale->id,
        'idempotency_key' => 'idem-cross-'.uniqid(),
    ]);

    $response = $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->getJson("/sells/{$this->sale->id}/sheet-data");

    $response->assertOk()
        ->assertJsonPath('cobranca.kind', 'none'); // global scope filtra biz=99
});
