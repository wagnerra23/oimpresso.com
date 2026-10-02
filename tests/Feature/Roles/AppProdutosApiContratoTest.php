<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

use App\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * API de Produtos do app das lojas (tela 19) — GET /api/app/produtos, só leitura.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §9.1. Permissão `product.view`; estoque
 * só dos locais permitidos ao usuário; "baixo" = regra do alerta da web (qtd <= alert_quantity).
 * NÃO derivado do controller.
 *
 * Tier 0: tenant fictício 98 (ADR 0358) contra o business 2 da lane. Transação revertida.
 */

const APP_PRD_BIZ = 98;
const APP_PRD_OUTRO = 2;

function appPrdUsuario(array $permissoes, int $biz = APP_PRD_BIZ): User
{
    $user = User::factory()->create(['business_id' => $biz]);
    $papel = Role::create(['name' => 'AppPrd' . uniqid() . '#' . $biz, 'business_id' => $biz, 'guard_name' => 'web']);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

/** Loja do business — FKs NOT NULL de invoice_scheme/layout criadas se faltarem (padrão do PurchaseIndexTenantContratoTest). */
function appPrdLoja(int $biz, string $nome): int
{
    $scheme = DB::table('invoice_schemes')->value('id') ?: DB::table('invoice_schemes')->insertGetId([
        'business_id' => $biz, 'name' => 'App Scheme', 'scheme_type' => 'blank', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $layout = DB::table('invoice_layouts')->value('id') ?: DB::table('invoice_layouts')->insertGetId([
        'business_id' => $biz, 'name' => 'App Layout', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('business_locations')->insertGetId([
        'business_id' => $biz, 'name' => $nome, 'location_id' => strtoupper(substr(md5($nome . microtime()), 0, 8)),
        'country' => 'BR', 'state' => 'SC', 'city' => 'App City', 'zip_code' => '00000000',
        'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/**
 * Produto com uma variação e estoque por loja ([loja => qtd]).
 *
 * @param  array<int, float>  $estoque
 */
function appPrdProduto(int $biz, int $criadoPor, string $nome, array $extra = [], array $estoque = [], float $preco = 10.0): int
{
    $pid = (int) DB::table('products')->insertGetId(array_merge([
        'business_id' => $biz, 'name' => $nome, 'type' => 'single', 'sku' => 'APP-' . uniqid(),
        'enable_stock' => 1, 'alert_quantity' => 5, 'created_by' => $criadoPor,
        'created_at' => now(), 'updated_at' => now(),
    ], $extra));
    $pv = (int) DB::table('product_variations')->insertGetId(['name' => 'DUMMY', 'product_id' => $pid, 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $v = (int) DB::table('variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $pid, 'sub_sku' => 'APP-' . uniqid(), 'product_variation_id' => $pv,
        'default_sell_price' => $preco, 'sell_price_inc_tax' => $preco, 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($estoque as $loja => $qtd) {
        DB::table('variation_location_details')->insert([
            'product_id' => $pid, 'product_variation_id' => $pv, 'variation_id' => $v, 'location_id' => $loja,
            'qty_available' => $qtd, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $pid;
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (DB::table('business')->whereIn('id', [APP_PRD_BIZ, APP_PRD_OUTRO])->count() !== 2) {
        $this->markTestSkipped('Tenants 98/2 ausentes nesta lane.');
    }
    DB::beginTransaction();
    $this->sufixo = uniqid();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('sem token 401; sem product.view 403', function () {
    $this->getJson('/api/app/produtos')->assertStatus(401);
    Passport::actingAs(appPrdUsuario([]), [], 'api');
    $this->getJson('/api/app/produtos')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
});

it('lista produtos do business com preço, estoque somado, baixo e categoria; outro business fica fora', function () {
    $u = appPrdUsuario(['product.view', 'access_all_locations']);
    $lojaA = appPrdLoja(APP_PRD_BIZ, "APP Loja A {$this->sufixo}");
    $lojaB = appPrdLoja(APP_PRD_BIZ, "APP Loja B {$this->sufixo}");
    $cat = (int) DB::table('categories')->insertGetId(['name' => "APP Cat {$this->sufixo}", 'business_id' => APP_PRD_BIZ, 'parent_id' => 0, 'created_by' => $u->id, 'category_type' => 'product', 'created_at' => now(), 'updated_at' => now()]);
    $unid = (int) DB::table('units')->insertGetId(['business_id' => APP_PRD_BIZ, 'actual_name' => 'Metro quadrado', 'short_name' => 'm²', 'allow_decimal' => 1, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);

    $ok = appPrdProduto(APP_PRD_BIZ, (int) $u->id, "APP Lona {$this->sufixo}", ['category_id' => $cat, 'unit_id' => $unid], [$lojaA => 10, $lojaB => 7], 48.5);
    $baixo = appPrdProduto(APP_PRD_BIZ, (int) $u->id, "APP Adesivo {$this->sufixo}", [], [$lojaA => 2]);
    $sobDemanda = appPrdProduto(APP_PRD_BIZ, (int) $u->id, "APP Arte {$this->sufixo}", ['enable_stock' => 0]);
    $inativo = appPrdProduto(APP_PRD_BIZ, (int) $u->id, "APP Inativo {$this->sufixo}", ['is_inactive' => 1]);
    $alheio = appPrdProduto(APP_PRD_OUTRO, (int) $u->id, "APP Alheio {$this->sufixo}");
    Passport::actingAs($u, [], 'api');

    $r = $this->getJson('/api/app/produtos?q=' . urlencode($this->sufixo))->assertOk();
    $itens = collect($r->json('itens'))->keyBy('id');

    expect($itens->keys()->all())->toEqualCanonicalizing([$ok, $baixo, $sobDemanda]);
    expect($itens[$ok]['categoria'])->toBe("APP Cat {$this->sufixo}");
    expect($itens[$ok]['calculo'])->toBe('por m²');
    expect((float) $itens[$ok]['preco'])->toBe(48.5);
    expect($itens[$ok]['variacoes'])->toBeNull();
    expect((float) $itens[$ok]['estoque']['qtd'])->toBe(17.0);
    expect($itens[$ok]['baixo'])->toBeFalse();
    expect($itens[$baixo]['baixo'])->toBeTrue();
    expect($itens[$sobDemanda]['estoque'])->toBe(['controla' => false, 'qtd' => null, 'unidade' => null]);
    expect($r->json('total'))->toBe(3);
    expect($r->json('categorias'))->toBe([['id' => $cat, 'nome' => "APP Cat {$this->sufixo}", 'total' => 1]]);
    expect($r->json('baixo_estoque'))->toBeGreaterThanOrEqual(1);
    // Um needle por assert: `not->toContain(a, b)` passa se SÓ UM faltar (§5 2026-09-22, LC-31).
    expect($itens->keys()->all())->not->toContain($inativo);
    expect($itens->keys()->all())->not->toContain($alheio);

    $porCat = $this->getJson("/api/app/produtos?categoria={$cat}&q=" . urlencode($this->sufixo))->assertOk();
    expect(collect($porCat->json('itens'))->pluck('id')->all())->toBe([$ok]);
});

it('estoque soma só os locais permitidos; sem local permitido o estoque é 0', function () {
    $lojaA = appPrdLoja(APP_PRD_BIZ, "APP Loja A {$this->sufixo}");
    $lojaB = appPrdLoja(APP_PRD_BIZ, "APP Loja B {$this->sufixo}");
    Permission::findOrCreate("location.{$lojaA}", 'web');
    $soA = appPrdUsuario(['product.view', "location.{$lojaA}"]);
    $nenhum = appPrdUsuario(['product.view']);
    $p = appPrdProduto(APP_PRD_BIZ, (int) $soA->id, "APP Lona {$this->sufixo}", [], [$lojaA => 10, $lojaB => 7]);

    Passport::actingAs($soA, [], 'api');
    $item = collect($this->getJson('/api/app/produtos?q=' . urlencode($this->sufixo))->assertOk()->json('itens'))->firstWhere('id', $p);
    expect((float) $item['estoque']['qtd'])->toBe(10.0);

    Passport::actingAs($nenhum, [], 'api');
    $item = collect($this->getJson('/api/app/produtos?q=' . urlencode($this->sufixo))->assertOk()->json('itens'))->firstWhere('id', $p);
    expect((float) $item['estoque']['qtd'])->toBe(0.0);
});
