<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Smoke tests pras rotas Superadmin com usuário autenticado COM permission `superadmin`.
 *
 * Não verifica conteúdo da view — apenas que o pipeline middleware + Controller não
 * lança 5xx (status < 500). Aceita 200/302/403 dependendo de feature flags / data.
 *
 * Objetivo: detectar regressão em estrutura de rota (binding, FQCN, middleware stack).
 *
 * NUNCA biz=4 (ROTA LIVRE — ADR 0101). Usar biz=1 (Wagner WR2).
 *
 * @see Modules/Superadmin/Routes/web.php
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Superadmin requer schema MySQL UltimatePOS (Spatie + business + users).');
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('business') || ! Schema::hasTable('permissions')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }
});

const BIZ_WAGNER_SMOKE = 1;

/**
 * Cria usuário autenticado com permission `superadmin` no biz=1 pra smoke.
 */
function makeSuperadminParaSmoke(): User
{
    Business::firstOrCreate(
        ['id' => BIZ_WAGNER_SMOKE],
        ['name' => 'Wagner Teste Smoke', 'currency_id' => 1]
    );

    $user = User::firstOrCreate(
        ['username' => 'superadmin_smoke_test'],
        [
            'email'       => 'superadmin_smoke@test.local',
            'password'    => bcrypt('secret'),
            'business_id' => BIZ_WAGNER_SMOKE,
            'first_name'  => 'Smoke',
            'last_name'   => 'Superadmin',
        ]
    );

    $permission = Permission::firstOrCreate(
        ['name' => 'superadmin', 'guard_name' => 'web']
    );

    if (! $user->hasPermissionTo('superadmin')) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

// ---------- Smoke 5 rotas principais ----------

it('GET /superadmin (dashboard) não retorna 5xx', function () {
    $user = makeSuperadminParaSmoke();

    $response = $this->actingAs($user)->get('/superadmin');

    expect($response->status())->toBeLessThan(500);
});

it('GET /superadmin/business (listagem businesses) não retorna 5xx', function () {
    $user = makeSuperadminParaSmoke();

    $response = $this->actingAs($user)->get('/superadmin/business');

    expect($response->status())->toBeLessThan(500);
});

it('GET /superadmin/packages (listagem packages) não retorna 5xx', function () {
    $user = makeSuperadminParaSmoke();

    $response = $this->actingAs($user)->get('/superadmin/packages');

    expect($response->status())->toBeLessThan(500);
});

it('GET /superadmin/communicator (comunicação) não retorna 5xx', function () {
    $user = makeSuperadminParaSmoke();

    $response = $this->actingAs($user)->get('/superadmin/communicator');

    expect($response->status())->toBeLessThan(500);
});

it('GET /superadmin/frontend-pages (CMS páginas públicas) não retorna 5xx', function () {
    $user = makeSuperadminParaSmoke();

    $response = $this->actingAs($user)->get('/superadmin/frontend-pages');

    expect($response->status())->toBeLessThan(500);
});

it('GET /pricing (rota pública pricing) não retorna 5xx', function () {
    // Rota pública (sem auth) — deve responder
    $response = $this->get('/pricing');

    expect($response->status())->toBeLessThan(500);
});
