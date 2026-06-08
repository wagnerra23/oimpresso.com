<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Observers\SubscriptionCachedFieldsObserver;
use Modules\RecurringBilling\Policies\SubscriptionPolicy;

uses(Tests\TestCase::class);

/**
 * Wave 2 (Observer cached fields) + Wave 3 (Controller actions + Policy).
 *
 * SQLite in-memory pattern (espelha AtualizarCobrancaAssinaturaTest.php) —
 * migrations legadas UltimatePOS usam sintaxe MySQL-only.
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 (ADR 0101) — NUNCA biz=4.
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite' || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Smoke test rodado apenas em SQLite in-memory.');
    }

    Schema::dropIfExists('rb_invoices');
    Schema::dropIfExists('rb_subscriptions');
    Schema::dropIfExists('contacts');

    Schema::create('contacts', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->nullable()->index();
        $t->string('name')->nullable();
        $t->string('mobile', 50)->nullable();
        $t->string('landline', 50)->nullable();
        $t->string('email')->nullable();
        $t->string('tax_number', 50)->nullable();
        $t->string('contact_type', 20)->default('customer');
        $t->string('type', 20)->default('customer');
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::dropIfExists('activity_log');
    Schema::create('activity_log', function ($t) {
        $t->id();
        $t->string('log_name')->nullable();
        $t->text('description')->nullable();
        $t->nullableMorphs('subject');
        $t->string('event')->nullable();
        $t->nullableMorphs('causer');
        $t->json('properties')->nullable();
        $t->uuid('batch_uuid')->nullable();
        $t->timestamps();
    });

    Schema::create('rb_subscriptions', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('plan_id')->nullable();
        $t->unsignedInteger('contact_id')->nullable();
        $t->string('status', 20)->default('active');
        $t->date('start_date')->nullable();
        $t->date('next_due_date')->nullable();
        $t->date('billing_anchor_date')->nullable();
        $t->dateTime('canceled_at')->nullable();
        $t->dateTime('paused_at')->nullable();
        $t->unsignedInteger('conta_bancaria_id')->nullable();
        $t->json('metadata')->nullable();
        // v9,75 cached cols
        $t->string('payment_method', 10)->nullable();
        $t->unsignedBigInteger('last_jobsheet_id')->nullable();
        $t->unsignedSmallInteger('total_paid_cached')->default(0);
        $t->unsignedSmallInteger('failed_count_cached')->default(0);
        $t->decimal('total_revenue_cached', 14, 2)->default(0);
        $t->date('paused_until')->nullable();
        $t->string('churn_reason', 64)->nullable();
        $t->string('contact_phone_cached', 32)->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('rb_invoices', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('subscription_id')->nullable();
        $t->unsignedInteger('contact_id')->nullable();
        $t->string('numero_documento', 50)->nullable();
        $t->decimal('valor', 15, 2);
        $t->string('status', 20)->default('open');
        $t->date('vencimento')->nullable();
        $t->dateTime('pago_em')->nullable();
        $t->string('gateway', 30)->nullable();
        $t->string('gateway_ref', 100)->nullable();
        $t->unsignedInteger('conta_bancaria_id')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
});

// ─── Wave 2 — Observer cached fields ──────────────────────────────────

it('R-RB-WAVE2-1 — Observer recompute total_paid_cached + total_revenue_cached quando Invoice vira paid', function () {
    $sub = Subscription::create([
        'business_id' => 1, 'contact_id' => 1, 'status' => 'active',
        'start_date'  => '2025-01-01', 'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2025-01-01',
    ]);

    // Cria 3 invoices: 2 paid + 1 open
    Invoice::create(['business_id' => 1, 'subscription_id' => $sub->id, 'valor' => 100, 'status' => 'paid']);
    Invoice::create(['business_id' => 1, 'subscription_id' => $sub->id, 'valor' => 150, 'status' => 'paid']);
    Invoice::create(['business_id' => 1, 'subscription_id' => $sub->id, 'valor' => 200, 'status' => 'open']);

    $sub->refresh();

    expect($sub->total_paid_cached)->toBe(2)
        ->and((float) $sub->total_revenue_cached)->toBe(250.0)
        ->and($sub->failed_count_cached)->toBe(0);
});

it('R-RB-WAVE2-2 — Observer recompute failed_count_cached quando Invoice vira overdue', function () {
    $sub = Subscription::create([
        'business_id' => 1, 'contact_id' => 1, 'status' => 'past_due',
        'start_date'  => '2025-01-01', 'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2025-01-01',
    ]);

    Invoice::create(['business_id' => 1, 'subscription_id' => $sub->id, 'valor' => 100, 'status' => 'paid']);
    Invoice::create(['business_id' => 1, 'subscription_id' => $sub->id, 'valor' => 100, 'status' => 'overdue']);
    Invoice::create(['business_id' => 1, 'subscription_id' => $sub->id, 'valor' => 100, 'status' => 'overdue']);

    $sub->refresh();

    expect($sub->failed_count_cached)->toBe(2)
        ->and($sub->total_paid_cached)->toBe(1);
});

it('R-RB-WAVE2-3 — recomputeForSubscription direto retorna estado correto', function () {
    $sub = Subscription::create([
        'business_id' => 1, 'contact_id' => 1, 'status' => 'active',
        'start_date'  => '2025-01-01', 'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2025-01-01',
        'total_paid_cached' => 99, // valor incorreto inicial
        'failed_count_cached' => 99,
        'total_revenue_cached' => 9999,
    ]);

    Invoice::create(['business_id' => 1, 'subscription_id' => $sub->id, 'valor' => 50, 'status' => 'paid']);

    $observer = new SubscriptionCachedFieldsObserver();
    $observer->recomputeForSubscription($sub->id);

    $sub->refresh();
    expect($sub->total_paid_cached)->toBe(1)
        ->and((float) $sub->total_revenue_cached)->toBe(50.0)
        ->and($sub->failed_count_cached)->toBe(0);
});

// ─── Wave 3 — Policy ──────────────────────────────────────────────────

it('R-RB-WAVE3-1 — Policy.cancel bloqueia se subscription já cancelada', function () {
    $sub = new Subscription();
    $sub->business_id = 1;
    $sub->status = 'canceled';

    $user = new App\User();
    $user->business_id = 1;
    $user->id = 1;
    // Skip permission check: simula superadmin
    $userMock = \Mockery::mock(\App\User::class)->makePartial();
    $userMock->business_id = 1;
    $userMock->shouldReceive('can')->with('superadmin')->andReturn(false);
    $userMock->shouldReceive('can')->with('recurringbilling.subscriptions.cancel')->andReturn(true);
    $userMock->shouldReceive('can')->with('recurringbilling.access')->andReturn(true);

    $policy = new SubscriptionPolicy();

    expect($policy->cancel($userMock, $sub))->toBeFalse();
});

it('R-RB-WAVE3-2 — Policy.cancel permite quando status != canceled e mesmo tenant', function () {
    $sub = new Subscription();
    $sub->business_id = 1;
    $sub->status = 'active';

    $userMock = \Mockery::mock(\App\User::class)->makePartial();
    $userMock->business_id = 1;
    $userMock->shouldReceive('can')->with('superadmin')->andReturn(false);
    $userMock->shouldReceive('can')->with('recurringbilling.subscriptions.cancel')->andReturn(true);
    $userMock->shouldReceive('can')->with('recurringbilling.access')->andReturn(true);

    $policy = new SubscriptionPolicy();

    expect($policy->cancel($userMock, $sub))->toBeTrue();
});

it('R-RB-WAVE3-3 — Policy.cancel bloqueia cross-tenant (biz=1 sub vs biz=99 user)', function () {
    $sub = new Subscription();
    $sub->business_id = 1;
    $sub->status = 'active';

    $userMock = \Mockery::mock(\App\User::class)->makePartial();
    $userMock->business_id = 99;
    $userMock->shouldReceive('can')->with('superadmin')->andReturn(false);
    $userMock->shouldReceive('can')->andReturn(true);

    $policy = new SubscriptionPolicy();

    expect($policy->cancel($userMock, $sub))->toBeFalse();
});

it('R-RB-WAVE3-4 — Policy.pause exige status in [active, trialing, past_due]', function () {
    $userMock = \Mockery::mock(\App\User::class)->makePartial();
    $userMock->business_id = 1;
    $userMock->shouldReceive('can')->with('superadmin')->andReturn(false);
    $userMock->shouldReceive('can')->andReturn(true);

    $policy = new SubscriptionPolicy();

    $subActive = (function () {
        $s = new Subscription();
        $s->business_id = 1;
        $s->status = 'active';

        return $s;
    })();
    $subCanceled = (function () {
        $s = new Subscription();
        $s->business_id = 1;
        $s->status = 'canceled';

        return $s;
    })();

    expect($policy->pause($userMock, $subActive))->toBeTrue()
        ->and($policy->pause($userMock, $subCanceled))->toBeFalse();
});

it('R-RB-WAVE3-5 — Policy.resume só permite quando status=paused', function () {
    $userMock = \Mockery::mock(\App\User::class)->makePartial();
    $userMock->business_id = 1;
    $userMock->shouldReceive('can')->with('superadmin')->andReturn(false);
    $userMock->shouldReceive('can')->andReturn(true);

    $policy = new SubscriptionPolicy();

    $subPaused = (function () {
        $s = new Subscription();
        $s->business_id = 1;
        $s->status = 'paused';

        return $s;
    })();
    $subActive = (function () {
        $s = new Subscription();
        $s->business_id = 1;
        $s->status = 'active';

        return $s;
    })();

    expect($policy->resume($userMock, $subPaused))->toBeTrue()
        ->and($policy->resume($userMock, $subActive))->toBeFalse();
});

afterEach(function () {
    \Mockery::close();
});
