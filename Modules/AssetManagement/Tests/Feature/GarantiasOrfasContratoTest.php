<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Entities\Asset;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Garantia órfã — a causa (`AssetService::remover()`) e a limpeza (`assetmanagement:garantias-orfas`).
 *
 * Medido em produção em 2026-09-30: uma garantia de 2026-09-23 cujo bem foi criado e excluído
 * 22s depois. O `remover()` apagava o bem e a mídia, não as garantias; como `asset_warranties`
 * não tem `business_id`, a linha ficava sem dono.
 *
 * Tenant 98 (ADR 0358) e adversário 99. Fixtures com prefixo `GAR-ORF-` e limpeza em `finally`.
 *
 * @see resources/js/Pages/Patrimonio/Bens.casos.md (UC-BENS-11)
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('assets') || ! Schema::hasTable('asset_warranties')) {
        $this->markTestSkipped('Tabelas assets/asset_warranties ausentes — rode migrate primeiro');
    }
});

function garOrfBem(int $businessId, int $ownerId, string $codigo): Asset
{
    return Asset::create([
        'business_id' => $businessId,
        'name' => 'Bem '.$codigo,
        'asset_code' => $codigo,
        'quantity' => 1,
        'unit_price' => 100,
        'is_allocatable' => 0,
        'purchase_type' => 'owned',
        'created_by' => $ownerId,
    ]);
}

function garOrfGarantia(int $assetId): int
{
    return (int) DB::table('asset_warranties')->insertGetId([
        'asset_id' => $assetId,
        'start_date' => '2026-01-01',
        'end_date' => '2027-01-01',
        'additional_cost' => 0,
        'additional_note' => 'GAR-ORF',
    ]);
}

function garOrfUsuarioQueExclui(int $businessId): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'gar_orf_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $role = Role::firstOrCreate(['name' => 'gar-orf#'.$businessId, 'guard_name' => 'web'], ['business_id' => $businessId]);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'asset.delete', 'guard_name' => 'web']));
    $user->assignRole($role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function garOrfLimpar(): void
{
    app()->forgetInstance(ModuleUtil::class);
    DB::table('asset_warranties')->where('additional_note', 'GAR-ORF')->delete();
    Asset::where('asset_code', 'like', 'GAR-ORF-%')->forceDelete();
    foreach (User::withTrashed()->where('username', 'like', 'gar_orf_%')->get() as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
    Role::where('name', 'like', 'gar-orf#%')->delete();
}

it('UC-BENS-11: excluir o bem apaga as garantias DELE, nunca a de bem de outro business', function () {
    $dono = $this->seededTenant();
    $adv = $this->seededSupportClientTenant();
    $bizId = (int) $dono->id;

    try {
        $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
        $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
        app()->instance(ModuleUtil::class, $moduleUtil);

        $user = garOrfUsuarioQueExclui($bizId);
        $meu = garOrfBem($bizId, (int) $dono->owner_id, 'GAR-ORF-MEU');
        $g1 = garOrfGarantia($meu->id);
        $g2 = garOrfGarantia($meu->id);
        $alheio = garOrfBem((int) $adv->id, (int) $adv->owner_id, 'GAR-ORF-ADV');
        $gAlheia = garOrfGarantia($alheio->id);

        $r = test()->actingAs($user)
            ->withSession(['user.business_id' => $bizId, 'user' => ['business_id' => $bizId, 'id' => $user->id]])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->delete('/asset/assets/'.$meu->id);

        expect($r->status())->toBe(200);
        expect($r->json('success'))->toBeTrue();
        // O bem saiu — e as DUAS garantias dele junto.
        expect(Asset::whereKey($meu->id)->exists())->toBeFalse();
        expect(DB::table('asset_warranties')->whereIn('id', [$g1, $g2])->count())->toBe(0);
        // Controle: a garantia do bem de outro business segue lá.
        expect(DB::table('asset_warranties')->where('id', $gAlheia)->exists())->toBeTrue();
    } finally {
        garOrfLimpar();
    }
});

it('UC-BENS-11: o comando garantias-orfas lista em dry-run e só apaga com --apply — nunca garantia de bem que existe', function () {
    $dono = $this->seededTenant();
    $bizId = (int) $dono->id;

    try {
        $vivo = garOrfBem($bizId, (int) $dono->owner_id, 'GAR-ORF-VIVO');
        $gViva = garOrfGarantia($vivo->id);
        // Órfã: o bem é criado e apagado DIRETO na tabela, como o `remover()` antigo deixava.
        $morto = garOrfBem($bizId, (int) $dono->owner_id, 'GAR-ORF-MORTO');
        $gOrfa = garOrfGarantia($morto->id);
        DB::table('assets')->where('id', $morto->id)->delete();

        // Dry-run: lista a órfã e não apaga nada. `Artisan::call` prova que o comando está
        // REGISTRADO no provider, não só que a classe existe.
        expect(Artisan::call('assetmanagement:garantias-orfas'))->toBe(0);
        expect(Artisan::output())->toContain("id={$gOrfa} ");
        expect(Artisan::output())->not->toContain("id={$gViva} ");
        expect(DB::table('asset_warranties')->where('id', $gOrfa)->exists())->toBeTrue();

        expect(Artisan::call('assetmanagement:garantias-orfas', ['--apply' => true]))->toBe(0);
        expect(DB::table('asset_warranties')->where('id', $gOrfa)->exists())->toBeFalse();
        expect(DB::table('asset_warranties')->where('id', $gViva)->exists())->toBeTrue();
    } finally {
        garOrfLimpar();
    }
});
