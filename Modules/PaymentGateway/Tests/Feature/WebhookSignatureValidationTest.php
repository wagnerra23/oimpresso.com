<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * US-PG-002 (audit-senior 2026-05-25 VULN SEC P0-#2 CATASTRÓFICA).
 *
 * Validação HMAC/token/mTLS em todos os 4 webhooks legacy do PaymentGateway
 * (Asaas, Inter legacy, C6, BCB Pix) — antes desta US, controllers gravavam
 * `signature_valid: false` HARDCODED e qualquer atacante anônimo conseguia
 * marcar Cobranca como paga falsamente.
 *
 * Strategy pattern por gateway_key em WebhookProcessor::validateSignature():
 *   - asaas:   header `asaas-access-token` ≡ config.webhook_token (hash_equals)
 *   - inter:   HMAC-SHA256 raw body via header `x-inter-signature`
 *   - c6:      HMAC-SHA256 GitHub-style `X-Hub-Signature-256: sha256=<hex>`
 *   - bcb_pix: mTLS cert fingerprint (SHA-256) via `SSL_CLIENT_CERT` server var
 *
 * Fail-secure: sem secret/token/fingerprint em config_json → 401.
 *
 * 8 cenários canônicos + 2 bônus — 4 happy + 4 fail-secure + 2 edge.
 *
 * ADR 0093 multi-tenant: biz=1 (NUNCA biz=4 cliente — ADR 0101).
 *
 * Schema in-memory por test (não RefreshDatabase — migrations canônicas usam
 * ALTER TABLE MODIFY COLUMN ENUM MySQL-only, incompatível SQLite). Pattern
 * canon já adotado em PagarmeDriverTest + RetryOrphanWebhookJobTest.
 */

function setupSigValSchema(): void
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
    // cobrancas: o linkage do WebhookProcessor (US-PG-008) consulta esta tabela
    // no happy-path pra resolver cobranca_id. Sem rows aqui o resolve retorna
    // null (nenhuma Cobranca pré-existe nestes cenários de signature).
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
}

function teardownSigValSchema(): void
{
    // Só tabelas do MÓDULO PaymentGateway (prefixo payment_gateway_* / gateway_*).
    // NÃO dropar `activity_log`: é CORE COMPARTILHADA (Spatie activitylog) — em
    // MySQL persistente do nightly o drop destruiria o schema usado por outros testes.
    Schema::dropIfExists('gateway_webhook_events');
    Schema::dropIfExists('cobrancas');
    Schema::dropIfExists('payment_gateway_credentials');
}

/**
 * Cert PEM fixture estático #1 (psp "autorizado") — gerado offline com
 * `openssl req -x509 -newkey rsa:2048 -days 3650 -nodes -subj "/CN=psp-test-1"`.
 *
 * Pattern fixture vs runtime: ambientes Windows-Herd não conseguem rodar
 * openssl_csr_new() sem openssl.cnf hardcoded; fixture é portável + idêntico
 * em CI/local.
 */
function cert1Pem(): string
{
    return "-----BEGIN CERTIFICATE-----\n" .
        "MIIDGzCCAgOgAwIBAgIUfChnC7COdAZKzmA625DUPxINqY4wDQYJKoZIhvcNAQEL\n" .
        "BQAwHTEbMBkGA1UEAwwSb2ltcHJlc3NvLXRlc3QtcHNwMB4XDTI2MDUyNjAwNDM0\n" .
        "M1oXDTM2MDUyMzAwNDM0M1owHTEbMBkGA1UEAwwSb2ltcHJlc3NvLXRlc3QtcHNw\n" .
        "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtIZpVrtezUrs/2rfc0GF\n" .
        "HTx69mOrpvpq8+IAux80LceE0VcyDn2AmUv4U/jAc5I5zGcTEqww8igTMEWwhKwk\n" .
        "PJjC0sT7golybvNLbqSmBmJsEUkwZZ6oxAZSLELtTtDfe0TYqhHGGKMo0OLg4kM9\n" .
        "c5IVUS+N6pc6dRpWiHXZq/QpuDywIfprBbxfm1fsa08Uy5HMZ4BtzWLl6hZOGaPS\n" .
        "ZU202VjZMg6uyHqqvGlUCmOK4M/1lpjwQoFM+S5aOyAbUB+piJ3UJ+cNqD/EudQL\n" .
        "ZcSfxlqjxldel54/dslxRlIydCNGbWITLAWGy5RX4njmB40i1J6LptFXG7RnN8lh\n" .
        "bQIDAQABo1MwUTAdBgNVHQ4EFgQUpxQUy8oXeWYbmUZ6UcIzlA25UWgwHwYDVR0j\n" .
        "BBgwFoAUpxQUy8oXeWYbmUZ6UcIzlA25UWgwDwYDVR0TAQH/BAUwAwEB/zANBgkq\n" .
        "hkiG9w0BAQsFAAOCAQEAqB2KaVWvuDGFHhObgw6iDH7MVNWUjMg7hvHTUiHM15r7\n" .
        "mfkGI3ASAHxQZ+32zeprB2uGeowk/ZX4/C6oAxBMLlhLtxpZgigxtn5snQ8BuyJf\n" .
        "LnTsdDlD3Qb5vj3QPgOofUVVt2Pt3bfouwLwpeyEKJvFfD0b01eHCHatZDswiQV3\n" .
        "OHjaSrx5/U5rjLWsepTUQDGeJEy1wIolEOrulVhzAq0oJTX8TutXRn+RnfieYh4m\n" .
        "sCiVi/xRyBc0csSOh7FTmVcuIKFh2CJCeYR2ZdnvoWAPB4qXSh3DEMUIjgzYq7iT\n" .
        "yYJDV5U+XSwe0ZB/f/paKNdqtyMVMGiLzJ7Y1k5UQw==\n" .
        "-----END CERTIFICATE-----\n";
}

