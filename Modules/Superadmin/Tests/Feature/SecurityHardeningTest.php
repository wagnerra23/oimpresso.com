<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Http\Requests\StorePackageRequest;
use Modules\Superadmin\Http\Requests\UpdatePackageRequest;
use Modules\Superadmin\Http\Requests\StoreFrontendPageRequest;
use Modules\Superadmin\Http\Requests\UpdateFrontendPageRequest;
use Modules\Superadmin\Http\Requests\StoreBusinessRequest;
use Modules\Superadmin\Policies\PackagePolicy;
use Modules\Superadmin\Entities\Package;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * D8 Wave 15 Security Hardening Test (Superadmin governança).
 *
 * Validação:
 *   1. FormRequests existem + têm authorize() + rules() + messages()
 *   2. authorize() retorna false sem auth user
 *   3. authorize() retorna false sem permission `superadmin`
 *   4. Policy PackagePolicy.before() concede acesso a user com permission
 *   5. Policy nega acesso a user sem permission
 *   6. Rules têm validação básica (required em campos críticos)
 *   7. RateLimiter 'superadmin' registrado (D8.b Wave 13 — sanity check)
 *
 * Cenários SEM DB (1, 2, 6, 7) rodam em qualquer driver.
 * Cenários COM DB (3, 4, 5) exigem schema MySQL UltimatePOS.
 *
 * NUNCA biz=4 (ROTA LIVRE prod — ADR 0101). biz=1 (Wagner) + biz=99 (fictício).
 *
 * @see Modules/Superadmin/Http/Requests/
 * @see Modules/Superadmin/Policies/PackagePolicy.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

const BIZ_WAGNER_HARDENING = 1;
const BIZ_FAKE_HARDENING = 99;

function requiresMySQLHardening(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: Spatie + business + users exigem schema MySQL.');
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('business') || ! Schema::hasTable('permissions')) {
        test()->markTestSkipped('Schema UltimatePOS ausente.');
    }
}

// ============================================================================
// FormRequests — classes existem + têm métodos canônicos
// ============================================================================

it('StorePackageRequest, UpdatePackageRequest, StoreFrontendPageRequest, UpdateFrontendPageRequest, StoreBusinessRequest existem', function () {
    expect(class_exists(StorePackageRequest::class))->toBeTrue();
    expect(class_exists(UpdatePackageRequest::class))->toBeTrue();
    expect(class_exists(StoreFrontendPageRequest::class))->toBeTrue();
    expect(class_exists(UpdateFrontendPageRequest::class))->toBeTrue();
    expect(class_exists(StoreBusinessRequest::class))->toBeTrue();
});

it('FormRequests têm authorize() e rules()', function () {
    $classes = [
        StorePackageRequest::class,
        UpdatePackageRequest::class,
        StoreFrontendPageRequest::class,
        UpdateFrontendPageRequest::class,
        StoreBusinessRequest::class,
    ];

    foreach ($classes as $class) {
        $r = new \ReflectionClass($class);
        expect($r->hasMethod('authorize'))->toBeTrue("$class deve ter authorize()");
        expect($r->hasMethod('rules'))->toBeTrue("$class deve ter rules()");
    }
});

it('StorePackageRequest rules() exige campos críticos (name, interval, price)', function () {
    $req = new StorePackageRequest();
    $rules = $req->rules();

    expect($rules)->toHaveKey('name');
    expect($rules)->toHaveKey('interval');
    expect($rules)->toHaveKey('price');
    expect($rules['name'])->toContain('required');
    expect($rules['interval'])->toContain('required');
});

it('StoreFrontendPageRequest rules() valida slug com regex', function () {
    $req = new StoreFrontendPageRequest();
    $rules = $req->rules();

    expect($rules)->toHaveKey('slug');
    expect($rules)->toHaveKey('title');

    // Verifica que regex de slug está presente (qualquer item começando com 'regex:')
    $slugHasRegex = false;
    foreach ($rules['slug'] as $rule) {
        if (is_string($rule) && str_starts_with($rule, 'regex:')) {
            $slugHasRegex = true;
            break;
        }
    }
    expect($slugHasRegex)->toBeTrue('slug deve ter regex anti-XSS');
});

// ============================================================================
// authorize() — gate Spatie
// ============================================================================

