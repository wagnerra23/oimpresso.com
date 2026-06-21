<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Onda 3 — ADR 0170. US-PG-002 audit-senior 2026-05-25 (VULN SEC P0-#2):
 * agora todos os webhooks legacy exigem signature válida — testes refatorados
 * pra mandar header correto antes de testar idempotência.
 *
 * Schema in-memory (não RefreshDatabase — migrations canon usam ALTER TABLE
 * MODIFY COLUMN ENUM MySQL-only). Pattern canon: PagarmeDriverTest +
 * RetryOrphanWebhookJobTest.
 *
 * ADR 0101: business_id = 1 (nunca cliente real).
 */

function setupWhEndpSchema(): void
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

function teardownWhEndpSchema(): void
{
    // Só tabelas do MÓDULO PaymentGateway (prefixo payment_gateway_* / gateway_*).
    // NÃO dropar `activity_log`: é CORE COMPARTILHADA (Spatie activitylog) — em
    // MySQL persistente do nightly o drop destruiria o schema usado por outros testes.
    Schema::dropIfExists('gateway_webhook_events');
    Schema::dropIfExists('payment_gateway_credentials');
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }
    setupWhEndpSchema();
    // Credenciais ativas com secrets/token cadastrados pra signature passar.
    $this->credAsaas = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'asaas',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Asaas Test biz=1',
        'config_json'  => [
            'api_key'       => 'fake-api',
            'webhook_token' => 'asaas-tok-1',
        ],
    ]);

    $this->credInter = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter Test biz=1',
        'config_json'  => [
            'client_id'      => 'x',
            'client_secret'  => 'y',
            'webhook_secret' => 'inter-secret-1',
        ],
    ]);

    $this->credC6 = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'c6',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'C6 Test biz=1',
        'config_json'  => [
            'webhook_secret' => 'c6-secret-1',
        ],
    ]);
});

afterEach(function () {
    // afterEach roda MESMO em teste pulado por markTestSkipped no beforeEach
    // (PHPUnit 12.5.x). Guardar o DDL por driver evita dropar as tabelas
    // REAL-migradas (gateway_webhook_events, payment_gateway_credentials).
    if (DB::connection()->getDriverName() === 'sqlite') {
        teardownWhEndpSchema();
    }
});

/**
 * Helper Inter: HMAC-SHA256 raw body via header x-inter-signature.
 */
function postIntr(\Tests\TestCase $test, int $businessId, array $payload, string $secret = 'inter-secret-1')
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $sig = hash_hmac('sha256', $raw, $secret);

    return $test->call(
        method: 'POST',
        uri: "/paymentgateway/webhooks/inter/{$businessId}",
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'           => 'application/json',
            'HTTP_X_INTER_SIGNATURE' => $sig,
            'HTTP_ACCEPT'            => 'application/json',
        ],
        content: $raw,
    );
}

/**
 * Helper Asaas: header `asaas-access-token` literal.
 */
function postAsa(\Tests\TestCase $test, int $businessId, array $payload, string $token = 'asaas-tok-1')
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    return $test->call(
        method: 'POST',
        uri: "/paymentgateway/webhooks/asaas/{$businessId}",
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ASAAS_ACCESS_TOKEN' => $token,
            'HTTP_ACCEPT'             => 'application/json',
        ],
        content: $raw,
    );
}

/**
 * Helper C6: HMAC-SHA256 GitHub-style `X-Hub-Signature-256: sha256=<hex>`.
 */
function postC6h(\Tests\TestCase $test, int $businessId, array $payload, string $secret = 'c6-secret-1')
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $sig = 'sha256=' . hash_hmac('sha256', $raw, $secret);

    return $test->call(
        method: 'POST',
        uri: "/paymentgateway/webhooks/c6/{$businessId}",
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $sig,
            'HTTP_ACCEPT'              => 'application/json',
        ],
        content: $raw,
    );
}

