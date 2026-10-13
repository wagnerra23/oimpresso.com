<?php

declare(strict_types=1);
// Cobre UC-COZ-01, UC-COZ-02, UC-COZ-03, UC-COZ-04 (Restaurante/Cozinha/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Thread restaurante/playbook/04, F3 — /modules/kitchen em Inertia atrás da flag useV2RestauranteCozinha.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção).
 * A Blade e o markAsCooked estão travados em CozinhaBaselineTest. Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('res_tables') || ! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['sell.view', 'access_all_locations'], $this->business);
    $this->local = EstoqueFixture::locationId($this->business->id, '-COZ-'.uniqid());
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

/** Pedido de cozinha (venda final) com uma linha por situação dada; devolve o id da venda. */
function cozinhaContratoPedido(int $businessId, int $local, int $criador, array $situacoesDasLinhas): int
{
    $produto = EstoqueFixture::singleProduct($businessId);
    $id = (int) DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due',
        'is_kitchen_order' => 1, 'transaction_date' => now(), 'final_total' => 10, 'total_before_tax' => 10,
        'created_by' => $criador, 'essentials_duration' => 0, 'invoice_no' => 'COZ-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($situacoesDasLinhas as $situacao) {
        DB::table('transaction_sell_lines')->insert([
            'transaction_id' => $id, 'product_id' => $produto->productId, 'variation_id' => $produto->variationId(),
            'quantity' => 2, 'unit_price' => 10, 'unit_price_inc_tax' => 10, 'item_tax' => 0,
            'res_line_order_status' => $situacao, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $id;
}

/** Prop deferida `pedidos`, pedida como o browser pede (X-Inertia + X-Requested-With). */
function cozinhaContratoFila($teste): \Illuminate\Support\Collection
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Restaurante/Cozinha/Index', 'X-Inertia-Partial-Data' => 'pedidos',
    ])->get('/modules/kitchen');
    $r->assertOk();

    return collect($r->json('props.pedidos') ?? [])->keyBy('id');
}

function cozinhaFlagDesligada(): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2RestauranteCozinha' ? false : parent::isOn($flag, $attrs);
        }
    });
}

test('UC-COZ-01 com a flag ligada, GET /modules/kitchen renderiza Inertia', function () {
    config(['feature-flags.forced_on' => 'useV2RestauranteCozinha']);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/modules/kitchen');

    $r->assertOk();
    expect($r->json('component'))->toBe('Restaurante/Cozinha/Index');
});

test('UC-COZ-02 com a flag desligada, ou com ?classico=1, GET /modules/kitchen segue na Blade', function () {
    cozinhaFlagDesligada();
    $this->get('/modules/kitchen')->assertOk()->assertViewIs('restaurant.kitchen.index');

    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    config(['feature-flags.forced_on' => 'useV2RestauranteCozinha']);
    $this->get('/modules/kitchen?classico=1')->assertOk()->assertViewIs('restaurant.kitchen.index');
});

test('UC-COZ-03 Tier 0 — a fila é a da Blade, com a situação derivada dos itens', function () {
    config(['feature-flags.forced_on' => 'useV2RestauranteCozinha']);
    $outro = $this->seededSupportClientTenant();
    $naFila = cozinhaContratoPedido($this->business->id, $this->local, $this->user->id, [null, 'cooked']);
    $pronto = cozinhaContratoPedido($this->business->id, $this->local, $this->user->id, ['cooked']);
    $alheio = cozinhaContratoPedido($outro->id, EstoqueFixture::locationId($outro->id, '-COZ-'.uniqid()), $this->user->id, [null]);

    $fila = cozinhaContratoFila($this);

    expect($fila->has($pronto))->toBeFalse();
    expect($fila->has($alheio))->toBeFalse();
    expect($fila[$naFila]['situacao'])->toBe('partial_cooked');
    expect(collect($fila[$naFila]['itens'])->pluck('situacao')->sort()->values()->all())->toBe(['cooked', 'received']);
    expect(collect($fila[$naFila]['itens'])->pluck('quantidade')->unique()->values()->all())->toEqual([2]);
});

test('UC-COZ-04 marcar como preparado pelo endpoint da tela tira o pedido da fila', function () {
    config(['feature-flags.forced_on' => 'useV2RestauranteCozinha']);
    $pedido = cozinhaContratoPedido($this->business->id, $this->local, $this->user->id, [null, 'received']);
    expect(cozinhaContratoFila($this)->has($pedido))->toBeTrue();

    $this->flushHeaders();
    $this->getJson('/modules/kitchen/mark-as-cooked/'.$pedido)->assertOk()->assertJson(['success' => 1]);

    expect(DB::table('transaction_sell_lines')->where('transaction_id', $pedido)->pluck('res_line_order_status')->unique()->all())->toBe(['cooked']);
    expect(cozinhaContratoFila($this)->has($pedido))->toBeFalse();
});
