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

/**
 * Contrato T0 (ADR 0093): `purchase_line_id` vindo do formulário só alcança linha DA transação que
 * está sendo gravada (auditoria Compras 2026-11-05, lote 0 PR 0.5, A4).
 *
 * - `CombinedPurchaseReturnController@update` fazia `PurchaseLine::find($id)` cru: a linha de outra
 *   empresa entrava na devolução (o `saveMany` trocava o `transaction_id` dela).
 * - `ProductUtil::createOrUpdatePurchaseLines` fazia `PurchaseLine::findOrFail($id)` cru. Os
 *   controllers de compra e pedido já recusam antes; este é o ponto de uso, para quem chamar sem a trava.
 *
 * Tenants: usuário no 98 (fictício, ADR 0358); vítima no 99 (`seededSupportClientTenant()`). Nunca 4.
 *
 * @covers-uc UC-DEVC-06
 *
 * @see app/Http/Controllers/CombinedPurchaseReturnController.php::update
 * @see app/Utils/ProductUtil.php::createOrUpdatePurchaseLines
 */
uses(DatabaseTransactions::class);

const LINTX_TENANT = 98;

function lintxLocation(int $bizId): int
{
    $scheme = DB::table('invoice_schemes')->value('id')
        ?: DB::table('invoice_schemes')->insertGetId([
            'business_id' => $bizId, 'name' => 'LINTX Scheme', 'scheme_type' => 'blank',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $layout = DB::table('invoice_layouts')->value('id')
        ?: DB::table('invoice_layouts')->insertGetId([
            'business_id' => $bizId, 'name' => 'LINTX Layout',
            'created_at' => now(), 'updated_at' => now(),
        ]);

    return (int) DB::table('business_locations')->insertGetId([
        'business_id' => $bizId, 'name' => 'LINTX Loc ' . $bizId,
        'location_id' => strtoupper(substr(md5('lintx' . $bizId . microtime()), 0, 8)),
        'country' => 'BR', 'state' => 'SC', 'city' => 'CT City', 'zip_code' => '00000000',
        'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Produto sem controle de estoque (enable_stock=0): o teste mede posse da linha, não estoque. */
function lintxProduto(int $bizId, int $userId): array
{
    $unit = DB::table('units')->insertGetId([
        'business_id' => $bizId, 'actual_name' => 'Unidade', 'short_name' => 'un',
        'allow_decimal' => 0, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $prod = DB::table('products')->insertGetId([
        'name' => 'LINTX Produto ' . $bizId, 'business_id' => $bizId, 'type' => 'single', 'unit_id' => $unit,
        'tax_type' => 'exclusive', 'sku' => 'LINTX' . uniqid(), 'enable_stock' => 0, 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $pv = DB::table('product_variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $prod, 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $var = DB::table('variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $prod, 'product_variation_id' => $pv, 'sub_sku' => 'LINTX' . uniqid(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [(int) $prod, (int) $var];
}

function lintxTransacao(int $bizId, int $locId, int $userId, string $type, string $status): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'location_id' => $locId, 'created_by' => $userId,
        'type' => $type, 'status' => $status, 'payment_status' => 'due', 'ref_no' => 'LINTX-' . uniqid(),
        'transaction_date' => now(), 'total_before_tax' => 30, 'final_total' => 30, 'tax_amount' => 0,
        'discount_amount' => 0, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function lintxLinha(int $txId, int $prod, int $var, array $extra = []): int
{
    return (int) DB::table('purchase_lines')->insertGetId(array_replace([
        'transaction_id' => $txId, 'product_id' => $prod, 'variation_id' => $var,
        'quantity' => 3, 'quantity_returned' => 0, 'purchase_price' => 10, 'purchase_price_inc_tax' => 10,
        'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ], $extra));
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Precisa do schema UltimatePOS em MySQL real (lane purchase-pest.yml).');
    }

    $this->assertNotNull(\App\Business::find(LINTX_TENANT), 'Lane sem tenant 98 semeado (ADR 0358).');
    $this->vitima = $this->seededSupportClientTenant();
    $this->assertNotContains((int) $this->vitima->id, [LINTX_TENANT, 4]);

    $this->loc = lintxLocation(LINTX_TENANT);
    $this->locVitima = lintxLocation((int) $this->vitima->id);

    Permission::findOrCreate('purchase.update', 'web');
    $this->user = User::factory()->create([
        'business_id' => LINTX_TENANT, 'username' => 'ct_lintx_' . uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $this->user->givePermissionTo('purchase.update');
    $this->donoVitima = User::factory()->create([
        'business_id' => (int) $this->vitima->id, 'username' => 'ct_lintx_v_' . uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    [$this->prod, $this->var] = lintxProduto(LINTX_TENANT, (int) $this->user->id);
    [$this->prodVitima, $this->varVitima] = lintxProduto((int) $this->vitima->id, (int) $this->donoVitima->id);
});

// ─── Devolução combinada ────────────────────────────────────────────────────

function lintxPayloadDevolucao(int $returnId, int $lineId, int $prod, int $var, string $qtd): array
{
    return [
        'purchase_return_id' => $returnId,
        'transaction_date' => now()->format('m/d/Y H:i'),
        'final_total' => '20', 'tax_amount' => '0', 'tax_id' => null,
        'products' => [[
            'purchase_line_id' => $lineId, 'product_id' => $prod, 'variation_id' => $var,
            'quantity' => $qtd, 'unit_price' => '10',
        ]],
    ];
}

it('UC-DEVC-06 (controle positivo) editar a devolucao com a PROPRIA linha grava a quantidade nova', function () {
    $ret = lintxTransacao(LINTX_TENANT, $this->loc, (int) $this->user->id, 'purchase_return', 'received');
    $linha = lintxLinha($ret, $this->prod, $this->var, ['quantity' => 0, 'quantity_returned' => 1]);

    $this->actingAs($this->user)->post('/purchase-return/update', lintxPayloadDevolucao($ret, $linha, $this->prod, $this->var, '2'));

    $this->assertSame(1, session('status')['success'] ?? null, 'msg=' . json_encode(session('status')));
    $depois = DB::table('purchase_lines')->where('id', $linha)->first();
    $this->assertSame($ret, (int) $depois->transaction_id);
    $this->assertSame(2.0, (float) $depois->quantity_returned);
});

it('UC-DEVC-06 (contrato T0) a linha de compra de OUTRA empresa nao entra na devolucao', function () {
    $ret = lintxTransacao(LINTX_TENANT, $this->loc, (int) $this->user->id, 'purchase_return', 'received');
    $compraVitima = lintxTransacao((int) $this->vitima->id, $this->locVitima, (int) $this->donoVitima->id, 'purchase', 'received');
    $linhaVitima = lintxLinha($compraVitima, $this->prodVitima, $this->varVitima);

    $this->actingAs($this->user)->post('/purchase-return/update', lintxPayloadDevolucao($ret, $linhaVitima, $this->prodVitima, $this->varVitima, '2'));

    $this->assertSame(0, session('status')['success'] ?? null, 'A devolucao com linha alheia nao pode reportar sucesso.');
    $depois = DB::table('purchase_lines')->where('id', $linhaVitima)->first();
    $this->assertSame($compraVitima, (int) $depois->transaction_id, 'VAZAMENTO TIER 0: a linha da vitima mudou de transacao.');
    $this->assertSame(0.0, (float) $depois->quantity_returned, 'VAZAMENTO TIER 0: a quantidade devolvida da linha da vitima mudou.');
    $this->assertSame(0, DB::table('purchase_lines')->where('transaction_id', $ret)->count());
});

// ─── ProductUtil::createOrUpdatePurchaseLines ───────────────────────────────

function lintxDadosLinha(int $lineId, int $prod, int $var, string $qtd): array
{
    return [
        'purchase_line_id' => $lineId, 'product_id' => $prod, 'variation_id' => $var,
        'quantity' => $qtd, 'pp_without_discount' => '10', 'discount_percent' => '0',
        'purchase_price' => '10', 'purchase_price_inc_tax' => '10', 'item_tax' => '0',
        'purchase_line_tax_id' => null,
    ];
}

/**
 * Chamado fora de um request, o ProductUtil lê `session('currency')` em `num_f` e estoura com
 * "array offset on null" antes de chegar à linha (medido no job 127403975499). No request real o
 * middleware SetSessionData põe isso na sessão; aqui o teste põe o mesmo formato.
 */
function lintxSessaoDeMoeda(): void
{
    session([
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$'],
        'business.currency_precision' => 2,
        'business.quantity_precision' => 2,
    ]);
}

it('UC-DEVC-06 (controle positivo) createOrUpdatePurchaseLines atualiza a linha da PROPRIA transacao', function () {
    $tx = lintxTransacao(LINTX_TENANT, $this->loc, (int) $this->user->id, 'purchase_order', 'ordered');
    $linha = lintxLinha($tx, $this->prod, $this->var);
    lintxSessaoDeMoeda();
    $util = app(ProductUtil::class);

    $util->createOrUpdatePurchaseLines(Transaction::find($tx), [lintxDadosLinha($linha, $this->prod, $this->var, '5')],
        app(TransactionUtil::class)->purchaseCurrencyDetails(LINTX_TENANT), false, 'ordered');

    $this->assertSame(5.0, (float) DB::table('purchase_lines')->where('id', $linha)->value('quantity'));
});

it('UC-DEVC-06 (contrato T0) createOrUpdatePurchaseLines nao alcanca linha de OUTRA transacao', function () {
    $tx = lintxTransacao(LINTX_TENANT, $this->loc, (int) $this->user->id, 'purchase_order', 'ordered');
    $compraVitima = lintxTransacao((int) $this->vitima->id, $this->locVitima, (int) $this->donoVitima->id, 'purchase_order', 'ordered');
    $linhaVitima = lintxLinha($compraVitima, $this->prodVitima, $this->varVitima);
    lintxSessaoDeMoeda();
    $util = app(ProductUtil::class);

    $erro = null;
    try {
        $util->createOrUpdatePurchaseLines(Transaction::find($tx), [lintxDadosLinha($linhaVitima, $this->prodVitima, $this->varVitima, '9')],
            app(TransactionUtil::class)->purchaseCurrencyDetails(LINTX_TENANT), false, 'ordered');
    } catch (\Throwable $e) {
        $erro = $e;
    }

    $this->assertInstanceOf(ModelNotFoundException::class, $erro, 'Esperava a busca escopada nao resolver; veio: ' . ($erro ? get_class($erro) : 'nada'));
    $depois = DB::table('purchase_lines')->where('id', $linhaVitima)->first();
    $this->assertSame($compraVitima, (int) $depois->transaction_id, 'VAZAMENTO TIER 0: a linha da vitima mudou de transacao.');
    $this->assertSame(3.0, (float) $depois->quantity, 'VAZAMENTO TIER 0: a quantidade da linha da vitima mudou.');
});
