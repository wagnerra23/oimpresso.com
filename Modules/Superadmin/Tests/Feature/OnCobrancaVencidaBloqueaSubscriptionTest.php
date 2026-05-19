<?php

declare(strict_types=1);

use App\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Events\CobrancaVencida;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Listeners\OnCobrancaVencidaBloqueaSubscription;

uses(Tests\TestCase::class);

/**
 * Pest — ADR 0170 Onda 5 SIMPLIFICADA. Par do listener Paga.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS + Superadmin + PaymentGateway.');
    }
    if (!Schema::hasTable('subscriptions') || !Schema::hasTable('packages') || !Schema::hasTable('business')) {
        $this->markTestSkipped('Schema Superadmin ausente.');
    }
});

const ONDA5V_BIZ_TENANT = 99;

function onda5v_makeSub(int $packageId, int $businessId, string $status = 'approved'): Subscription
{
    return Subscription::create([
        'business_id'             => $businessId,
        'package_id'              => $packageId,
        'paid_via'                => 'paymentgateway_pix_automatico',
        'payment_transaction_id'  => 'OLD-1',
        'start_date'              => now()->subMonth()->toDateString(),
        'end_date'                => now()->subDays(5)->toDateString(),
        'trial_end_date'          => null,
        'status'                  => $status,
        'package_price'           => 99.90,
        'package_details'         => ['name' => 'Pkg Vencida Test'],
        'created_id'              => 1,
    ]);
}

function onda5v_cleanup(): void
{
    Subscription::whereHas('package', fn ($q) => $q->where('name', 'Pkg Vencida Test'))->forceDelete();
    Package::where('name', 'Pkg Vencida Test')->forceDelete();
    Business::withoutGlobalScopes()->where('id', ONDA5V_BIZ_TENANT)->update(['officeimpresso_bloqueado' => false]);
}

it('CobrancaVencida subscription_license marca declined + bloqueia business', function () {
    $package = Package::create([
        'name' => 'Pkg Vencida Test', 'description' => '...',
        'location_count' => 0, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0,
        'interval' => 'months', 'interval_count' => 1, 'trial_days' => 0,
        'price' => 99.90, 'is_active' => 1, 'sort_order' => 999, 'is_private' => 0, 'is_one_time' => 0,
    ]);
    $business = Business::firstOrCreate(['id' => ONDA5V_BIZ_TENANT], ['name' => 'Tenant Vencida', 'currency_id' => 1]);
    Business::withoutGlobalScopes()->where('id', ONDA5V_BIZ_TENANT)->update(['officeimpresso_bloqueado' => false]);
    $subscription = onda5v_makeSub($package->id, ONDA5V_BIZ_TENANT, 'approved');

    $event = new CobrancaVencida(
        cobrancaId: 9998001,
        businessId: 1,
        diasVencido: 5,
        vencimentoOriginal: new \DateTimeImmutable('-5 days'),
        occurredAt: new \DateTimeImmutable(),
        origemType: 'subscription_license',
        origemId: $subscription->id,
    );

    (new OnCobrancaVencidaBloqueaSubscription())->handle($event);

    $subscription->refresh();
    $business->refresh();
    expect($subscription->status)->toBe('declined');
    expect((bool) $business->officeimpresso_bloqueado)->toBeTrue();

    onda5v_cleanup();
});

it('CobrancaVencida origem diferente NÃO bloqueia business', function () {
    $package = Package::create([
        'name' => 'Pkg Vencida Test', 'description' => '...',
        'location_count' => 0, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0,
        'interval' => 'months', 'interval_count' => 1, 'trial_days' => 0,
        'price' => 99.90, 'is_active' => 1, 'sort_order' => 999, 'is_private' => 0, 'is_one_time' => 0,
    ]);
    $business = Business::firstOrCreate(['id' => ONDA5V_BIZ_TENANT], ['name' => 'Tenant Vencida', 'currency_id' => 1]);
    Business::withoutGlobalScopes()->where('id', ONDA5V_BIZ_TENANT)->update(['officeimpresso_bloqueado' => false]);
    $subscription = onda5v_makeSub($package->id, ONDA5V_BIZ_TENANT, 'approved');

    $event = new CobrancaVencida(
        cobrancaId: 9998002,
        businessId: 1,
        diasVencido: 5,
        vencimentoOriginal: new \DateTimeImmutable('-5 days'),
        occurredAt: new \DateTimeImmutable(),
        origemType: 'invoice',
        origemId: $subscription->id,
    );

    (new OnCobrancaVencidaBloqueaSubscription())->handle($event);

    $subscription->refresh();
    $business->refresh();
    expect($subscription->status)->toBe('approved');
    expect((bool) $business->officeimpresso_bloqueado)->toBeFalse();

    onda5v_cleanup();
});