/**
 * Fingerprint SHA-256 do cert1 — gerado por
 * `openssl x509 -fingerprint -sha256 -noout` (lowercase sem dois-pontos).
 */
function cert1Fingerprint(): string
{
    return '2d429fc35869078426c5b639acef05c73354683d3706e7baf2ca8fb5323c8938';
}

/**
 * Cert fixture #2 — distinto do #1, usado em fail-secure (fingerprint não bate).
 * Gerado da mesma forma (offline com openssl req -x509 CN=psp-attacker-fake).
 */
function cert2Pem(): string
{
    return "-----BEGIN CERTIFICATE-----\n" .
        "MIIDGTCCAgGgAwIBAgIUU8m0t+ZuwWl/geG+6qQ6ORihLAgwDQYJKoZIhvcNAQEL\n" .
        "BQAwHDEaMBgGA1UEAwwRcHNwLWF0dGFja2VyLWZha2UwHhcNMjYwNTI2MDA0NDI2\n" .
        "WhcNMzYwNTIzMDA0NDI2WjAcMRowGAYDVQQDDBFwc3AtYXR0YWNrZXItZmFrZTCC\n" .
        "ASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBANSuG+vG5Ir9ZAP+9VggnqVV\n" .
        "TbUJ66oWjmkrRxV5v954veWf19vFmqbmGayL5jvAcqhKII/gN/gFDfTPKfMkC+H9\n" .
        "V5W82NZCN/f013IRyHwV8LwzuTOCvet82qj1gXEpb8ff2PGYRDW2JJw8XkGPSGwa\n" .
        "EcO3Prf88UF26jkgMzKNtK6b021K/0ZztEcAlX00+FYtxBzr2jlif+Ec2b3XmI0R\n" .
        "PxSAPsifyYhiHp7RpJnPx8tFLsx56tahHRpGk6FhlgZI8o5pqhu64Hvi3669ULQi\n" .
        "Cznyms24v2N+bVTHByz3W+XCLaWFvuKim0LQKSe9LEY5HfreVFR3JvC02Vb0IeMC\n" .
        "AwEAAaNTMFEwHQYDVR0OBBYEFAkSwQ3rOj+d3B4bayAS+ILFUvCqMB8GA1UdIwQY\n" .
        "MBaAFAkSwQ3rOj+d3B4bayAS+ILFUvCqMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZI\n" .
        "hvcNAQELBQADggEBAKO9DEbFaF8ZI66/Jz6UC2esDNd1RKaprh8Yl/Y1ZZ4xbiih\n" .
        "jC2lQQ2XWEWclAHRTZoCvqYcLXA2K17P+l3sSReUEz3F76IsLPvhZkuNo0j8Hqbx\n" .
        "BiS/m+S4iDZqZ10j5BVuZrLjK6qTm8FAqRqNjsD73jUfIP17BMgNHL+I0gxQsbjC\n" .
        "ONNIKIUH28AAwRrlHJ3I8IHLyXI3WacFvmFKkNruB/GLQDw1ttQ0PijmKXE7cq5X\n" .
        "RbXZJxUL+j5OvMKZIs8oEGTUEZcH8DXa383Oj3kq/mKSbQ3GgIdpEICLX6EBJT7Z\n" .
        "ozxxpB8oSWaQt7ZqeS80fnfdsZOqYclG7lOctz8=\n" .
        "-----END CERTIFICATE-----\n";
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }
    setupSigValSchema();

    // Asaas: token estático
    $this->credAsaas = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'asaas',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Asaas SEC test',
        'config_json'  => [
            'api_key'       => 'fake-api-key',
            'webhook_token' => 'asaas-secret-token-xyz',
        ],
    ]);

    // Inter legacy: HMAC-SHA256
    $this->credInter = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter legacy SEC test',
        'config_json'  => [
            'client_id'      => 'fake',
            'client_secret'  => 'fake',
            'webhook_secret' => 'inter-hmac-secret-abc',
        ],
    ]);

    // C6: HMAC-SHA256 GitHub-style
    $this->credC6 = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'c6',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'C6 SEC test',
        'config_json'  => [
            'webhook_secret' => 'c6-hmac-secret-qwe',
        ],
    ]);

    // BCB PIX: mTLS — usa cert fixture #1 + seu fingerprint conhecido.
    $this->bcbCertPem = cert1Pem();
    $this->bcbFingerprint = cert1Fingerprint();

    $this->credBcb = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'bcb_pix',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'BCB Pix SEC test',
        'config_json'  => [
            'psp_cert_fingerprint' => $this->bcbFingerprint,
        ],
    ]);
});

