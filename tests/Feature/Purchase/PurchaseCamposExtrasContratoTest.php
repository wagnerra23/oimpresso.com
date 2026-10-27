<?php

declare(strict_types=1);

use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\Support\EstoqueFixture;

/**
 * Prazo de pagamento, detalhes do frete e campos personalizados na compra React.
 *
 * UC-PURCRE-18 — a Nova compra mostra os campos personalizados que a empresa configurou e grava
 * prazo, frete e esses campos.
 * UC-PUREDT-11 — o Edit recebe os valores gravados para mostrar e devolver no update.
 *
 * POR QUE EXISTE: esses campos só existiam na Blade; entram no React antes do cutover, pedido de
 * [W] em 2026-10-27. Sem eles o cutover perderia os campos que a empresa configurou.
 *
 * Tenant 98 (ADR 0358). Nunca biz=1, nunca biz=4.
 *
 * @covers-uc UC-PURCRE-18
 * @covers-uc UC-PUREDT-11
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS (MySQL) ausente — roda na lane purchase-pest / CT 100.');
    }
    Event::fake([App\Events\PurchaseCreatedOrModified::class]);

    $this->biz = EstoqueFixture::businessId();
    DB::table('business')->where('id', $this->biz)->update(['custom_labels' => json_encode([
        'purchase' => ['custom_field_1' => 'Pedido do fornecedor', 'is_custom_field_1_required' => '1'],
        'purchase_shipping' => ['custom_field_2' => 'Transportadora'],
    ])]);

    $role = Role::firstOrCreate(['name' => 'Admin#' . $this->biz, 'guard_name' => 'web'], ['business_id' => $this->biz]);
    $this->user = App\User::factory()->create([
        'business_id' => $this->biz,
        'username' => 'pce_' . uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $this->user->assignRole($role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->location = EstoqueFixture::locationId($this->biz, '-PCE');
    $this->produto = EstoqueFixture::singleProduct($this->biz);
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz,
        'type' => 'supplier',
        'name' => 'Fornecedor PCE',
        'contact_id' => 'CT-PCE-' . uniqid(),
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->versao = file_exists(public_path('build-inertia/manifest.json')) ? md5_file(public_path('build-inertia/manifest.json')) : '1';
});

it('UC-PURCRE-18 a Nova compra recebe só os campos com rótulo, com o obrigatório marcado', function () {
    $this->actingAs($this->user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versao])
        ->get('/purchases/create')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Purchase/Create', false)
            ->where('campos_extras.compra', [['campo' => 'custom_field_1', 'rotulo' => 'Pedido do fornecedor', 'obrigatorio' => true]])
            ->where('campos_extras.frete', [['campo' => 'shipping_custom_field_2', 'rotulo' => 'Transportadora', 'obrigatorio' => false]]));
});

it('UC-PURCRE-18 a compra grava prazo, detalhes do frete e os campos personalizados', function () {
    $ref = 'PCE-' . uniqid();
    $this->actingAs($this->user)->post('/purchases', [
        'ref_no' => $ref,
        'status' => 'received',
        'contact_id' => $this->contactId,
        'transaction_date' => '2026-10-27 10:00',
        'location_id' => $this->location,
        'exchange_rate' => '1',
        'total_before_tax' => '40',
        'discount_type' => 'fixed',
        'discount_amount' => '0',
        'tax_amount' => '0',
        'shipping_charges' => '0',
        'final_total' => '40',
        'pay_term_number' => '30',
        'pay_term_type' => 'days',
        'shipping_details' => 'CIF por conta do fornecedor',
        'custom_field_1' => 'OC-1',
        'shipping_custom_field_2' => 'Transportadora Y',
        'purchases' => [[
            'product_id' => $this->produto->productId,
            'variation_id' => $this->produto->variationId(),
            'product_unit_id' => $this->produto->unitId,
            'quantity' => '4',
            'pp_without_discount' => '10',
            'discount_percent' => '0',
            'purchase_price' => '10',
            'purchase_price_inc_tax' => '10',
            'item_tax' => '0',
            'purchase_line_tax_id' => '',
        ]],
    ]);

    $tx = Transaction::where('business_id', $this->biz)->where('type', 'purchase')->where('ref_no', $ref)->first();
    expect($tx)->not->toBeNull();
    expect((int) $tx->pay_term_number)->toBe(30);
    expect($tx->pay_term_type)->toBe('days');
    expect($tx->shipping_details)->toBe('CIF por conta do fornecedor');
    expect($tx->custom_field_1)->toBe('OC-1');
    expect($tx->shipping_custom_field_2)->toBe('Transportadora Y');

    // UC-PUREDT-11: o Edit recebe os valores gravados.
    $this->actingAs($this->user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versao])
        ->get("/purchases/{$tx->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Purchase/Edit', false)
            ->where('purchase.extras.pay_term_number', '30')
            ->where('purchase.extras.pay_term_type', 'days')
            ->where('purchase.extras.custom_field_1', 'OC-1')
            ->where('purchase.extras.shipping_custom_field_2', 'Transportadora Y')
            ->has('campos_extras.compra', 1));
});
