<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Http\Controllers\Webhooks\WebhookProcessor;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Webhook\CobrancaWebhookResolver;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * ADR 0170 · US-PG-008 — Linkage cobranca_id no webhook genérico.
 *
 * Antes: WebhookProcessor gravava SEMPRE cobranca_id=NULL → branch de quitação
 * do RetryOrphanWebhookJob INALCANÇÁVEL. Estas GUARDs provam:
 *   1. O CobrancaWebhookResolver extrai o id externo POR DRIVER (reusando
 *      driver->processWebhook) — incl. o caso event-id ≠ payment-id de
 *      Asaas (payment.id) / Pagar.me (data.id) e nossoNumero do Inter.
 *   2. Multi-tenant Tier 0 (ADR 0093): resolve biz=1 NUNCA acha Cobranca biz=99.
 *   3. WebhookProcessor::handle persiste cobranca_id + payment_gateway_credential_id.
 *
 * Pattern era-sqlite (schema sintético, skip em MySQL persistente) — espelha
 * RetryOrphanWebhookJobTest + WebhookSignatureValidationTest. Tests biz=1
 * (ADR 0101), NUNCA biz=4 cliente real.
 */

function setupLinkageSchema(): void
{
    if (! Schema::hasTable('payment_gateway_credentials')) {
        Schema::create('payment_gateway_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('gateway_key', 20)->index();
            $table->string('ambiente', 20)->default('production');
            $table->boolean('ativo')->default(true)->index();
            $table->string('nome_display')->nullable();
            $table->json('config_json');
            $table->unsignedInteger('conta_bancaria_id')->nullable();
            $table->string('health_status', 20)->default('unknown');
            $table->timestamp('health_checked_at')->nullable();
            $table->timestamps();
        });
    }
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
            $table->unique(['business_id', 'gateway_key', 'gateway_event_id'], 'gw_wh_link_biz_key_extid_unique');
        });
    }
}

function teardownLinkageSchema(): void
{
    // NÃO dropar activity_log (CORE compartilhada Spatie — MySQL persistente).
    Schema::dropIfExists('gateway_webhook_events');
    Schema::dropIfExists('cobrancas');
    Schema::dropIfExists('payment_gateway_credentials');
}

/** Cria credencial + Cobranca emitida pra um gateway, retorna [cred, cobranca]. */
function makeCobrancaFor(string $gatewayKey, string $externalId, int $businessId = 1): array
{
    $cred = PaymentGatewayCredential::query()->create([
        'business_id'  => $businessId,
        'gateway_key'  => $gatewayKey,
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => "{$gatewayKey} linkage test",
        'config_json'  => ['webhook_token' => 'x', 'webhook_secret' => 'x'],
    ]);

    $cobranca = Cobranca::query()->withoutGlobalScopes()->create([
        'business_id'                   => $businessId,
        'payment_gateway_credential_id' => $cred->id,
        'gateway_external_id'           => $externalId,
        'tipo'                          => 'pix_cob',
        'status'                        => 'emitida',
        'valor_centavos'                => 12345,
        'valor_pago_centavos'           => 12345,
        'paga_em'                       => now()->subMinutes(5),
        'forma_pagamento'               => 'pix',
        'idempotency_key'               => "idem-{$gatewayKey}-{$externalId}",
        'payload_gateway'               => [],
    ]);

    return [$cred, $cobranca];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — espelha RetryOrphanWebhookJobTest.');
    }
    setupLinkageSchema();
    session(['business.id' => 1]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        teardownLinkageSchema();
    }
});

// ─── RESOLVER: extração POR DRIVER (event-id ≠ payment-id) ────────────────

it('GUARD 1: Asaas resolve pela payment.id (NÃO pelo id do evento)', function () {
    [$cred, $cobranca] = makeCobrancaFor('asaas', 'pay-asaas-001');
    $resolver = app(CobrancaWebhookResolver::class);

    // id do EVENTO ('evt-999') é diferente do id do pagamento ('pay-asaas-001').
    $found = $resolver->resolve(1, 'asaas', [
        'event'   => 'PAYMENT_RECEIVED',
        'id'      => 'evt-999',
        'payment' => ['id' => 'pay-asaas-001'],
    ], $cred);

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($cobranca->id);
});

it('GUARD 2: Pagar.me resolve pela data.id (NÃO pelo id do hook)', function () {
    [$cred, $cobranca] = makeCobrancaFor('pagarme', 'ch-pagarme-001');
    $resolver = app(CobrancaWebhookResolver::class);

    $found = $resolver->resolve(1, 'pagarme', [
        'type' => 'charge.paid',
        'id'   => 'hook_999',
        'data' => ['id' => 'ch-pagarme-001'],
    ], $cred);

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($cobranca->id);
});

