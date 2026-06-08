<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Services\PackageManagerService;

uses(Tests\TestCase::class);

/**
 * PackageManagerServiceTest — Wave 18 RETRY (Superadmin D4 boost +15).
 *
 * Valida extract Service do legacy `Package::listPackages()` estático:
 *   - listActive() retorna apenas Package onde is_active=1
 *   - listActive(excludePrivate=true) filtra is_private=1
 *   - countActive() conta corretamente
 *   - find() retorna null se não existe
 *   - listForBusiness() é cross-tenant intencional (catalog global)
 *
 * Cross-tenant intencional (Superadmin Wagner-only):
 *   - Package NÃO tem business_id — catalog global
 *   - Service não usa global scope nem session.business_id
 *
 * Schema requer MySQL UltimatePOS (packages table custom UPOS).
 *
 * @see Modules\Superadmin\Services\PackageManagerService
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md §exceções
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema packages UPOS requer MySQL.');
    }
    if (! Schema::hasTable('packages')) {
        $this->markTestSkipped('Tabela packages ausente — rode migrations Superadmin primeiro.');
    }
});

it('PackageManagerService é instanciável (Service stub canônico)', function () {
    $service = new PackageManagerService();
    expect($service)->toBeInstanceOf(PackageManagerService::class);
});

it('listActive() filtra apenas is_active=1', function () {
    $active = Package::create([
        'name' => 'Test Active Package Wave 18 RETRY',
        'is_active' => 1,
        'is_private' => 0,
        'sort_order' => 100,
        'location_count' => 1,
        'user_count' => 1,
        'product_count' => 100,
        'invoice_count' => 100,
        'interval' => 'months',
        'interval_count' => 1,
        'price' => 0,
        'created_by' => 1,
    ]);

    $inactive = Package::create([
        'name' => 'Test Inactive Package Wave 18 RETRY',
        'is_active' => 0,
        'is_private' => 0,
        'sort_order' => 101,
        'location_count' => 1,
        'user_count' => 1,
        'product_count' => 100,
        'invoice_count' => 100,
        'interval' => 'months',
        'interval_count' => 1,
        'price' => 0,
        'created_by' => 1,
    ]);

    $service = new PackageManagerService();
    $result = $service->listActive();

    $ids = $result->pluck('id')->all();
    expect($ids)->toContain($active->id);
    expect($ids)->not->toContain($inactive->id);

    // Cleanup.
    $active->forceDelete();
    $inactive->forceDelete();
});

it('listActive(excludePrivate=true) filtra is_private=1', function () {
    $public = Package::create([
        'name' => 'Test Public Package Wave 18 RETRY',
        'is_active' => 1,
        'is_private' => 0,
        'sort_order' => 200,
        'location_count' => 1,
        'user_count' => 1,
        'product_count' => 100,
        'invoice_count' => 100,
        'interval' => 'months',
        'interval_count' => 1,
        'price' => 0,
        'created_by' => 1,
    ]);

    $private = Package::create([
        'name' => 'Test Private Package Wave 18 RETRY',
        'is_active' => 1,
        'is_private' => 1,
        'sort_order' => 201,
        'location_count' => 1,
        'user_count' => 1,
        'product_count' => 100,
        'invoice_count' => 100,
        'interval' => 'months',
        'interval_count' => 1,
        'price' => 0,
        'created_by' => 1,
    ]);

    $service = new PackageManagerService();
    $publicOnly = $service->listActive(excludePrivate: true);
    $allActive = $service->listActive(excludePrivate: false);

    $publicIds = $publicOnly->pluck('id')->all();
    $allIds = $allActive->pluck('id')->all();

    expect($publicIds)->toContain($public->id);
    expect($publicIds)->not->toContain($private->id);
    expect($allIds)->toContain($public->id);
    expect($allIds)->toContain($private->id);

    // Cleanup.
    $public->forceDelete();
    $private->forceDelete();
});

it('countActive() é coerente com listActive().count()', function () {
    $service = new PackageManagerService();

    expect($service->countActive())->toBe($service->listActive()->count());
    expect($service->countActive(excludePrivate: true))->toBe($service->listActive(excludePrivate: true)->count());
});

it('find() retorna null se package não existe', function () {
    $service = new PackageManagerService();
    expect($service->find(999999999))->toBeNull();
});

it('listForBusiness() é cross-tenant intencional (Wagner-only)', function () {
    // SUPERADMIN: Package não tem business_id — catalog é global.
    // listForBusiness retorna mesma lista pra qualquer biz.
    $service = new PackageManagerService();

    $forBiz1 = $service->listForBusiness(1);
    $forBiz99 = $service->listForBusiness(99);

    expect($forBiz1->count())->toBe($forBiz99->count());
});
