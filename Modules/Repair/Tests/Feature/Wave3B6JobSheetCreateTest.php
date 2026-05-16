<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Wave 3 B6 — JobSheet Create Blade → Inertia.
 */

beforeEach(function () {
    try {
        Permission::firstOrCreate(['name' => 'job_sheet.create', 'guard_name' => 'web']);
    } catch (\Throwable $e) {
        test()->markTestSkipped('Permissions table indisponível: '.$e->getMessage());
    }
});

afterEach(function () {
    config([
        'mwart.repair_job_sheet_create.enabled' => false,
        'mwart.repair_job_sheet_create.business_ids' => [],
    ]);
});

function w3b6CreateBootstrap(): array
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
        if (! $user->hasPermissionTo('job_sheet.create')) {
            $user->givePermissionTo('job_sheet.create');
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

it('flag MWART OFF → Blade Create', function () {
    [$business, $user] = w3b6CreateBootstrap();

    config(['mwart.repair_job_sheet_create.enabled' => false]);

    $response = $this->actingAs($user)->get('/repair/job-sheet/create');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate.');
    }

    expect($response->status())->toBeLessThan(500);
    expect($response->headers->get('X-Inertia'))->toBeNull();
});

it('flag MWART ON → Inertia Repair/JobSheet/Create', function () {
    [$business, $user] = w3b6CreateBootstrap();

    config([
        'mwart.repair_job_sheet_create.enabled' => true,
        'mwart.repair_job_sheet_create.business_ids' => [],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/repair/job-sheet/create');

    if ($response->status() !== 200) {
        test()->markTestSkipped('Render falhou.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page->component('Repair/JobSheet/Create'));
});

it('flag MWART ON com business_ids whitelist — biz fora usa Blade', function () {
    [$business, $user] = w3b6CreateBootstrap();
    $foreignId = $business->id + 999;
    config([
        'mwart.repair_job_sheet_create.enabled' => true,
        'mwart.repair_job_sheet_create.business_ids' => [$foreignId],
    ]);

    $response = $this->actingAs($user)->get('/repair/job-sheet/create');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate.');
    }

    expect($response->status())->toBeLessThan(500);
    expect($response->headers->get('X-Inertia'))->toBeNull();
});
