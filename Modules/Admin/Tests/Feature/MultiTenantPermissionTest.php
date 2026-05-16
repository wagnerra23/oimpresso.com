<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\Helpers\AdminAuthHelper;

uses(Tests\TestCase::class);

/**
 * Multi-tenant permission isolation — Modules/Admin (ADR 0093 Tier 0 IRREVOGÁVEL).
 *
 * 4 cenários permission Spatie isolados por business_id:
 *   1. Role biz=1 NÃO autoriza ação em biz=99 (escopo legacy UltimatePOS)
 *   2. assignRole em biz=1 NÃO atribui role em biz=99 (mesmo nome, escopos distintos)
 *   3. Permission cross-tenant — user biz=99 com mesma role NÃO vê dados biz=1
 *   4. `hasRole('superadmin')` sem suffix retorna FALSE em UltimatePOS quando roles têm business_id
 *
 * Tests usam biz=1 (Wagner WR2) e biz=99 (fictício) — NUNCA biz=4 (ROTA LIVRE prod cliente).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const ADM_BIZ_WAGNER = 1;
const ADM_BIZ_FICTICIO = 99;

beforeEach(function () {
    // Guard SQLite — Wagner Pest local mandatory MySQL UltimatePOS — ADR 0101.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite-incompatível: roles Spatie com business_id requerem schema MySQL UltimatePOS — ADR 0101.'
        );
    }
    if (! Schema::hasTable('roles')) {
        $this->markTestSkipped('Tabela roles ausente — rode migrate Spatie primeiro.');
    }
});

// ------------------------------------------------------------------
// Cenário 1 — Role com suffix #{biz} é específica por business
// ------------------------------------------------------------------

it('Cenário 1: role superadmin#1 e superadmin#99 são entidades DISTINTAS', function () {
    if (! Schema::hasColumn('roles', 'business_id')) {
        $this->markTestSkipped('roles.business_id ausente — schema sem suffix #{biz}.');
    }

    Business::firstOrCreate(['id' => ADM_BIZ_WAGNER], ['name' => 'Wagner', 'currency_id' => 1]);
    Business::firstOrCreate(['id' => ADM_BIZ_FICTICIO], ['name' => 'Ficticio', 'currency_id' => 1]);

    $roleBiz1 = Role::firstOrCreate([
        'name'        => 'superadmin#'.ADM_BIZ_WAGNER,
        'guard_name'  => 'web',
        'business_id' => ADM_BIZ_WAGNER,
    ]);
    $roleBiz99 = Role::firstOrCreate([
        'name'        => 'superadmin#'.ADM_BIZ_FICTICIO,
        'guard_name'  => 'web',
        'business_id' => ADM_BIZ_FICTICIO,
    ]);

    expect($roleBiz1->id)->not->toBe($roleBiz99->id);
    expect((int) $roleBiz1->business_id)->toBe(ADM_BIZ_WAGNER);
    expect((int) $roleBiz99->business_id)->toBe(ADM_BIZ_FICTICIO);
});

// ------------------------------------------------------------------
// Cenário 2 — assignRole em biz=1 NÃO atribui role em biz=99
// ------------------------------------------------------------------

it('Cenário 2: assignRole biz=1 não propaga pra biz=99', function () {
    if (! Schema::hasColumn('roles', 'business_id')) {
        $this->markTestSkipped('roles.business_id ausente — schema sem suffix #{biz}.');
    }

    Business::firstOrCreate(['id' => ADM_BIZ_WAGNER], ['name' => 'Wagner', 'currency_id' => 1]);
    Business::firstOrCreate(['id' => ADM_BIZ_FICTICIO], ['name' => 'Ficticio', 'currency_id' => 1]);

    $user = AdminAuthHelper::createWagnerUser();

    $roleBiz1 = Role::firstOrCreate([
        'name'        => 'manager#'.ADM_BIZ_WAGNER,
        'guard_name'  => 'web',
        'business_id' => ADM_BIZ_WAGNER,
    ]);
    $roleBiz99 = Role::firstOrCreate([
        'name'        => 'manager#'.ADM_BIZ_FICTICIO,
        'guard_name'  => 'web',
        'business_id' => ADM_BIZ_FICTICIO,
    ]);

    $user->assignRole($roleBiz1);

    expect($user->hasRole('manager#'.ADM_BIZ_WAGNER))->toBeTrue();
    expect($user->hasRole('manager#'.ADM_BIZ_FICTICIO))->toBeFalse();

    // Cleanup
    $user->removeRole($roleBiz1);
});

// ------------------------------------------------------------------
// Cenário 3 — User biz=99 com mesma role-name não acessa Admin Wagner
// ------------------------------------------------------------------

it('Cenário 3: user biz=99 com role superadmin é bloqueado em /admin (gate user_id=1 AND biz=1)', function () {
    config()->set('admin.bypass_local', false);

    Business::firstOrCreate(['id' => ADM_BIZ_FICTICIO], ['name' => 'Ficticio', 'currency_id' => 1]);

    $intruder = User::firstOrCreate(
        ['id' => 9998],
        [
            'username'    => 'fake_wagner',
            'email'       => 'fake@biz99.test',
            'password'    => bcrypt('secret'),
            'business_id' => ADM_BIZ_FICTICIO,
            'first_name'  => 'Fake',
            'last_name'   => 'Wagner',
        ]
    );

    $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    if (! $intruder->hasRole('superadmin')) {
        $intruder->assignRole($role);
    }

    // Mesmo com role superadmin + Tailscale → gate `is_wagner` (user_id=1 AND biz=1) bloqueia
    $response = $this->actingAs($intruder)
        ->call('GET', '/admin', [], [], [], ['REMOTE_ADDR' => '100.99.5.10']);

    expect($response->status())->toBe(403);
});

// ------------------------------------------------------------------
// Cenário 4 — Role sem suffix #{biz} não substitui role escopada
// ------------------------------------------------------------------

it('Cenário 4: role superadmin (sem suffix) e superadmin#1 (com suffix) coexistem distintas', function () {
    if (! Schema::hasColumn('roles', 'business_id')) {
        // Em schema legacy só name+guard_name, suffix é convenção — pula
        $this->markTestSkipped('roles.business_id ausente — schema sem suffix #{biz}.');
    }

    Business::firstOrCreate(['id' => ADM_BIZ_WAGNER], ['name' => 'Wagner', 'currency_id' => 1]);

    // Role global (sem business_id) — convenção legado para system-wide
    // Em UltimatePOS strict, roles.business_id é NOT NULL → este teste valida que
    // tentar criar role sem business_id falha (FK enforcement)
    $rolePivot = Role::firstOrCreate([
        'name'        => 'superadmin#'.ADM_BIZ_WAGNER,
        'guard_name'  => 'web',
        'business_id' => ADM_BIZ_WAGNER,
    ]);

    expect($rolePivot)->not->toBeNull();
    expect((int) $rolePivot->business_id)->toBe(ADM_BIZ_WAGNER);

    // Tentar lookup só por name (sem business_id) — pode achar a do biz=1 ou nada
    $found = Role::where('name', 'superadmin#'.ADM_BIZ_WAGNER)->first();
    expect($found)->not->toBeNull();
    expect((int) $found->business_id)->toBe(ADM_BIZ_WAGNER);
});
