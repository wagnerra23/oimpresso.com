<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Models\BoletoCredential;
use Modules\RecurringBilling\Services\Boleto\BoletoCredentialResolver;

uses(Tests\TestCase::class);

/**
 * Wave 18 RETRY D4 — BoletoCredentialResolver extraído de BoletoService.
 *
 * Cobre: resolve() decifrando campos sensíveis + resolveDriverName() fail-safe.
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 (ADR 0101).
 *
 * @see Modules\RecurringBilling\Services\Boleto\BoletoCredentialResolver
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
    // Spatie Activitylog precisa de tabela activity_log; criar in-memory pra
    // BoletoCredential::create disparar sem QueryException.
    config()->set('activitylog.enabled', false);

    if (config('database.default') !== 'sqlite' && ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Smoke test rodado apenas em SQLite in-memory.');
    }

    Schema::dropIfExists('rb_boleto_credentials');
    Schema::create('rb_boleto_credentials', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('banco', 20);
        $t->string('ambiente', 20)->default('production');
        $t->boolean('ativo')->default(true);
        $t->json('config_json')->nullable();
        $t->unsignedBigInteger('conta_bancaria_id')->nullable();
        $t->string('nome_display')->nullable();
        $t->timestamps();
    });

    // activity_log table fallback (Spatie LogsActivity ATIVA em BoletoCredential).
    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function ($t) {
            $t->id();
            $t->string('log_name')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('causer_id')->nullable();
            $t->string('causer_type')->nullable();
            $t->json('properties')->nullable();
            $t->string('event')->nullable();
            $t->uuid('batch_uuid')->nullable();
            $t->timestamps();
        });
    }
});

afterEach(function () {
    Schema::dropIfExists('rb_boleto_credentials');
});

it('D4 — resolve() retorna config decifrado com api_key plain', function () {
    BoletoCredential::create([
        'business_id' => 1,
        'banco'       => 'asaas',
        'ambiente'    => 'sandbox',
        'ativo'       => true,
        'config_json' => [
            'api_key'  => Crypt::encryptString('plain-api-key-123'),
            'webhook_token' => 'public-token',
        ],
    ]);

    $resolver = new BoletoCredentialResolver();
    $result = $resolver->resolve(1);

    expect($result['banco'])->toBe('asaas');
    expect($result['ambiente'])->toBe('sandbox');
    expect($result['config']['api_key'])->toBe('plain-api-key-123');
    expect($result['config']['webhook_token'])->toBe('public-token'); // não-sensível, não decifra
});

it('D4 — resolve() decifra TODOS os 4 campos sensíveis', function () {
    BoletoCredential::create([
        'business_id' => 1,
        'banco'       => 'inter',
        'ativo'       => true,
        'config_json' => [
            'client_secret'        => Crypt::encryptString('secret-abc'),
            'api_key'              => Crypt::encryptString('key-def'),
            'certificado_senha'    => Crypt::encryptString('passwd-ghi'),
            'certificado_key_b64'  => Crypt::encryptString('base64-key-jkl'),
        ],
    ]);

    $resolver = new BoletoCredentialResolver();
    $result = $resolver->resolve(1);

    expect($result['config']['client_secret'])->toBe('secret-abc');
    expect($result['config']['api_key'])->toBe('key-def');
    expect($result['config']['certificado_senha'])->toBe('passwd-ghi');
    expect($result['config']['certificado_key_b64'])->toBe('base64-key-jkl');
});

it('D4 — resolve() lança ModelNotFoundException quando biz sem credencial ativa', function () {
    $resolver = new BoletoCredentialResolver();

    expect(fn () => $resolver->resolve(99))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('D4 — resolveDriverName() retorna unknown fail-safe sem credencial', function () {
    $resolver = new BoletoCredentialResolver();

    expect($resolver->resolveDriverName(99))->toBe('unknown');
});

it('D4 — resolveDriverName() retorna nome banco sem custo Crypt', function () {
    BoletoCredential::create([
        'business_id' => 1,
        'banco'       => 'inter',
        'ativo'       => true,
        'config_json' => ['client_secret' => Crypt::encryptString('plain')],
    ]);

    $resolver = new BoletoCredentialResolver();
    expect($resolver->resolveDriverName(1))->toBe('inter');
});

it('D1 — resolve() biz=99 NÃO vaza credencial de biz=1 (isolamento Tier 0)', function () {
    BoletoCredential::create([
        'business_id' => 1,
        'banco'       => 'asaas',
        'ativo'       => true,
        'config_json' => ['api_key' => Crypt::encryptString('biz1-key')],
    ]);

    $resolver = new BoletoCredentialResolver();

    expect(fn () => $resolver->resolve(99))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('D9.a — BoletoCredentialResolver wrap em OtelHelper::spanBiz', function () {
    $source = file_get_contents(__DIR__ . '/../../Services/Boleto/BoletoCredentialResolver.php');

    expect($source)->toContain('use App\Util\OtelHelper');
    expect($source)->toContain("OtelHelper::spanBiz('rb.boleto.credential.resolve'");
});

it('D4 — decryptConfig ignora campos ausentes graciosamente (back-compat)', function () {
    $resolver = new BoletoCredentialResolver();

    // Config com apenas alguns campos sensíveis presentes
    $partial = ['api_key' => Crypt::encryptString('plain-key'), 'webhook_token' => 'public'];
    $result = $resolver->decryptConfig($partial);

    expect($result['api_key'])->toBe('plain-key');
    expect($result['webhook_token'])->toBe('public');

    // Config completamente vazia não explode
    expect($resolver->decryptConfig([]))->toBe([]);
});
