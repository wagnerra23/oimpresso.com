<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Modules\RecurringBilling\Http\Controllers\RecurringBillingController;
use Modules\RecurringBilling\Http\Requests\StoreAssinaturaRequest;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Repositories\SubscriptionRepository;

uses(Tests\TestCase::class);

/**
 * Wave 21 v9,75 — ativa o botão "Nova assinatura" (drawer de criação).
 *
 * Cobre o que mudou nesta onda:
 *  - StoreAssinaturaRequest aceita plan_id (nullable) + contrato required.
 *  - RecurringBillingController::store cria Subscription mapeando campos PT→DB.
 *  - RecurringBillingController::searchContacts scopa por business_id (Tier 0).
 *
 * SQLite in-memory (espelha Wave6PlanCrudTest) — migrations legadas UltimatePOS
 * usam sintaxe MySQL-only. Auth Spatie/Gate é bypassado via Gate::before.
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 (ADR 0101): NUNCA biz=4.
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite'
        || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Smoke test rodado apenas em SQLite in-memory.');
    }

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

    Schema::dropIfExists('rb_plans');
    Schema::create('rb_plans', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('name', 150);
        $t->string('slug', 80)->nullable();
        $t->string('descricao_curta', 200)->nullable();
        $t->decimal('valor', 15, 2);
        $t->string('ciclo', 20);
        $t->boolean('ativo')->default(true);
        $t->string('fiscal_type', 10)->default('none');
        $t->json('metadata')->nullable();
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

    Schema::dropIfExists('rb_subscriptions');
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
        $t->string('payment_method', 20)->nullable();
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

    // Bypass auth/Gate — qualquer authorize() passa.
    Gate::before(fn () => true);
    session(['user.business_id' => 1]);
});

afterEach(function () {
    session()->flush();
    // rb_subscriptions/rb_plans são reais-migradas; o afterEach roda mesmo em teste
    // pulado (PHPUnit 12: tearDown gated só por hasMetRequirements), então dropá-las no
    // MySQL persistente do nightly corromperia testes irmãos do módulo. DDL só em
    // sqlite :memory: (espelha o skip-guard do beforeEach).
    if (config('database.default') === 'sqlite'
        && str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        Schema::dropIfExists('rb_subscriptions');
        Schema::dropIfExists('rb_plans');
    }
});

function validateStoreAssinatura(array $payload): \Illuminate\Contracts\Validation\Validator
{
    $req = new StoreAssinaturaRequest();

    return Validator::make($payload, $req->rules(), $req->messages());
}

function validPayload(array $override = []): array
{
    return array_merge([
        'contact_id'            => 1,
        'plan_id'               => null,
        'valor'                 => 199.90,
        'ciclo'                 => 'mensal',
        'data_proxima_cobranca' => now()->addMonth()->toDateString(),
        'gateway'               => 'inter',
        'forma_pagamento'       => 'boleto',
        'descricao'             => 'Mensalidade teste',
    ], $override);
}

// ─── Validação do FormRequest ──────────────────────────────────────────

it('R-RB-WAVE21-1 — request aceita payload completo válido (com plan_id null)', function () {
    expect(validateStoreAssinatura(validPayload())->passes())->toBeTrue();
});

it('R-RB-WAVE21-2 — request aceita plan_id inteiro (campo novo da Onda 21)', function () {
    expect(validateStoreAssinatura(validPayload(['plan_id' => 7]))->passes())->toBeTrue();
});

it('R-RB-WAVE21-3 — request rejeita contact_id ausente', function () {
    $v = validateStoreAssinatura(validPayload(['contact_id' => null]));
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('contact_id'))->toBeTrue();
});

it('R-RB-WAVE21-4 — request rejeita ciclo/gateway/forma fora do enum + data no passado', function () {
    $v = validateStoreAssinatura(validPayload([
        'ciclo'                 => 'quinzenal',
        'gateway'               => 'paypal',
        'forma_pagamento'       => 'cripto',
        'data_proxima_cobranca' => now()->subDay()->toDateString(),
    ]));
    expect($v->fails())->toBeTrue()
        ->and($v->errors()->has('ciclo'))->toBeTrue()
        ->and($v->errors()->has('gateway'))->toBeTrue()
        ->and($v->errors()->has('forma_pagamento'))->toBeTrue()
        ->and($v->errors()->has('data_proxima_cobranca'))->toBeTrue();
});

// ─── store() cria Subscription ─────────────────────────────────────────

it('R-RB-WAVE21-5 — store cria Subscription biz=1 mapeando forma_pagamento→payment_method', function () {
    \App\Contact::create(['business_id' => 1, 'name' => 'Cliente Teste', 'type' => 'customer']);

    $controller = new RecurringBillingController(new SubscriptionRepository());
    $request = StoreAssinaturaRequest::create('/recurring-billing', 'POST', validPayload([
        'contact_id'      => 1,
        'forma_pagamento' => 'pix',
        'valor'           => 250.00,
        'ciclo'           => 'mensal',
    ]));
    $request->setContainer(app())->validateResolved();

    $controller->store($request);

    $sub = Subscription::query()->where('business_id', 1)->first();
    expect($sub)->not->toBeNull()
        ->and((int) $sub->contact_id)->toBe(1)
        ->and($sub->status)->toBe('active')
        ->and($sub->payment_method)->toBe('pix')
        ->and((float) ($sub->metadata['valor'] ?? 0))->toBe(250.0)
        ->and($sub->metadata['created_via'] ?? null)->toBe('recurring-billing.store');
});

// ─── searchContacts() scoping Tier 0 ───────────────────────────────────

it('R-RB-WAVE21-6 — searchContacts só retorna contatos do business da sessão', function () {
    \App\Contact::create(['business_id' => 1, 'name' => 'Larissa Costa', 'type' => 'customer']);
    \App\Contact::create(['business_id' => 99, 'name' => 'Larissa Outra Empresa', 'type' => 'customer']);

    $controller = new RecurringBillingController(new SubscriptionRepository());
    $request = \Illuminate\Http\Request::create('/recurring-billing/contacts/search', 'GET', ['q' => 'Larissa']);

    $json = $controller->searchContacts($request)->getData(true);

    expect($json['contacts'])->toHaveCount(1)
        ->and($json['contacts'][0]['name'])->toBe('Larissa Costa');
});

it('R-RB-WAVE21-7 — searchContacts ignora query < 2 chars', function () {
    \App\Contact::create(['business_id' => 1, 'name' => 'Ab', 'type' => 'customer']);

    $controller = new RecurringBillingController(new SubscriptionRepository());
    $request = \Illuminate\Http\Request::create('/recurring-billing/contacts/search', 'GET', ['q' => 'a']);

    $json = $controller->searchContacts($request)->getData(true);

    expect($json['contacts'])->toBe([]);
});

it('R-RB-WAVE21-8 — searchContacts exclui type=lead/supplier (só customer/both)', function () {
    \App\Contact::create(['business_id' => 1, 'name' => 'Lead Frio', 'type' => 'lead']);
    \App\Contact::create(['business_id' => 1, 'name' => 'Lead Ambos', 'type' => 'both']);

    $controller = new RecurringBillingController(new SubscriptionRepository());
    $request = \Illuminate\Http\Request::create('/recurring-billing/contacts/search', 'GET', ['q' => 'Lead']);

    $json = $controller->searchContacts($request)->getData(true);

    expect($json['contacts'])->toHaveCount(1)
        ->and($json['contacts'][0]['name'])->toBe('Lead Ambos');
});
