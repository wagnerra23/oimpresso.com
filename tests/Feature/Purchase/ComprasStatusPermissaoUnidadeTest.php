<?php

declare(strict_types=1);

use App\Transaction;
use App\User;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * D3 da auditoria de Compras (2026-11-05), lote 2.3:
 *
 * 1. `PurchaseOrderController@postEditPurchaseOrderStatus` aceitava qualquer transação da empresa
 *    e qualquer texto em `status`. Agora: só `purchase_order`, e só os status do pedido.
 * 2. Cinco endpoints de apoio da tela de compra/pedido/requisição não conferiam permissão.
 * 3. `ProductUtil::createOrUpdatePurchaseLines` resolvia a sub-unidade com `Unit::find` cru: a
 *    unidade de outra empresa entrava e o multiplicador dela ia para a quantidade da linha.
 *
 * Tenants: usuário no 98 (ADR 0358); unidade alheia no 99 (`seededSupportClientTenant()`). Nunca 4.
 *
 * @covers-uc UC-PEDC-09
 * @covers-uc UC-PEDC-10
 *
 * @see app/Http/Controllers/PurchaseOrderController.php::postEditPurchaseOrderStatus
 * @see app/Utils/ProductUtil.php::createOrUpdatePurchaseLines
 */
uses(DatabaseTransactions::class);

const D3_TENANT = 98;

