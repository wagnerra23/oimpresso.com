<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Thread restaurante/playbook/04, F2 — o que a Blade da Cozinha (/modules/kitchen) mostra e grava HOJE, antes da tela
 * React. Trava KitchenController::index (fila = RestaurantUtil::getAllOrders com item por preparar e is_kitchen_order)
 * e markAsCooked (só itens do negócio da sessão, received → cooked). Tenant 98 × 99 (ADR 0358).
 *
 * @see memory/requisitos/Restaurante/RUNBOOK-cozinha.md
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('res_tables') || ! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-COZ-'.uniqid());
});

function cozinhaBaselineEntrar($teste, \App\User $user): void
{
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);
}

/** Pedido de cozinha (venda final) com uma linha por situação dada; devolve o id da venda. */
function cozinhaBaselinePedido(int $businessId, int $local, int $criador, array $situacoesDasLinhas, int $cozinha = 1): int
{
    $produto = EstoqueFixture::singleProduct($businessId);
    $id = (int) DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due',
        'is_kitchen_order' => $cozinha, 'transaction_date' => now(), 'final_total' => 10, 'total_before_tax' => 10,
        'created_by' => $criador, 'essentials_duration' => 0, 'invoice_no' => 'COZ-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($situacoesDasLinhas as $situacao) {
        DB::table('transaction_sell_lines')->insert([
            'transaction_id' => $id, 'product_id' => $produto->productId, 'variation_id' => $produto->variationId(),
            'quantity' => 1, 'unit_price' => 10, 'unit_price_inc_tax' => 10, 'item_tax' => 0,
            'res_line_order_status' => $situacao, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $id;
}

test('a fila da Blade traz só pedidos de cozinha do negócio com item por preparar', function () {
    $user = $this->usuarioComPermissoes(['sell.view', 'access_all_locations'], $this->business);
    cozinhaBaselineEntrar($this, $user);
    $outro = $this->seededSupportClientTenant();

    $naFila = cozinhaBaselinePedido($this->business->id, $this->local, $user->id, [null, 'cooked']);
    $pronto = cozinhaBaselinePedido($this->business->id, $this->local, $user->id, ['cooked']);
    $foraDaCozinha = cozinhaBaselinePedido($this->business->id, $this->local, $user->id, [null], 0);
    $alheio = cozinhaBaselinePedido($outro->id, EstoqueFixture::locationId($outro->id, '-COZ-'.uniqid()), $user->id, [null]);

    $r = $this->get('/modules/kitchen')->assertOk()->assertViewIs('restaurant.kitchen.index');
    $ids = collect($r->viewData('orders'))->pluck('id');

    expect($ids)->toContain($naFila);
    expect($ids)->not->toContain($pronto);
    expect($ids)->not->toContain($foraDaCozinha);
    expect($ids)->not->toContain($alheio);
});

test('sem sell.view a rota devolve 403', function () {
    cozinhaBaselineEntrar($this, $this->usuarioComPermissoes([], $this->business));

    $this->get('/modules/kitchen')->assertForbidden();
});

test('marcar como preparado muda só os itens recebidos do negócio da sessão', function () {
    $user = $this->usuarioComPermissoes(['sell.view', 'access_all_locations'], $this->business);
    cozinhaBaselineEntrar($this, $user);
    $outro = $this->seededSupportClientTenant();
    $meu = cozinhaBaselinePedido($this->business->id, $this->local, $user->id, [null, 'received', 'served']);
    $alheio = cozinhaBaselinePedido($outro->id, EstoqueFixture::locationId($outro->id, '-COZ-'.uniqid()), $user->id, [null]);

    $this->getJson('/modules/kitchen/mark-as-cooked/'.$meu)->assertOk()->assertJson(['success' => 1]);
    $this->getJson('/modules/kitchen/mark-as-cooked/'.$alheio)->assertOk();

    expect(DB::table('transaction_sell_lines')->where('transaction_id', $meu)->orderBy('id')->pluck('res_line_order_status')->all())
        ->toBe(['cooked', 'cooked', 'served']);
    expect(DB::table('transaction_sell_lines')->where('transaction_id', $alheio)->value('res_line_order_status'))->toBeNull();
});
