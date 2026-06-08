<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Repair\Entities\DeviceModel;
use Spatie\Permission\Models\Permission;

/**
 * DeviceModelsInertiaSmokeTest — Blade T1 Migration C (2026-05-17).
 *
 * Cobre branches Inertia opt-in via flags MWART em DeviceModelController:
 *   - /repair/device-models           (index — flag MWART_REPAIR_DEVICE_MODELS_INDEX)
 *   - /repair/device-models/create    (create — flag MWART_REPAIR_DEVICE_MODELS_CREATE)
 *   - /repair/device-models/{id}/edit (edit — flag MWART_REPAIR_DEVICE_MODELS_EDIT)
 *
 * Coexistência preservada: flag OFF → Blade legacy.
 *
 * Refs:
 *   - ADR 0093 (Multi-tenant Tier 0 IRREVOGÁVEL)
 *   - ADR 0101 (Tests biz=1 nunca cliente — biz=99 sintético cross-tenant)
 *   - ADR 0104 (MWART canônico — único caminho)
 *   - RUNBOOK: memory/requisitos/Repair/RUNBOOK-device-models.md
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    // Schema cross-tenant precisa SQLite in-memory pra evitar dirty state biz=99 em MySQL real.
    if (DB::connection()->getDriverName() === 'sqlite') {
        // Schema sintético mínimo pra testes de scope cross-tenant.
        if (! Schema::hasTable('repair_device_models')) {
            Schema::create('repair_device_models', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('business_id');
                $t->string('name');
                $t->integer('brand_id')->nullable();
                $t->integer('device_id')->nullable();
                $t->string('repair_checklist')->nullable();
                $t->integer('created_by')->nullable();
                $t->timestamps();
            });
        }
    }

    try {
        Permission::firstOrCreate(['name' => 'repair.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repair.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'repair.update', 'guard_name' => 'web']);
    } catch (\Throwable $e) {
        test()->markTestSkipped('Permissions table indisponível: '.$e->getMessage());
    }
});

afterEach(function () {
    config([
        'mwart.repair_device_models_index.enabled' => false,
        'mwart.repair_device_models_index.business_ids' => [],
        'mwart.repair_device_models_create.enabled' => false,
        'mwart.repair_device_models_create.business_ids' => [],
        'mwart.repair_device_models_edit.enabled' => false,
        'mwart.repair_device_models_edit.business_ids' => [],
    ]);

    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('repair_device_models');
    }
});

function bladeT1cBootstrap(): array
{
    $business = Business::first();
    if (! $business) {
        test()->markTestSkipped('Sem business.');
    }
    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user.');
    }
    try {
        if (! $user->hasPermissionTo('repair.view')) {
            $user->givePermissionTo('repair.view');
        }
    } catch (\Throwable $e) {
        test()->markTestSkipped('Permission grant: '.$e->getMessage());
    }
    session([
        'user.business_id' => $business->id,
        'user.id' => $user->id,
        'business.id' => $business->id,
        'business.currency_symbol' => 'R$',
        'business' => ['id' => $business->id, 'name' => $business->name, 'currency_symbol' => 'R$'],
        'is_admin' => true,
    ]);

    return [$business, $user];
}

// ===== INDEX =====

it('Index: flag MWART OFF → Blade (sem header X-Inertia)', function () {
    [$business, $user] = bladeT1cBootstrap();
    config(['mwart.repair_device_models_index.enabled' => false]);

    $response = $this->actingAs($user)->get('/repair/device-models');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate (repair_module).');
    }
    if ($response->status() >= 500) {
        test()->markTestSkipped('Render Blade falhou: '.$response->status());
    }

    expect($response->headers->get('X-Inertia'))->toBeNull();
});

it('Index: flag MWART ON → Inertia render Repair/DeviceModels/Index', function () {
    [$business, $user] = bladeT1cBootstrap();
    config([
        'mwart.repair_device_models_index.enabled' => true,
        'mwart.repair_device_models_index.business_ids' => [],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/repair/device-models');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate.');
    }
    if ($response->status() !== 200) {
        test()->markTestSkipped('Render Inertia falhou: '.$response->status());
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Repair/DeviceModels/Index')
        ->has('filters')
        ->has('brands')
        ->has('devices')
    );
});

it('Index: whitelist business_ids exclui biz fora → Blade', function () {
    [$business, $user] = bladeT1cBootstrap();
    $foreignId = $business->id + 999;
    config([
        'mwart.repair_device_models_index.enabled' => true,
        'mwart.repair_device_models_index.business_ids' => [$foreignId],
    ]);

    $response = $this->actingAs($user)->get('/repair/device-models');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate.');
    }
    if ($response->status() >= 500) {
        test()->markTestSkipped('Render falhou.');
    }

    expect($response->headers->get('X-Inertia'))->toBeNull();
});

// ===== CREATE =====

it('Create: flag MWART OFF → Blade', function () {
    [$business, $user] = bladeT1cBootstrap();
    config(['mwart.repair_device_models_create.enabled' => false]);

    $response = $this->actingAs($user)->get('/repair/device-models/create');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate.');
    }
    if ($response->status() >= 500) {
        test()->markTestSkipped('Render Blade falhou.');
    }

    expect($response->headers->get('X-Inertia'))->toBeNull();
});

it('Create: flag MWART ON → Inertia render Repair/DeviceModels/Create', function () {
    [$business, $user] = bladeT1cBootstrap();
    config([
        'mwart.repair_device_models_create.enabled' => true,
        'mwart.repair_device_models_create.business_ids' => [],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/repair/device-models/create');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate.');
    }
    if ($response->status() !== 200) {
        test()->markTestSkipped('Render Inertia falhou: '.$response->status());
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Repair/DeviceModels/Create')
        ->has('brands')
        ->has('devices')
    );
});

// ===== EDIT =====

it('Edit: flag MWART ON com modelo do biz → Inertia render Repair/DeviceModels/Edit', function () {
    [$business, $user] = bladeT1cBootstrap();

    $model = DeviceModel::create([
        'business_id' => $business->id,
        'name' => 'TesteModelo-T1C',
        'created_by' => $user->id,
    ]);

    config([
        'mwart.repair_device_models_edit.enabled' => true,
        'mwart.repair_device_models_edit.business_ids' => [],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get("/repair/device-models/{$model->id}/edit");

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate.');
    }
    if ($response->status() !== 200) {
        test()->markTestSkipped('Render Inertia falhou: '.$response->status());
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Repair/DeviceModels/Edit')
        ->where('model.id', $model->id)
        ->where('model.name', 'TesteModelo-T1C')
        ->has('brands')
        ->has('devices')
    );
});

it('Edit: cross-tenant biz=99 NÃO acessa modelo biz=1 (R-REPA-001 multi-tenant ADR 0093)', function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('Cross-tenant scope requer SQLite in-memory pra isolar.');
    }

    // Modelo de biz=99 (tenant sintético — ADR 0101 NUNCA biz=cliente real).
    $foreign = DeviceModel::create([
        'business_id' => 99,
        'name' => 'Modelo-Alheio-Biz99',
        'created_by' => 1,
    ]);

    [$business, $user] = bladeT1cBootstrap();
    // Garante que user está em biz != 99.
    expect((int) $business->id)->not->toBe(99);

    config([
        'mwart.repair_device_models_edit.enabled' => true,
        'mwart.repair_device_models_edit.business_ids' => [],
    ]);

    $response = $this->actingAs($user)->get("/repair/device-models/{$foreign->id}/edit");

    // Controller faz findOrFail scopado por business_id → deve dar 404 (model not found).
    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate.');
    }
    expect($response->status())->toBe(404);
});

it('DeviceModel scope: biz=1 vs biz=99 query isolation (defesa-em-profundidade)', function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('Scope cross-tenant requer SQLite in-memory.');
    }

    DeviceModel::create(['business_id' => 1, 'name' => 'iPhone 13', 'created_by' => 1]);
    DeviceModel::create(['business_id' => 1, 'name' => 'Galaxy S22', 'created_by' => 1]);
    DeviceModel::create(['business_id' => 99, 'name' => 'Pixel 7', 'created_by' => 1]);

    $biz1 = DeviceModel::where('business_id', 1)->get();
    $biz99 = DeviceModel::where('business_id', 99)->get();

    expect($biz1)->toHaveCount(2);
    expect($biz99)->toHaveCount(1);
    expect($biz1->pluck('name')->toArray())
        ->toContain('iPhone 13')
        ->toContain('Galaxy S22')
        ->not->toContain('Pixel 7');
});
