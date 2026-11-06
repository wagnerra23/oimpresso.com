<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Contrato T0 do `PurchaseRequisitionController@store` (auditoria Compras 2026-11-05, lote 0 PR 0.6, A6).
 *
 * O store gravava `location_id` e as linhas (`variation_id`/`product_id`) crus: a requisição nascia
 * num local de outra empresa ou com produto de outra empresa.
 *
 * Tenants: usuário no 98 (fictício, ADR 0358); vítima no 99 (`seededSupportClientTenant()`). Nunca 4.
 *
 * @covers-uc UC-REQC-08
 *
 * @see app/Http/Controllers/PurchaseRequisitionController.php::store
 */
uses(DatabaseTransactions::class);

const REQSTO_TENANT = 98;

function reqstoLocation(int $bizId): int
{
    $scheme = DB::table('invoice_schemes')->value('id')
        ?: DB::table('invoice_schemes')->insertGetId([
            'business_id' => $bizId, 'name' => 'REQSTO Scheme', 'scheme_type' => 'blank',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $layout = DB::table('invoice_layouts')->value('id')
        ?: DB::table('invoice_layouts')->insertGetId([
            'business_id' => $bizId, 'name' => 'REQSTO Layout',
            'created_at' => now(), 'updated_at' => now(),
        ]);

    return (int) DB::table('business_locations')->insertGetId([
        'business_id' => $bizId, 'name' => 'REQSTO Loc ' . $bizId,
        'location_id' => strtoupper(substr(md5('reqsto' . $bizId . microtime()), 0, 8)),
        'country' => 'BR', 'state' => 'SC', 'city' => 'CT City', 'zip_code' => '00000000',
        'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function reqstoProduto(int $bizId, int $userId): array
{
    $unit = DB::table('units')->insertGetId([
        'business_id' => $bizId, 'actual_name' => 'Unidade', 'short_name' => 'un',
        'allow_decimal' => 0, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $prod = DB::table('products')->insertGetId([
        'name' => 'REQSTO Produto ' . $bizId, 'business_id' => $bizId, 'type' => 'single', 'unit_id' => $unit,
        'tax_type' => 'exclusive', 'sku' => 'REQSTO' . uniqid(), 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $pv = DB::table('product_variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $prod, 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $var = DB::table('variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $prod, 'product_variation_id' => $pv, 'sub_sku' => 'REQSTO' . uniqid(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [(int) $prod, (int) $var];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Precisa do schema UltimatePOS em MySQL real (lane purchase-pest.yml).');
    }

    $this->assertNotNull(\App\Business::find(REQSTO_TENANT), 'Lane sem tenant 98 semeado (ADR 0358).');
    $this->vitima = $this->seededSupportClientTenant();
    $this->assertNotContains((int) $this->vitima->id, [REQSTO_TENANT, 4]);

    $this->loc = reqstoLocation(REQSTO_TENANT);
    $this->locVitima = reqstoLocation((int) $this->vitima->id);

    foreach (['purchase_requisition.create', 'access_all_locations'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $this->user = User::factory()->create([
        'business_id' => REQSTO_TENANT, 'username' => 'ct_reqsto_' . uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $this->user->givePermissionTo(['purchase_requisition.create', 'access_all_locations']);
    $donoVitima = User::factory()->create([
        'business_id' => (int) $this->vitima->id, 'username' => 'ct_reqsto_v_' . uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    [$this->prod, $this->var] = reqstoProduto(REQSTO_TENANT, (int) $this->user->id);
    [$this->prodVitima, $this->varVitima] = reqstoProduto((int) $this->vitima->id, (int) $donoVitima->id);

    $this->requisicoes = fn () => DB::table('transactions')->where('type', 'purchase_requisition')
        ->whereIn('business_id', [REQSTO_TENANT, (int) $this->vitima->id])->count();
    $this->antes = ($this->requisicoes)();

    $this->enviar = function (int $locationId, int $prod, int $var) {
        try {
            $this->withoutExceptionHandling()->actingAs($this->user)->post('/purchase-requisition', [
                'location_id' => $locationId,
                'purchases' => [['product_id' => $prod, 'variation_id' => $var, 'quantity' => '3']],
            ]);
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    };
});

function reqstoAssert422($test, ?\Throwable $erro, string $trecho): void
{
    $test->assertInstanceOf(HttpException::class, $erro, 'Esperava 422 da trava; veio: ' . ($erro ? get_class($erro) . ' ' . $erro->getMessage() : 'nada'));
    $test->assertSame(422, $erro->getStatusCode());
    $test->assertStringContainsString($trecho, $erro->getMessage());
    $test->assertSame($test->antes, ($test->requisicoes)(), 'Uma requisição nasceu apesar da recusa.');
}

it('UC-REQC-08 (controle positivo) local e produto da PROPRIA empresa criam a requisicao com a linha', function () {
    $erro = ($this->enviar)($this->loc, $this->prod, $this->var);

    $this->assertNull($erro, 'O request da propria empresa falhou: ' . ($erro?->getMessage() ?? ''));
    $this->assertSame(1, session('status')['success'] ?? null, 'msg=' . json_encode(session('status')));
    $this->assertSame($this->antes + 1, ($this->requisicoes)());
    $req = DB::table('transactions')->where('business_id', REQSTO_TENANT)->where('type', 'purchase_requisition')
        ->orderByDesc('id')->first();
    $this->assertSame($this->loc, (int) $req->location_id);
    $this->assertSame($this->var, (int) DB::table('purchase_lines')->where('transaction_id', $req->id)->value('variation_id'));
});

it('UC-REQC-08 (contrato T0) local de OUTRA empresa e recusado', function () {
    reqstoAssert422($this, ($this->enviar)($this->locVitima, $this->prod, $this->var), 'Local inválido');
});

it('UC-REQC-08 (contrato T0) produto de OUTRA empresa e recusado', function () {
    reqstoAssert422($this, ($this->enviar)($this->loc, $this->prodVitima, $this->varVitima), 'Variação de produto inválida');
});

it('UC-REQC-08 (contrato T0) product_id que nao e o da variacao e recusado', function () {
    reqstoAssert422($this, ($this->enviar)($this->loc, $this->prodVitima, $this->var), 'Variação de produto inválida');
});
