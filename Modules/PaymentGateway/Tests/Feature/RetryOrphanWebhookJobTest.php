<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Jobs\RetryOrphanWebhookJob;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;

uses(Tests\TestCase::class);

/**
 * Setup: cria SOMENTE `cobrancas` + `gateway_webhook_events` no SQLite in-memory.
 *
 * Por que não RefreshDatabase: migrations do projeto contém ALTER TABLE ... MODIFY
 * COLUMN ENUM (MySQL-only) — SQLite barra. Pattern já adotado em PagarmeDriverTest.
 */
function setupOrphanWebhookSchema(): void
{
    if (! Schema::hasTable('cobrancas')) {
        Schema::create('cobrancas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('payment_gateway_credential_id')->nullable();
            $table->string('gateway_external_id')->nullable();
            $table->string('tipo', 20);
            $table->string('status', 20);
            $table->bigInteger('valor_centavos');
            $table->bigInteger('valor_pago_centavos')->nullable();
            $table->date('vencimento')->nullable();
            $table->timestamp('paga_em')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('payer_cpf_cnpj')->nullable();
            $table->string('payer_name')->nullable();
            $table->string('payer_email')->nullable();
            $table->string('descricao')->nullable();
            $table->string('idempotency_key');
            $table->string('origem_type')->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->text('linha_digitavel')->nullable();
            $table->text('codigo_barras')->nullable();
            $table->text('pix_emv')->nullable();
            $table->string('pix_qr_code_path')->nullable();
            $table->string('boleto_pdf_url')->nullable();
            $table->string('nosso_numero')->nullable();
            $table->string('forma_pagamento')->nullable();
            $table->json('payload_gateway')->nullable();
            $table->timestamps();
        });
    }

    // Cobranca usa LogsActivity (Spatie) — precisa de activity_log table
    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->string('event')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('gateway_webhook_events')) {
        Schema::create('gateway_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('payment_gateway_credential_id')->nullable();
            $table->string('gateway_key', 20)->index();
            $table->string('evento', 60)->index();
            $table->string('gateway_event_id', 191);
            $table->unsignedBigInteger('cobranca_id')->nullable()->index();
            $table->json('payload');
            $table->boolean('signature_valid')->default(false);
            $table->timestamp('processed_at')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'gateway_key', 'gateway_event_id'], 'gw_wh_biz_key_extid_unique');
        });
    }
}

function teardownOrphanWebhookSchema(): void
{
    Schema::dropIfExists('gateway_webhook_events');
    Schema::dropIfExists('cobrancas');
    Schema::dropIfExists('activity_log');
}

/**
 * ADR 0170 Onda 4e — Cobertura RetryOrphanWebhookJob (race condition retry).
 *
 * 3 GUARDs canônicos:
 *   1. Job re-dispatcha CobrancaPaga quando órfão tem cobranca_id válido + evento paid
 *   2. Job pula evento ainda órfão (cobranca_id NULL ou Cobranca não encontrada)
 *      → log warn + processed_at PERMANECE NULL (próximo run tenta)
 *   3. Job ignora evento muito antigo (> 24h) — fora da janela
 *
 * Pattern ADR 0093: business_id explícito por evento; withoutGlobalScopes() consciente.
 * Pattern ADR 0101: testes biz=1, NUNCA biz=4 cliente real.
 */

beforeEach(function () {
    setupOrphanWebhookSchema();
    session(['business.id' => 1]);
});

afterEach(function () {
    teardownOrphanWebhookSchema();
});

it('GUARD 1: re-dispatcha CobrancaPaga quando órfão tem cobranca_id + evento paid', function () {
    Event::fake([CobrancaPaga::class]);

    $cobranca = Cobranca::query()->create([
        'business_id' => 1,
        'gateway_external_id' => 'tx-orphan-001',
        'tipo' => 'pix_cob',
        'status' => 'emitida',
        'valor_centavos' => 15000,
        'valor_pago_centavos' => 15000,
        'paga_em' => now()->subMinutes(30),
        'forma_pagamento' => 'pix',
        'idempotency_key' => 'orphan-test-001',
        'payload_gateway' => [],
    ]);

    // Evento órfão: criado há 2h (janela 1h..24h), processed_at NULL, evento "paid"
    $orphan = GatewayWebhookEvent::query()->create([
        'business_id' => 1,
        'gateway_key' => 'inter',
        'evento' => 'cob.paid',
        'gateway_event_id' => 'evt-orphan-001',
        'cobranca_id' => $cobranca->id,
        'payload' => ['raw' => 'paid'],
        'signature_valid' => true,
    ]);
    // backdate created_at pra fora da janela "espera 1h"
    $orphan->created_at = now()->subHours(2);
    $orphan->saveQuietly();

    (new RetryOrphanWebhookJob())->handle();

    $orphan->refresh();
    expect($orphan->processed_at)->not->toBeNull();
    expect($orphan->error_message)->toBeNull();

    Event::assertDispatched(CobrancaPaga::class, function (CobrancaPaga $e) use ($cobranca) {
        return $e->cobrancaId === $cobranca->id
            && $e->businessId === 1
            && $e->formaPagamento === 'pix'
            && $e->valorPagoCentavos === 15000;
    });
});

