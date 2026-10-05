<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EstoqueFixture;

/**
 * Venda rápida do app das lojas (tela 11) — GET /api/app/venda/produtos?q=, só leitura.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §2.2. NÃO derivado do controller:
 * local de venda = 1º local ativo que o usuário acessa (regra da web), preço = o do grupo padrão
 * do local quando houver (o mesmo do PDV), estoque do local de venda e `null` sem controle de
 * estoque, mesmas permissões da venda direta web (sell.create / direct_sell.access).
 *
 * Tier 0 (ADR 0093): tenant fictício 98 (ADR 0358) contra o 2. O produto do 2 é ligado ao local
 * do 98 de propósito — só o filtro de business o esconde. Controle positivo em par: o do 98
 * APARECE, senão o "não aparece" seria verde por vácuo.
 */
uses(DatabaseTransactions::class);

const APP_VR_BIZ = 98;
const APP_VR_OUTRO = 2;

function appVrUsuario(array $permissoes): User
{
    $user = User::factory()->create(['business_id' => APP_VR_BIZ]);
    $papel = Role::create(['name' => 'AppVr' . uniqid() . '#' . APP_VR_BIZ, 'business_id' => APP_VR_BIZ, 'guard_name' => 'web']);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    // `location.<id>` vai DIRETO no usuário: User::permitted_locations lê só `$user->permissions`
    // (permissões diretas), como a tela de usuários do ERP grava. No papel, o local não conta.
    $locais = array_values(array_filter($permissoes, fn ($p) => str_starts_with($p, 'location.')));
    $papel->syncPermissions(array_values(array_diff($permissoes, $locais)));
    $user->assignRole($papel);
    if ($locais !== []) {
        $user->givePermissionTo($locais);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

/** Produto `single` no business, disponível no local, com preço e (opcional) estoque nele. */
function appVrProduto(int $biz, string $nome, float $preco, int $localId, ?float $estoque, bool $controlaEstoque = true): int
{
    $p = EstoqueFixture::singleProduct($biz, $controlaEstoque);
    DB::table('products')->where('id', $p->productId)->update(['name' => $nome]);
    DB::table('variations')->where('id', $p->variationId())->update(['sell_price_inc_tax' => $preco]);
    DB::table('product_locations')->insert(['product_id' => $p->productId, 'location_id' => $localId]);
    if ($estoque !== null) {
        EstoqueFixture::setStock($p, 0, $localId, $estoque);
    }

    return $p->variationId();
}

function appVrVenderSemEstoque(bool $permite): void
{
    $pos = json_decode((string) DB::table('business')->where('id', APP_VR_BIZ)->value('pos_settings'), true) ?: [];
    $pos['allow_overselling'] = $permite ? 1 : 0;
    DB::table('business')->where('id', APP_VR_BIZ)->update(['pos_settings' => json_encode($pos)]);
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite' || ! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS exige MySQL (ADR 0358).');
    }
    if (DB::table('business')->whereIn('id', [APP_VR_BIZ, APP_VR_OUTRO])->count() !== 2) {
        $this->markTestSkipped('Tenants 98/2 ausentes nesta lane.');
    }

    // Estado conhecido: o 98 NÃO vende sem estoque (cada caso que precisa do contrário liga).
    appVrVenderSemEstoque(false);

    $this->local = EstoqueFixture::locationId(APP_VR_BIZ, '-APPVR');
    $this->outroLocal = EstoqueFixture::locationId(APP_VR_BIZ, '-APPVR2');
    $this->prefixo = 'AppVR ' . uniqid();
    // Usuário que só acessa o local -APPVR: o local de venda é determinado, sem depender da ordem
    // dos locais já semeados no 98.
    $this->user = appVrUsuario(['direct_sell.access', 'location.' . $this->local]);
    Passport::actingAs($this->user, [], 'api');
});

it('UC-APPVR-01 · sem token responde 401', function () {
    $this->app['auth']->forgetGuards();
    $this->withHeaders(['Accept' => 'application/json'])->get('/api/app/venda/produtos')->assertStatus(401);
});

it('UC-APPVR-02 · sem sell.create nem direct_sell.access responde 403 sem_permissao; com sell.create responde 200 (controle)', function () {
    Passport::actingAs(appVrUsuario(['location.' . $this->local]), [], 'api');
    $this->getJson('/api/app/venda/produtos')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    Passport::actingAs(appVrUsuario(['sell.create', 'location.' . $this->local]), [], 'api');
    $this->getJson('/api/app/venda/produtos')->assertOk()->assertJsonStructure(['itens']);
});

it('UC-APPVR-03 · devolve o produto do business com preço, categoria e o estoque do local de venda; o do outro business não aparece nem ligado ao mesmo local', function () {
    $nosso = appVrProduto(APP_VR_BIZ, $this->prefixo . ' Caneca', 34.9, $this->local, 7);
    $alheio = appVrProduto(APP_VR_OUTRO, $this->prefixo . ' Caneca alheia', 10, $this->local, 99);

    $itens = collect($this->getJson('/api/app/venda/produtos?q=' . urlencode($this->prefixo))->assertOk()->json('itens'));

    $item = $itens->firstWhere('id', $nosso);
    expect($item)->not->toBeNull();
    expect($item['nome'])->toBe($this->prefixo . ' Caneca');
    expect((float) $item['preco'])->toEqualWithDelta(34.90, 0.0001);
    expect((float) $item['estoque'])->toEqualWithDelta(7.0, 0.0001);
    expect(array_key_exists('categoria', $item))->toBeTrue();
    expect($itens->firstWhere('id', $alheio))->toBeNull();
});

