<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Thread restaurante/playbook/05, F2 — o que a Blade de Pedidos (/modules/orders) mostra e grava HOJE, antes da tela
 * React. Trava OrderController::index (atendente vê só os dele; outro usuário escolhe o atendente) e markAsServed
 * (só o negócio da sessão). Tenant 98 × 99 (ADR 0358).
 *
 * markLineOrderAsServed NÃO é travado aqui de propósito: ele não filtra negócio (RUNBOOK-pedidos §10, Tier 0 aberto) e
 * baseline não congela furo de isolamento — o conserto vem em PR próprio, com teste que reprove hoje.
 *
 * @see memory/requisitos/Restaurante/RUNBOOK-pedidos.md
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('res_tables') || ! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-PED-'.uniqid());
});

function pedidosBaselineEntrar($teste, \App\User $user): void
{
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id,
        // A Blade restaurant.* (layouts.restaurant:39) lê session('currency'); sem ela, 500.
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
}

/** Usuário cujo (único) papel é de atendente — `RestaurantUtil::is_service_staff` olha o primeiro papel. */
function pedidosBaselineAtendente($teste): \App\User
{
    $user = $teste->usuarioComPermissoes(['access_all_locations'], $teste->business);
    DB::table('roles')->where('id', $user->roles->first()->id)->update(['is_service_staff' => 1]);

    return \App\User::findOrFail($user->id);
}

/** Venda final com o atendente no pedido e em cada linha; devolve [id da venda, ids das linhas]. */
function pedidosBaselinePedido(int $businessId, int $local, int $atendente, array $situacoes): array
{
    $id = (int) DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due',
        'res_waiter_id' => $atendente, 'transaction_date' => now(), 'final_total' => 10, 'total_before_tax' => 10,
        'created_by' => $atendente, 'essentials_duration' => 0, 'invoice_no' => 'PED-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $linhas = [];
    foreach ($situacoes as $situacao) {
        // Um produto por linha: uk_tsl_dup_prevent proíbe venda+produto+variação repetidos.
        $produto = EstoqueFixture::singleProduct($businessId);
        $linhas[] = (int) DB::table('transaction_sell_lines')->insertGetId([
            'transaction_id' => $id, 'product_id' => $produto->productId, 'variation_id' => $produto->variationId(),
            'quantity' => 1, 'unit_price' => 10, 'unit_price_inc_tax' => 10, 'item_tax' => 0,
            'res_service_staff_id' => $atendente, 'res_line_order_status' => $situacao, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return [$id, $linhas];
}

test('o atendente vê só os pedidos e itens dele, do negócio da sessão', function () {
    $eu = pedidosBaselineAtendente($this);
    $colega = pedidosBaselineAtendente($this);
    [$meu, $minhasLinhas] = pedidosBaselinePedido($this->business->id, $this->local, $eu->id, [null, 'cooked']);
    [$doColega] = pedidosBaselinePedido($this->business->id, $this->local, $colega->id, [null]);
    $outro = $this->seededSupportClientTenant();
    [$alheio] = pedidosBaselinePedido($outro->id, EstoqueFixture::locationId($outro->id, '-PED-'.uniqid()), $eu->id, [null]);

    pedidosBaselineEntrar($this, $eu);
    $r = $this->get('/modules/orders')->assertOk()->assertViewIs('restaurant.orders.index');

    expect($r->viewData('is_service_staff'))->toBeTrue();
    expect(collect($r->viewData('orders'))->pluck('id')->all())->toContain($meu)->not->toContain($doColega)->not->toContain($alheio);
    expect(collect($r->viewData('line_orders'))->pluck('id')->sort()->values()->all())->toBe(collect($minhasLinhas)->sort()->values()->all());
});

test('quem não é atendente escolhe o atendente; sem escolha, não vê pedido nenhum', function () {
    $atendente = pedidosBaselineAtendente($this);
    [$pedido] = pedidosBaselinePedido($this->business->id, $this->local, $atendente->id, [null]);
    pedidosBaselineEntrar($this, $this->usuarioComPermissoes(['access_all_locations'], $this->business));

    $sem = $this->get('/modules/orders')->assertOk();
    expect($sem->viewData('is_service_staff'))->toBeFalse();
    expect($sem->viewData('orders'))->toBeEmpty();
    expect(collect($sem->viewData('service_staff'))->keys()->all())->toContain($atendente->id);

    $com = $this->get('/modules/orders?service_staff='.$atendente->id)->assertOk();
    expect(collect($com->viewData('orders'))->pluck('id')->all())->toContain($pedido);
});

test('marcar o pedido como servido não toca pedido de outro negócio', function () {
    $eu = pedidosBaselineAtendente($this);
    [$meu] = pedidosBaselinePedido($this->business->id, $this->local, $eu->id, [null, 'cooked']);
    $outro = $this->seededSupportClientTenant();
    [$alheio] = pedidosBaselinePedido($outro->id, EstoqueFixture::locationId($outro->id, '-PED-'.uniqid()), $eu->id, [null]);
    pedidosBaselineEntrar($this, $eu);

    $this->getJson('/modules/orders/mark-as-served/'.$meu)->assertOk()->assertJson(['success' => 1]);
    $this->getJson('/modules/orders/mark-as-served/'.$alheio)->assertOk();

    expect(DB::table('transaction_sell_lines')->where('transaction_id', $meu)->pluck('res_line_order_status')->unique()->all())->toBe(['served']);
    expect(DB::table('transaction_sell_lines')->where('transaction_id', $alheio)->value('res_line_order_status'))->toBeNull();
});
