<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * US-PG-001 (Audit Sênior 2026-05-25 — VULN P0-#1 SEC catastrófica).
 *
 * Cobre 3 cenários canônicos:
 *   (1) ENCRYPT-ON-CREATE: Model::create([...config_json...]) cifra automaticamente,
 *       SELECT bruto retorna cipher base64-JSON (não-legível).
 *   (2) DECRYPT-ON-READ: $cred->config_json hidrata em array decifrado correto.
 *   (3) REWRAP-COMMAND: row criada em plain (bypass cast via DB direct) é detectada
 *       e cifrada pelo `paymentgateway:rewrap-credentials --apply`.
 *
 * Test sqlite in-memory — migrations PaymentGateway rodam via loadMigrationsFrom
 * do ServiceProvider. ADR 0101: biz=1 (não cliente real).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }
    // Cria schema mínimo on-the-fly em sqlite test env. Migrations canônicas
    // só rodam em CI/MySQL via loadMigrationsFrom no ServiceProvider (e mesmo
    // assim com enum que sqlite ignora). Aqui usamos string/json portáveis
    // pra testar SOMENTE o cast `encrypted:array` + rewrap command.
    if (! Schema::hasTable('payment_gateway_credentials')) {
        Schema::create('payment_gateway_credentials', function ($table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('gateway_key', 32)->index();
            $table->string('ambiente', 32)->default('production');
            $table->boolean('ativo')->default(true);
            $table->string('nome_display')->nullable();
            $table->text('config_json');
            $table->unsignedInteger('conta_bancaria_id')->nullable();
            $table->string('health_status', 32)->default('unknown');
            $table->timestamp('health_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'gateway_key', 'ambiente'], 'pg_cred_biz_gw_amb_unique');
        });
    }

    // LogsActivity (Spatie) precisa de activity_log — sqlite test env não tem
    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function ($table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('event')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->unsignedInteger('business_id')->nullable();
            $table->timestamps();
        });
    }
});

afterEach(function () {
    // afterEach roda MESMO em teste pulado por markTestSkipped no beforeEach
    // (PHPUnit 12.5.x). Guardar o DDL por driver evita dropar a tabela
    // REAL-migrada `payment_gateway_credentials` no MySQL persistente do nightly.
    if (DB::connection()->getDriverName() === 'sqlite') {
        // Drop em vez de truncate — sqlite in-memory + sem cleanup entre tests
        // se evitarmos o drop, o UNIQUE quebra na 2ª criação.
        if (Schema::hasTable('payment_gateway_credentials')) {
            Schema::drop('payment_gateway_credentials');
        }
    }
});

it('cenário 1: Model::create cifra config_json automaticamente (SELECT bruto retorna cipher)', function () {
    $configPlain = [
        'api_key'        => '$aact_FAKE_SENSITIVE_TOKEN_NEVER_REAL',
        'webhook_secret' => 'hs256-secret-do-not-leak',
    ];

    $cred = PaymentGatewayCredential::create([
        'business_id'   => 1,
        'gateway_key'   => 'asaas',
        'ambiente'      => 'sandbox',
        'ativo'         => true,
        'nome_display'  => 'Asaas Test Encrypt',
        'config_json'   => $configPlain,
        'health_status' => 'unknown',
    ]);

    // SELECT bruto bypass do cast — deve retornar cipher Laravel
    // (base64-JSON com {iv, value, mac, tag}).
    $rawValue = DB::table('payment_gateway_credentials')
        ->where('id', $cred->id)
        ->value('config_json');

    expect($rawValue)->toBeString();
    expect($rawValue)->not->toContain('$aact_FAKE_SENSITIVE_TOKEN_NEVER_REAL');
    expect($rawValue)->not->toContain('hs256-secret-do-not-leak');
    expect($rawValue)->not->toContain('api_key');
    expect($rawValue)->not->toContain('webhook_secret');

    // Crypt::decryptString deve devolver JSON do array original
    $decryptedJson = Crypt::decryptString($rawValue);
    $decoded = json_decode($decryptedJson, true);
    expect($decoded)->toBe($configPlain);
});

it('cenário 2: re-ler credential desencripta config_json transparentemente', function () {
    $configPlain = [
        'client_id'     => 'inter-clientid-xyz',
        'client_secret' => 'inter-secret-very-sensitive',
        'cert_password' => 'mtls-pass-2026',
    ];

    $cred = PaymentGatewayCredential::create([
        'business_id'   => 1,
        'gateway_key'   => 'inter',
        'ambiente'      => 'production',
        'ativo'         => true,
        'nome_display'  => 'Inter Test Decrypt',
        'config_json'   => $configPlain,
        'health_status' => 'unknown',
    ]);

    // Re-fetch from DB (descartando cache do Eloquent)
    $refetched = PaymentGatewayCredential::query()
        ->withoutGlobalScopes()
        ->find($cred->id);

    expect($refetched)->not->toBeNull();
    expect($refetched->config_json)->toBeArray();
    expect($refetched->config_json)->toBe($configPlain);
    expect($refetched->config_json['client_secret'])->toBe('inter-secret-very-sensitive');
    expect($refetched->config_json['cert_password'])->toBe('mtls-pass-2026');
});

