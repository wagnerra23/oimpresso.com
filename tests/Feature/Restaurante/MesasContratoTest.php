<?php

declare(strict_types=1);
// Cobre UC-MESA-01, UC-MESA-02, UC-MESA-03, UC-MESA-04 (Restaurante/Mesas/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Thread restaurante/playbook/01, F3 — /modules/tables em Inertia atrás da flag useV2RestauranteMesas.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção).
 * A Blade está travada em MesasBaselineTest. Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('res_tables') || ! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['access_tables', 'access_all_locations'], $this->business);
    $this->local = EstoqueFixture::locationId($this->business->id, '-MESA-'.uniqid());
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function mesasContratoMesa(int $businessId, int $local, int $criador, string $nome): int
{
    return DB::table('res_tables')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'name' => $nome, 'description' => 'Desc '.$nome,
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function mesasContratoPedido(int $businessId, int $local, int $criador, int $mesa, ?int $atendente, ?string $situacao = null): int
{
    return DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due',
        'res_table_id' => $mesa, 'res_waiter_id' => $atendente, 'res_order_status' => $situacao,
        'transaction_date' => now(), 'final_total' => 10, 'total_before_tax' => 10,
        'created_by' => $criador, 'essentials_duration' => 0, 'invoice_no' => 'MESA-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Props deferidas `mesas` e `pedidos_hoje`. */
function mesasContratoProps($teste): array
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Restaurante/Mesas/Index', 'X-Inertia-Partial-Data' => 'mesas,pedidos_hoje',
    ])->get('/modules/tables');
    $r->assertOk();

    return ['mesas' => collect($r->json('props.mesas') ?? [])->keyBy('id'), 'pedidos_hoje' => $r->json('props.pedidos_hoje')];
}

function mesasFlagDesligada(): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2RestauranteMesas' ? false : parent::isOn($flag, $attrs);
        }
    });
}

test('UC-MESA-01 com a flag ligada, GET /modules/tables renderiza Inertia (não a DataTable)', function () {
    config(['feature-flags.forced_on' => 'useV2RestauranteMesas']);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/modules/tables');

    $r->assertOk();
    expect($r->json('component'))->toBe('Restaurante/Mesas/Index');
});

test('UC-MESA-02 com a flag desligada, ou com ?classico=1, GET /modules/tables segue na Blade', function () {
    mesasFlagDesligada();
    $this->get('/modules/tables')->assertOk()->assertViewIs('restaurant.table.index');

    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    config(['feature-flags.forced_on' => 'useV2RestauranteMesas']);
    $this->get('/modules/tables?classico=1')->assertOk()->assertViewIs('restaurant.table.index');
});

test('UC-MESA-03 Tier 0 — minhas mesas, ocupada pelo pedido aberto e com o atendente dele', function () {
    config(['feature-flags.forced_on' => 'useV2RestauranteMesas']);
    $outro = $this->seededSupportClientTenant();
    $localOutro = EstoqueFixture::locationId($outro->id, '-MESA-'.uniqid());
    $atendente = \App\User::factory()->create(['business_id' => $this->business->id, 'first_name' => 'Larissa', 'surname' => null, 'last_name' => 'Prado']);

    $ocupada = mesasContratoMesa($this->business->id, $this->local, $this->user->id, 'Ocupada '.uniqid());
    $servida = mesasContratoMesa($this->business->id, $this->local, $this->user->id, 'Servida '.uniqid());
    $livre = mesasContratoMesa($this->business->id, $this->local, $this->user->id, 'Livre '.uniqid());
    $alheia = mesasContratoMesa($outro->id, $localOutro, $this->user->id, 'Alheia '.uniqid());
    mesasContratoPedido($this->business->id, $this->local, $this->user->id, $ocupada, $atendente->id);
    mesasContratoPedido($this->business->id, $this->local, $this->user->id, $servida, $atendente->id, 'served');
    mesasContratoPedido($outro->id, $localOutro, $this->user->id, $alheia, null);

    $mesas = mesasContratoProps($this)['mesas'];

    expect($mesas->has($alheia))->toBeFalse();
    expect($mesas[$ocupada]['ocupada'])->toBeTrue();
    expect($mesas[$ocupada]['pedidos_abertos'])->toBe(1);
    expect($mesas[$ocupada]['atendente'])->toBe('Larissa Prado');
    expect($mesas[$servida]['ocupada'])->toBeFalse();
    expect($mesas[$servida]['atendente'])->toBeNull();
    expect($mesas[$livre]['ocupada'])->toBeFalse();
    expect($mesas[$livre]['local'])->toBe(DB::table('business_locations')->where('id', $this->local)->value('name'));
});

test('UC-MESA-04 pedidos de hoje contam só vendas finalizadas com mesa do meu negócio; gerenciar só para Admin#', function () {
    config(['feature-flags.forced_on' => 'useV2RestauranteMesas']);
    $antes = mesasContratoProps($this)['pedidos_hoje'];
    $mesa = mesasContratoMesa($this->business->id, $this->local, $this->user->id, 'Hoje '.uniqid());
    mesasContratoPedido($this->business->id, $this->local, $this->user->id, $mesa, null);
    mesasContratoPedido($this->business->id, $this->local, $this->user->id, $mesa, null, 'served');
    $outro = $this->seededSupportClientTenant();
    $localOutro = EstoqueFixture::locationId($outro->id, '-MESA-'.uniqid());
    mesasContratoPedido($outro->id, $localOutro, $this->user->id, mesasContratoMesa($outro->id, $localOutro, $this->user->id, 'X'), null);

    expect(mesasContratoProps($this)['pedidos_hoje'])->toBe($antes + 2);

    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia])->get('/modules/tables');
    expect($r->json('props.pode_gerenciar'))->toBeFalse();
});
