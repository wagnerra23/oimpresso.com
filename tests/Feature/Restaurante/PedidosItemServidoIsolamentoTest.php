<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Tier 0 (ADR 0093) — OrderController::markLineOrderAsServed buscava o item de venda só pelo id, sem o negócio da
 * sessão: um usuário de qualquer empresa marcava `served` o item de outra. Os irmãos markAsServed e
 * KitchenController::markAsCooked já filtram por transactions.business_id. Tenant 98 × 99 (ADR 0358).
 *
 * @see memory/requisitos/Restaurante/RUNBOOK-pedidos.md §10
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('res_tables') || ! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-SRV-'.uniqid());
});

function itemServidoEntrar($teste, \App\User $user): void
{
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);
}

/** Usuário cujo (único) papel é de atendente — `RestaurantUtil::is_service_staff` olha o primeiro papel. */
function itemServidoAtendente($teste): \App\User
{
    $user = $teste->usuarioComPermissoes(['access_all_locations'], $teste->business);
    DB::table('roles')->where('id', $user->roles->first()->id)->update(['is_service_staff' => 1]);

    return \App\User::findOrFail($user->id);
}

/** Venda final com um item atendido por `$atendente`; devolve o id do item. */
function itemServidoLinha(int $businessId, int $local, int $atendente): int
{
    $produto = EstoqueFixture::singleProduct($businessId);
    $venda = (int) DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due',
        'res_waiter_id' => $atendente, 'transaction_date' => now(), 'final_total' => 10, 'total_before_tax' => 10,
        'created_by' => $atendente, 'essentials_duration' => 0, 'invoice_no' => 'SRV-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('transaction_sell_lines')->insertGetId([
        'transaction_id' => $venda, 'product_id' => $produto->productId, 'variation_id' => $produto->variationId(),
        'quantity' => 1, 'unit_price' => 10, 'unit_price_inc_tax' => 10, 'item_tax' => 0,
        'res_service_staff_id' => $atendente, 'res_line_order_status' => null, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function itemServidoSituacao(int $linha): ?string
{
    return DB::table('transaction_sell_lines')->where('id', $linha)->value('res_line_order_status');
}

test('marcar item como servido não toca item de venda de outro negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $atendenteAlheio = $this->usuarioComPermissoes(['access_all_locations'], $outro);
    $alheia = itemServidoLinha($outro->id, EstoqueFixture::locationId($outro->id, '-SRV-'.uniqid()), $atendenteAlheio->id);
    itemServidoEntrar($this, $this->usuarioComPermissoes(['access_all_locations'], $this->business));

    $this->getJson('/modules/orders/mark-line-order-as-served/'.$alheia)->assertOk()->assertJson(['success' => 0]);

    expect(itemServidoSituacao($alheia))->toBeNull();
});

test('no próprio negócio, quem não é atendente marca o item como servido', function () {
    $atendente = itemServidoAtendente($this);
    $minha = itemServidoLinha($this->business->id, $this->local, $atendente->id);
    itemServidoEntrar($this, $this->usuarioComPermissoes(['access_all_locations'], $this->business));

    $this->getJson('/modules/orders/mark-line-order-as-served/'.$minha)->assertOk()->assertJson(['success' => 1]);

    expect(itemServidoSituacao($minha))->toBe('served');
});

test('o atendente marca só os itens dele', function () {
    $eu = itemServidoAtendente($this);
    $colega = itemServidoAtendente($this);
    $minha = itemServidoLinha($this->business->id, $this->local, $eu->id);
    $doColega = itemServidoLinha($this->business->id, $this->local, $colega->id);
    itemServidoEntrar($this, $eu);

    $this->getJson('/modules/orders/mark-line-order-as-served/'.$minha)->assertOk()->assertJson(['success' => 1]);
    $this->getJson('/modules/orders/mark-line-order-as-served/'.$doColega)->assertOk()->assertJson(['success' => 0]);

    expect(itemServidoSituacao($minha))->toBe('served');
    expect(itemServidoSituacao($doColega))->toBeNull();
});
