<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Models\SubscriptionEvent;
use Modules\RecurringBilling\Models\SubscriptionFavorite;
use Modules\RecurringBilling\Models\SubscriptionNote;

uses(Tests\TestCase::class);

/**
 * Onda 1 v9,75 schema · valida 3 tabelas novas (notes/favorites/events) +
 * 12 colunas aditivas em subs/plans + scope multi-tenant + UNIQUE constraints.
 *
 * Padrão SQLite-stub (mesmo DomainModelsTest) — UPos legacy quebra com migrations.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach ([
        'rb_subscription_events', 'rb_subscription_favorites', 'rb_subscription_notes',
        'rb_subscriptions', 'rb_plans', 'users', 'contacts',
        'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'roles', 'permissions',
    ] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('contacts', function ($t) {
        $t->increments('id');
        $t->unsignedInteger('business_id')->index();
        $t->softDeletes();
        $t->timestamps();
    });
    // Auth scaffold mínimo — ScopeByBusiness (ADR 0093) só filtra com auth()->check()
    // true; o Gate::before chama $user->can(...) → Spatie lê estas tabelas (vazias = user comum).
    Schema::create('permissions', function ($t) {
        $t->bigIncrements('id'); $t->string('name'); $t->string('guard_name')->default('web'); $t->timestamps();
    });
    Schema::create('roles', function ($t) {
        $t->bigIncrements('id'); $t->string('name'); $t->string('guard_name')->default('web'); $t->timestamps();
    });
    Schema::create('role_has_permissions', function ($t) {
        $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id');
    });
    Schema::create('model_has_permissions', function ($t) {
        $t->unsignedBigInteger('permission_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id');
    });
    Schema::create('model_has_roles', function ($t) {
        $t->unsignedBigInteger('role_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id');
    });
    Schema::create('users', function ($t) {
        $t->increments('id');
        $t->unsignedInteger('business_id')->nullable();
        $t->string('email', 100)->nullable();
        $t->string('username')->nullable();
        $t->string('password')->nullable();
        $t->softDeletes();
        $t->timestamps();
    });
    Schema::create('rb_plans', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('name', 150);
        $t->string('slug', 80);
        $t->text('description')->nullable();
        $t->string('descricao_curta', 200)->nullable();
        $t->decimal('valor', 15, 2);
        $t->string('ciclo', 20);
        $t->unsignedSmallInteger('ciclo_dias')->nullable();
        $t->unsignedSmallInteger('trial_days')->default(0);
        $t->boolean('ativo')->default(true);
        $t->string('fiscal_type', 10)->default('none');
        $t->string('fiscal_cfop', 8)->nullable();
        $t->string('fiscal_servico', 8)->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
        $t->unique(['business_id', 'slug']);
    });
    Schema::create('rb_subscriptions', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->foreignId('plan_id')->constrained('rb_plans');
        $t->unsignedInteger('contact_id');
        $t->string('status', 20)->default('active');
        $t->date('start_date');
        $t->date('next_due_date');
        $t->date('billing_anchor_date');
        $t->dateTime('canceled_at')->nullable();
        $t->dateTime('paused_at')->nullable();
        $t->unsignedInteger('conta_bancaria_id')->nullable();
        $t->string('payment_method', 10)->nullable();
        $t->unsignedBigInteger('last_jobsheet_id')->nullable();
        $t->unsignedSmallInteger('total_paid_cached')->default(0);
        $t->unsignedSmallInteger('failed_count_cached')->default(0);
        $t->decimal('total_revenue_cached', 14, 2)->default(0);
        $t->date('paused_until')->nullable();
        $t->string('churn_reason', 64)->nullable();
        $t->string('contact_phone_cached', 32)->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('rb_subscription_notes', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->foreignId('subscription_id')->constrained('rb_subscriptions');
        $t->unsignedInteger('user_id');
        $t->text('body');
        $t->boolean('is_pinned')->default(false);
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('rb_subscription_favorites', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->foreignId('subscription_id')->constrained('rb_subscriptions');
        $t->unsignedInteger('user_id');
        $t->timestamp('created_at')->useCurrent();
        $t->unique(['user_id', 'subscription_id']);
    });
    Schema::create('rb_subscription_events', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->foreignId('subscription_id')->constrained('rb_subscriptions');
        $t->string('kind', 20);
        $t->string('by_actor', 64);
        $t->text('body');
        $t->dateTime('occurred_at');
        $t->timestamps();
    });

    \DB::table('users')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    \DB::table('contacts')->insert(['id' => 1, 'business_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
});

afterEach(function () {
    // contacts/users + rb_* são reais-migradas; o afterEach roda mesmo em teste pulado
    // (PHPUnit 12: tearDown gated só por hasMetRequirements), então dropá-las no MySQL
    // persistente corromperia testes irmãos do módulo. DDL só em sqlite.
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach ([
            'rb_subscription_events', 'rb_subscription_favorites', 'rb_subscription_notes',
            'rb_subscriptions', 'rb_plans', 'users', 'contacts',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
});

function makeV975Sub(int $bizId = 1, ?string $method = null): Subscription
{
    $plan = Plan::create([
        'business_id' => $bizId, 'name' => 'P', 'slug' => "p-{$bizId}",
        'valor' => 100, 'ciclo' => 'monthly',
        'fiscal_type' => 'nfe', 'fiscal_cfop' => '5102',
    ]);
    return Subscription::create([
        'business_id'         => $bizId,
        'plan_id'             => $plan->id,
        'contact_id'          => 1,
        'status'              => 'active',
        'start_date'          => '2026-05-01',
        'next_due_date'       => '2026-06-01',
        'billing_anchor_date' => '2026-05-01',
        'payment_method'      => $method,
    ]);
}

it('Plan aceita campos fiscais v9,75 (fiscal_type + cfop + servico)', function () {
    $plan = Plan::create([
        'business_id' => 1, 'name' => 'Fachada', 'slug' => 'fachada',
        'valor' => 1620, 'ciclo' => 'monthly',
        'descricao_curta' => 'Fachada ACM + faixa promocional 30 dias',
        'fiscal_type' => 'nfse', 'fiscal_servico' => '01.07',
    ]);

    expect($plan->fiscal_type)->toBe('nfse')
        ->and($plan->fiscal_servico)->toBe('01.07')
        ->and($plan->descricao_curta)->toContain('Fachada ACM');
});

it('Subscription aceita payment_method + paused_until + churn_reason + cached cols', function () {
    $sub = makeV975Sub(1, 'pix');
    $sub->total_paid_cached = 14;
    $sub->failed_count_cached = 0;
    $sub->total_revenue_cached = 6720;
    $sub->paused_until = '2026-07-01';
    $sub->churn_reason = 'preço';
    $sub->contact_phone_cached = '19 99876-1234';
    $sub->save();

    $sub->refresh();
    expect($sub->payment_method)->toBe('pix')
        ->and($sub->total_paid_cached)->toBe(14)
        ->and((float) $sub->total_revenue_cached)->toBe(6720.00)
        ->and($sub->paused_until->format('Y-m-d'))->toBe('2026-07-01')
        ->and($sub->churn_reason)->toBe('preço');
});

it('SubscriptionNote cria + is_pinned cast bool + scope pinned()', function () {
    $sub = makeV975Sub();
    SubscriptionNote::create([
        'business_id' => 1, 'subscription_id' => $sub->id, 'user_id' => 1,
        'body' => 'Nota normal', 'is_pinned' => false,
    ]);
    SubscriptionNote::create([
        'business_id' => 1, 'subscription_id' => $sub->id, 'user_id' => 1,
        'body' => '5 lojas · KV diferente', 'is_pinned' => true,
    ]);

    expect(SubscriptionNote::count())->toBe(2)
        ->and(SubscriptionNote::pinned()->count())->toBe(1)
        ->and(SubscriptionNote::pinned()->first()->is_pinned)->toBeTrue();
});

it('SubscriptionFavorite UNIQUE(user_id, subscription_id) impede duplicação', function () {
    $sub = makeV975Sub();
    SubscriptionFavorite::create(['business_id' => 1, 'subscription_id' => $sub->id, 'user_id' => 1]);

    expect(fn () => SubscriptionFavorite::create([
        'business_id' => 1, 'subscription_id' => $sub->id, 'user_id' => 1,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('SubscriptionEvent registra kind canônico + ordena por occurred_at desc (scope recent)', function () {
    $sub = makeV975Sub();
    SubscriptionEvent::create([
        'business_id' => 1, 'subscription_id' => $sub->id,
        'kind' => SubscriptionEvent::KIND_CREATE, 'by_actor' => 'sistema',
        'body' => 'Assinatura criada', 'occurred_at' => '2026-05-01 10:00',
    ]);
    SubscriptionEvent::create([
        'business_id' => 1, 'subscription_id' => $sub->id,
        'kind' => SubscriptionEvent::KIND_CHARGE, 'by_actor' => 'sistema',
        'body' => 'Cobrança paga R$ [redacted Tier 0]', 'occurred_at' => '2026-05-02 10:00',
    ]);

    $recent = SubscriptionEvent::recent(5)->get();

    expect($recent)->toHaveCount(2)
        ->and($recent->first()->kind)->toBe(SubscriptionEvent::KIND_CHARGE);
});

it('multi-tenant: biz=99 não enxerga notes/favorites/events de biz=1 via HasBusinessScope', function () {
    $subA = makeV975Sub(1);
    $subB = makeV975Sub(99);

    SubscriptionNote::create(['business_id' => 1, 'subscription_id' => $subA->id, 'user_id' => 1, 'body' => 'a']);
    SubscriptionNote::create(['business_id' => 99, 'subscription_id' => $subB->id, 'user_id' => 1, 'body' => 'b']);
    SubscriptionFavorite::create(['business_id' => 1, 'subscription_id' => $subA->id, 'user_id' => 1]);
    SubscriptionFavorite::create(['business_id' => 99, 'subscription_id' => $subB->id, 'user_id' => 1]);
    SubscriptionEvent::create(['business_id' => 1, 'subscription_id' => $subA->id, 'kind' => 'note', 'by_actor' => 'x', 'body' => 'a', 'occurred_at' => now()]);
    SubscriptionEvent::create(['business_id' => 99, 'subscription_id' => $subB->id, 'kind' => 'note', 'by_actor' => 'x', 'body' => 'b', 'occurred_at' => now()]);

    // Engaja ScopeByBusiness (ADR 0093): precisa auth()->check() + session('user.business_id').
    $user = \App\User::create(['business_id' => 1, 'email' => 'biz1@rb.test', 'username' => 'biz1-rb']);
    $this->actingAs($user);
    session(['user' => ['business_id' => 1]]);

    expect(SubscriptionNote::count())->toBe(1)
        ->and(SubscriptionFavorite::count())->toBe(1)
        ->and(SubscriptionEvent::count())->toBe(1);
});