afterEach(function () {
    // afterEach roda MESMO em teste pulado por markTestSkipped no beforeEach
    // (PHPUnit 12.5.x). Guardar o DDL por driver evita dropar as tabelas
    // REAL-migradas (gateway_webhook_events, payment_gateway_credentials).
    if (DB::connection()->getDriverName() === 'sqlite') {
        teardownSigValSchema();
    }
});

// ─── HAPPY PATH (4) ──────────────────────────────────────────────────────

it('asaas: signature válida (asaas-access-token bate) → 200 + signature_valid=true', function () {
    $payload = ['event' => 'PAYMENT_RECEIVED', 'id' => 'evt-1', 'payment' => ['id' => 'pay-1']];
    $raw = json_encode($payload);

    $response = $this->call(
        method: 'POST',
        uri: '/paymentgateway/webhooks/asaas/1',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ASAAS_ACCESS_TOKEN' => 'asaas-secret-token-xyz',
        ],
        content: $raw,
    );

    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'duplicate' => false]);

    $event = GatewayWebhookEvent::withoutGlobalScopes()->first();
    expect($event)->not->toBeNull();
    expect($event->gateway_key)->toBe('asaas');
    expect($event->signature_valid)->toBeTrue();
});

it('inter legacy: signature válida (HMAC-SHA256 x-inter-signature) → 200 + signature_valid=true', function () {
    $payload = ['evento' => 'cobranca.paga', 'txid' => 'inter-tx-good'];
    $raw = json_encode($payload);
    $sig = hash_hmac('sha256', $raw, 'inter-hmac-secret-abc');

    $response = $this->call(
        method: 'POST',
        uri: '/paymentgateway/webhooks/inter/1',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'           => 'application/json',
            'HTTP_X_INTER_SIGNATURE' => $sig,
        ],
        content: $raw,
    );

    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'duplicate' => false]);

    $event = GatewayWebhookEvent::withoutGlobalScopes()->first();
    expect($event->gateway_key)->toBe('inter');
    expect($event->signature_valid)->toBeTrue();
});

it('c6: signature válida (HMAC GitHub-style sha256=<hex>) → 200 + signature_valid=true', function () {
    $payload = ['eventType' => 'PAYMENT_OK', 'transactionId' => 'c6-tx-good'];
    $raw = json_encode($payload);
    $sig = 'sha256=' . hash_hmac('sha256', $raw, 'c6-hmac-secret-qwe');

    $response = $this->call(
        method: 'POST',
        uri: '/paymentgateway/webhooks/c6/1',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $sig,
        ],
        content: $raw,
    );

    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'duplicate' => false]);

    $event = GatewayWebhookEvent::withoutGlobalScopes()->first();
    expect($event->gateway_key)->toBe('c6');
    expect($event->signature_valid)->toBeTrue();
});

it('bcb_pix: mTLS cert fingerprint válido (SSL_CLIENT_CERT bate) → 200 + signature_valid=true', function () {
    $payload = ['evento' => 'PIX_RECEBIDO', 'txid' => 'bcb-tx-good'];
    $raw = json_encode($payload);

    $response = $this->call(
        method: 'POST',
        uri: '/paymentgateway/webhooks/bcb-pix/1',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'    => 'application/json',
            'SSL_CLIENT_CERT' => $this->bcbCertPem,
        ],
        content: $raw,
    );

    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'duplicate' => false]);

    $event = GatewayWebhookEvent::withoutGlobalScopes()->first();
    expect($event->gateway_key)->toBe('bcb_pix');
    expect($event->signature_valid)->toBeTrue();
});