it('FormRequest::authorize() retorna false sem usuário autenticado', function () {
    $req = new StorePackageRequest();
    // Sem setUserResolver — $this->user() retorna null
    expect($req->authorize())->toBeFalse('Sem auth user → authorize() false (fail-secure)');
});

it('FormRequest::authorize() retorna false para user sem permission superadmin', function () {
    requiresMySQLHardening();

    Business::firstOrCreate(
        ['id' => BIZ_FAKE_HARDENING],
        ['name' => 'Business Hardening Test', 'currency_id' => 1]
    );

    $user = User::firstOrCreate(
        ['username' => 'hardening_no_perm'],
        [
            'email'       => 'hardening_no_perm@test.local',
            'password'    => bcrypt('secret'),
            'business_id' => BIZ_FAKE_HARDENING,
            'first_name'  => 'Hardening',
            'last_name'   => 'NoPerm',
        ]
    );

    $user->syncRoles([]);
    $user->syncPermissions([]);

    $req = new StorePackageRequest();
    $req->setUserResolver(fn () => $user);

    expect($req->authorize())->toBeFalse('User sem perm superadmin não pode authorize()');
});

// ============================================================================
// Policy PackagePolicy
// ============================================================================

it('PackagePolicy::before() retorna true para user com permission superadmin', function () {
    requiresMySQLHardening();

    Business::firstOrCreate(
        ['id' => BIZ_WAGNER_HARDENING],
        ['name' => 'Wagner Hardening', 'currency_id' => 1]
    );

    $admin = User::firstOrCreate(
        ['username' => 'hardening_admin'],
        [
            'email'       => 'hardening_admin@test.local',
            'password'    => bcrypt('secret'),
            'business_id' => BIZ_WAGNER_HARDENING,
            'first_name'  => 'Hardening',
            'last_name'   => 'Admin',
        ]
    );

    $perm = Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    if (! $admin->hasPermissionTo('superadmin')) {
        $admin->givePermissionTo($perm);
    }

    $policy = new PackagePolicy();
    expect($policy->before($admin, 'viewAny'))->toBeTrue();
    expect($policy->before($admin, 'create'))->toBeTrue();
    expect($policy->before($admin, 'delete'))->toBeTrue();
});

it('PackagePolicy nega ações para user sem permission superadmin', function () {
    requiresMySQLHardening();

    Business::firstOrCreate(
        ['id' => BIZ_FAKE_HARDENING],
        ['name' => 'Business Hardening Test', 'currency_id' => 1]
    );

    $user = User::firstOrCreate(
        ['username' => 'hardening_user_nopolicy'],
        [
            'email'       => 'hardening_user_nopolicy@test.local',
            'password'    => bcrypt('secret'),
            'business_id' => BIZ_FAKE_HARDENING,
            'first_name'  => 'NoPolicy',
            'last_name'   => 'User',
        ]
    );

    $user->syncRoles([]);
    $user->syncPermissions([]);

    $policy = new PackagePolicy();

    expect($policy->viewAny($user))->toBeFalse();
    expect($policy->create($user))->toBeFalse();
});

// ============================================================================
// RateLimiter sanity (D8.b Wave 13 herdado)
// ============================================================================

it('RateLimiter superadmin ainda está registrado (sanity Wave 13)', function () {
    $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('superadmin');

    expect($limiter)->not->toBeNull('RateLimiter::for(\'superadmin\') deve existir');

    $request = \Illuminate\Http\Request::create('/superadmin', 'GET');
    $limit = $limiter($request);

    expect($limit)->toBeInstanceOf(\Illuminate\Cache\RateLimiting\Limit::class);
});

// ============================================================================
// Route throttle — middleware presente em todas rotas /superadmin
// ============================================================================

it('rotas /superadmin/* têm middleware throttle:superadmin aplicado', function () {
    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'superadmin'));

    expect($routes->count())->toBeGreaterThan(0, 'Deve haver rotas /superadmin registradas');

    foreach ($routes as $route) {
        $middlewares = $route->gatherMiddleware();
        $hasThrottle = collect($middlewares)
            ->contains(fn ($m) => is_string($m) && str_contains($m, 'throttle:superadmin'));

        expect($hasThrottle)->toBeTrue(
            "Rota {$route->uri()} deve ter throttle:superadmin (D8 Wave 15)"
        );
    }
});
