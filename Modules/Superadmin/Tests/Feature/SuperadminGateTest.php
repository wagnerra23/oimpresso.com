<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Pest gate test pras rotas do módulo Superadmin (backoffice UltimatePOS).
 *
 * Toda rota sob prefixo `/superadmin` é protegida pelo middleware `superadmin`
 * + gate manual `auth()->user()->can('superadmin')` em vários Controllers.
 *
 * Cobertura:
 *   - usuário sem permission `superadmin` → 403/302 (bloqueio)
 *   - usuário guest → 302 (redirect login) ou 403
 *
 * NUNCA biz=4 (ROTA LIVRE produção — ADR 0101). Usar biz=1 (Wagner WR2).
 *
 * @see Modules/Superadmin/Routes/web.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

// Guard SQLite: rotas Superadmin requerem schema MySQL UltimatePOS (users, business, roles, permissions Spatie).
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Superadmin requer schema MySQL UltimatePOS (Spatie roles + business + users).');
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('business') || ! Schema::hasTable('permissions')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }
});

const BIZ_WAGNER_GATE = 1;

/**
 * Cria usuário SEM permission `superadmin` no biz=1 (Wagner).
 */
function makeUserSemSuperadmin(): User
{
    Business::firstOrCreate(
        ['id' => BIZ_WAGNER_GATE],
        ['name' => 'Wagner Teste Gate', 'currency_id' => 1]
    );

    $user = User::firstOrCreate(
        ['username' => 'usuario_sem_superadmin_test'],
        [
            'email'       => 'sem_superadmin@test.local',
            'password'    => bcrypt('secret'),
            'business_id' => BIZ_WAGNER_GATE,
            'first_name'  => 'Sem',
            'last_name'   => 'Superadmin',
        ]
    );

    // Garantir que o user NÃO tem permission/role superadmin
    $user->syncRoles([]);
    $user->syncPermissions([]);

    return $user;
}

/**
 * Cria usuário COM permission `superadmin` no biz=1.
 */
function makeUserComSuperadmin(): User
{
    Business::firstOrCreate(
        ['id' => BIZ_WAGNER_GATE],
        ['name' => 'Wagner Teste Gate', 'currency_id' => 1]
    );

    $user = User::firstOrCreate(
        ['username' => 'usuario_com_superadmin_test'],
        [
            'email'       => 'com_superadmin@test.local',
            'password'    => bcrypt('secret'),
            'business_id' => BIZ_WAGNER_GATE,
            'first_name'  => 'Com',
            'last_name'   => 'Superadmin',
        ]
    );

    // Permission Spatie: superadmin global (guard web)
    $permission = Permission::firstOrCreate(
        ['name' => 'superadmin', 'guard_name' => 'web']
    );

    if (! $user->hasPermissionTo('superadmin')) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

// ---------- Cenário 1: usuário SEM permission é bloqueado ----------

it('rota superadmin dashboard bloqueia usuário sem permission', function () {
    $user = makeUserSemSuperadmin();

    $response = $this->actingAs($user)->get('/superadmin');

    // Middleware `superadmin` redireciona (302) ou nega (403); ambos válidos como bloqueio.
    expect($response->status())->toBeIn([302, 403]);
});

it('rota superadmin business.index bloqueia usuário sem permission', function () {
    $user = makeUserSemSuperadmin();

    $response = $this->actingAs($user)->get('/superadmin/business');

    expect($response->status())->toBeIn([302, 403]);
});

it('rota superadmin packages.index bloqueia usuário sem permission', function () {
    $user = makeUserSemSuperadmin();

    $response = $this->actingAs($user)->get('/superadmin/packages');

    expect($response->status())->toBeIn([302, 403]);
});

it('rota superadmin communicator bloqueia usuário sem permission', function () {
    $user = makeUserSemSuperadmin();

    $response = $this->actingAs($user)->get('/superadmin/communicator');

    expect($response->status())->toBeIn([302, 403]);
});

// ---------- Cenário 2: guest (sem auth) é bloqueado ----------

it('rota superadmin dashboard exige autenticação (guest redireciona)', function () {
    $response = $this->get('/superadmin');

    // Sem auth: middleware `auth` redireciona pra login (302) OU middleware `superadmin` nega (403).
    expect($response->status())->toBeIn([302, 403]);
});

it('rota superadmin packages exige autenticação (guest redireciona)', function () {
    $response = $this->get('/superadmin/packages');

    expect($response->status())->toBeIn([302, 403]);
});
