<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Events\AssinaturaAtualizada;
use Modules\RecurringBilling\Models\BoletoCredential;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Services\AssinaturaCobrancaService;
use Modules\RecurringBilling\Services\Boleto\BoletoService;

uses(Tests\TestCase::class);

/**
 * FIN-004 — Smoke Pest do atualizarCobrancaAssinatura().
 *
 * SQLite guard + manual schema (mesmo pattern de AssinaturaCobrancaServiceTest)
 * — migrations legadas UltimatePOS usam sintaxe MySQL-only.
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 (ADR 0101): NUNCA biz=4 (ROTA LIVRE PROD).
 * HTTP Asaas SEMPRE mockado via Http::fake — zero call real.
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite' || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Smoke test rodado apenas em SQLite in-memory.');
    }

    Schema::dropIfExists('activity_log');
    // Wave 10 D7 LGPD: Subscription/BoletoCredential com LogsActivity (Spatie).
    Schema::create('activity_log', function ($table) {
        $table->bigIncrements('id');
        $table->string('log_name')->nullable()->index();
        $table->text('description')->nullable();
        $table->nullableMorphs('subject', 'subject');
        $table->string('event')->nullable();
        $table->nullableMorphs('causer', 'causer');
        $table->longText('properties')->nullable();
        $table->uuid('batch_uuid')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('rb_subscriptions');
    Schema::create('rb_subscriptions', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('status', 20)->default('active');
        $table->date('start_date')->nullable();
        $table->date('next_due_date')->nullable();
        $table->date('billing_anchor_date')->nullable();
        $table->dateTime('canceled_at')->nullable();
        $table->dateTime('paused_at')->nullable();
        $table->unsignedInteger('conta_bancaria_id')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::dropIfExists('rb_boleto_credentials');
    Schema::create('rb_boleto_credentials', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->unsignedBigInteger('conta_bancaria_id')->nullable();
        $table->string('banco', 20);
        $table->string('ambiente', 20)->default('production');
        $table->boolean('ativo')->default(true);
        $table->json('config_json')->nullable();
        $table->string('nome_display')->nullable();
        $table->timestamps();
    });

    Event::fake([AssinaturaAtualizada::class]);
});

afterEach(function () {
    Schema::dropIfExists('rb_subscriptions');
    Schema::dropIfExists('rb_boleto_credentials');
    Schema::dropIfExists('activity_log');
});

/**
 * Cenario 1: Asaas mockado — atualiza valor refletido no gateway + DB local + evento.
 */
it('atualiza valor e ciclo, reflete no Asaas mock e dispara evento AssinaturaAtualizada', function () {
    Http::fake([
        '*/subscriptions/sub_abc*' => Http::response(['id' => 'sub_abc', 'value' => 350.00, 'cycle' => 'MONTHLY'], 200),
    ]);

    BoletoCredential::create([
        'business_id' => 1,
        'banco' => 'asaas',
        'ambiente' => 'sandbox',
        'ativo' => true,
        'config_json' => ['api_key' => 'test_fake_key'],
    ]);

    $subscription = Subscription::create([
        'business_id' => 1,
        'plan_id' => null,
        'contact_id' => 99,
        'status' => 'active',
        'start_date' => '2026-01-01',
        'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2026-01-01',
        'metadata' => [
            'valor' => 200.00,
            'ciclo' => 'mensal',
            'forma_pagamento' => 'boleto',
            'gateway' => 'asaas',
            'gateway_subscription_ref' => 'sub_abc',
        ],
    ]);

    $boletos = Mockery::mock(BoletoService::class);
    $service = new AssinaturaCobrancaService($boletos);

    $result = $service->atualizarCobrancaAssinatura(1, $subscription->id, [
        'valor' => 350.00,
        'ciclo' => 'mensal',
    ]);

    expect($result['ok'])->toBeTrue();
    expect($result['gateway_call'])->toBeTrue();

    $subscription->refresh();
    expect((float) $subscription->metadata['valor'])->toBe(350.0);

    Event::assertDispatched(AssinaturaAtualizada::class, function ($e) use ($subscription) {
        return $e->businessId === 1
            && $e->subscriptionId === $subscription->id
            && $e->mudouValor === true
            && $e->gatewayCall === true;
    });

    Http::assertSent(fn ($req) => str_contains($req->url(), '/subscriptions/sub_abc'));
});

/**
 * Cenario 2 (multi-tenant): assinatura de outro business retorna 404 mesmo sendo superadmin.
 */
it('retorna 404 quando assinatura nao pertence ao business solicitado', function () {
    $subscription = Subscription::create([
        'business_id' => 2, // OUTRO business
        'plan_id' => null,
        'contact_id' => 50,
        'status' => 'active',
        'start_date' => '2026-01-01',
        'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2026-01-01',
        'metadata' => ['valor' => 100.00, 'ciclo' => 'mensal', 'forma_pagamento' => 'boleto'],
    ]);

    $boletos = Mockery::mock(BoletoService::class);
    $service = new AssinaturaCobrancaService($boletos);

    // Tenta acessar a assinatura de biz=2 passando biz=1
    $result = $service->atualizarCobrancaAssinatura(1, $subscription->id, [
        'valor' => 500.00,
    ]);

    expect($result['ok'])->toBeFalse();
    expect($result['http_status'])->toBe(404);

    // Garante que o registro do outro business NAO foi alterado
    $subscription->refresh();
    expect((float) $subscription->metadata['valor'])->toBe(100.0);

    Event::assertNotDispatched(AssinaturaAtualizada::class);
});

/**
 * Cenario 3 (idempotencia + sem-gateway): payload identico ao atual + sem gateway_ref
 * retorna skipped sem chamar HTTP.
 */
it('retorna idempotente quando nada muda e nao chama gateway', function () {
    Http::fake(); // qualquer call HTTP marcaria erro

    $subscription = Subscription::create([
        'business_id' => 1,
        'plan_id' => null,
        'contact_id' => 10,
        'status' => 'active',
        'start_date' => '2026-01-01',
        'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2026-01-01',
        'metadata' => [
            'valor' => 150.00,
            'ciclo' => 'mensal',
            'forma_pagamento' => 'pix',
            // sem gateway_ref — local-only
        ],
    ]);

    $boletos = Mockery::mock(BoletoService::class);
    $service = new AssinaturaCobrancaService($boletos);

    $result = $service->atualizarCobrancaAssinatura(1, $subscription->id, [
        'valor' => 150.00,
        'ciclo' => 'mensal',
        'forma_pagamento' => 'pix',
    ]);

    expect($result['ok'])->toBeTrue();
    expect($result['skipped'] ?? null)->toBe('no_changes');
    expect($result['gateway_call'])->toBeFalse();

    Http::assertNothingSent();
    Event::assertNotDispatched(AssinaturaAtualizada::class);
});