it('Inter webhook cria GatewayWebhookEvent na 1ª chamada (signature válida)', function () {
    $response = postIntr($this, 1, [
        'evento' => 'cobranca.paga',
        'txid'   => 'inter-tx-001',
        'valor'  => 100.00,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'duplicate' => false]);

    $event = GatewayWebhookEvent::query()->withoutGlobalScopes()->first();
    expect($event)->not->toBeNull();
    expect($event->business_id)->toBe(1);
    expect($event->gateway_key)->toBe('inter');
    expect($event->evento)->toBe('cobranca.paga');
    expect($event->gateway_event_id)->toBe('inter-tx-001');
    // US-PG-002: signature_valid agora TRUE quando HMAC bate
    expect($event->signature_valid)->toBeTrue();
    expect($event->processed_at)->toBeNull();
});

it('Inter webhook duplicado retorna duplicate=true sem inserir 2x', function () {
    $payload = ['evento' => 'cobranca.paga', 'txid' => 'inter-dup-001'];

    postIntr($this, 1, $payload)->assertOk();
    postIntr($this, 1, $payload)
        ->assertOk()
        ->assertJson(['ok' => true, 'duplicate' => true]);

    expect(GatewayWebhookEvent::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('Mesmo event_id em gateways DIFERENTES NÃO colide (UNIQUE inclui gateway_key)', function () {
    postIntr($this, 1, [
        'evento' => 'cobranca.paga',
        'txid'   => 'shared-id',
    ])->assertOk();

    postAsa($this, 1, [
        'event'   => 'PAYMENT_RECEIVED',
        'id'      => 'shared-id',
        'payment' => ['id' => 'p1'],
    ])->assertOk()->assertJson(['duplicate' => false]);

    expect(GatewayWebhookEvent::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('Mesmo gateway_event_id em businesses DIFERENTES NÃO colide (UNIQUE inclui business_id)', function () {
    // Credencial Inter biz=99
    PaymentGatewayCredential::query()->create([
        'business_id'  => 99,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter biz=99',
        'config_json'  => ['webhook_secret' => 'inter-secret-99'],
    ]);

    $payload = ['evento' => 'cobranca.paga', 'txid' => 'cross-biz-id'];

    postIntr($this, 1, $payload)->assertOk();
    postIntr($this, 99, $payload, 'inter-secret-99')
        ->assertOk()
        ->assertJson(['duplicate' => false]);

    expect(GatewayWebhookEvent::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('Asaas webhook extrai event_id de id ou payment.id', function () {
    // Sem `id` top-level — usa fallback `event:payment.id`
    postAsa($this, 1, [
        'event'   => 'PAYMENT_CONFIRMED',
        'payment' => ['id' => 'p123'],
    ])->assertOk();

    $event = GatewayWebhookEvent::query()->withoutGlobalScopes()->first();
    expect($event->gateway_key)->toBe('asaas');
    expect($event->gateway_event_id)->toBe('PAYMENT_CONFIRMED:p123');
});

it('C6 webhook usa transactionId', function () {
    postC6h($this, 1, [
        'eventType'     => 'PAYMENT_OK',
        'transactionId' => 'c6-tx-999',
    ])->assertOk();

    $event = GatewayWebhookEvent::query()->withoutGlobalScopes()->first();
    expect($event->gateway_key)->toBe('c6');
    expect($event->gateway_event_id)->toBe('c6-tx-999');
});

it('Webhook sem id/txid usa fallback md5 (não trava)', function () {
    postIntr($this, 1, [
        'evento' => 'cobranca.paga',
        // sem id, sem txid, sem nossoNumero
    ])->assertOk()->assertJson(['duplicate' => false]);

    expect(GatewayWebhookEvent::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('Webhook routes NÃO exigem auth (sem auth middleware)', function () {
    // Sem login, sem session — deve passar (com signature válida).
    auth()->logout();

    postIntr($this, 1, [
        'evento' => 'test',
        'txid'   => 'no-auth-test',
    ])->assertOk(); // se exigisse auth, retornaria 302 ou 401
});