it('GUARD 3: Inter resolve pelo nossoNumero', function () {
    [$cred, $cobranca] = makeCobrancaFor('inter', 'NN-inter-001');
    $resolver = app(CobrancaWebhookResolver::class);

    $found = $resolver->resolve(1, 'inter', [
        'evento'      => 'cobranca.paga',
        'nossoNumero' => 'NN-inter-001',
    ], $cred);

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($cobranca->id);
});

// ─── RESOLVER: casos de não-resolução (fica órfão) ───────────────────────

it('GUARD 4: payload sem id externo → null (fica órfão)', function () {
    [$cred] = makeCobrancaFor('asaas', 'pay-x');
    $resolver = app(CobrancaWebhookResolver::class);

    expect($resolver->resolve(1, 'asaas', ['event' => 'PING'], $cred))->toBeNull();
});

it('GUARD 5: id externo que não casa nenhuma Cobranca → null', function () {
    [$cred] = makeCobrancaFor('asaas', 'pay-existe');
    $resolver = app(CobrancaWebhookResolver::class);

    $found = $resolver->resolve(1, 'asaas', ['payment' => ['id' => 'pay-NAO-existe']], $cred);
    expect($found)->toBeNull();
});

it('GUARD 6: gateway desconhecida → null (driver ausente, não explode)', function () {
    [$cred] = makeCobrancaFor('asaas', 'pay-x');
    $resolver = app(CobrancaWebhookResolver::class);

    expect($resolver->resolve(1, 'gateway_inexistente', ['payment' => ['id' => 'pay-x']], $cred))->toBeNull();
});

// ─── MULTI-TENANT Tier 0 (ADR 0093) ──────────────────────────────────────

it('GUARD 7: resolve biz=1 NUNCA acha Cobranca biz=99 com mesmo id externo', function () {
    // Cobranca + credencial vivem em biz=99.
    [$credBiz99] = makeCobrancaFor('asaas', 'shared-ext-id', 99);

    // Credencial separada biz=1 (mesmo gateway). NÃO existe Cobranca biz=1.
    [$credBiz1] = makeCobrancaFor('asaas', 'outro-id-biz1', 1);

    $resolver = app(CobrancaWebhookResolver::class);

    // Resolve scoped em biz=1 com o id externo que SÓ existe em biz=99 → null.
    $found = $resolver->resolve(1, 'asaas', ['payment' => ['id' => 'shared-ext-id']], $credBiz1);
    expect($found)->toBeNull();

    // Sanity: o mesmo id resolve corretamente DENTRO de biz=99.
    $foundBiz99 = $resolver->resolve(99, 'asaas', ['payment' => ['id' => 'shared-ext-id']], $credBiz99);
    expect($foundBiz99)->not->toBeNull();
    expect($foundBiz99->business_id)->toBe(99);
});

// ─── WEBHOOK PROCESSOR: persiste cobranca_id + credential_id ──────────────

it('GUARD 8: WebhookProcessor::handle grava cobranca_id + credential_id na linha', function () {
    [$cred, $cobranca] = makeCobrancaFor('asaas', 'pay-persist-001');

    $processor = app(WebhookProcessor::class);
    $request = Request::create('/paymentgateway/webhooks/asaas/1', 'POST', [
        'event'   => 'PAYMENT_RECEIVED',
        'id'      => 'evt-persist',
        'payment' => ['id' => 'pay-persist-001'],
    ]);

    $processor->handle(
        gatewayKey: 'asaas',
        request: $request,
        businessId: 1,
        eventName: 'PAYMENT_RECEIVED',
        eventId: 'evt-persist',
        signatureValid: true,
        credential: $cred,
    );

    $event = GatewayWebhookEvent::withoutGlobalScopes()->first();
    expect($event)->not->toBeNull();
    expect($event->cobranca_id)->toBe($cobranca->id);
    expect($event->payment_gateway_credential_id)->toBe($cred->id);
    // Linkage é metadata-only: NÃO marca processed_at (quitação fica pro cron/flag).
    expect($event->processed_at)->toBeNull();
});

it('GUARD 9: WebhookProcessor::handle sem Cobranca existente → cobranca_id NULL (órfão p/ retry)', function () {
    $cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'asaas',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'asaas sem cobranca',
        'config_json'  => ['webhook_token' => 'x'],
    ]);

    $processor = app(WebhookProcessor::class);
    $request = Request::create('/paymentgateway/webhooks/asaas/1', 'POST', [
        'event'   => 'PAYMENT_RECEIVED',
        'payment' => ['id' => 'pay-ainda-nao-emitida'],
    ]);

    $processor->handle(
        gatewayKey: 'asaas',
        request: $request,
        businessId: 1,
        eventName: 'PAYMENT_RECEIVED',
        eventId: 'evt-race',
        signatureValid: true,
        credential: $cred,
    );

    $event = GatewayWebhookEvent::withoutGlobalScopes()->first();
    expect($event)->not->toBeNull();
    expect($event->cobranca_id)->toBeNull(); // race → RetryOrphanWebhookJob re-resolve depois
    expect($event->payment_gateway_credential_id)->toBe($cred->id);
});
