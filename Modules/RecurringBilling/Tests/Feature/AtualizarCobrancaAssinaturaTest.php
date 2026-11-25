<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Events\AssinaturaAtualizada;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Services\AssinaturaCobrancaService;
use Modules\RecurringBilling\Services\CobrancaNoGateway;

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

    Event::fake([AssinaturaAtualizada::class]);
});

afterEach(function () {
    // rb_* são reais-migradas; o afterEach roda mesmo em teste pulado (PHPUnit 12:
    // tearDown gated só por hasMetRequirements), então dropá-las no MySQL persistente
    // corromperia testes irmãos do módulo. DDL só em sqlite.
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('rb_subscriptions');
    }
});

/**
 * Cenario 1: Asaas mockado — atualiza valor refletido no gateway + DB local + evento.
 */
it('atualiza valor e ciclo, reflete no Asaas mock e dispara evento AssinaturaAtualizada', function () {
    Http::fake([
        '*/subscriptions/sub_abc*' => Http::response(['id' => 'sub_abc', 'value' => 350.00, 'cycle' => 'MONTHLY'], 200),
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

    // Credencial do PaymentGateway (onda 1 da saída de rb_boleto_credentials, 2026-11-24).
    // O cast `encrypted:array` entrega o api_key em claro; é ele que vai no header.
    $cred = new PaymentGatewayCredential();
    $cred->forceFill([
        'business_id' => 1,
        'gateway_key' => 'asaas',
        'ambiente' => 'sandbox',
        'ativo' => true,
        'config_json' => ['api_key' => 'test_fake_key'],
    ]);

    $boletos = Mockery::mock(CobrancaNoGateway::class);
    $boletos->shouldReceive('credencial')->once()->with(1, 'asaas')->andReturn($cred);
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

    Http::assertSent(fn ($req) => str_starts_with($req->url(), 'https://sandbox.asaas.com/api/v3/subscriptions/sub_abc')
        && $req->header('access_token') === ['test_fake_key']);
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

    $boletos = Mockery::mock(CobrancaNoGateway::class);
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

    $boletos = Mockery::mock(CobrancaNoGateway::class);
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

/**
 * Cenario 4: sem credencial Asaas no PaymentGateway — nenhuma chamada sai e o motivo volta
 * como aviso. Antes o reajuste lia rb_boleto_credentials (onda 1, 2026-11-24).
 */
it('sem credencial Asaas no PaymentGateway nao chama o gateway e devolve o aviso', function () {
    Http::fake();

    $subscription = Subscription::create([
        'business_id' => 1,
        'plan_id' => null,
        'contact_id' => 11,
        'status' => 'active',
        'start_date' => '2026-01-01',
        'next_due_date' => '2026-06-01',
        'billing_anchor_date' => '2026-01-01',
        'metadata' => [
            'valor' => 200.00,
            'ciclo' => 'mensal',
            'forma_pagamento' => 'boleto',
            'gateway' => 'asaas',
            'gateway_subscription_ref' => 'sub_sem_cred',
        ],
    ]);

    $boletos = Mockery::mock(CobrancaNoGateway::class);
    $boletos->shouldReceive('credencial')->once()->with(1, 'asaas')
        ->andThrow(new CredentialMisconfiguredException('sem credencial'));
    $service = new AssinaturaCobrancaService($boletos);

    $result = $service->atualizarCobrancaAssinatura(1, $subscription->id, ['valor' => 260.00]);

    expect($result['gateway_call'])->toBeFalse()
        ->and($result['gateway_warning'] ?? null)->toBe('Sem credencial Asaas configurada para o business.');
    Http::assertNothingSent();
});
