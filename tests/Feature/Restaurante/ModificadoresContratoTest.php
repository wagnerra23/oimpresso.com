<?php

declare(strict_types=1);
// Cobre UC-MODIF-01, UC-MODIF-02, UC-MODIF-03, UC-MODIF-04 (Restaurante/Modificadores/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread restaurante/playbook/02, F3 — /modules/modifiers em Inertia atrás da flag useV2RestauranteModificadores.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção).
 * A Blade está travada em ModificadoresBaselineTest. Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('res_product_modifier_sets') || ! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
});

function modContrEntrar($teste, array $permissoes): \App\User
{
    $user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);

    return $user;
}

function modContrProduto(int $businessId, int $criador, string $nome, string $tipo = 'single'): int
{
    return DB::table('products')->insertGetId([
        'name' => $nome, 'business_id' => $businessId, 'type' => $tipo, 'tax_type' => 'inclusive',
        'sku' => 'MODC-'.uniqid(), 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function modContrConjunto(int $businessId, int $criador, string $nome, array $modificadores): int
{
    $id = modContrProduto($businessId, $criador, $nome, 'modifier');
    $pv = DB::table('product_variations')->insertGetId(['name' => 'DUMMY', 'product_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
    foreach ($modificadores as $m) {
        DB::table('variations')->insert([
            'name' => $m, 'product_id' => $id, 'product_variation_id' => $pv, 'sub_sku' => 'MODCV-'.uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $id;
}

function modContrInertia($teste, array $extra = []): \Illuminate\Testing\TestResponse
{
    return $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ] + $extra)->get('/modules/modifiers');
}

function modContrFlagDesligada(): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2RestauranteModificadores' ? false : parent::isOn($flag, $attrs);
        }
    });
}

test('UC-MODIF-01 com a flag ligada, GET /modules/modifiers renderiza Inertia (não a DataTable)', function () {
    config(['feature-flags.forced_on' => 'useV2RestauranteModificadores']);
    modContrEntrar($this, ['product.view']);

    $r = modContrInertia($this);

    $r->assertOk();
    expect($r->json('component'))->toBe('Restaurante/Modificadores/Index');
});

test('UC-MODIF-02 com a flag desligada, ou com ?classico=1, GET /modules/modifiers segue na Blade', function () {
    modContrEntrar($this, ['product.view']);
    modContrFlagDesligada();
    $this->get('/modules/modifiers')->assertOk()->assertViewIs('restaurant.modifier_sets.index');

    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    config(['feature-flags.forced_on' => 'useV2RestauranteModificadores']);
    $this->get('/modules/modifiers?classico=1')->assertOk()->assertViewIs('restaurant.modifier_sets.index');
});

test('UC-MODIF-03 Tier 0 — meus conjuntos, com modificadores e só os produtos do meu negócio', function () {
    config(['feature-flags.forced_on' => 'useV2RestauranteModificadores']);
    $user = modContrEntrar($this, ['product.view']);
    $outro = $this->seededSupportClientTenant();

    $meu = modContrConjunto($this->business->id, $user->id, 'Acabamento '.uniqid(), ['Bastão', 'Ilhós']);
    $alheio = modContrConjunto($outro->id, $user->id, 'Alheio '.uniqid(), ['X']);
    $produtoMeu = 'Banner '.uniqid();
    $produtoAlheio = 'Alheio '.uniqid();
    DB::table('res_product_modifier_sets')->insert([
        ['modifier_set_id' => $meu, 'product_id' => modContrProduto($this->business->id, $user->id, $produtoMeu)],
        ['modifier_set_id' => $meu, 'product_id' => modContrProduto($outro->id, $user->id, $produtoAlheio)],
    ]);

    $r = modContrInertia($this, ['X-Inertia-Partial-Component' => 'Restaurante/Modificadores/Index', 'X-Inertia-Partial-Data' => 'conjuntos']);
    $r->assertOk();
    $conjuntos = collect($r->json('props.conjuntos') ?? [])->keyBy('id');

    expect($conjuntos->has($alheio))->toBeFalse();
    expect($conjuntos[$meu]['modificadores'])->toBe(['Bastão', 'Ilhós']);
    expect($conjuntos[$meu]['produtos'])->toBe([$produtoMeu]);
});

test('UC-MODIF-04 quem só cadastra também vê a lista; quem só vê não gerencia', function () {
    config(['feature-flags.forced_on' => 'useV2RestauranteModificadores']);

    modContrEntrar($this, ['product.create']);
    $r = modContrInertia($this);
    $r->assertOk();
    expect($r->json('component'))->toBe('Restaurante/Modificadores/Index');
    expect($r->json('props.pode_gerenciar'))->toBeTrue();

    modContrEntrar($this, ['product.view']);
    expect(modContrInertia($this)->json('props.pode_gerenciar'))->toBeFalse();
});
