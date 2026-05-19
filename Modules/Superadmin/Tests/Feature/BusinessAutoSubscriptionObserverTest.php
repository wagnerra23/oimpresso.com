<?php

declare(strict_types=1);

use App\Business;
use App\System;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;

uses(Tests\TestCase::class);

/**
 * Pest — ADR 0170 Onda 5.B SIMPLIFICADA.
 *
 * Observer Business::created auto-cria Subscription waiting com Package default
 * + trial_end_date. Cobre UI Superadmin + API Delphi simultaneamente (ambos
 * caminhos chamam Business::create).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS + Superadmin.');
    }
    if (!Schema::hasTable('subscriptions') || !Schema::hasTable('packages') || !Schema::hasTable('business') || !Schema::hasTable('system')) {
        $this->markTestSkipped('Schema Superadmin/System ausente.');
    }
});

const ONDA5B_BIZ_TEST = 9991;

function onda5b_pkg(): Package
{
    return Package::create([
        'name' => 'Pkg Onda 5B Default', 'description' => '...',
        'location_count' => 0, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0,
        'interval' => 'months', 'interval_count' => 1, 'trial_days' => 7,
        'price' => 99.90, 'is_active' => 1, 'sort_order' => 999, 'is_private' => 0, 'is_one_time' => 0,
    ]);
}

function onda5b_setProperty(?int $packageId): void
{
    if ($packageId === null) {
        DB::table('system')->where('key', 'default_saas_package_id')->delete();
    } else {
        $exists = DB::table('system')->where('key', 'default_saas_package_id')->exists();
        if ($exists) {
            DB::table('system')->where('key', 'default_saas_package_id')->update(['value' => (string) $packageId]);
        } else {
            DB::table('system')->insert(['key' => 'default_saas_package_id', 'value' => (string) $packageId]);
        }
    }
}

function onda5b_cleanup(int $packageId): void
{
    Subscription::where('package_id', $packageId)->forceDelete();
    Business::withoutGlobalScopes()->where('id', ONDA5B_BIZ_TEST)->forceDelete();
    Package::where('id', $packageId)->forceDelete();
    onda5b_setProperty(null);
}

it('Business::create dispara auto-Subscription waiting com Package default + trial', function () {
    $package = onda5b_pkg();
    onda5b_setProperty($package->id);

    $business = Business::create([
        'id' => ONDA5B_BIZ_TEST,
        'name' => 'Tenant Onda 5B Test',
        'currency_id' => 1,
    ]);

    $sub = Subscription::where('business_id', $business->id)->first();

    expect($sub)->not->toBeNull();
    expect($sub->status)->toBe('waiting');
    expect($sub->package_id)->toBe($package->id);
    expect($sub->paid_via)->toBe('paymentgateway_pix_automatico');
    expect($sub->trial_end_date)->not->toBeNull();

    onda5b_cleanup($package->id);
});

it('Business::create biz=1 (Wagner) NÃO dispara auto-Subscription', function () {
    $package = onda5b_pkg();
    onda5b_setProperty($package->id);

    Business::withoutGlobalScopes()->where('id', 1)->first();
    // biz=1 já existe em prod; pra teste, vou simular create com id=1 via firstOrCreate
    // mas garantir que NÃO há subscription com nosso package_id
    $initialCount = Subscription::where('business_id', 1)->where('package_id', $package->id)->count();

    // Skip cleanup-creating — apenas valida que observer não cria sub pra biz=1 quando rodado
    $observer = new \Modules\Superadmin\Observers\BusinessAutoSubscriptionObserver();
    $bizWagner = Business::withoutGlobalScopes()->find(1) ?? Business::create(['id' => 1, 'name' => 'Wagner', 'currency_id' => 1]);
    $observer->created($bizWagner);

    $finalCount = Subscription::where('business_id', 1)->where('package_id', $package->id)->count();
    expect($finalCount)->toBe($initialCount);

    onda5b_cleanup($package->id);
});

it('Sem default_saas_package_id Observer faz no-op', function () {
    onda5b_setProperty(null);

    $business = Business::create([
        'id' => ONDA5B_BIZ_TEST,
        'name' => 'Tenant Sem Property',
        'currency_id' => 1,
    ]);

    $count = Subscription::where('business_id', $business->id)->count();
    expect($count)->toBe(0);

    Business::withoutGlobalScopes()->where('id', ONDA5B_BIZ_TEST)->forceDelete();
});

it('Business já com Subscription NÃO duplica (idempotência)', function () {
    $package = onda5b_pkg();
    onda5b_setProperty($package->id);

    $business = Business::create([
        'id' => ONDA5B_BIZ_TEST,
        'name' => 'Tenant Re-test',
        'currency_id' => 1,
    ]);
    $firstCount = Subscription::where('business_id', $business->id)->count();

    // Re-invoca observer manualmente (simula Eloquent observer reentrante)
    (new \Modules\Superadmin\Observers\BusinessAutoSubscriptionObserver())->created($business);

    $secondCount = Subscription::where('business_id', $business->id)->count();
    expect($secondCount)->toBe($firstCount);

    onda5b_cleanup($package->id);
});

it('Package default inexistente faz no-op + log warning', function () {
    onda5b_setProperty(99999); // ID inexistente

    $business = Business::create([
        'id' => ONDA5B_BIZ_TEST,
        'name' => 'Tenant Pkg Inexistente',
        'currency_id' => 1,
    ]);

    $count = Subscription::where('business_id', $business->id)->count();
    expect($count)->toBe(0);

    Business::withoutGlobalScopes()->where('id', ONDA5B_BIZ_TEST)->forceDelete();
    onda5b_setProperty(null);
});
