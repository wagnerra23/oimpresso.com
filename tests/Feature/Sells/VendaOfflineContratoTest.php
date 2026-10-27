<?php
// @covers-us US-SELL-054

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * UC-VOFF-01..04 · US-SELL-054 — venda feita sem internet no caixa e reenviada ao voltar a conexão.
 *
 * Âncora: resources/js/Pages/Sells/Create.casos.md (UC-VOFF-*). O caixa reenvia o MESMO
 * payload com a mesma `offline_uuid` até receber resposta; o servidor grava uma vez só.
 *
 * Dupla prova da REGRA MESTRE (estoque/valor), números feitos à mão: produto com 10 em
 * estoque, venda de 2 × 50,00 = 100,00.
 *   caminho 1 (contagem de vendas): 1 venda com a chave, mesmo após 2 envios.
 *   caminho 2 (saldo): 10 − 2 = 8 depois do 1º envio, e continua 8 depois do 2º.
 *
 * Payload no formato do transform do Sells/Create.tsx (o mesmo do SellsRepairSubtipoContratoTest).
 * Tenant 98 (ADR 0358). Nunca biz=4.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! \Illuminate\Support\Facades\Schema::hasTable('vendas_offline_recebidas')) {
        $this->markTestSkipped('Schema ausente — roda na lane MySQL (sells-pest).');
    }

    $this->bizId = EstoqueFixture::businessId();
    $this->locationId = EstoqueFixture::locationId($this->bizId);
    $this->contactId = (int) DB::table('contacts')->where('business_id', $this->bizId)->orderBy('id')->value('id');
    $this->user = User::where('business_id', $this->bizId)->orderBy('id')->first();
    if (! $this->user || $this->contactId === 0) {
        $this->markTestSkipped('Sem user ou contato no business seeded.');
    }

    config(['sells.offline.enabled' => true, 'sells.offline.business_ids' => [$this->bizId]]);

    $this->actingAs($this->user);
    $business = \App\Business::findOrFail($this->bizId);
    $business->date_format = 'd/m/Y';
    $business->time_format = 24;
    session(['user.business_id' => $this->bizId, 'user.id' => $this->user->id, 'business' => $business]);
    $currency = DB::table('currencies')->where('id', $business->currency_id)->first();
    session(['currency' => [
        'id' => (int) ($currency->id ?? 1), 'code' => (string) ($currency->code ?? 'BRL'),
        'symbol' => (string) ($currency->symbol ?? 'R$'),
        'thousand_separator' => (string) ($currency->thousand_separator ?? '.'),
        'decimal_separator' => (string) ($currency->decimal_separator ?? ','),
    ]]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('sell.create', 'web');
    $this->user->givePermissionTo('sell.create');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // Produto com 10 em estoque e lastro de compra (sem lastro o store() recusa por PurchaseSellMismatch).
    $this->produto = EstoqueFixture::singleProduct($this->bizId);
    EstoqueFixture::setStock($this->produto, 0, $this->locationId, 10);
    $compra = (int) DB::table('transactions')->insertGetId([
        'business_id' => $this->bizId, 'type' => 'purchase', 'status' => 'received',
        'location_id' => $this->locationId, 'payment_status' => 'paid', 'transaction_date' => now()->subDay(),
        'total_before_tax' => 0, 'final_total' => 0, 'created_by' => $this->user->id,
        'essentials_duration' => 0, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compra, 'product_id' => $this->produto->productId,
        'variation_id' => $this->produto->variations[0]['variation_id'], 'quantity' => 10,
        'quantity_sold' => 0, 'quantity_adjusted' => 0, 'quantity_returned' => 0,
        'purchase_price' => 0, 'purchase_price_inc_tax' => 0, 'item_tax' => 0,
        'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);

    $this->payload = fn (array $extra = []) => array_merge([
        'location_id' => $this->locationId, 'contact_id' => $this->contactId,
        'transaction_date' => now()->format('d/m/Y H:i'), 'status' => 'final', 'is_direct_sale' => 1,
        'is_save_and_print' => 0, 'discount_type' => 'percentage', 'discount_amount' => 0, 'tax_rate_id' => null,
        'final_total' => 100,
        'payment' => [['amount' => 0, 'method' => 'cash', 'paid_on' => '', 'account_id' => null, 'note' => '']],
        'products' => [[
            'product_id' => $this->produto->productId, 'variation_id' => $this->produto->variations[0]['variation_id'],
            'quantity' => 2, 'unit_price' => 50, 'unit_price_inc_tax' => 50, 'item_tax' => 0, 'tax_id' => null,
            'line_discount_type' => 'fixed', 'line_discount_amount' => 0, 'imei_number' => '',
            'enable_stock' => 1, 'product_type' => 'single',
        ]],
    ], $extra);

    $this->vendasDoProduto = fn () => DB::table('transaction_sell_lines')
        ->where('product_id', $this->produto->productId)->distinct()->count('transaction_id');
});

it('UC-VOFF-01 a venda offline reenviada duas vezes grava uma venda e baixa o estoque uma vez', function () {
    $uuid = (string) Str::uuid();
    $dados = ($this->payload)(['offline_uuid' => $uuid, 'offline_vendida_em' => now()->subMinutes(5)->toIso8601String()]);

    $primeira = $this->post('/pos', $dados)->assertOk()->json();
    expect($primeira['success'])->toBe(1)->and($primeira['duplicada'])->toBeFalse()
        ->and($primeira['transaction_id'])->toBeInt();
    expect(EstoqueFixture::currentStock($this->produto, 0, $this->locationId))->toEqual(8.0);

    $segunda = $this->post('/pos', $dados)->assertOk()->json();
    expect($segunda['success'])->toBe(1)->and($segunda['duplicada'])->toBeTrue()
        ->and($segunda['transaction_id'])->toBe($primeira['transaction_id']);

    expect(($this->vendasDoProduto)())->toBe(1);
    expect(EstoqueFixture::currentStock($this->produto, 0, $this->locationId))->toEqual(8.0);
    expect((float) DB::table('transactions')->where('id', $primeira['transaction_id'])->value('final_total'))->toEqual(100.0);
});

it('UC-VOFF-02 a chave de uma empresa não acha venda de outra (Tier 0)', function () {
    $uuid = (string) Str::uuid();
    DB::table('vendas_offline_recebidas')->insert([
        'business_id' => 99, 'offline_uuid' => $uuid, 'transaction_id' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $r = $this->post('/pos', ($this->payload)(['offline_uuid' => $uuid]))->assertOk()->json();

    expect($r['duplicada'])->toBeFalse()->and($r['transaction_id'])->not->toBe(1);
    expect(($this->vendasDoProduto)())->toBe(1);
});

it('UC-VOFF-03 com a flag desligada a chave é ignorada e a venda segue o fluxo de hoje', function () {
    config(['sells.offline.enabled' => false]);

    $this->post('/pos', ($this->payload)(['offline_uuid' => (string) Str::uuid()]))->assertRedirect();

    expect(DB::table('vendas_offline_recebidas')->where('business_id', $this->bizId)->count())->toBe(0);
    expect(($this->vendasDoProduto)())->toBe(1);
});

it('UC-VOFF-04 venda offline recusada volta em JSON sem gravar a chave, para o caixa manter na fila', function () {
    $uuid = (string) Str::uuid();
    $dados = ($this->payload)(['offline_uuid' => $uuid]);
    $dados['products'][0]['quantity'] = 50; // mais do que o lastro de compra: o store() recusa

    $r = $this->post('/pos', $dados)->assertOk()->json();

    expect($r['success'])->toBe(0)->and($r['transaction_id'])->toBeNull();
    expect(DB::table('vendas_offline_recebidas')->where('offline_uuid', $uuid)->count())->toBe(0);
    expect(EstoqueFixture::currentStock($this->produto, 0, $this->locationId))->toEqual(10.0);
});