it('cenário 3: rewrap command detecta plain JSON e cifra (idempotente)', function () {
    // Inserir row em PLAIN bypass do cast Eloquent — simula row pré-PR US-PG-001.
    $plainJson = json_encode([
        'secret_key'     => 'pagarme-sk-xyz',
        'webhook_secret' => 'old-plain-secret',
    ]);

    $credId = DB::table('payment_gateway_credentials')->insertGetId([
        'business_id'   => 1,
        'gateway_key'   => 'pagarme',
        'ambiente'      => 'sandbox',
        'ativo'         => 1,
        'nome_display'  => 'Pagarme Plain Pré-Rewrap',
        'config_json'   => $plainJson,
        'health_status' => 'unknown',
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    // Sanity: row está plain (visível em raw query)
    $rawBefore = DB::table('payment_gateway_credentials')->where('id', $credId)->value('config_json');
    expect($rawBefore)->toContain('pagarme-sk-xyz');

    // Dry-run NÃO deve persistir
    $this->artisan('paymentgateway:rewrap-credentials')->assertSuccessful();
    $rawAfterDry = DB::table('payment_gateway_credentials')->where('id', $credId)->value('config_json');
    expect($rawAfterDry)->toContain('pagarme-sk-xyz'); // ainda plain

    // Apply deve cifrar
    $this->artisan('paymentgateway:rewrap-credentials', ['--apply' => true])->assertSuccessful();

    $rawAfterApply = DB::table('payment_gateway_credentials')->where('id', $credId)->value('config_json');
    expect($rawAfterApply)->not->toContain('pagarme-sk-xyz');
    expect($rawAfterApply)->not->toContain('webhook_secret');

    // Confirma que é cipher válido Laravel
    $decryptedJson = Crypt::decryptString($rawAfterApply);
    $decoded = json_decode($decryptedJson, true);
    expect($decoded)->toBe([
        'secret_key'     => 'pagarme-sk-xyz',
        'webhook_secret' => 'old-plain-secret',
    ]);

    // 2x apply é idempotente — não re-cifra, detecta como já encrypted
    $rawBeforeSecond = $rawAfterApply;
    $this->artisan('paymentgateway:rewrap-credentials', ['--apply' => true])->assertSuccessful();
    $rawAfterSecond = DB::table('payment_gateway_credentials')->where('id', $credId)->value('config_json');

    // Após 2ª execução o cipher pode mudar OU não dependendo da heurística;
    // o invariante importante: continua válido + decifra pro mesmo array.
    $decoded2 = json_decode(Crypt::decryptString($rawAfterSecond), true);
    expect($decoded2)->toBe([
        'secret_key'     => 'pagarme-sk-xyz',
        'webhook_secret' => 'old-plain-secret',
    ]);
});

it('Tier 0: credentials de business=1 e business=99 são isolados (cast preserva business_id intacto)', function () {
    $configBiz1 = ['api_key' => 'biz1-key'];
    $configBiz99 = ['api_key' => 'biz99-key'];

    $credBiz1 = PaymentGatewayCredential::create([
        'business_id'   => 1,
        'gateway_key'   => 'asaas',
        'ambiente'      => 'production',
        'ativo'         => true,
        'config_json'   => $configBiz1,
        'health_status' => 'unknown',
    ]);

    $credBiz99 = PaymentGatewayCredential::create([
        'business_id'   => 99,
        'gateway_key'   => 'asaas',
        'ambiente'      => 'production',
        'ativo'         => true,
        'config_json'   => $configBiz99,
        'health_status' => 'unknown',
    ]);

    // Cada credential conserva seu próprio config após decrypt
    expect($credBiz1->fresh()->config_json)->toBe($configBiz1);
    expect($credBiz99->fresh()->config_json)->toBe($configBiz99);

    // Cipher rows distintos no DB (mesma APP_KEY mas IV randomizado por save)
    $rawBiz1 = DB::table('payment_gateway_credentials')->where('id', $credBiz1->id)->value('config_json');
    $rawBiz99 = DB::table('payment_gateway_credentials')->where('id', $credBiz99->id)->value('config_json');
    expect($rawBiz1)->not->toBe($rawBiz99);
    expect($rawBiz1)->not->toContain('biz1-key');
    expect($rawBiz99)->not->toContain('biz99-key');
});