it('GUARD 2a: pula evento órfão com cobranca_id NULL — processed_at PERMANECE NULL', function () {
    Event::fake([CobrancaPaga::class]);

    $orphan = GatewayWebhookEvent::query()->create([
        'business_id' => 1,
        'gateway_key' => 'asaas',
        'evento' => 'PAYMENT_RECEIVED',
        'gateway_event_id' => 'evt-still-orphan-001',
        'cobranca_id' => null,
        'payload' => ['raw' => 'received'],
        'signature_valid' => true,
    ]);
    $orphan->created_at = now()->subHours(3);
    $orphan->saveQuietly();

    (new RetryOrphanWebhookJob())->handle();

    $orphan->refresh();
    expect($orphan->processed_at)->toBeNull(); // próximo run tenta de novo
    expect($orphan->error_message)->toContain('still_orphan');
    expect($orphan->error_message)->toContain('cobranca_id_null');

    Event::assertNotDispatched(CobrancaPaga::class);
});

it('GUARD 2b: pula órfão quando Cobranca não encontrada — processed_at PERMANECE NULL', function () {
    Event::fake([CobrancaPaga::class]);

    $orphan = GatewayWebhookEvent::query()->create([
        'business_id' => 1,
        'gateway_key' => 'c6',
        'evento' => 'PAYMENT_OK',
        'gateway_event_id' => 'evt-cobranca-deleted',
        'cobranca_id' => 99999, // não existe
        'payload' => ['raw' => 'ok'],
        'signature_valid' => true,
    ]);
    $orphan->created_at = now()->subHours(4);
    $orphan->saveQuietly();

    (new RetryOrphanWebhookJob())->handle();

    $orphan->refresh();
    expect($orphan->processed_at)->toBeNull();
    expect($orphan->error_message)->toContain('still_orphan');
    expect($orphan->error_message)->toContain('cobranca_not_found');

    Event::assertNotDispatched(CobrancaPaga::class);
});

it('GUARD 3: ignora evento muito antigo (> 24h) — fora da janela', function () {
    Event::fake([CobrancaPaga::class]);

    $cobranca = Cobranca::query()->create([
        'business_id' => 1,
        'gateway_external_id' => 'tx-too-old',
        'tipo' => 'pix_cob',
        'status' => 'emitida',
        'valor_centavos' => 10000,
        'idempotency_key' => 'too-old-001',
        'payload_gateway' => [],
    ]);

    $orphan = GatewayWebhookEvent::query()->create([
        'business_id' => 1,
        'gateway_key' => 'inter',
        'evento' => 'cob.paid',
        'gateway_event_id' => 'evt-too-old-001',
        'cobranca_id' => $cobranca->id,
        'payload' => ['raw' => 'paid'],
        'signature_valid' => true,
    ]);
    // backdate created_at pra MAIS DE 24h atrás → fora da janela
    $orphan->created_at = now()->subDays(2);
    $orphan->saveQuietly();

    (new RetryOrphanWebhookJob())->handle();

    $orphan->refresh();
    expect($orphan->processed_at)->toBeNull(); // nem tocou nele
    expect($orphan->error_message)->toBeNull(); // nem tocou nele

    Event::assertNotDispatched(CobrancaPaga::class);
});

it('GUARD 3b: ignora evento muito recente (< 1h) — espera fluxo original gravar Cobranca', function () {
    Event::fake([CobrancaPaga::class]);

    $cobranca = Cobranca::query()->create([
        'business_id' => 1,
        'gateway_external_id' => 'tx-too-recent',
        'tipo' => 'pix_cob',
        'status' => 'emitida',
        'valor_centavos' => 8000,
        'idempotency_key' => 'too-recent-001',
        'payload_gateway' => [],
    ]);

    $orphan = GatewayWebhookEvent::query()->create([
        'business_id' => 1,
        'gateway_key' => 'inter',
        'evento' => 'cob.paid',
        'gateway_event_id' => 'evt-too-recent-001',
        'cobranca_id' => $cobranca->id,
        'payload' => ['raw' => 'paid'],
        'signature_valid' => true,
    ]);
    // 10min atrás → ainda dentro da espera 1h
    $orphan->created_at = now()->subMinutes(10);
    $orphan->saveQuietly();

    (new RetryOrphanWebhookJob())->handle();

    $orphan->refresh();
    expect($orphan->processed_at)->toBeNull();
    Event::assertNotDispatched(CobrancaPaga::class);
});