it('UC-APPVR-04 · produto sem controle de estoque vem com estoque null; fora de venda e inativo não aparecem', function () {
    $semEstoque = appVrProduto(APP_VR_BIZ, $this->prefixo . ' Serviço arte', 50, $this->local, null, false);
    $foraDeVenda = appVrProduto(APP_VR_BIZ, $this->prefixo . ' Insumo', 5, $this->local, 3);
    $inativo = appVrProduto(APP_VR_BIZ, $this->prefixo . ' Antigo', 5, $this->local, 3);
    DB::table('products')->where('id', DB::table('variations')->where('id', $foraDeVenda)->value('product_id'))->update(['not_for_selling' => 1]);
    DB::table('products')->where('id', DB::table('variations')->where('id', $inativo)->value('product_id'))->update(['is_inactive' => 1]);

    $itens = collect($this->getJson('/api/app/venda/produtos?q=' . urlencode($this->prefixo))->assertOk()->json('itens'));

    expect($itens->firstWhere('id', $semEstoque))->not->toBeNull();
    expect($itens->firstWhere('id', $semEstoque)['estoque'])->toBeNull();
    expect($itens->firstWhere('id', $foraDeVenda))->toBeNull();
    expect($itens->firstWhere('id', $inativo))->toBeNull();
});

it('UC-APPVR-05 · estoque é o do local de venda: saldo em outro local do mesmo business não conta', function () {
    $v = appVrProduto(APP_VR_BIZ, $this->prefixo . ' Banner', 80, $this->local, 2);
    $p = DB::table('variations')->where('id', $v)->value('product_id');
    DB::table('product_locations')->insert(['product_id' => $p, 'location_id' => $this->outroLocal]);
    DB::table('variation_location_details')->insert([
        'product_id' => $p, 'variation_id' => $v, 'location_id' => $this->outroLocal,
        'product_variation_id' => DB::table('variations')->where('id', $v)->value('product_variation_id'),
        'qty_available' => 500, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $item = collect($this->getJson('/api/app/venda/produtos?q=' . urlencode($this->prefixo))->json('itens'))->firstWhere('id', $v);

    expect((float) $item['estoque'])->toEqualWithDelta(2.0, 0.0001);
});

it('UC-APPVR-06 · local com grupo de preço padrão: a busca devolve o preço do grupo (fixo 40 sobre base 50); variação sem preço no grupo fica no base', function () {
    $comGrupo = appVrProduto(APP_VR_BIZ, $this->prefixo . ' Cartão', 50, $this->local, 1);
    $semGrupo = appVrProduto(APP_VR_BIZ, $this->prefixo . ' Flyer', 50, $this->local, 1);
    $grupo = DB::table('selling_price_groups')->insertGetId([
        'name' => 'APPVR-' . uniqid(), 'business_id' => APP_VR_BIZ, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('variation_group_prices')->insert([
        'variation_id' => $comGrupo, 'price_group_id' => $grupo, 'price_inc_tax' => 40, 'price_type' => 'fixed',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('business_locations')->where('id', $this->local)->update(['selling_price_group_id' => $grupo]);

    $itens = collect($this->getJson('/api/app/venda/produtos?q=' . urlencode($this->prefixo))->json('itens'));

    expect((float) $itens->firstWhere('id', $comGrupo)['preco'])->toEqualWithDelta(40.0, 0.0001);
    expect((float) $itens->firstWhere('id', $semGrupo)['preco'])->toEqualWithDelta(50.0, 0.0001);
});

it('UC-APPVR-07 · usuário sem nenhum local de venda acessível responde 403 sem_local', function () {
    Passport::actingAs(appVrUsuario(['direct_sell.access']), [], 'api');

    $this->getJson('/api/app/venda/produtos')->assertStatus(403)->assertJsonPath('erro', 'sem_local');
});

it('UC-APPVR-08 · business que vende sem estoque: estoque vem null (sem teto no app) mesmo com controle de estoque; sem a opção, vem o saldo (controle)', function () {
    $v = appVrProduto(APP_VR_BIZ, $this->prefixo . ' Placa', 30, $this->local, 0);
    $url = '/api/app/venda/produtos?q=' . urlencode($this->prefixo);

    expect((float) collect($this->getJson($url)->json('itens'))->firstWhere('id', $v)['estoque'])->toEqualWithDelta(0.0, 0.0001);

    appVrVenderSemEstoque(true);
    expect(collect($this->getJson($url)->json('itens'))->firstWhere('id', $v)['estoque'])->toBeNull();
});

it('UC-APPVR-09 · bloqueia_preco_zero no topo da busca: false por padrão e quando desligado; true só com o ajuste da empresa ligado', function () {
    $pos = json_decode((string) DB::table('business')->where('id', APP_VR_BIZ)->value('pos_settings'), true) ?: [];
    unset($pos['bloquear_venda_preco_zero_app']);
    DB::table('business')->where('id', APP_VR_BIZ)->update(['pos_settings' => json_encode($pos)]);
    $this->getJson('/api/app/venda/produtos')->assertOk()->assertJsonPath('bloqueia_preco_zero', false);

    $pos['bloquear_venda_preco_zero_app'] = 0;
    DB::table('business')->where('id', APP_VR_BIZ)->update(['pos_settings' => json_encode($pos)]);
    $this->getJson('/api/app/venda/produtos')->assertOk()->assertJsonPath('bloqueia_preco_zero', false);

    $pos['bloquear_venda_preco_zero_app'] = 1;
    DB::table('business')->where('id', APP_VR_BIZ)->update(['pos_settings' => json_encode($pos)]);
    $this->getJson('/api/app/venda/produtos')->assertOk()->assertJsonPath('bloqueia_preco_zero', true);
});
