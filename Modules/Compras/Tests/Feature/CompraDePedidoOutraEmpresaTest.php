<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\Support\EstoqueFixture;

/**
 * US-COM-029 — compra feita a partir de pedido de compra não aceita pedido nem linha de
 * outra empresa (Tier 0, ADR 0093).
 *
 * O contrato vem da US no SPEC (memory/requisitos/Compras/SPEC.md#US-COM-029), não do
 * código: `purchase_order_ids` e `purchases.*.purchase_order_line_id` chegam crus e eram
 * escritos por `PurchaseLine::find`/`Transaction::find` sem filtro de empresa.
 *
 * Por que os dois primeiros casos assertam o 422 e não só "o pedido alheio não mudou": sem
 * a trava, o store pode parar no `isSubscribed` antes de escrever, e "não mudou" ficaria
 * verde sem trava nenhuma. O 422 é a resposta da trava; o "não mudou" vem junto.
 * O 3º caso é o controle positivo: o pedido da própria empresa segue recebendo.
 *
 * Execução: CT 100 / lane compras-pest (MySQL). Tenant 98 (ADR 0358), nunca biz=4.
 *
 * @covers-us US-COM-029
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

function c029Pedido(int $biz, int $locationId, int $userId, \Tests\Support\EstoqueProduto $p, float $qty): array
{
    $poId = DB::table('transactions')->insertGetId([
        'business_id' => $biz,
        'location_id' => $locationId,
        'type' => 'purchase_order',
        'status' => 'ordered',
        'ref_no' => 'PO-C029-' . uniqid(),
        'transaction_date' => now(),
        'created_by' => $userId,
        'essentials_duration' => 0,
        'final_total' => $qty * 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $lineId = DB::table('purchase_lines')->insertGetId([
        'transaction_id' => $poId,
        'product_id' => $p->productId,
        'variation_id' => $p->variationId(),
        'quantity' => $qty,
        'po_quantity_purchased' => 0,
        'purchase_price' => 10,
        'purchase_price_inc_tax' => 10,
        'item_tax' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$poId, $lineId];
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS (MySQL) ausente — rode no CT 100 / lane compras-pest.');
    }
    $this->biz = EstoqueFixture::businessId();
    // Adversário: o cliente fictício 99 (ADR 0358) ou o biz=2 do seed do CI — nunca 1 nem 4,
    // que são empresas reais no clone de produção do CT 100.
    $this->outra = (int) DB::table('business')->whereIn('id', [99, 2])->where('id', '!=', $this->biz)
        ->orderByDesc('id')->value('id');
    if ($this->outra <= 0) {
        $this->markTestSkipped('Sem empresa adversária fictícia (99 ou 2) neste ambiente.');
    }

    $role = Role::firstOrCreate(['name' => 'Admin#' . $this->biz, 'guard_name' => 'web'], ['business_id' => $this->biz]);
    $this->user = App\User::factory()->create([
        'business_id' => $this->biz,
        'username' => 'c029_' . uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $this->user->assignRole($role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->location = EstoqueFixture::locationId($this->biz, '-C029');
    $this->produto = EstoqueFixture::singleProduct($this->biz);
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz,
        'type' => 'supplier',
        'name' => 'Fornecedor C029',
        'contact_id' => 'CT-C029-' . uniqid(),
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $outraLoc = EstoqueFixture::locationId($this->outra, '-C029');
    $outroProduto = EstoqueFixture::singleProduct($this->outra);
    [$this->poAlheio, $this->linhaAlheia] = c029Pedido(
        $this->outra, $outraLoc, EstoqueFixture::userId($this->outra), $outroProduto, 10
    );

    $biz = DB::table('business')->where('id', $this->biz)->first();
    $fmt = (string) ($biz->date_format ?: 'm/d/Y') . (((int) ($biz->time_format ?? 24)) === 12 ? ' h:i A' : ' H:i');
    $this->data = now()->format($fmt);
});

function c029Payload($t, array $linha, array $extra = []): array
{
    return array_merge([
        'ref_no' => 'C029-' . uniqid(),
        'status' => 'received',
        'contact_id' => $t->contactId,
        'transaction_date' => $t->data,
        'location_id' => $t->location,
        'exchange_rate' => '1',
        'total_before_tax' => '40.00',
        'discount_type' => 'fixed',
        'discount_amount' => '0',
        'tax_amount' => '0',
        'shipping_charges' => '0',
        'final_total' => '40.00',
        'purchases' => [array_merge([
            'product_id' => $t->produto->productId,
            'variation_id' => $t->produto->variationId(),
            'product_unit_id' => $t->produto->unitId,
            'quantity' => '4',
            'pp_without_discount' => '10.00',
            'discount_percent' => '0',
            'purchase_price' => '10.00',
            'purchase_price_inc_tax' => '10.00',
            'item_tax' => '0',
            'purchase_line_tax_id' => null,
        ], $linha)],
        'payment' => [],
    ], $extra);
}

it('recusa compra que aponta pedido de outra empresa e não toca o pedido alheio', function () {
    Event::fake([App\Events\PurchaseCreatedOrModified::class]);

    $resp = $this->actingAs($this->user)->post('/purchases', c029Payload($this, [], [
        'purchase_order_ids' => [$this->poAlheio],
    ]));

    $resp->assertStatus(422);
    expect(DB::table('transactions')->where('id', $this->poAlheio)->value('status'))->toBe('ordered');
});

it('recusa linha de pedido de outra empresa e não soma quantidade recebida nela', function () {
    Event::fake([App\Events\PurchaseCreatedOrModified::class]);

    $resp = $this->actingAs($this->user)->post('/purchases', c029Payload($this, [
        'purchase_order_line_id' => $this->linhaAlheia,
    ]));

    $resp->assertStatus(422);
    expect((float) DB::table('purchase_lines')->where('id', $this->linhaAlheia)->value('po_quantity_purchased'))->toBe(0.0);
});

it('pedido da própria empresa segue recebendo: 4 de 10 deixa o pedido parcial (controle positivo)', function () {
    Event::fake([App\Events\PurchaseCreatedOrModified::class]);
    [$po, $linha] = c029Pedido($this->biz, $this->location, $this->user->id, $this->produto, 10);

    $payload = c029Payload($this, ['purchase_order_line_id' => $linha], ['purchase_order_ids' => [$po]]);
    $resp = $this->actingAs($this->user)->post('/purchases', $payload);

    $compra = DB::table('transactions')->where('business_id', $this->biz)
        ->where('type', 'purchase')->where('ref_no', $payload['ref_no'])->first();
    expect($compra)->not->toBeNull('Compra não criada. HTTP=' . $resp->status() . ' flash=' . json_encode(session('status')));

    expect((float) DB::table('purchase_lines')->where('id', $linha)->value('po_quantity_purchased'))->toBe(4.0);
    expect(DB::table('transactions')->where('id', $po)->value('status'))->toBe('partial');
});
