<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Models\ChargeAttempt;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;

uses(Tests\TestCase::class);

/**
 * US-RB-043 foundation · Plan / Subscription / Invoice / ChargeAttempt.
 *
 * Tests sem RefreshDatabase (migrations UltimatePOS quebram SQLite —
 * ver BoletoServiceTest pra contexto). Cria as 4 tabelas + tabelas-FK
 * legadas (contacts, fin_contas_bancarias) manualmente.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['rb_charge_attempts', 'rb_invoices', 'rb_subscriptions', 'rb_plans', 'fin_contas_bancarias', 'contacts'] as $t) {
        Schema::dropIfExists($t);
    }

    // Stubs das tabelas legadas com PK int unsigned (ADR tech/0008)
    Schema::create('contacts', function ($table) {
        $table->increments('id'); // int unsigned
        $table->unsignedInteger('business_id')->index();
        $table->string('name')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });
    Schema::create('fin_contas_bancarias', function ($table) {
        $table->increments('id'); // int unsigned
        $table->unsignedInteger('business_id')->index();
        $table->timestamps();
    });

    // 4 tabelas RB (mesmas migrations 001000..001003)
    Schema::create('rb_plans', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->string('name', 150);
        $table->string('slug', 80);
        $table->text('description')->nullable();
        $table->decimal('valor', 15, 2);
        $table->string('ciclo', 20);
        $table->unsignedSmallInteger('ciclo_dias')->nullable();
        $table->unsignedSmallInteger('trial_days')->default(0);
        $table->boolean('ativo')->default(true)->index();
        $table->json('metadata')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['business_id', 'slug']);
    });
    Schema::create('rb_subscriptions', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->foreignId('plan_id')->constrained('rb_plans');
        $table->unsignedInteger('contact_id');
        $table->string('status', 20)->default('active')->index();
        $table->date('start_date');
        $table->date('next_due_date')->index();
        $table->date('billing_anchor_date');
        $table->dateTime('canceled_at')->nullable();
        $table->dateTime('paused_at')->nullable();
        $table->unsignedInteger('conta_bancaria_id')->nullable();
        $table->unsignedSmallInteger('total_paid_cached')->default(0);
        $table->unsignedSmallInteger('failed_count_cached')->default(0);
        $table->decimal('total_revenue_cached', 14, 2)->default(0);
        $table->date('paused_until')->nullable();
        $table->string('churn_reason', 64)->nullable();
        $table->string('contact_phone_cached', 32)->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('rb_invoices', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->foreignId('subscription_id')->nullable()->constrained('rb_subscriptions');
        $table->unsignedInteger('contact_id')->index();
        $table->string('numero_documento', 50);
        $table->decimal('valor', 15, 2);
        $table->string('status', 20)->default('open')->index();
        $table->date('vencimento')->index();
        $table->dateTime('pago_em')->nullable();
        $table->string('gateway', 30)->nullable();
        $table->string('gateway_ref', 100)->nullable();
        $table->unsignedInteger('conta_bancaria_id')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['business_id', 'numero_documento']);
    });
    Schema::create('rb_charge_attempts', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->foreignId('invoice_id')->constrained('rb_invoices');
        $table->string('gateway', 30)->index();
        $table->unsignedSmallInteger('attempt_n');
        $table->string('status', 20)->index();
        $table->json('request_json')->nullable();
        $table->json('response_json')->nullable();
        $table->string('error_code', 50)->nullable();
        $table->text('error_message')->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->unique(['invoice_id', 'attempt_n']);
    });
});

afterEach(function () {
    // contacts/fin_contas_bancarias/rb_* são reais-migradas; o afterEach roda mesmo em
    // teste pulado (PHPUnit 12: tearDown gated só por hasMetRequirements), então dropá-las
    // no MySQL persistente corromperia testes irmãos do módulo. DDL só em sqlite.
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (['rb_charge_attempts', 'rb_invoices', 'rb_subscriptions', 'rb_plans', 'fin_contas_bancarias', 'contacts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
});

it('cria Plan com fillable + casts corretos', function () {
    $plan = Plan::create([
        'business_id' => 1,
        'name'        => 'Mensalidade Pro',
        'slug'        => 'mensalidade-pro',
        'valor'       => 199.90,
        'ciclo'       => 'monthly',
        'trial_days'  => 7,
        'ativo'       => true,
        'metadata'    => ['feature_flag' => 'beta'],
    ]);

    expect($plan->id)->toBeInt()
        ->and((float) $plan->valor)->toBe(199.90)
        ->and($plan->ativo)->toBeTrue()
        ->and($plan->trial_days)->toBe(7)
        ->and($plan->metadata)->toBe(['feature_flag' => 'beta']);
});

it('Plan::scopeAtivos() filtra inativos', function () {
    Plan::create(['business_id' => 1, 'name' => 'A', 'slug' => 'a', 'valor' => 10, 'ciclo' => 'monthly', 'ativo' => true]);
    Plan::create(['business_id' => 1, 'name' => 'B', 'slug' => 'b', 'valor' => 20, 'ciclo' => 'monthly', 'ativo' => false]);

    expect(Plan::ativos()->count())->toBe(1);
});

it('UNIQUE(business_id, slug) impede duplicação no mesmo tenant', function () {
    Plan::create(['business_id' => 1, 'name' => 'Plano', 'slug' => 'plano', 'valor' => 10, 'ciclo' => 'monthly']);

    expect(fn () => Plan::create([
        'business_id' => 1, 'name' => 'Outro nome', 'slug' => 'plano',
        'valor' => 10, 'ciclo' => 'monthly',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('mesmo slug em business diferente é OK (multi-tenant)', function () {
    Plan::create(['business_id' => 1, 'name' => 'Plano', 'slug' => 'plano', 'valor' => 10, 'ciclo' => 'monthly']);
    Plan::create(['business_id' => 5, 'name' => 'Plano', 'slug' => 'plano', 'valor' => 10, 'ciclo' => 'monthly']);

    expect(Plan::count())->toBe(2);
});

it('cria Subscription vinculada a Plan + Contact com relacionamentos', function () {
    \DB::table('contacts')->insert(['id' => 1, 'business_id' => 1, 'name' => 'Cliente A', 'created_at' => now(), 'updated_at' => now()]);

    $plan = Plan::create([
        'business_id' => 1, 'name' => 'Pro', 'slug' => 'pro',
        'valor' => 199.90, 'ciclo' => 'monthly',
    ]);

    $sub = Subscription::create([
        'business_id'         => 1,
        'plan_id'             => $plan->id,
        'contact_id'          => 1,
        'status'              => 'active',
        'start_date'          => '2026-05-06',
        'next_due_date'       => '2026-06-06',
        'billing_anchor_date' => '2026-05-06',
    ]);

    expect($sub->plan->slug)->toBe('pro')
        ->and($sub->isAtiva())->toBeTrue();
});

it('Invoice rastreia ChargeAttempts com unique (invoice_id, attempt_n)', function () {
    \DB::table('contacts')->insert(['id' => 1, 'business_id' => 1, 'name' => 'X', 'created_at' => now(), 'updated_at' => now()]);
    $plan = Plan::create(['business_id' => 1, 'name' => 'P', 'slug' => 'p', 'valor' => 100, 'ciclo' => 'monthly']);
    $sub  = Subscription::create([
        'business_id' => 1, 'plan_id' => $plan->id, 'contact_id' => 1,
        'status' => 'active', 'start_date' => '2026-05-06',
        'next_due_date' => '2026-06-06', 'billing_anchor_date' => '2026-05-06',
    ]);
    $inv = Invoice::create([
        'business_id' => 1, 'subscription_id' => $sub->id, 'contact_id' => 1,
        'numero_documento' => 'INV-001', 'valor' => 100, 'status' => 'open',
        'vencimento' => '2026-06-06',
    ]);

    ChargeAttempt::create([
        'business_id' => 1, 'invoice_id' => $inv->id, 'gateway' => 'asaas',
        'attempt_n' => 1, 'status' => 'sent',
    ]);
    ChargeAttempt::create([
        'business_id' => 1, 'invoice_id' => $inv->id, 'gateway' => 'asaas',
        'attempt_n' => 2, 'status' => 'succeeded', 'response_json' => ['id' => 'pay_x'],
    ]);

    expect($inv->chargeAttempts)->toHaveCount(2);

    expect(fn () => ChargeAttempt::create([
        'business_id' => 1, 'invoice_id' => $inv->id, 'gateway' => 'asaas',
        'attempt_n' => 1, 'status' => 'failed',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('Invoice::scopeVencidas() pega open com vencimento passado', function () {
    \DB::table('contacts')->insert(['id' => 1, 'business_id' => 1, 'name' => 'X', 'created_at' => now(), 'updated_at' => now()]);
    $plan = Plan::create(['business_id' => 1, 'name' => 'P', 'slug' => 'p', 'valor' => 100, 'ciclo' => 'monthly']);

    Invoice::create([
        'business_id' => 1, 'subscription_id' => null, 'contact_id' => 1,
        'numero_documento' => 'INV-OK', 'valor' => 100, 'status' => 'open',
        'vencimento' => now()->addDays(5)->toDateString(),
    ]);
    Invoice::create([
        'business_id' => 1, 'subscription_id' => null, 'contact_id' => 1,
        'numero_documento' => 'INV-VENCIDA', 'valor' => 100, 'status' => 'open',
        'vencimento' => now()->subDays(3)->toDateString(),
    ]);
    Invoice::create([
        'business_id' => 1, 'subscription_id' => null, 'contact_id' => 1,
        'numero_documento' => 'INV-PAGA', 'valor' => 100, 'status' => 'paid',
        'vencimento' => now()->subDays(10)->toDateString(),
    ]);

    expect(Invoice::vencidas()->pluck('numero_documento')->all())
        ->toBe(['INV-VENCIDA']);
});

it('ChargeAttempt é append-only (sem updated_at)', function () {
    \DB::table('contacts')->insert(['id' => 1, 'business_id' => 1, 'name' => 'X', 'created_at' => now(), 'updated_at' => now()]);
    $plan = Plan::create(['business_id' => 1, 'name' => 'P', 'slug' => 'p', 'valor' => 100, 'ciclo' => 'monthly']);
    $inv = Invoice::create([
        'business_id' => 1, 'subscription_id' => null, 'contact_id' => 1,
        'numero_documento' => 'X', 'valor' => 100, 'status' => 'open',
        'vencimento' => '2026-06-06',
    ]);

    $attempt = ChargeAttempt::create([
        'business_id' => 1, 'invoice_id' => $inv->id, 'gateway' => 'inter',
        'attempt_n' => 1, 'status' => 'pending',
    ]);

    // ::UPDATED_AT é null → no updated_at column
    expect(ChargeAttempt::UPDATED_AT)->toBeNull();
});
