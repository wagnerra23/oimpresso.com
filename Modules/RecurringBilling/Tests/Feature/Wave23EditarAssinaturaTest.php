<?php

declare(strict_types=1);

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

    Gate::before(fn () => true);
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
    }
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
    $req->setContainer(app())->validateResolved();

    return $req;
}

// ─── Wiring ────────────────────────────────────────────────────────────

it('R-RB-WAVE23-1 — controller.update altera valor da assinatura biz=1 (local-only)', function () {
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

it('R-RB-WAVE23-2 — controller.update aborta 404 cross-tenant (sub biz=99, sessão biz=1)', function () {
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

it('R-RB-WAVE23-3 — controller.update devolve erro do serviço (assinatura cancelada → 422)', function () {
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
