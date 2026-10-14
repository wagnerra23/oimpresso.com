<?php

declare(strict_types=1);
// Cobre UC-PEDR-01, UC-PEDR-02, UC-PEDR-03, UC-PEDR-04 (Restaurante/Pedidos/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Thread restaurante/playbook/05, F3 — /modules/orders em Inertia atrás da flag useV2RestaurantePedidos.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção).
 * A Blade e o markAsServed estão travados em PedidosBaselineTest. Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('res_tables') || ! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-PED-'.uniqid());
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
});

function pedidosContratoEntrar($teste, \App\User $user): void
{
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id,
        // A Blade restaurant.* (layouts.restaurant:39) lê session('currency'); sem ela, 500.
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
}

/** Usuário cujo (único) papel é de atendente — `RestaurantUtil::is_service_staff` olha o primeiro papel. */
function pedidosContratoAtendente($teste): \App\User
{
    $user = $teste->usuarioComPermissoes(['access_all_locations'], $teste->business);
    DB::table('roles')->where('id', $user->roles->first()->id)->update(['is_service_staff' => 1]);

    return \App\User::findOrFail($user->id);
}

/** Venda final com o atendente no pedido e em cada linha; devolve o id da venda. */
function pedidosContratoPedido(int $businessId, int $local, int $atendente, array $situacoes): int
{
    $id = (int) DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due',
        'res_waiter_id' => $atendente, 'transaction_date' => now(), 'final_total' => 10, 'total_before_tax' => 10,
        'created_by' => $atendente, 'essentials_duration' => 0, 'invoice_no' => 'PED-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($situacoes as $situacao) {
        // Um produto por linha: uk_tsl_dup_prevent proíbe venda+produto+variação repetidos.
        $produto = EstoqueFixture::singleProduct($businessId);
        DB::table('transaction_sell_lines')->insert([
            'transaction_id' => $id, 'product_id' => $produto->productId, 'variation_id' => $produto->variationId(),
            'quantity' => 1, 'unit_price' => 10, 'unit_price_inc_tax' => 10, 'item_tax' => 0,
            'res_service_staff_id' => $atendente, 'res_line_order_status' => $situacao, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $id;
}

/** A visita Inertia inteira, com as props deferidas pedidas como o browser pede. */
function pedidosContratoVisita($teste, string $url, ?string $parcial = null): \Illuminate\Testing\TestResponse
{
    $teste->flushHeaders();
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];
    if ($parcial) {
        $headers += ['X-Inertia-Partial-Component' => 'Restaurante/Pedidos/Index', 'X-Inertia-Partial-Data' => $parcial];
    }
    $r = $teste->withHeaders($headers)->get($url);
    $r->assertOk();

    return $r;
}

function pedidosFlagDesligada(): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2RestaurantePedidos' ? false : parent::isOn($flag, $attrs);
        }
    });
}

test('UC-PEDR-01 com a flag ligada, GET /modules/orders renderiza Inertia', function () {
    config(['feature-flags.forced_on' => 'useV2RestaurantePedidos']);
    pedidosContratoEntrar($this, pedidosContratoAtendente($this));

    expect(pedidosContratoVisita($this, '/modules/orders')->json('component'))->toBe('Restaurante/Pedidos/Index');
});

test('UC-PEDR-02 com a flag desligada, ou com ?classico=1, GET /modules/orders segue na Blade', function () {
    pedidosContratoEntrar($this, pedidosContratoAtendente($this));
    pedidosFlagDesligada();
    $this->get('/modules/orders')->assertOk()->assertViewIs('restaurant.orders.index');

    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    config(['feature-flags.forced_on' => 'useV2RestaurantePedidos']);
    $this->get('/modules/orders?classico=1')->assertOk()->assertViewIs('restaurant.orders.index');
});

test('UC-PEDR-03 Tier 0 — o atendente vê só os pedidos e itens dele, do negócio da sessão', function () {
    config(['feature-flags.forced_on' => 'useV2RestaurantePedidos']);
    $eu = pedidosContratoAtendente($this);
    $colega = pedidosContratoAtendente($this);
    $meu = pedidosContratoPedido($this->business->id, $this->local, $eu->id, [null, 'cooked']);
    $doColega = pedidosContratoPedido($this->business->id, $this->local, $colega->id, [null]);
    $outro = $this->seededSupportClientTenant();
    $alheio = pedidosContratoPedido($outro->id, EstoqueFixture::locationId($outro->id, '-PED-'.uniqid()), $eu->id, [null]);
    pedidosContratoEntrar($this, $eu);

    expect(pedidosContratoVisita($this, '/modules/orders')->json('props.e_atendente'))->toBeTrue();
    $r = pedidosContratoVisita($this, '/modules/orders', 'itens,pedidos');
    $pedidos = collect($r->json('props.pedidos'))->keyBy('id');
    $itens = collect($r->json('props.itens'));

    expect($pedidos->keys()->all())->toContain($meu)->not->toContain($doColega)->not->toContain($alheio);
    expect($pedidos[$meu]['situacao'])->toBe('partial_cooked');
    expect($itens->pluck('pedido_id')->unique()->values()->all())->toBe([$meu]);
    expect($itens->pluck('situacao')->sort()->values()->all())->toBe(['cooked', 'received']);
});

test('UC-PEDR-04 quem não é atendente escolhe o atendente; sem escolha, nenhum pedido', function () {
    config(['feature-flags.forced_on' => 'useV2RestaurantePedidos']);
    $atendente = pedidosContratoAtendente($this);
    $pedido = pedidosContratoPedido($this->business->id, $this->local, $atendente->id, [null]);
    pedidosContratoEntrar($this, $this->usuarioComPermissoes(['access_all_locations'], $this->business));

    $sem = pedidosContratoVisita($this, '/modules/orders');
    expect($sem->json('props.e_atendente'))->toBeFalse();
    expect($sem->json('props.atendente_escolhido'))->toBeNull();
    expect(collect($sem->json('props.atendentes'))->pluck('id')->all())->toContain($atendente->id);
    expect(pedidosContratoVisita($this, '/modules/orders', 'pedidos')->json('props.pedidos'))->toBe([]);

    $com = pedidosContratoVisita($this, '/modules/orders?service_staff='.$atendente->id, 'pedidos');
    expect(collect($com->json('props.pedidos'))->pluck('id')->all())->toContain($pedido);
});
