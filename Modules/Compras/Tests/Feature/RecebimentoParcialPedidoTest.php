<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\Support\EstoqueFixture;

/**
 * US-COM-013 — recebimento parcial pelo pedido de compra (decisão coordenação 2026-10-26).
 *
 * Contrato (SPEC Compras US-COM-013, não o código): o pedido de 10 recebe 4 e depois 6; cada
 * entrega é uma compra ligada ao pedido; o estoque sobe só o que chegou; o pedido fica parcial e
 * depois concluído; a lista de pedidos abertos mostra o que falta e some quando não falta nada.
 *
 * Regra mestre (estoque): DOIS caminhos independentes em cada passo —
 *  (1) saldo em `variation_location_details.qty_available`;
 *  (2) soma das linhas das compras RECEBIDAS daquela variação no local.
 * Os dois têm de bater com o esperado escrito à mão (0 → 4 → 10).
 *
 * Tenant 98 (ADR 0358); adversário 99 ou o biz=2 do seed, nunca 1 nem 4.
 *
 * @covers-uc UC-PURCRE-12 — resources/js/Pages/Purchase/Create.casos.md
 * @covers-us US-COM-013
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS (MySQL) ausente — rode no CT 100 / lane compras-pest.');
    }
    $this->biz = EstoqueFixture::businessId();

    $role = Role::firstOrCreate(['name' => 'Admin#' . $this->biz, 'guard_name' => 'web'], ['business_id' => $this->biz]);
    $this->user = App\User::factory()->create([
        'business_id' => $this->biz,
        'username' => 'rp013_' . uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $this->user->assignRole($role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->location = EstoqueFixture::locationId($this->biz, '-RP013');
    $this->produto = EstoqueFixture::singleProduct($this->biz);
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz,
        'type' => 'supplier',
        'name' => 'Fornecedor RP013',
        'contact_id' => 'CT-RP013-' . uniqid(),
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->pedido = DB::table('transactions')->insertGetId([
        'business_id' => $this->biz,
        'location_id' => $this->location,
        'contact_id' => $this->contactId,
        'type' => 'purchase_order',
        'status' => 'ordered',
        'ref_no' => 'PO-RP013-' . uniqid(),
        'transaction_date' => now(),
        'created_by' => $this->user->id,
        'essentials_duration' => 0,
        'final_total' => 100,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->linhaPedido = DB::table('purchase_lines')->insertGetId([
        'transaction_id' => $this->pedido,
        'product_id' => $this->produto->productId,
        'variation_id' => $this->produto->variationId(),
        'quantity' => 10,
        'po_quantity_purchased' => 0,
        'pp_without_discount' => 10,
        'purchase_price' => 10,
        'purchase_price_inc_tax' => 10,
        'item_tax' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $biz = DB::table('business')->where('id', $this->biz)->first();
    $fmt = (string) ($biz->date_format ?: 'm/d/Y') . (((int) ($biz->time_format ?? 24)) === 12 ? ' h:i A' : ' H:i');
    $this->data = now()->format($fmt);
});

/** Caminho 1 do estoque: o saldo gravado. */
function rp013Saldo($t): float
{
    return (float) DB::table('variation_location_details')
        ->where('variation_id', $t->produto->variationId())
        ->where('location_id', $t->location)
        ->sum('qty_available');
}

/** Caminho 2 do estoque: soma das linhas das compras recebidas dessa variação no local. */
function rp013SomaRecebida($t): float
{
    return (float) DB::table('purchase_lines as pl')
        ->join('transactions as tx', 'tx.id', '=', 'pl.transaction_id')
        ->where('tx.business_id', $t->biz)
        ->where('tx.type', 'purchase')
        ->where('tx.status', 'received')
        ->where('tx.location_id', $t->location)
        ->where('pl.variation_id', $t->produto->variationId())
        ->sum('pl.quantity');
}

function rp013Receber($t, int $qtd): string
{
    $ref = 'RP013-' . uniqid();
    $total = number_format($qtd * 10, 2, '.', '');
    $t->actingAs($t->user)->post('/purchases', [
        'ref_no' => $ref,
        'status' => 'received',
        'contact_id' => $t->contactId,
        'transaction_date' => $t->data,
        'location_id' => $t->location,
        'exchange_rate' => '1',
        'total_before_tax' => $total,
        'discount_type' => 'fixed',
        'discount_amount' => '0',
        'tax_amount' => '0',
        'shipping_charges' => '0',
        'final_total' => $total,
        'purchase_order_ids' => [$t->pedido],
        'purchases' => [[
            'product_id' => $t->produto->productId,
            'variation_id' => $t->produto->variationId(),
            'product_unit_id' => $t->produto->unitId,
            'quantity' => (string) $qtd,
            'pp_without_discount' => '10.00',
            'discount_percent' => '0',
            'purchase_price' => '10.00',
            'purchase_price_inc_tax' => '10.00',
            'item_tax' => '0',
            'purchase_line_tax_id' => null,
            'purchase_order_line_id' => $t->linhaPedido,
        ]],
        'payment' => [],
    ]);

    return $ref;
}

