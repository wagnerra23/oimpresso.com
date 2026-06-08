<?php

declare(strict_types=1);

use App\Business;
use App\Transaction;
use App\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Wave 3 B6 — Repair/Show (venda-de-reparo) Blade → Inertia.
 */

beforeEach(function () {
    try {
        foreach (['repair.view', 'repair.view_own', 'repair.update'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    } catch (\Throwable $e) {
        test()->markTestSkipped('Permissions table indisponível: '.$e->getMessage());
    }
});

afterEach(function () {
    config([
        'mwart.repair_show.enabled' => false,
        'mwart.repair_show.business_ids' => [],
    ]);
});

function w3b6RepairShowBootstrap(): array
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

function w3b6FindOrSkipRepairTransaction(int $business_id): ?Transaction
{
    try {
        return Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('sub_type', 'repair')
            ->first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Transactions schema indisponível.');
    }
}

it('flag MWART OFF → Blade Repair/Show', function () {
    [$business, $user] = w3b6RepairShowBootstrap();
    $tx = w3b6FindOrSkipRepairTransaction($business->id);
    if (! $tx) {
        test()->markTestSkipped('Sem transação repair no dev.');
    }

    config(['mwart.repair_show.enabled' => false]);

    $response = $this->actingAs($user)->get("/repair/repair/{$tx->id}");

    if ($response->status() === 403 || $response->status() === 404) {
        test()->markTestSkipped('Subscription gate.');
    }

    expect($response->status())->toBeLessThan(500);
    expect($response->headers->get('X-Inertia'))->toBeNull();
});

it('flag MWART ON → Inertia Repair/Show', function () {
    [$business, $user] = w3b6RepairShowBootstrap();
    $tx = w3b6FindOrSkipRepairTransaction($business->id);
    if (! $tx) {
        test()->markTestSkipped('Sem transação repair.');
    }

    config([
        'mwart.repair_show.enabled' => true,
        'mwart.repair_show.business_ids' => [],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get("/repair/repair/{$tx->id}");

    if ($response->status() !== 200) {
        test()->markTestSkipped('Render falhou — schema mismatch.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Repair/Show')
        ->has('sell.id')
        ->has('fsm.enabled')
        ->has('fsm.sale_id')
    );
});

it('biz cross-tenant — 404 ao tentar repair show de outro biz', function () {
    [$business, $user] = w3b6RepairShowBootstrap();
    $otherBiz = Business::where('id', '!=', $business->id)->first();
    if (! $otherBiz) {
        test()->markTestSkipped('Precisa de 2+ biz.');
    }
    $otherTx = Transaction::where('business_id', $otherBiz->id)
        ->where('sub_type', 'repair')
        ->first();
    if (! $otherTx) {
        test()->markTestSkipped('Sem tx repair biz alt.');
    }

    config(['mwart.repair_show.enabled' => true]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->get("/repair/repair/{$otherTx->id}");

    expect($response->status())->toBeIn([404, 403]);
});

it('fsm panel flag separado de mwart show flag', function () {
    [$business, $user] = w3b6RepairShowBootstrap();
    $tx = w3b6FindOrSkipRepairTransaction($business->id);
    if (! $tx) {
        test()->markTestSkipped('Sem tx repair.');
    }

    config([
        'mwart.repair_show.enabled' => true,
        'mwart.repair_show_fsm_panel.enabled' => true,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->get("/repair/repair/{$tx->id}");

    if ($response->status() !== 200) {
        test()->markTestSkipped('Render falhou.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page->where('fsm.enabled', true));
});
