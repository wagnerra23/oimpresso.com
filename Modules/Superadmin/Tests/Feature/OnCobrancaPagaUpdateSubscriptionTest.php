<?php

declare(strict_types=1);

use App\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Listeners\OnCobrancaPagaUpdateSubscription;

uses(Tests\TestCase::class);

/**
 * Pest — ADR 0170 Onda 5 SIMPLIFICADA.
 *
 * Listener Superadmin escuta CobrancaPaga(origem_type='subscription_license')
 * e marca Subscription do tenant como approved + desbloqueia officeimpresso_bloqueado.
 *
 * Tier 0: cross-tenant (cobranca.business_id=1, subscription.business_id=tenant).
 * Pattern documentado em Subscription.php:30 "cross-tenant intencional Wagner-only".
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS + Superadmin + PaymentGateway.');
    }
    if (!Schema::hasTable('subscriptions') || !Schema::hasTable('packages') || !Schema::hasTable('business')) {
        $this->markTestSkipped('Schema Superadmin ausente.');
    }
});

const ONDA5_BIZ_TENANT_TEST = 99;

function onda5_makeTestPackage(): Package
{
    return Package::create([
        'name'           => 'Pacote Onda 5 Test',
        'description'    => 'pacote teste listener pago',
        'location_count' => 1,
        'user_count'     => 5,
        'product_count'  => 100,
        'invoice_count'  => 1000,
        'interval'       => 'months',
        'interval_count' => 1,
        'trial_days'     => 0,
        'price'          => 99.90,
        'is_active'      => 1,
        'sort_order'     => 999,
        'is_private'     => 0,
        'is_one_time'    => 0,
    ]);
}

function onda5_makeTestSubscription(int $packageId, int $businessId, string $status = 'waiting'): Subscription
{
    return Subscription::create([
        'business_id'             => $businessId,
        'package_id'              => $packageId,
        'paid_via'                => 'paymentgateway_pix_automatico',
        'payment_transaction_id'  => null,
        'start_date'              => null,
        'end_date'                => null,
        'trial_end_date'          => null,
        'status'                  => $status,
        'package_price'           => 99.90,
        'package_details'         => ['name' => 'Pacote Onda 5 Test'],
        'created_id'              => 1,
    ]);
}

function onda5_cleanup(): void
{
    Subscription::where('package_id', '!=', null)
        ->whereHas('package', fn ($q) => $q->where('name', 'Pacote Onda 5 Test'))
        ->forceDelete();
    Package::where('name', 'Pacote Onda 5 Test')->forceDelete();
    Business::withoutGlobalScopes()->where('id', ONDA5_BIZ_TENANT_TEST)->update(['officeimpresso_bloqueado' => false]);
}

it('CobrancaPaga origem subscription_license aprova Subscription waiting do tenant', function () {
    $package = onda5_makeTestPackage();
    Business::firstOrCreate(['id' => ONDA5_BIZ_TENANT_TEST], ['name' => 'Tenant Onda 5 Test', 'currency_id' => 1]);
    $subscription = onda5_makeTestSubscription($package->id, ONDA5_BIZ_TENANT_TEST, 'waiting');

    $event = new CobrancaPaga(
        cobrancaId: 9999001,
        businessId: 1,
        valorPagoCentavos: 9990,
        pagaEm: new \DateTimeImmutable(),
        formaPagamento: 'pix',
        occurredAt: new \DateTimeImmutable(),
        payerCpfCnpj: null,
        origemType: 'subscription_license',
        origemId: $subscription->id,
    );

    (new OnCobrancaPagaUpdateSubscription())->handle($event);

    $subscription->refresh();
    expect($subscription->status)->toBe('approved');
    expect($subscription->start_date)->not->toBeNull();
    expect($subscription->end_date)->not->toBeNull();
    expect($subscription->paid_via)->toBe('paymentgateway_pix_automatico');
    expect((string) $subscription->payment_transaction_id)->toBe('9999001');

    onda5_cleanup();
});

it('CobrancaPaga origem diferente NÃO toca Subscription', function () {
    $package = onda5_makeTestPackage();
    Business::firstOrCreate(['id' => ONDA5_BIZ_TENANT_TEST], ['name' => 'Tenant Onda 5 Test', 'currency_id' => 1]);
    $subscription = onda5_makeTestSubscription($package->id, ONDA5_BIZ_TENANT_TEST, 'waiting');

    $event = new CobrancaPaga(
        cobrancaId: 9999002,
        businessId: 1,
        valorPagoCentavos: 5000,
        pagaEm: new \DateTimeImmutable(),
        formaPagamento: 'boleto',
        occurredAt: new \DateTimeImmutable(),
        payerCpfCnpj: null,
        origemType: 'sale',
        origemId: $subscription->id,
    );

    (new OnCobrancaPagaUpdateSubscription())->handle($event);

    $subscription->refresh();
    expect($subscription->status)->toBe('waiting');
    expect($subscription->start_date)->toBeNull();

    onda5_cleanup();
});

it('CobrancaPaga desbloqueia business officeimpresso_bloqueado quando aprova', function () {
    $package = onda5_makeTestPackage();
    $business = Business::firstOrCreate(['id' => ONDA5_BIZ_TENANT_TEST], ['name' => 'Tenant Onda 5 Test', 'currency_id' => 1]);
    Business::withoutGlobalScopes()->where('id', ONDA5_BIZ_TENANT_TEST)->update(['officeimpresso_bloqueado' => true]);

    $subscription = onda5_makeTestSubscription($package->id, ONDA5_BIZ_TENANT_TEST, 'waiting');

    $event = new CobrancaPaga(
        cobrancaId: 9999003,
        businessId: 1,
        valorPagoCentavos: 9990,
        pagaEm: new \DateTimeImmutable(),
        formaPagamento: 'pix',
        occurredAt: new \DateTimeImmutable(),
        payerCpfCnpj: null,
        origemType: 'subscription_license',
        origemId: $subscription->id,
    );

    (new OnCobrancaPagaUpdateSubscription())->handle($event);

    $business->refresh();
    expect((bool) $business->officeimpresso_bloqueado)->toBeFalse();

    onda5_cleanup();
});

it('CobrancaPaga em Subscription já approved é idempotente (return sem touch)', function () {
    $package = onda5_makeTestPackage();
    Business::firstOrCreate(['id' => ONDA5_BIZ_TENANT_TEST], ['name' => 'Tenant Onda 5 Test', 'currency_id' => 1]);
    $subscription = onda5_makeTestSubscription($package->id, ONDA5_BIZ_TENANT_TEST, 'approved');
    $subscription->start_date = now()->subDays(1)->toDateString();
    $subscription->end_date = now()->addMonth()->toDateString();
    $subscription->save();
    $originalStart = $subscription->start_date->toDateString();

    $event = new CobrancaPaga(
        cobrancaId: 9999004,
        businessId: 1,
        valorPagoCentavos: 9990,
        pagaEm: new \DateTimeImmutable(),
        formaPagamento: 'pix',
        occurredAt: new \DateTimeImmutable(),
        payerCpfCnpj: null,
        origemType: 'subscription_license',
        origemId: $subscription->id,
    );

    (new OnCobrancaPagaUpdateSubscription())->handle($event);

    $subscription->refresh();
    expect($subscription->start_date->toDateString())->toBe($originalStart);
    onda5_cleanup();
});

/*
 * L2 PR1 (auditoria Superadmin 2026-11-05) — o listener confere o valor pago com o preço
 * da assinatura antes de aprovar, e a aprovação passa pelo SubscriptionLifecycleService.
 * Preço do pacote de teste: 99.90 → 9990 centavos (round(preço × 100), a mesma conta das
 * duas origens da cobrança subscription_license).
 */