function rp013Abertos($t): array
{
    return $t->actingAs($t->user)
        ->getJson('/purchases/pedidos-abertos?contact_id=' . $t->contactId . '&location_id=' . $t->location)
        ->assertOk()
        ->json('pedidos');
}

it('lista o pedido aberto com pedido, recebido e a receber por linha', function () {
    $pedidos = rp013Abertos($this);

    expect($pedidos)->toHaveCount(1);
    expect($pedidos[0]['id'])->toBe($this->pedido);
    expect($pedidos[0]['linhas'][0]['purchase_order_line_id'])->toBe($this->linhaPedido);
    expect((float) $pedidos[0]['linhas'][0]['pedido'])->toBe(10.0);
    expect((float) $pedidos[0]['linhas'][0]['recebido'])->toBe(0.0);
    expect((float) $pedidos[0]['linhas'][0]['a_receber'])->toBe(10.0);
});

it('recebe 4 e depois 6: estoque 0 → 4 → 10 pelos dois caminhos, pedido parcial e depois concluído', function () {
    Event::fake([App\Events\PurchaseCreatedOrModified::class]);

    expect(rp013Saldo($this))->toBe(0.0);
    expect(rp013SomaRecebida($this))->toBe(0.0);

    $ref1 = rp013Receber($this, 4);
    expect(DB::table('transactions')->where('business_id', $this->biz)->where('ref_no', $ref1)->exists())
        ->toBeTrue('A 1ª entrega não virou compra.');

    expect(rp013Saldo($this))->toBe(4.0);
    expect(rp013SomaRecebida($this))->toBe(4.0);
    expect(DB::table('transactions')->where('id', $this->pedido)->value('status'))->toBe('partial');
    expect((float) rp013Abertos($this)[0]['linhas'][0]['a_receber'])->toBe(6.0);

    rp013Receber($this, 6);

    expect(rp013Saldo($this))->toBe(10.0);
    expect(rp013SomaRecebida($this))->toBe(10.0);
    expect(DB::table('transactions')->where('id', $this->pedido)->value('status'))->toBe('completed');
    expect(rp013Abertos($this))->toBe([]);
});

it('não mostra pedido de fornecedor de outra empresa', function () {
    $outra = (int) DB::table('business')->whereIn('id', [99, 2])->where('id', '!=', $this->biz)
        ->orderByDesc('id')->value('id');
    if ($outra <= 0) {
        $this->markTestSkipped('Sem empresa adversária fictícia (99 ou 2) neste ambiente.');
    }
    $fornecedorAlheio = DB::table('contacts')->insertGetId([
        'business_id' => $outra,
        'type' => 'supplier',
        'name' => 'Fornecedor alheio RP013',
        'contact_id' => 'CT-RP013X-' . uniqid(),
        'created_by' => EstoqueFixture::userId($outra),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    // O pedido alheio TEM linha a receber: sem ela o serviço já o esconderia, e o caso ficaria
    // verde mesmo sem o filtro por empresa.
    $produtoAlheio = EstoqueFixture::singleProduct($outra);
    $pedidoAlheio = DB::table('transactions')->insertGetId([
        'business_id' => $outra,
        'contact_id' => $fornecedorAlheio,
        'type' => 'purchase_order',
        'status' => 'ordered',
        'transaction_date' => now(),
        'created_by' => EstoqueFixture::userId($outra),
        'essentials_duration' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $pedidoAlheio,
        'product_id' => $produtoAlheio->productId,
        'variation_id' => $produtoAlheio->variationId(),
        'quantity' => 5,
        'po_quantity_purchased' => 0,
        'purchase_price' => 10,
        'purchase_price_inc_tax' => 10,
        'item_tax' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson('/purchases/pedidos-abertos?contact_id=' . $fornecedorAlheio)
        ->assertOk()
        ->assertExactJson(['pedidos' => []]);
});