it('GUARD 4: evento que NÃO matches paid pattern → marca processed sem dispatch', function () {
    Event::fake([CobrancaPaga::class]);

    $cobranca = Cobranca::query()->create([
        'business_id' => 1,
        'gateway_external_id' => 'tx-non-paid',
        'tipo' => 'pix_cob',
        'status' => 'emitida',
        'valor_centavos' => 5000,
        'idempotency_key' => 'non-paid-001',
        'payload_gateway' => [],
    ]);

    $orphan = GatewayWebhookEvent::query()->create([
        'business_id' => 1,
        'gateway_key' => 'inter',
        'evento' => 'cob.created', // NÃO matches paid/received/confirmed
        'gateway_event_id' => 'evt-non-paid-001',
        'cobranca_id' => $cobranca->id,
        'payload' => ['raw' => 'created'],
        'signature_valid' => true,
    ]);
    $orphan->created_at = now()->subHours(2);
    $orphan->saveQuietly();

    (new RetryOrphanWebhookJob())->handle();

    $orphan->refresh();
    expect($orphan->processed_at)->not->toBeNull(); // marcado processed
    expect($orphan->error_message)->toContain('orphan_event_not_mapped_to_dispatch');
    Event::assertNotDispatched(CobrancaPaga::class);
});

it('GUARD 5: respeita limit 50 — só processa 50 órfãos por run', function () {
    Event::fake([CobrancaPaga::class]);

    // Cria 51 órfãos válidos (na janela, cobranca_id válido, evento paid)
    for ($i = 1; $i <= 51; $i++) {
        $cobranca = Cobranca::query()->create([
            'business_id' => 1,
            'gateway_external_id' => "tx-bulk-{$i}",
            'tipo' => 'pix_cob',
            'status' => 'emitida',
            'valor_centavos' => 1000,
            'idempotency_key' => "bulk-{$i}",
            'payload_gateway' => [],
        ]);

        $orphan = GatewayWebhookEvent::query()->create([
            'business_id' => 1,
            'gateway_key' => 'inter',
            'evento' => 'cob.paid',
            'gateway_event_id' => "evt-bulk-{$i}",
            'cobranca_id' => $cobranca->id,
            'payload' => ['raw' => 'paid'],
            'signature_valid' => true,
        ]);
        $orphan->created_at = now()->subHours(2)->subMinutes($i); // ordem cronológica
        $orphan->saveQuietly();
    }

    (new RetryOrphanWebhookJob())->handle();

    // 50 dispatched no run; o 51º fica pro próximo run
    Event::assertDispatchedTimes(CobrancaPaga::class, 50);

    $stillOrphans = GatewayWebhookEvent::withoutGlobalScopes()
        ->whereNull('processed_at')
        ->count();
    expect($stillOrphans)->toBe(1);
});

it('GUARD 6: dispatch propaga business_id correto em multi-tenant (biz=99 NÃO contamina biz=1)', function () {
    Event::fake([CobrancaPaga::class]);

    // Cobranca + órfão biz=99
    $cob99 = Cobranca::query()->withoutGlobalScopes()->create([
        'business_id' => 99,
        'gateway_external_id' => 'tx-biz99',
        'tipo' => 'pix_cob',
        'status' => 'emitida',
        'valor_centavos' => 7000,
        'valor_pago_centavos' => 7000,
        'paga_em' => now()->subMinutes(30),
        'forma_pagamento' => 'pix',
        'idempotency_key' => 'biz99-001',
        'payload_gateway' => [],
    ]);

    $orphan99 = GatewayWebhookEvent::query()->withoutGlobalScopes()->create([
        'business_id' => 99,
        'gateway_key' => 'inter',
        'evento' => 'cob.paid',
        'gateway_event_id' => 'evt-biz99-001',
        'cobranca_id' => $cob99->id,
        'payload' => ['raw' => 'paid'],
        'signature_valid' => true,
    ]);
    $orphan99->created_at = now()->subHours(2);
    $orphan99->saveQuietly();

    (new RetryOrphanWebhookJob())->handle();

    Event::assertDispatched(CobrancaPaga::class, function (CobrancaPaga $e) {
        return $e->businessId === 99 && $e->cobrancaId > 0;
    });
});
