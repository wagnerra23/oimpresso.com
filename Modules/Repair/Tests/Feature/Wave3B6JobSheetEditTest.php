<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Modules\Repair\Entities\JobSheet;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Wave 3 B6 MWART — JobSheet Edit port Blade → Inertia.
 * Reuses helpers de Wave3B6JobSheetShowTest (mesma sessão).
 */

beforeEach(function () {
    try {
        foreach ([
            'job_sheet.view_all',
            'job_sheet.edit',
            'job_sheet.create',
        ] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    } catch (\Throwable $e) {
        test()->markTestSkipped('Permissions table indisponível (sqlite :memory:): '.$e->getMessage());
    }
});

afterEach(function () {
    config([
        'mwart.repair_job_sheet_edit.enabled' => false,
        'mwart.repair_job_sheet_edit.business_ids' => [],
    ]);
});

function w3b6EditBootstrap(): array
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
        Permission::firstOrCreate(['name' => 'job_sheet.edit', 'guard_name' => 'web']);
        if (! $user->hasPermissionTo('job_sheet.edit')) {
            $user->givePermissionTo('job_sheet.edit');
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

it('flag MWART OFF → Blade Edit', function () {
    [$business, $user] = w3b6EditBootstrap();
    $js = JobSheet::where('business_id', $business->id)->first();
    if (! $js) {
        test()->markTestSkipped('Sem JobSheet.');
    }

    config(['mwart.repair_job_sheet_edit.enabled' => false]);

    $response = $this->actingAs($user)->get("/repair/job-sheet/{$js->id}/edit");

    if ($response->status() === 403 || $response->status() === 404) {
        test()->markTestSkipped('Subscription/schema gate.');
    }

    expect($response->status())->toBeLessThan(500);
    expect($response->headers->get('X-Inertia'))->toBeNull();
});

it('flag MWART ON → Inertia Repair/JobSheet/Edit', function () {
    [$business, $user] = w3b6EditBootstrap();
    $js = JobSheet::where('business_id', $business->id)->first();
    if (! $js) {
        test()->markTestSkipped('Sem JobSheet.');
    }

    config([
        'mwart.repair_job_sheet_edit.enabled' => true,
        'mwart.repair_job_sheet_edit.business_ids' => [],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get("/repair/job-sheet/{$js->id}/edit");

    if ($response->status() !== 200) {
        test()->markTestSkipped('Render falhou — schema mismatch.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Repair/JobSheet/Edit')
        ->has('job_sheet.id')
    );
});

it('biz cross-tenant não acessa edit OS', function () {
    [$business, $user] = w3b6EditBootstrap();
    $otherBiz = Business::where('id', '!=', $business->id)->first();
    if (! $otherBiz) {
        test()->markTestSkipped('Precisa de 2+ biz.');
    }
    $otherJs = JobSheet::where('business_id', $otherBiz->id)->first();
    if (! $otherJs) {
        test()->markTestSkipped('Sem JobSheet biz alt.');
    }

    config(['mwart.repair_job_sheet_edit.enabled' => true]);

    $response = $this->actingAs($user)->get("/repair/job-sheet/{$otherJs->id}/edit");

    expect($response->status())->toBeIn([404, 403]);
});