// ─── FAIL-SECURE (4) ─────────────────────────────────────────────────────

it('asaas: token errado → 401 + NÃO grava evento', function () {
    $payload = ['event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => 'attacker-fake-1']];

    $response = $this->call(
        method: 'POST',
        uri: '/paymentgateway/webhooks/asaas/1',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ASAAS_ACCESS_TOKEN' => 'TOKEN-FALSO-DE-ATACANTE',
        ],
        content: json_encode($payload),
    );

    $response->assertStatus(401);
    $response->assertJson(['ok' => false, 'error' => 'signature_invalid']);
    expect(GatewayWebhookEvent::withoutGlobalScopes()->count())->toBe(0);
});

it('inter legacy: HMAC inválido → 401 + NÃO grava evento (anti-spoofing)', function () {
    $payload = ['evento' => 'cobranca.paga', 'txid' => 'fake-tx-001'];

    $response = $this->call(
        method: 'POST',
        uri: '/paymentgateway/webhooks/inter/1',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'           => 'application/json',
            'HTTP_X_INTER_SIGNATURE' => 'deadbeef-assinatura-falsa',
        ],
        content: json_encode($payload),
    );

    $response->assertStatus(401);
    $response->assertJson(['ok' => false, 'error' => 'signature_invalid']);
    expect(GatewayWebhookEvent::withoutGlobalScopes()->count())->toBe(0);
});

it('c6: header X-Hub-Signature-256 ausente → 401 + NÃO grava evento', function () {
    $payload = ['eventType' => 'PAYMENT_OK', 'transactionId' => 'fake-c6'];

    $response = $this->call(
        method: 'POST',
        uri: '/paymentgateway/webhooks/c6/1',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE' => 'application/json',
            // sem HTTP_X_HUB_SIGNATURE_256
        ],
        content: json_encode($payload),
    );

    $response->assertStatus(401);
    $response->assertJson(['ok' => false, 'error' => 'signature_invalid']);
    expect(GatewayWebhookEvent::withoutGlobalScopes()->count())->toBe(0);
});

it('bcb_pix: cert fingerprint NÃO bate → 401 + NÃO grava evento', function () {
    // Cert #2 — distinto do cadastrado (#1) → fingerprint outro.
    $payload = ['evento' => 'PIX_RECEBIDO', 'txid' => 'attacker-bcb'];
    $response = $this->call(
        method: 'POST',
        uri: '/paymentgateway/webhooks/bcb-pix/1',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'    => 'application/json',
            'SSL_CLIENT_CERT' => cert2Pem(),
        ],
        content: json_encode($payload),
    );

    $response->assertStatus(401);
    $response->assertJson(['ok' => false, 'error' => 'signature_invalid']);
    expect(GatewayWebhookEvent::withoutGlobalScopes()->count())->toBe(0);
});

// ─── BÔNUS: fail-secure secret ausente / biz sem credencial ──────────────

it('BÔNUS: credencial Asaas SEM webhook_token cadastrado → 401 (fail-secure)', function () {
    // Apaga a credencial padrão biz=1 do beforeEach pra ficar só esta:
    PaymentGatewayCredential::withoutGlobalScopes()->where('id', $this->credAsaas->id)->delete();

    PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'asaas',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Asaas sem token',
        'config_json'  => ['api_key' => 'só-api-key-sem-webhook'],
    ]);

    $response = $this->call(
        method: 'POST',
        uri: '/paymentgateway/webhooks/asaas/1',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ASAAS_ACCESS_TOKEN' => 'qualquer-coisa',
        ],
        content: json_encode(['event' => 'X']),
    );

    $response->assertStatus(401);
    expect(GatewayWebhookEvent::withoutGlobalScopes()->count())->toBe(0);
});

it('BÔNUS: businessId sem credencial cadastrada → 404 credential_not_found', function () {
    $response = $this->call(
        method: 'POST',
        uri: '/paymentgateway/webhooks/asaas/99999', // biz inexistente
        parameters: [],
        cookies: [],
        files: [],
        server: ['CONTENT_TYPE' => 'application/json'],
        content: json_encode(['event' => 'X']),
    );

    $response->assertStatus(404);
    $response->assertJson(['ok' => false, 'error' => 'credential_not_found']);
});
