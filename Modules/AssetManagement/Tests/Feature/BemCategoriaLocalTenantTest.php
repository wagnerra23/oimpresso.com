<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Entities\Asset;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Lote 0.3 da auditoria do Patrimonio (2026-11-05) — categoria e local do bem.
 *
 * `StoreAssetRequest` nao conferia `category_id`, e `location_id` so precisava existir em
 * QUALQUER empresa (`exists:business_locations,id`). `UpdateAssetRequest` igual. O bem
 * gravava com categoria ou local de outro tenant.
 *
 * ADR 0358: tenant 98 (dono) x 99 (adversario). biz=1 e biz=4 nunca.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('assets') || ! Schema::hasTable('categories')) {
        $this->markTestSkipped('Tabelas assets/categories ausentes — rode migrate primeiro');
    }
});

function reqTenUsuario(int $businessId): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'req_ten_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $role = Role::firstOrCreate(['name' => 'req-ten#'.$businessId, 'guard_name' => 'web'], ['business_id' => $businessId]);
    foreach (['asset.view', 'asset.create', 'asset.update', 'access_all_locations'] as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $user->assignRole($role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function reqTenCategoria(int $businessId, int $userId): int
{
    return (int) DB::table('categories')->insertGetId([
        'name' => 'REQ-TEN cat '.uniqid(),
        'business_id' => $businessId,
        'parent_id' => 0,
        'created_by' => $userId,
        'category_type' => 'asset',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Local da empresa adversaria, criado aqui (o seed do CI nao tem local fora do 98). */
function reqTenLocal(int $businessId): int
{
    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $businessId, 'name' => 'REQ-TEN esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $businessId, 'name' => 'REQ-TEN lay '.uniqid()]);

    return (int) DB::table('business_locations')->insertGetId([
        'business_id' => $businessId, 'name' => 'REQ-TEN loc '.uniqid(), 'country' => 'Brasil', 'state' => 'MT',
        'city' => 'Cuiaba', 'zip_code' => '7806500', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1,
    ]);
}

function reqTenComo(User $user, int $businessId)
{
    $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
    $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $moduleUtil);

    return test()->actingAs($user)->withSession([
        'user.business_id' => $businessId,
        'user.id' => $user->id,
        'user' => ['business_id' => $businessId, 'id' => $user->id],
        'business.date_format' => 'd/m/Y',
    ]);
}

function reqTenCorpo(string $nome, ?int $categoria, ?int $local): array
{
    return [
        'asset_code' => '', 'name' => $nome, 'category_id' => $categoria, 'location_id' => $local,
        'model' => 'D60', 'serial_no' => 'SN-1', 'purchase_date' => '10/09/2026',
        'purchase_type' => 'owned', 'unit_price' => '100,00', 'quantity' => '1',
        'depreciation' => '', 'description' => '', 'is_allocatable' => '1',
    ];
}

function reqTenLimpar(): void
{
    app()->forgetInstance(ModuleUtil::class);
    Asset::where('name', 'like', 'REQ-TEN-%')->forceDelete();
    DB::table('categories')->where('name', 'like', 'REQ-TEN cat %')->delete();
    DB::table('business_locations')->where('name', 'like', 'REQ-TEN loc %')->delete();
    DB::table('invoice_layouts')->where('name', 'like', 'REQ-TEN lay %')->delete();
    DB::table('invoice_schemes')->where('name', 'like', 'REQ-TEN esq %')->delete();
    foreach (User::withTrashed()->where('username', 'like', 'req_ten_%')->get() as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
    Role::where('name', 'like', 'req-ten#%')->delete();
}

it('cadastro de bem recusa categoria e local de outra empresa; com os da empresa grava', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $u = reqTenUsuario($dono);
    $uAdv = reqTenUsuario($adv);

    try {
        $minhaCat = reqTenCategoria($dono, $u->id);
        $catAlheia = reqTenCategoria($adv, $uAdv->id);
        $meuLocal = DB::table('business_locations')->where('business_id', $dono)->value('id');
        $localAlheio = reqTenLocal($adv);
        // Pre-condicao: sem os dois locais o teste nao prova nada.
        expect($meuLocal)->not->toBeNull();
        expect($localAlheio)->not->toBeNull();

        $nomeCat = 'REQ-TEN-'.uniqid();
        reqTenComo($u, $dono)->from('/asset/assets')
            ->post('/asset/assets', reqTenCorpo($nomeCat, $catAlheia, (int) $meuLocal))
            ->assertSessionHasErrors(['category_id']);
        expect(Asset::where('name', $nomeCat)->exists())->toBeFalse();

        $nomeLocal = 'REQ-TEN-'.uniqid();
        reqTenComo($u, $dono)->from('/asset/assets')
            ->post('/asset/assets', reqTenCorpo($nomeLocal, $minhaCat, (int) $localAlheio))
            ->assertSessionHasErrors(['location_id']);
        expect(Asset::where('name', $nomeLocal)->exists())->toBeFalse();

        // Controle: categoria e local da empresa gravam.
        $nomeOk = 'REQ-TEN-'.uniqid();
        reqTenComo($u, $dono)->from('/asset/assets')
            ->post('/asset/assets', reqTenCorpo($nomeOk, $minhaCat, (int) $meuLocal))
            ->assertSessionHasNoErrors();
        $gravado = Asset::where('name', $nomeOk)->first();
        expect($gravado)->not->toBeNull();
        expect((int) $gravado->category_id)->toBe($minhaCat);
        expect((int) $gravado->business_id)->toBe($dono);
    } finally {
        reqTenLimpar();
    }
});

it('edicao de bem recusa categoria e local de outra empresa e o bem fica como estava', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $u = reqTenUsuario($dono);
    $uAdv = reqTenUsuario($adv);

    try {
        $minhaCat = reqTenCategoria($dono, $u->id);
        $catAlheia = reqTenCategoria($adv, $uAdv->id);
        $meuLocal = (int) DB::table('business_locations')->where('business_id', $dono)->value('id');
        $localAlheio = reqTenLocal($adv);
        expect($meuLocal)->toBeGreaterThan(0);
        expect($localAlheio)->toBeGreaterThan(0);

        $bem = Asset::create([
            'business_id' => $dono, 'name' => 'REQ-TEN-'.uniqid(), 'asset_code' => 'REQ-TEN-'.uniqid(),
            'quantity' => 1, 'unit_price' => 100, 'is_allocatable' => 1, 'purchase_type' => 'owned',
            'category_id' => $minhaCat, 'location_id' => $meuLocal, 'created_by' => $u->id,
        ]);

        reqTenComo($u, $dono)->from('/asset/assets')
            ->put("/asset/assets/{$bem->id}", reqTenCorpo($bem->name, $catAlheia, $localAlheio))
            ->assertSessionHasErrors(['category_id', 'location_id']);

        $bem->refresh();
        expect((int) $bem->category_id)->toBe($minhaCat);
        expect((int) $bem->location_id)->toBe($meuLocal);
    } finally {
        reqTenLimpar();
    }
});
