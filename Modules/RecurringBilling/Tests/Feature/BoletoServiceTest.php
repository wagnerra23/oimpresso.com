<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\RecurringBilling\Models\BoletoCredential;
use Modules\RecurringBilling\Services\Boleto\BoletoService;
use Modules\RecurringBilling\Services\Boleto\Drivers\AsaasDriver;
use Modules\RecurringBilling\Services\Boleto\Drivers\C6Driver;
use Modules\RecurringBilling\Services\Boleto\Drivers\InterDriver;

uses(Tests\TestCase::class);

/**
 * Sem RefreshDatabase: migrations legadas UltimatePOS usam
 * ALTER TABLE ... MODIFY COLUMN ENUM (sintaxe MySQL-only) e quebram
 * em SQLite. Criamos só a tabela rb_boleto_credentials manualmente.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    \Illuminate\Support\Facades\Schema::dropIfExists('rb_boleto_credentials');
    \Illuminate\Support\Facades\Schema::create('rb_boleto_credentials', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->unsignedInteger('conta_bancaria_id')->nullable();
        $table->string('banco', 30);
        $table->string('ambiente', 20)->default('production');
        $table->boolean('ativo')->default(true);
        $table->string('nome_display')->nullable();
        $table->json('config_json');
        $table->timestamps();
    });
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        \Illuminate\Support\Facades\Schema::dropIfExists('rb_boleto_credentials');
    }
});

/**
 * US-RB-040 · Cobertura Pest dos 3 drivers (BoletoService).
 *
 * Trava o contrato de orquestração: dado um business_id, BoletoService
 * resolve o driver correto a partir de rb_boleto_credentials.banco e
 * descripta os campos sensíveis antes de instanciar o driver.
 *
 * Esse teste cobre o pattern definido em ADR tech/0007 (encryption pattern
 * de credenciais boleto) — sem ele, regressões silenciosas levam a falhas
 * de mTLS / API auth em produção.
 */

it('resolve InterDriver para credencial banco=inter', function () {
    BoletoCredential::create([
        'business_id' => 99,
        'banco'       => 'inter',
        'ambiente'    => 'sandbox',
        'ativo'       => true,
        'nome_display' => 'Inter PJ',
        'config_json' => [
            'client_id'             => 'inter-client-id',
            'client_secret'         => Crypt::encryptString('inter-secret'),
            'certificado_crt_b64'   => base64_encode("-----BEGIN CERTIFICATE-----\nDUMMY\n-----END CERTIFICATE-----"),
            'certificado_key_b64'   => Crypt::encryptString(base64_encode("-----BEGIN PRIVATE KEY-----\nDUMMY\n-----END PRIVATE KEY-----")),
        ],
    ]);

    $svc = new BoletoService();
    $reflect = (new ReflectionClass($svc))->getMethod('driver');
    $reflect->setAccessible(true);

    $driver = $reflect->invoke($svc, 99);

    expect($driver)->toBeInstanceOf(InterDriver::class);
});

it('resolve C6Driver para credencial banco=c6', function () {
    BoletoCredential::create([
        'business_id' => 100,
        'banco'       => 'c6',
        'ambiente'    => 'production',
        'ativo'       => true,
        'config_json' => ['agencia' => '0001', 'conta' => '12345', 'codigo_cliente' => '999'],
    ]);

    $svc = new BoletoService();
    $reflect = (new ReflectionClass($svc))->getMethod('driver');
    $reflect->setAccessible(true);

    expect($reflect->invoke($svc, 100))->toBeInstanceOf(C6Driver::class);
});

it('resolve AsaasDriver para credencial banco=asaas', function () {
    BoletoCredential::create([
        'business_id' => 101,
        'banco'       => 'asaas',
        'ambiente'    => 'sandbox',
        'ativo'       => true,
        'config_json' => ['api_key' => Crypt::encryptString('$aact_test_key')],
    ]);

    $svc = new BoletoService();
    $reflect = (new ReflectionClass($svc))->getMethod('driver');
    $reflect->setAccessible(true);

    expect($reflect->invoke($svc, 101))->toBeInstanceOf(AsaasDriver::class);
});

it('decryptConfig descriptografa campos sensíveis (client_secret, api_key, certificado_key_b64)', function () {
    $cred = BoletoCredential::create([
        'business_id' => 200,
        'banco'       => 'inter',
        'ambiente'    => 'production',
        'ativo'       => true,
        'config_json' => [
            'client_id'             => 'public-id-not-encrypted',
            'client_secret'         => Crypt::encryptString('plaintext-secret'),
            'api_key'               => Crypt::encryptString('plaintext-api-key'),
            'certificado_senha'     => Crypt::encryptString('cert-pass'),
            'certificado_crt_b64'   => base64_encode('public-cert-content'),
            'certificado_key_b64'   => Crypt::encryptString(base64_encode('private-key-pem')),
        ],
    ]);

    $svc = new BoletoService();
    $reflect = (new ReflectionClass($svc))->getMethod('decryptConfig');
    $reflect->setAccessible(true);

    $config = $reflect->invoke($svc, $cred);

    expect($config['client_id'])->toBe('public-id-not-encrypted')
        ->and($config['client_secret'])->toBe('plaintext-secret')
        ->and($config['api_key'])->toBe('plaintext-api-key')
        ->and($config['certificado_senha'])->toBe('cert-pass')
        ->and($config['certificado_crt_b64'])->toBe(base64_encode('public-cert-content'))
        ->and(base64_decode($config['certificado_key_b64']))->toBe('private-key-pem');
});

it('lança exceção quando business não tem credencial ativa', function () {
    $svc = new BoletoService();
    $reflect = (new ReflectionClass($svc))->getMethod('driver');
    $reflect->setAccessible(true);

    expect(fn () => $reflect->invoke($svc, 999))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('ignora credenciais inativas ao resolver', function () {
    BoletoCredential::create([
        'business_id' => 300,
        'banco'       => 'asaas',
        'ambiente'    => 'sandbox',
        'ativo'       => false,
        'config_json' => ['api_key' => Crypt::encryptString('inactive-key')],
    ]);

    $svc = new BoletoService();
    $reflect = (new ReflectionClass($svc))->getMethod('driver');
    $reflect->setAccessible(true);

    expect(fn () => $reflect->invoke($svc, 300))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