function d3Location(int $bizId): int
{
    $scheme = DB::table('invoice_schemes')->value('id')
        ?: DB::table('invoice_schemes')->insertGetId(['business_id' => $bizId, 'name' => 'D3 Scheme', 'scheme_type' => 'blank', 'created_at' => now(), 'updated_at' => now()]);
    $layout = DB::table('invoice_layouts')->value('id')
        ?: DB::table('invoice_layouts')->insertGetId(['business_id' => $bizId, 'name' => 'D3 Layout', 'created_at' => now(), 'updated_at' => now()]);

    return (int) DB::table('business_locations')->insertGetId([
        'business_id' => $bizId, 'name' => 'D3 Loc '.$bizId,
        'location_id' => strtoupper(substr(md5('d3'.$bizId.microtime()), 0, 8)),
        'country' => 'BR', 'state' => 'SC', 'city' => 'CT City', 'zip_code' => '00000000',
        'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function d3Transacao(int $bizId, int $locId, int $userId, string $type, string $status): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'location_id' => $locId, 'created_by' => $userId,
        'type' => $type, 'status' => $status, 'payment_status' => 'due', 'ref_no' => 'D3-'.uniqid(),
        'transaction_date' => now(), 'total_before_tax' => 30, 'final_total' => 30, 'tax_amount' => 0,
        'discount_amount' => 0, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function d3Unidade(int $bizId, int $userId, ?int $base = null, ?float $mult = null): int
{
    return (int) DB::table('units')->insertGetId([
        'business_id' => $bizId, 'actual_name' => $base ? 'Caixa' : 'Unidade', 'short_name' => $base ? 'cx' : 'un',
        'allow_decimal' => 0, 'base_unit_id' => $base, 'base_unit_multiplier' => $mult,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Precisa do schema UltimatePOS em MySQL real (lane purchase-pest.yml).');
    }

    $this->assertNotNull(\App\Business::find(D3_TENANT), 'Lane sem tenant 98 semeado (ADR 0358).');
    $this->loc = d3Location(D3_TENANT);
    $this->novoUsuario = function (array $permissoes = [], bool $admin = false): User {
        $u = User::factory()->create([
            'business_id' => D3_TENANT, 'username' => 'ct_d3_'.uniqid(), 'user_type' => 'user', 'allow_login' => 1,
        ]);
        foreach ($permissoes as $p) {
            $u->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        if ($admin) {
            $u->assignRole(Role::firstOrCreate(['name' => 'Admin#'.D3_TENANT, 'guard_name' => 'web'], ['business_id' => D3_TENANT]));
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return User::findOrFail($u->id);
    };
});

// ─── 1. Status do pedido de compra ───────────────────────────────────────────

function d3TrocarStatus($test, User $user, int $id, string $status): array
{
    return $test->actingAs($user)
        ->withSession(['user' => ['business_id' => D3_TENANT, 'id' => $user->id]])
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->put('/update-purchase-orders/'.$id.'/status', ['status' => $status])
        ->json();
}

it('UC-PEDC-09 (controle positivo) troca o status do pedido para um da lista', function () {
    $admin = ($this->novoUsuario)([], true);
    $po = d3Transacao(D3_TENANT, $this->loc, $admin->id, 'purchase_order', 'ordered');

    $out = d3TrocarStatus($this, $admin, $po, 'completed');

    expect($out['success'] ?? null)->toBe(1)
        ->and(DB::table('transactions')->where('id', $po)->value('status'))->toBe('completed');
});

it('UC-PEDC-09 status fora da lista do pedido nao grava', function () {
    $admin = ($this->novoUsuario)([], true);
    $po = d3Transacao(D3_TENANT, $this->loc, $admin->id, 'purchase_order', 'ordered');

    $out = d3TrocarStatus($this, $admin, $po, 'final');

    expect($out['success'] ?? null)->toBe(0)
        ->and(DB::table('transactions')->where('id', $po)->value('status'))->toBe('ordered');
});

it('UC-PEDC-09 a rota de status do pedido nao altera transacao que nao e pedido de compra', function () {
    $admin = ($this->novoUsuario)([], true);
    $venda = d3Transacao(D3_TENANT, $this->loc, $admin->id, 'sell', 'final');

    $out = d3TrocarStatus($this, $admin, $venda, 'ordered');

    expect($out['success'] ?? null)->toBe(0)
        ->and(DB::table('transactions')->where('id', $venda)->value('status'))->toBe('final');
});

// ─── 2. Endpoints de apoio exigem permissão ──────────────────────────────────

it('UC-PEDC-10 os endpoints de apoio respondem 403 sem a permissao e liberam com ela', function () {
    $semNada = ($this->novoUsuario)();
    $po = d3Transacao(D3_TENANT, $this->loc, $semNada->id, 'purchase_order', 'ordered');
    $req = d3Transacao(D3_TENANT, $this->loc, $semNada->id, 'purchase_requisition', 'ordered');
    $compra = d3Transacao(D3_TENANT, $this->loc, $semNada->id, 'purchase', 'received');

    $casos = [
        ['get', '/get-purchase-order-lines/'.$po, ['purchase.create']],
        ['get', '/purchases/print/'.$compra, ['purchase.view']],
        ['post', '/get-requisition-products', ['purchase_requisition.create']],
        ['get', '/get-purchase-requisitions/'.$this->loc, ['purchase_order.create']],
        ['get', '/get-purchase-requisition-lines/'.$req, ['purchase_order.create']],
    ];

    foreach ($casos as [$verbo, $url, $perms]) {
        $sem = $this->actingAs($semNada)
            ->withSession(['user' => ['business_id' => D3_TENANT, 'id' => $semNada->id]])
            ->{$verbo}($url);
        expect($sem->status())->toBe(403, "{$url} sem permissao deveria responder 403");

        $com = ($this->novoUsuario)($perms);
        $resp = $this->actingAs($com)
            ->withSession(['user' => ['business_id' => D3_TENANT, 'id' => $com->id]])
            ->{$verbo}($url);
        expect($resp->status())->not->toBe(403, "{$url} com ".implode(',', $perms).' nao pode responder 403');
    }
});

// ─── 3. Sub-unidade da linha de compra é da empresa da compra ────────────────

function d3SessaoDeMoeda(): void
{
    session([
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$'],
        'business.currency_precision' => 2,
        'business.quantity_precision' => 2,
    ]);
}

function d3LinhaNova(int $prod, int $var, int $unidadeBase, int $subUnidade): array
{
    return [
        'product_id' => $prod, 'variation_id' => $var,
        'product_unit_id' => $unidadeBase, 'sub_unit_id' => $subUnidade,
        'quantity' => '2', 'pp_without_discount' => '120', 'discount_percent' => '0',
        'purchase_price' => '120', 'purchase_price_inc_tax' => '120', 'item_tax' => '0',
        'purchase_line_tax_id' => null,
    ];
}

it('UC-PEDC-10 sub-unidade de OUTRA empresa nao entra na linha de compra', function () {
    $user = ($this->novoUsuario)();
    $vitima = $this->seededSupportClientTenant();
    $this->assertNotContains((int) $vitima->id, [D3_TENANT, 4]);

    $base = d3Unidade(D3_TENANT, $user->id);
    $baseVitima = d3Unidade((int) $vitima->id, $user->id);
    $caixaPropria = d3Unidade(D3_TENANT, $user->id, $base, 12);
    $caixaVitima = d3Unidade((int) $vitima->id, $user->id, $baseVitima, 12);

    $prod = (int) DB::table('products')->insertGetId([
        'name' => 'D3 Produto', 'business_id' => D3_TENANT, 'type' => 'single', 'unit_id' => $base,
        'tax_type' => 'exclusive', 'sku' => 'D3'.uniqid(), 'enable_stock' => 0, 'created_by' => $user->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $pv = DB::table('product_variations')->insertGetId(['name' => 'DUMMY', 'product_id' => $prod, 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $var = (int) DB::table('variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $prod, 'product_variation_id' => $pv, 'sub_sku' => 'D3'.uniqid(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    d3SessaoDeMoeda();
    $util = app(ProductUtil::class);
    $moeda = app(TransactionUtil::class)->purchaseCurrencyDetails(D3_TENANT);

    // Controle positivo: a caixa da PRÓPRIA empresa (12 un) vale.
    // Caminho 1, à mão: 2 caixas × 12 = 24 un; preço 120 por caixa ÷ 12 = 10,00 por un.
    $tx = d3Transacao(D3_TENANT, $this->loc, $user->id, 'purchase_order', 'ordered');
    $util->createOrUpdatePurchaseLines(Transaction::find($tx), [d3LinhaNova($prod, $var, $base, $caixaPropria)], $moeda, false, 'ordered');
    // Caminho 2: o que ficou gravado.
    $linha = DB::table('purchase_lines')->where('transaction_id', $tx)->first();
    expect((float) $linha->quantity)->toBe(24.0)
        ->and(round((float) $linha->purchase_price, 2))->toBe(10.0);

    // Contrato: a caixa de OUTRA empresa não resolve, e nenhuma linha é gravada.
    $tx2 = d3Transacao(D3_TENANT, $this->loc, $user->id, 'purchase_order', 'ordered');
    $erro = null;
    try {
        $util->createOrUpdatePurchaseLines(Transaction::find($tx2), [d3LinhaNova($prod, $var, $base, $caixaVitima)], $moeda, false, 'ordered');
    } catch (\Throwable $e) {
        $erro = $e;
    }

    expect($erro)->toBeInstanceOf(ModelNotFoundException::class)
        ->and(DB::table('purchase_lines')->where('transaction_id', $tx2)->count())->toBe(0);
});