function onda5_eventoPago(int $subscriptionId, int $cobrancaId, int $centavos): CobrancaPaga
{
    return new CobrancaPaga(
        cobrancaId: $cobrancaId,
        businessId: 1,
        valorPagoCentavos: $centavos,
        pagaEm: new \DateTimeImmutable(),
        formaPagamento: 'pix',
        occurredAt: new \DateTimeImmutable(),
        payerCpfCnpj: null,
        origemType: 'subscription_license',
        origemId: $subscriptionId,
    );
}

it('valorEsperadoCentavos usa package_price e cai no preço do pacote quando ele é zero', function () {
    $pacote = new Package(['price' => 149.90]);

    expect(OnCobrancaPagaUpdateSubscription::valorEsperadoCentavos(new Subscription(['package_price' => 99.90]), $pacote))->toBe(9990);
    expect(OnCobrancaPagaUpdateSubscription::valorEsperadoCentavos(new Subscription(['package_price' => 0]), $pacote))->toBe(14990);
});

it('CobrancaPaga com valor MENOR que o preço não aprova nem desbloqueia', function () {
    $package = onda5_makeTestPackage();
    $business = Business::firstOrCreate(['id' => ONDA5_BIZ_TENANT_TEST], ['name' => 'Tenant Onda 5 Test', 'currency_id' => 1]);
    Business::withoutGlobalScopes()->where('id', ONDA5_BIZ_TENANT_TEST)->update(['officeimpresso_bloqueado' => true]);
    $subscription = onda5_makeTestSubscription($package->id, ONDA5_BIZ_TENANT_TEST, 'waiting');

    (new OnCobrancaPagaUpdateSubscription())->handle(onda5_eventoPago($subscription->id, 9999005, 9989));

    $subscription->refresh();
    $business->refresh();
    expect($subscription->status)->toBe('waiting');
    expect($subscription->start_date)->toBeNull();
    expect($subscription->payment_transaction_id)->toBeNull();
    expect((bool) $business->officeimpresso_bloqueado)->toBeTrue();

    onda5_cleanup();
});

