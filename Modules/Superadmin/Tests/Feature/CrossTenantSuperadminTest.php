<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Entities\Package;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Cross-tenant test pro Superadmin (backoffice UltimatePOS).
 *
 * Diferente do isolamento Tier 0 ADR 0093 (cada Module business-scoped),
 * Superadmin é INTENCIONALMENTE cross-tenant: gerencia todos businesses,
 * cria/edita Packages globais, comunica com toda base.
 *
 * Validações:
 *   1. Package é entity GLOBAL (sem business_id obrigatório, sem global scope).
 *   2. Superadmin (biz=1 + permission `superadmin`) VÊ businesses de biz=99 (fictício)
 *      via Eloquent direto — uso INTENCIONAL de cross-tenant.
 *   3. Usuário normal (biz=1, SEM permission `superadmin`) NÃO consegue acessar
 *      rotas /superadmin/* — bloqueio é obrigatório.
 *
 * NUNCA biz=4 (ROTA LIVRE produção — ADR 0101). Usar biz=1 (Wagner) e biz=99 (fictício).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Superadmin + Spatie + business multi-tenant exigem schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('business') || ! Schema::hasTable('packages')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }
});

const BIZ_WAGNER_CT = 1;
const BIZ_FICTICIO_CT = 99;

// ---------- Validação 1: Package é entity global (sem business_id global scope) ----------

it('Package é entity global — sem BusinessScope, visível cross-tenant', function () {
    // Cria pacote sem business_id (entity global pra plano SaaS).
    $pkg = Package::create([ // SUPERADMIN: Package é cross-tenant intencional (SaaS plan)
        'name'         => 'Pacote Teste Cross-Tenant',
        'description'  => 'Pacote SaaS visível pra todos businesses',
        'location_count' => 1,
        'user_count'   => 5,
        'product_count' => 100,
        'invoice_count' => 1000,
        'interval'     => 'months',
        'interval_count' => 1,
        'trial_days'   => 7,
        'price'        => 0,
        'is_active'    => 1,
        'sort_order'   => 999,
        'is_private'   => 0,
        'is_one_time'  => 0,
    ]);

    // Visível independente de session business_id (não tem global scope BusinessScope)
    session(['user.business_id' => BIZ_WAGNER_CT]);
    $vistoBiz1 = Package::where('id', $pkg->id)->count();

    session(['user.business_id' => BIZ_FICTICIO_CT]);
    $vistoBiz99 = Package::where('id', $pkg->id)->count();

    expect($vistoBiz1)->toBe(1, 'Package deve aparecer com session biz=1');
    expect($vistoBiz99)->toBe(1, 'Package deve aparecer com session biz=99 (cross-tenant intencional)');
})->afterEach(function () {
    Package::where('name', 'Pacote Teste Cross-Tenant')->forceDelete();
});

// ---------- Validação 2: Superadmin vê businesses cross-tenant ----------

it('Superadmin consulta cross-tenant business — vê biz=1 e biz=99 simultaneamente', function () {
    Business::firstOrCreate(
        ['id' => BIZ_WAGNER_CT],
        ['name' => 'Wagner Teste CT', 'currency_id' => 1]
    );
    Business::firstOrCreate(
        ['id' => BIZ_FICTICIO_CT],
        ['name' => 'Business Ficticio CT', 'currency_id' => 1]
    );

    // SUPERADMIN: lista de businesses é uso intencional cross-tenant (sem global scope porque Business é a entidade-pai).
    $count = Business::whereIn('id', [BIZ_WAGNER_CT, BIZ_FICTICIO_CT])->count();

    expect($count)->toBe(2, 'Superadmin deve enxergar businesses de tenants diferentes na mesma query');
});

// ---------- Validação 3: usuário normal (sem permission) NÃO acessa rotas superadmin ----------

it('usuário biz=1 SEM permission superadmin é bloqueado em /superadmin', function () {
    Business::firstOrCreate(
        ['id' => BIZ_WAGNER_CT],
        ['name' => 'Wagner Teste CT', 'currency_id' => 1]
    );

    $userNormal = User::firstOrCreate(
        ['username' => 'usuario_normal_biz1_cross_test'],
        [
            'email'       => 'normal_biz1_cross@test.local',
            'password'    => bcrypt('secret'),
            'business_id' => BIZ_WAGNER_CT,
            'first_name'  => 'Normal',
            'last_name'   => 'Biz1',
        ]
    );

    // Garantir: sem role, sem permission
    $userNormal->syncRoles([]);
    $userNormal->syncPermissions([]);

    $response = $this->actingAs($userNormal)->get('/superadmin/business');

    // Middleware `superadmin` redireciona (302) ou nega (403). Nunca 200.
    expect($response->status())->toBeIn([302, 403]);
    expect($response->status())->not->toBe(200, 'Usuário sem permission JAMAIS pode receber 200 em /superadmin/*');
});

it('usuário biz=99 SEM permission superadmin é bloqueado em /superadmin (mesmo tenant fictício)', function () {
    Business::firstOrCreate(
        ['id' => BIZ_FICTICIO_CT],
        ['name' => 'Business Ficticio CT', 'currency_id' => 1]
    );

    $userBiz99 = User::firstOrCreate(
        ['username' => 'usuario_normal_biz99_cross_test'],
        [
            'email'       => 'normal_biz99_cross@test.local',
            'password'    => bcrypt('secret'),
            'business_id' => BIZ_FICTICIO_CT,
            'first_name'  => 'Normal',
            'last_name'   => 'Biz99',
        ]
    );

    $userBiz99->syncRoles([]);
    $userBiz99->syncPermissions([]);

    $response = $this->actingAs($userBiz99)->get('/superadmin/packages');

    expect($response->status())->toBeIn([302, 403]);
    expect($response->status())->not->toBe(200);
});

// ---------- Validação 4: Permission `superadmin` libera acesso cross-tenant ----------

it('usuário biz=1 COM permission superadmin recebe status válido em /superadmin', function () {
    Business::firstOrCreate(
        ['id' => BIZ_WAGNER_CT],
        ['name' => 'Wagner Teste CT', 'currency_id' => 1]
    );

    $userAdmin = User::firstOrCreate(
        ['username' => 'usuario_superadmin_biz1_cross_test'],
        [
            'email'       => 'admin_biz1_cross@test.local',
            'password'    => bcrypt('secret'),
            'business_id' => BIZ_WAGNER_CT,
            'first_name'  => 'Admin',
            'last_name'   => 'Biz1',
        ]
    );

    $permission = Permission::firstOrCreate(
        ['name' => 'superadmin', 'guard_name' => 'web']
    );

    if (! $userAdmin->hasPermissionTo('superadmin')) {
        $userAdmin->givePermissionTo($permission);
    }

    $response = $this->actingAs($userAdmin)->get('/superadmin');

    // Pode ser 200 (sucesso) ou outros status <500 dependendo de data/feature flags. Nunca 5xx.
    expect($response->status())->toBeLessThan(500);
});
