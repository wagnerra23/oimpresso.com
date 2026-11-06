<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Contrato Tier 0 (ADR 0093) de duas portas de `PurchaseController` que aceitavam id de outra
 * empresa (auditoria Compras 2026-11-05, lote 0 PR 0.2).
 *
 * A3 — `store()` validava variação, pedido e aprovação, mas NÃO `purchase_line_id`. Compra
 *      nova não tem linha própria, e `ProductUtil::createOrUpdatePurchaseLines` faz
 *      `PurchaseLine::findOrFail` no id cru: a linha de outra empresa era reaproveitada
 *      (movida para a compra nova, com o estoque dela). Parte restante da US-COM-029.
 * A5 — `getPurchaseEntryRow()` não conferia permissão nem empresa: devolvia nome, variações,
 *      estoque e último custo do produto de outra empresa pelo id.
 *
 * Tenants: o usuário é do 98 (fictício, ADR 0358); a vítima é o 99 (fictício do
 * `seededSupportClientTenant()`). Nunca biz=4.
 *
 * @covers-uc UC-PURCRE-14  store recusa linha de compra de outra empresa
 * @covers-uc UC-PURCRE-15  linha de entrada não lê produto nem local de outra empresa
 *
 * @see app/Http/Controllers/PurchaseController.php::store
 * @see app/Http/Controllers/PurchaseController.php::getPurchaseEntryRow
 */
uses(DatabaseTransactions::class);

const SLER_TENANT = 98;

