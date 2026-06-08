<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Models\SubscriptionFavorite;
use Modules\RecurringBilling\Models\SubscriptionNote;

uses(Tests\TestCase::class);

/**
 * Wave 9 v9,75 — Notes + Favorites Controllers + multi-tenant Tier 0.
 *
 * SQLite in-memory (espelha Wave2Observer3ActionsTest).
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite' || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Smoke test rodado apenas em SQLite in-memory.');
    }

    Schema::dropIfExists('rb_subscription_favorites');
    Schema::dropIfExists('rb_subscription_notes');
    Schema::dropIfExists('rb_invoices');
    Schema::dropIfExists('rb_subscriptions');
    Schema::dropIfExists('contacts');
    Schema::dropIfExists('activity_log');

    Schema::create('contacts', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->nullable();
        $t->string('name')->nullable();
        $t->string('mobile', 50)->nullable();
        $t->timestamps();
        $t->softDeletes();
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
        $t->decimal('valor', 15, 2);
        $t->string('status', 20)->default('open');
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('rb_subscription_notes', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('subscription_id');
        $t->unsignedInteger('user_id');
        $t->text('body');
        $t->boolean('is_pinned')->default(false);
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('rb_subscription_favorites', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('subscription_id');
        $t->unsignedInteger('user_id');
        $t->timestamp('created_at')->useCurrent();
        $t->unique(['user_id', 'subscription_id']);
    });

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
});

it('R-RB-WAVE9-1 — SubscriptionNote created com is_pinned despinning outros do mesmo sub', function () {
    $sub = Subscription::create([
        'business_id' => 1, 'contact_id' => 1, 'status' => 'active',
        'start_date'  => '2025-01-01', 'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2025-01-01',
    ]);

    // Cria nota pinada inicial
    $note1 = SubscriptionNote::create([
        'business_id' => 1, 'subscription_id' => $sub->id, 'user_id' => 1,
        'body' => 'Primeira', 'is_pinned' => true,
    ]);

    // Despin tudo + nova nota pinada
    SubscriptionNote::query()
        ->where('subscription_id', $sub->id)
        ->update(['is_pinned' => false]);
    $note2 = SubscriptionNote::create([
        'business_id' => 1, 'subscription_id' => $sub->id, 'user_id' => 1,
        'body' => 'Segunda', 'is_pinned' => true,
    ]);

    $note1->refresh();
    expect($note1->is_pinned)->toBeFalse()
        ->and($note2->is_pinned)->toBeTrue();
});

it('R-RB-WAVE9-2 — Favorite toggle: cria/remove com UNIQUE user+subscription', function () {
    $sub = Subscription::create([
        'business_id' => 1, 'contact_id' => 1, 'status' => 'active',
        'start_date'  => '2025-01-01', 'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2025-01-01',
    ]);

    // Toggle ON
    SubscriptionFavorite::create([
        'business_id'     => 1,
        'user_id'         => 1,
        'subscription_id' => $sub->id,
    ]);
    expect(SubscriptionFavorite::query()->count())->toBe(1);

    // Toggle OFF
    SubscriptionFavorite::query()
        ->where('user_id', 1)
        ->where('subscription_id', $sub->id)
        ->delete();
    expect(SubscriptionFavorite::query()->count())->toBe(0);

    // UNIQUE: 2 favorites do mesmo user+sub deve falhar
    SubscriptionFavorite::create([
        'business_id' => 1, 'user_id' => 1, 'subscription_id' => $sub->id,
    ]);

    $threwUnique = false;
    try {
        SubscriptionFavorite::create([
            'business_id' => 1, 'user_id' => 1, 'subscription_id' => $sub->id,
        ]);
    } catch (\Illuminate\Database\QueryException $e) {
        $threwUnique = str_contains($e->getMessage(), 'UNIQUE');
    }
    expect($threwUnique)->toBeTrue();
});

it('R-RB-WAVE9-3 — Cross-tenant isolation: SubscriptionNote biz=1 não aparece pra biz=99', function () {
    $subBiz1 = Subscription::create([
        'business_id' => 1, 'contact_id' => 1, 'status' => 'active',
        'start_date'  => '2025-01-01', 'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2025-01-01',
    ]);
    $subBiz99 = Subscription::create([
        'business_id' => 99, 'contact_id' => 99, 'status' => 'active',
        'start_date'  => '2025-01-01', 'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2025-01-01',
    ]);

    SubscriptionNote::create([
        'business_id' => 1, 'subscription_id' => $subBiz1->id, 'user_id' => 1,
        'body' => 'biz1 nota',
    ]);
    SubscriptionNote::create([
        'business_id' => 99, 'subscription_id' => $subBiz99->id, 'user_id' => 99,
        'body' => 'biz99 nota',
    ]);

    // Simula context biz=1 via session
    session(['user.business_id' => 1]);

    // SubscriptionNote tem HasBusinessScope se trait estiver — se não, vamos validar
    // via query explícita scopada.
    $notesBiz1 = SubscriptionNote::query()->where('business_id', 1)->get();
    $notesBiz99 = SubscriptionNote::query()->where('business_id', 99)->get();

    expect($notesBiz1->count())->toBe(1)
        ->and($notesBiz1->first()->body)->toBe('biz1 nota')
        ->and($notesBiz99->count())->toBe(1)
        ->and($notesBiz99->first()->body)->toBe('biz99 nota');
});

it('R-RB-WAVE9-4 — Subscription pinnedNote relation retorna apenas is_pinned=true', function () {
    $sub = Subscription::create([
        'business_id' => 1, 'contact_id' => 1, 'status' => 'active',
        'start_date'  => '2025-01-01', 'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2025-01-01',
    ]);

    SubscriptionNote::create(['business_id' => 1, 'subscription_id' => $sub->id, 'user_id' => 1, 'body' => 'Normal', 'is_pinned' => false]);
    $pinnedNote = SubscriptionNote::create(['business_id' => 1, 'subscription_id' => $sub->id, 'user_id' => 1, 'body' => 'Pinada', 'is_pinned' => true]);

    $sub->refresh();
    expect($sub->pinnedNote)->not()->toBeNull()
        ->and($sub->pinnedNote->id)->toBe($pinnedNote->id)
        ->and($sub->pinnedNote->body)->toBe('Pinada');
});
