<?php

declare(strict_types=1);

/*
 * Contrato de tela — RecurringBilling/Index (editar cobrança pelo drawer).
 *
 * @covers-us US-RB-002
 *
 * UC (resources/js/Pages/RecurringBilling/Index.casos.md · CU-RB-04 do SDD §6.1):
 *   UC-RBSUB-04 — editar cobrança: 404 cross-tenant · 422 se cancelada [T0][V0]
 */

use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Events\AssinaturaAtualizada;
use Modules\RecurringBilling\Http\Controllers\RecurringBillingController;
use Modules\RecurringBilling\Http\Requests\UpdateAssinaturaRequest;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Repositories\SubscriptionRepository;
use Modules\RecurringBilling\Services\AssinaturaCobrancaService;
use Modules\RecurringBilling\Services\Boleto\BoletoService;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Wave 23 v9,75 — wiring do PUT /recurring-billing/{id} (editar cobrança).
 *
 * A LÓGICA já é testada em AtualizarCobrancaAssinaturaTest (serviço). Aqui cobre
 * só o CONTROLLER: resolve serviço, usa business_id da SESSÃO (Tier 0), mapeia
 * ok→200 / erro→http_status, e 404 cross-tenant via loadOwnedOrFail.
 *
 * SQLite in-memory (pattern Wave21). Gate bypass. biz=1 (ADR 0101).
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite'
        || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Smoke test rodado apenas em SQLite in-memory.');
    }

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

    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $t) {
        $t->increments('id');
        $t->string('username')->unique();
        $t->string('password');
        $t->integer('business_id')->nullable();
        $t->rememberToken();
        $t->softDeletes();
        $t->timestamps();
    });

    // Tabelas Spatie — o PermissionServiceProvider registra um `Gate::before` no boot
    // que consulta `permissions`, e ele roda ANTES do nosso. Sem elas, o
    // `Gate::authorize('update', $sub)` morre em "no such table: permissions".
    foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions'] as $tbl) {
        Schema::dropIfExists($tbl);
    }
    Schema::create('permissions', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
        $t->unique(['name', 'guard_name']);
    });
    Schema::create('roles', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
        $t->unique(['name', 'guard_name']);
    });
    Schema::create('model_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk_rbw23');
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk_rbw23');
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id']);
    });

    // AUTENTICAR É OBRIGATÓRIO, não opcional (fix 2026-08-03) — mesma causa do
    // Wave21NewSubscriptionTest, ver o comentário longo de lá. Resumo:
    //  1. `UpdateAssinaturaRequest::authorize()` devolve `$this->user() !== null`.
    //  2. `Gate::before(fn () => true)` tem ZERO parâmetros e, sem usuário logado, o
    //     Laravel nem invoca o callback (Gate::callbackAllowsGuests) — logo o
    //     `Gate::authorize('update', $sub)` do controller negava.
    // biz=1 sempre — NUNCA biz=4, que é cliente real (ADR 0101).
    $this->actingAs(User::forceCreate([
        'username' => 'rbw23_biz1_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => 1,
    ]));
    Gate::before(fn () => true);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Event::fake([AssinaturaAtualizada::class]);
    session(['user.business_id' => 1]);
});

afterEach(function () {
    session()->flush();
    // rb_subscriptions é real-migrada; o afterEach roda mesmo em teste pulado (PHPUnit
    // 12: tearDown gated só por hasMetRequirements), então dropá-la no MySQL persistente
    // corromperia testes irmãos do módulo. DDL só em sqlite :memory:.
    if (config('database.default') === 'sqlite'
        && str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        Schema::dropIfExists('rb_subscriptions');
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'users'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Mockery::close();
});

function makeUpdateController(): RecurringBillingController
{
    return new RecurringBillingController(new SubscriptionRepository());
}

function makeUpdateService(): AssinaturaCobrancaService
{
    return new AssinaturaCobrancaService(Mockery::mock(BoletoService::class));
}

function jsonPutRequest(array $payload): UpdateAssinaturaRequest
{
    $req = UpdateAssinaturaRequest::create('/recurring-billing/1', 'PUT', $payload);
    $req->headers->set('Accept', 'application/json');
    // setUserResolver é OBRIGATÓRIO: `Request::create()` não herda o usuário do
    // `actingAs`, então `authorize()` (`$this->user() !== null`) veria null e lançaria
    // AuthorizationException ANTES de qualquer regra — inclusive antes do abort(404)
    // cross-tenant que o R-RB-WAVE23-2 quer observar.
    $req->setUserResolver(fn () => auth()->user());
    $req->setContainer(app())->validateResolved();

    return $req;
}

// ─── Wiring ────────────────────────────────────────────────────────────

it('UC-RBSUB-04 · R-RB-WAVE23-1 — controller.update altera valor da assinatura biz=1 (local-only)', function () {
    $sub = Subscription::create([
        'business_id' => 1, 'contact_id' => 10, 'status' => 'active',
        'start_date' => '2026-01-01', 'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2026-01-01',
        'metadata' => ['valor' => 200.00, 'ciclo' => 'mensal', 'forma_pagamento' => 'pix'],
    ]);

    $resp = makeUpdateController()->update(jsonPutRequest(['valor' => 350.00]), $sub->id, makeUpdateService());
    $data = $resp->getData(true);

    expect($data['ok'])->toBeTrue();
    $sub->refresh();
    expect((float) $sub->metadata['valor'])->toBe(350.0);
});

it('UC-RBSUB-04 · R-RB-WAVE23-2 — controller.update aborta 404 cross-tenant (sub biz=99, sessão biz=1)', function () {
    $sub = Subscription::create([
        'business_id' => 99, 'contact_id' => 10, 'status' => 'active',
        'start_date' => '2026-01-01', 'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2026-01-01',
        'metadata' => ['valor' => 100.00, 'ciclo' => 'mensal', 'forma_pagamento' => 'pix'],
    ]);

    expect(fn () => makeUpdateController()->update(jsonPutRequest(['valor' => 500.00]), $sub->id, makeUpdateService()))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    $sub->refresh();
    expect((float) $sub->metadata['valor'])->toBe(100.0);
});

it('UC-RBSUB-04 · R-RB-WAVE23-3 — controller.update devolve erro do serviço (assinatura cancelada → 422)', function () {
    $sub = Subscription::create([
        'business_id' => 1, 'contact_id' => 10, 'status' => 'canceled',
        'start_date' => '2026-01-01', 'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2026-01-01', 'canceled_at' => now(),
        'metadata' => ['valor' => 120.00, 'ciclo' => 'mensal', 'forma_pagamento' => 'pix'],
    ]);

    $resp = makeUpdateController()->update(jsonPutRequest(['valor' => 999.00]), $sub->id, makeUpdateService());
    $data = $resp->getData(true);

    expect($data['ok'])->toBeFalse()
        ->and($resp->getStatusCode())->toBe(422);
    $sub->refresh();
    expect((float) $sub->metadata['valor'])->toBe(120.0);
});
