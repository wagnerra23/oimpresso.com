<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetMaintenance;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Lote 0.5 da auditoria do Patrimonio (2026-11-05).
 *
 *  - a-8 (P2 do eixo 5): quem tem so `asset.view_own_maintenance` abria, editava e apagava
 *    manutencao de colega da mesma empresa pelo id. O recorte "so as minhas" (criada por mim
 *    ou atribuida a mim) existia so na listagem.
 *  - a-9 (P3): o painel `/asset/dashboard` nao conferia permissao nenhuma.
 *
 * ADR 0358: tenant 98. biz=1 e biz=4 nunca.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('asset_maintenances') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Tabelas asset_maintenances/business ausentes — rode migrate primeiro');
    }
});

/** `$permissoes = []` cria usuario sem permissao nenhuma do modulo. */
function vownUsuario(int $businessId, array $permissoes): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'vown_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    if ($permissoes !== []) {
        $role = Role::create(['name' => 'vown-'.uniqid().'#'.$businessId, 'guard_name' => 'web', 'business_id' => $businessId]);
        foreach ($permissoes as $p) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function vownManutencao(int $businessId, int $criadoPor, ?int $atribuidoA = null): AssetMaintenance
{
    $bem = Asset::create([
        'business_id' => $businessId, 'name' => 'VOWN bem', 'asset_code' => 'VOWN-'.uniqid(),
        'quantity' => 1, 'unit_price' => 100, 'is_allocatable' => 1, 'purchase_type' => 'owned',
        'created_by' => $criadoPor,
    ]);

    return AssetMaintenance::create([
        'business_id' => $businessId, 'asset_id' => $bem->id, 'maitenance_id' => 'VOWN-'.uniqid(),
        'status' => 'in_progress', 'priority' => 'high', 'created_by' => $criadoPor,
        'assigned_to' => $atribuidoA, 'details' => 'Fixture VOWN',
    ]);
}

function vownComo(User $user, int $businessId)
{
    $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
    $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $moduleUtil);
    test()->flushHeaders();

    return test()->actingAs($user)->withSession([
        'user.business_id' => $businessId,
        'user.id' => $user->id,
        'user' => ['business_id' => $businessId, 'id' => $user->id],
        'business.date_format' => 'd/m/Y',
    ]);
}

function vownLimpar(): void
{
    app()->forgetInstance(ModuleUtil::class);
    $bens = Asset::where('asset_code', 'like', 'VOWN-%')->pluck('id');
    AssetMaintenance::whereIn('asset_id', $bens)->forceDelete();
    Asset::whereIn('id', $bens)->forceDelete();
    foreach (User::withTrashed()->where('username', 'like', 'vown_%')->get() as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
    Role::where('name', 'like', 'vown-%')->delete();
}

it('a-8: com so view_own, manutencao de colega nao abre, nao edita e nao apaga; a propria sim', function () {
    $biz = (int) $this->seededTenant()->id;
    $eu = vownUsuario($biz, ['asset.view_own_maintenance']);
    $colega = vownUsuario($biz, ['asset.view_own_maintenance']);

    try {
        $doColega = vownManutencao($biz, $colega->id);
        $minha = vownManutencao($biz, $eu->id);
        $atribuidaAMim = vownManutencao($biz, $colega->id, $eu->id);

        vownComo($eu, $biz)->get("/asset/asset-maintenance/{$doColega->id}/edit")->assertStatus(403);

        vownComo($eu, $biz)->put("/asset/asset-maintenance/{$doColega->id}", [
            'status' => 'completed', 'priority' => 'low', 'details' => 'invasao',
        ])->assertStatus(403);
        expect($doColega->refresh()->status)->toBe('in_progress');

        vownComo($eu, $biz)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->delete("/asset/asset-maintenance/{$doColega->id}")->assertStatus(403);
        expect(AssetMaintenance::whereKey($doColega->id)->exists())->toBeTrue();

        // Controle: a minha (criada por mim) e a atribuida a mim seguem abrindo e gravando.
        vownComo($eu, $biz)->get("/asset/asset-maintenance/{$minha->id}/edit")->assertStatus(200);
        vownComo($eu, $biz)->put("/asset/asset-maintenance/{$atribuidaAMim->id}", [
            'status' => 'completed', 'priority' => 'low', 'details' => 'feito',
        ])->assertRedirect();
        expect($atribuidaAMim->refresh()->status)->toBe('completed');
    } finally {
        vownLimpar();
    }
});

it('a-8: com view_all, a manutencao de colega continua editavel', function () {
    $biz = (int) $this->seededTenant()->id;
    $gestor = vownUsuario($biz, ['asset.view_all_maintenance']);
    $colega = vownUsuario($biz, ['asset.view_own_maintenance']);

    try {
        $doColega = vownManutencao($biz, $colega->id);

        vownComo($gestor, $biz)->get("/asset/asset-maintenance/{$doColega->id}/edit")->assertStatus(200);
        vownComo($gestor, $biz)->put("/asset/asset-maintenance/{$doColega->id}", [
            'status' => 'completed', 'priority' => 'low', 'details' => 'revisado',
        ])->assertRedirect();
        expect($doColega->refresh()->status)->toBe('completed');
    } finally {
        vownLimpar();
    }
});

it('a-9: painel recusa usuario sem permissao do modulo e abre para quem tem asset.view', function () {
    $biz = (int) $this->seededTenant()->id;
    $semNada = vownUsuario($biz, []);
    $leitor = vownUsuario($biz, ['asset.view']);
    $soManutencao = vownUsuario($biz, ['asset.view_own_maintenance']);

    try {
        vownComo($semNada, $biz)->get('/asset/dashboard')->assertStatus(403);
        vownComo($leitor, $biz)->get('/asset/dashboard')->assertStatus(200);
        // O menu do modulo aparece para quem tem so permissao de manutencao; o painel tambem.
        vownComo($soManutencao, $biz)->get('/asset/dashboard')->assertStatus(200);
    } finally {
        vownLimpar();
    }
});