function slerLocation(int $bizId, string $nome): int
{
    $scheme = DB::table('invoice_schemes')->value('id')
        ?: DB::table('invoice_schemes')->insertGetId([
            'business_id' => $bizId, 'name' => 'SLER Scheme', 'scheme_type' => 'blank',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $layout = DB::table('invoice_layouts')->value('id')
        ?: DB::table('invoice_layouts')->insertGetId([
            'business_id' => $bizId, 'name' => 'SLER Layout',
            'created_at' => now(), 'updated_at' => now(),
        ]);

    return (int) DB::table('business_locations')->insertGetId([
        'business_id' => $bizId, 'name' => $nome,
        'location_id' => strtoupper(substr(md5($nome . microtime()), 0, 8)),
        'country' => 'BR', 'state' => 'SC', 'city' => 'CT City', 'zip_code' => '00000000',
        'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Produto com unidade e uma variação. Devolve [product_id, variation_id]. */
function slerProduto(int $bizId, int $userId, string $nome): array
{
    $unit = DB::table('units')->insertGetId([
        'business_id' => $bizId, 'actual_name' => 'Unidade', 'short_name' => 'un',
        'allow_decimal' => 0, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $productId = DB::table('products')->insertGetId([
        'name' => $nome, 'business_id' => $bizId, 'type' => 'single', 'unit_id' => $unit,
        'tax_type' => 'exclusive', 'sku' => 'SLER' . uniqid(), 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $pv = DB::table('product_variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $productId, 'is_dummy' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $variationId = DB::table('variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $productId, 'product_variation_id' => $pv,
        'sub_sku' => 'SLER' . uniqid(), 'default_purchase_price' => 10, 'dpp_inc_tax' => 10,
        'default_sell_price' => 15, 'sell_price_inc_tax' => 15,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$productId, $variationId];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Precisa do schema UltimatePOS em MySQL real (lane purchase-pest.yml).');
    }

    $this->tenant = \App\Business::find(SLER_TENANT);
    expect($this->tenant)->not->toBeNull('Lane sem tenant 98 semeado (ADR 0358).');
    $this->vitima = $this->seededSupportClientTenant();
    expect((int) $this->vitima->id)->not->toBe(SLER_TENANT)->not->toBe(4);

    $this->locPropria = slerLocation(SLER_TENANT, 'SLER Propria');
    $this->locVitima = slerLocation((int) $this->vitima->id, 'SLER Vitima');

    $this->user = User::factory()->create([
        'business_id' => SLER_TENANT, 'username' => 'ct_sler_' . uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    Permission::firstOrCreate(['name' => 'purchase.create', 'guard_name' => 'web']);
    $this->user->givePermissionTo('purchase.create');

    $this->donoVitima = User::factory()->create([
        'business_id' => (int) $this->vitima->id, 'username' => 'ct_sler_v_' . uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// A3 — store() e purchase_line_id
// ─────────────────────────────────────────────────────────────────────────────

/** Linha de compra da vítima, numa compra da vítima. Devolve [line_id, transaction_id]. */
function slerLinhaDaVitima(int $bizId, int $locId, int $userId): array
{
    [$prod, $var] = slerProduto($bizId, $userId, 'SLER Produto Vitima');
    $tx = DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'location_id' => $locId, 'created_by' => $userId,
        'type' => 'purchase', 'status' => 'received', 'payment_status' => 'due',
        'ref_no' => 'SLER-V-' . uniqid(), 'transaction_date' => now(),
        'total_before_tax' => 70, 'final_total' => 70, 'tax_amount' => 0, 'discount_amount' => 0,
        'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $line = DB::table('purchase_lines')->insertGetId([
        'transaction_id' => $tx, 'product_id' => $prod, 'variation_id' => $var,
        'quantity' => 7, 'purchase_price' => 10, 'purchase_price_inc_tax' => 10, 'item_tax' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$line, $tx];
}

function slerPayloadStore(?int $purchaseLineId): array
{
    $linha = [
        'product_id' => 1, 'variation_id' => null, 'quantity' => '1',
        'pp_without_discount' => '10', 'discount_percent' => '0', 'purchase_price' => '10',
        'purchase_price_inc_tax' => '10', 'item_tax' => '0', 'purchase_line_tax_id' => null,
    ];
    if ($purchaseLineId !== null) {
        $linha['purchase_line_id'] = $purchaseLineId;
    }

    return ['status' => 'received', 'purchases' => [$linha]];
}

it('UC-PURCRE-14 (contrato T0) store() recusa purchase_line_id de outra empresa e a linha da vitima fica intacta', function () {
    [$linhaVitima, $txVitima] = slerLinhaDaVitima((int) $this->vitima->id, $this->locVitima, (int) $this->donoVitima->id);
    $comprasAntes = DB::table('transactions')->where('business_id', SLER_TENANT)->count();

    $erro = null;
    try {
        $this->withoutExceptionHandling()
            ->actingAs($this->user)
            ->post('/purchases', slerPayloadStore($linhaVitima));
    } catch (\Throwable $e) {
        $erro = $e;
    }

    // A recusa tem de vir da trava da linha, não de validação lateral (anti-vácuo).
    $this->assertInstanceOf(HttpException::class, $erro, 'Esperava 422 da trava da linha; veio: ' . ($erro ? get_class($erro) . ' ' . $erro->getMessage() : 'nada'));
    $this->assertSame(422, $erro->getStatusCode());
    $this->assertStringContainsString('Linha de compra inválida', $erro->getMessage());

    // Efeito: a linha segue na compra da vítima, com a quantidade dela, e nada foi criado no 98.
    $depois = DB::table('purchase_lines')->where('id', $linhaVitima)->first();
    $this->assertSame($txVitima, (int) $depois->transaction_id, 'A linha da vitima foi movida de compra.');
    $this->assertSame(7.0, (float) $depois->quantity, 'A quantidade da linha da vitima mudou.');
    $this->assertSame($comprasAntes, DB::table('transactions')->where('business_id', SLER_TENANT)->count());
});

it('UC-PURCRE-14 (controle) sem purchase_line_id a trava da linha nao dispara', function () {
    $erro = null;
    try {
        $this->withoutExceptionHandling()
            ->actingAs($this->user)
            ->post('/purchases', slerPayloadStore(null));
    } catch (\Throwable $e) {
        $erro = $e;
    }

    // O payload é incompleto de propósito: o request para na validação (contact_id), mas não
    // na trava da linha. Sem esta perna, um 422 incondicional passaria no teste acima.
    $this->assertNotNull($erro);
    $this->assertStringNotContainsString('Linha de compra inválida', $erro->getMessage());
});

// ─────────────────────────────────────────────────────────────────────────────
// A5 — getPurchaseEntryRow()
// ─────────────────────────────────────────────────────────────────────────────

function slerEntryRow($test, User $user, int $productId, int $variationId, int $locationId)
{
    return $test->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->post('/purchases/get_purchase_entry_row', [
            'product_id' => $productId, 'variation_id' => (string) $variationId,
            'location_id' => $locationId, 'row_count' => 0,
        ]);
}

it('UC-PURCRE-15 (controle positivo) a linha de entrada do PROPRIO produto responde 200 com o nome', function () {
    [$prod, $var] = slerProduto(SLER_TENANT, (int) $this->user->id, 'SLER Produto Proprio');

    $r = slerEntryRow($this, $this->user, $prod, $var, $this->locPropria);

    $r->assertOk();
    $r->assertSee('SLER Produto Proprio');
});

it('UC-PURCRE-15 (contrato T0) produto de outra empresa responde 404 sem vazar nome', function () {
    [$prod, $var] = slerProduto((int) $this->vitima->id, (int) $this->donoVitima->id, 'SLER Segredo Vitima');

    $r = slerEntryRow($this, $this->user, $prod, $var, $this->locPropria);

    $this->assertSame(404, $r->status(), 'VAZAMENTO TIER 0: a linha de entrada resolveu produto do business ' . $this->vitima->id);
    $r->assertDontSee('SLER Segredo Vitima');
});

it('UC-PURCRE-15 (contrato T0) local de outra empresa responde 404', function () {
    [$prod, $var] = slerProduto(SLER_TENANT, (int) $this->user->id, 'SLER Produto Proprio 2');

    $r = slerEntryRow($this, $this->user, $prod, $var, $this->locVitima);

    $this->assertSame(404, $r->status(), 'Estoque/custo lidos do local de outra empresa.');
});

it('UC-PURCRE-15 (contrato) sem permissao de compra nem de pedido responde 403', function () {
    [$prod, $var] = slerProduto(SLER_TENANT, (int) $this->user->id, 'SLER Produto Proprio 3');
    $semPermissao = User::factory()->create([
        'business_id' => SLER_TENANT, 'username' => 'ct_sler_np_' . uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);

    $r = slerEntryRow($this, $semPermissao, $prod, $var, $this->locPropria);

    $this->assertSame(403, $r->status());
    $r->assertDontSee('SLER Produto Proprio 3');
});