it('CobrancaPaga com valor MAIOR que o preço não aprova', function () {
    $package = onda5_makeTestPackage();
    Business::firstOrCreate(['id' => ONDA5_BIZ_TENANT_TEST], ['name' => 'Tenant Onda 5 Test', 'currency_id' => 1]);
    $subscription = onda5_makeTestSubscription($package->id, ONDA5_BIZ_TENANT_TEST, 'waiting');

    (new OnCobrancaPagaUpdateSubscription())->handle(onda5_eventoPago($subscription->id, 9999006, 9991));

    $subscription->refresh();
    expect($subscription->status)->toBe('waiting');
    expect($subscription->end_date)->toBeNull();

    onda5_cleanup();
});

it('pacote anual aprovado pelo serviço termina 1 ano depois do início (não cai no default de 1 mês)', function () {
    $package = onda5_makeTestPackage();
    $package->update(['interval' => 'years', 'interval_count' => 1]);
    Business::firstOrCreate(['id' => ONDA5_BIZ_TENANT_TEST], ['name' => 'Tenant Onda 5 Test', 'currency_id' => 1]);
    $subscription = onda5_makeTestSubscription($package->id, ONDA5_BIZ_TENANT_TEST, 'waiting');

    (new OnCobrancaPagaUpdateSubscription())->handle(onda5_eventoPago($subscription->id, 9999007, 9990));

    $subscription->refresh();
    expect($subscription->status)->toBe('approved');
    expect($subscription->end_date->toDateString())
        ->toBe($subscription->start_date->copy()->addYear()->toDateString());
    expect((string) $subscription->payment_transaction_id)->toBe('9999007');

    onda5_cleanup();
});

it('CobrancaPaga em assinatura cancelada não aprova nem desbloqueia', function () {
    $package = onda5_makeTestPackage();
    $business = Business::firstOrCreate(['id' => ONDA5_BIZ_TENANT_TEST], ['name' => 'Tenant Onda 5 Test', 'currency_id' => 1]);
    Business::withoutGlobalScopes()->where('id', ONDA5_BIZ_TENANT_TEST)->update(['officeimpresso_bloqueado' => true]);
    $subscription = onda5_makeTestSubscription($package->id, ONDA5_BIZ_TENANT_TEST, 'cancelled');

    (new OnCobrancaPagaUpdateSubscription())->handle(onda5_eventoPago($subscription->id, 9999008, 9990));

    $subscription->refresh();
    $business->refresh();
    expect($subscription->status)->toBe('cancelled');
    expect((bool) $business->officeimpresso_bloqueado)->toBeTrue();

    onda5_cleanup();
});
