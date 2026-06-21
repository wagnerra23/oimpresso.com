<?php

declare(strict_types=1);

use Eduardokum\LaravelBoleto\Boleto\Banco\Banrisul as BanrisulBoleto;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Cnab\Drivers\BanrisulCnabDriver;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Onda 4f.cnab/Banrisul — ADR 0170 (driver fino sobre CnabBoletoAdapter).
 *
 * Schema in-memory per test (não RefreshDatabase — migrations canon usam
 * ALTER TABLE MODIFY COLUMN ENUM MySQL-only). Pattern canon:
 * WebhookEndpointsTest + CnabBoletoAdapterContractTest (Onda 4f.0).
 *
 * Multi-tenant Tier 0: business_id=1 (ADR 0101 — nunca cliente real).
 */
function setupBanrisulCnabSchema(): void
{
    if (! Schema::hasTable('payment_gateway_credentials')) {
        Schema::create('payment_gateway_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('gateway_key', 30)->index();
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
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }
    setupBanrisulCnabSchema();
    session(['business.id' => 1]);
    Storage::fake('local');

    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'banrisul_cnab',
        'ambiente'     => 'production',
        'ativo'        => true,
        'nome_display' => 'Banrisul CNAB Test',
        'config_json'  => [
            'agencia'           => '1102',          // 4 dígitos típicos Banrisul
            'conta'             => '0099999',       // 7 dígitos
            'carteira'          => '1',             // '1' Cobrança Simples
            'codigo_cliente'    => '0001234567',    // 7 + 2 DV (formato vai pelo Util)
            'cedente_nome'      => 'Confeccao Gaucha LTDA',
            'cedente_documento' => '12345678000199',
            'cedente_endereco'  => 'Av. Ipiranga, 100',
            'cedente_cep'       => '90160091',
            'cedente_uf'        => 'RS',
            'cedente_cidade'    => 'Porto Alegre',
        ],
    ]);

    $this->driver = new BanrisulCnabDriver();
});

// ─── key / classe boleto / layout ────────────────────────────────────────

it('key() retorna banrisul_cnab', function () {
    expect($this->driver->key())->toBe('banrisul_cnab');
});

it('usa classe Boleto Banrisul da lib eduardokum', function () {
    // smoke: healthCheck ok prova que getBoletoClass() devolve classe que existe
    $h = $this->driver->healthCheck($this->cred);
    expect($h->ok)->toBeTrue();
});

it('opera CNAB layout 240 (FEBRABAN — circular 12/2020 descontinuou 400)', function () {
    $input = makeBanrisulInput();
    $result = $this->driver->emitirBoleto($input, $this->cred);

    expect($result->payloadGateway['layout'])->toBe(240);
});

// ─── supports ────────────────────────────────────────────────────────────

it('supports() aceita apenas boleto (CNAB = file-based)', function () {
    expect($this->driver->supports('boleto'))->toBeTrue();
    expect($this->driver->supports('pix_cob'))->toBeFalse();
    expect($this->driver->supports('pix_cobv'))->toBeFalse();
    expect($this->driver->supports('card'))->toBeFalse();
});

// ─── PIX / cartão / refund / consultar / webhook → throw ─────────────────

it('emitirPix lança DriverNotSupportedException', function () {
    expect(fn () => $this->driver->emitirPix(makeBanrisulInput(), $this->cred, 'cob'))
        ->toThrow(DriverNotSupportedException::class, 'PIX');
});

it('emitirPixAutomatico lança DriverNotSupportedException', function () {
    expect(fn () => $this->driver->emitirPixAutomatico(makeBanrisulInput(), $this->cred))
        ->toThrow(DriverNotSupportedException::class);
});

it('cobrarCartao lança DriverNotSupportedException', function () {
    $token = new CardToken(token: 't', brand: 'visa', lastFour: '4242', holderName: 'X', expMonth: '12', expYear: '2030');

    expect(fn () => $this->driver->cobrarCartao(makeBanrisulInput(), $this->cred, $token))
        ->toThrow(DriverNotSupportedException::class);
});

it('refund lança DriverNotSupportedException com explicação TED reverso', function () {
    $cobranca = (object) ['gateway_external_id' => 'X', 'tipo' => 'boleto'];

    expect(fn () => $this->driver->refund($cobranca, $this->cred, 1000, 'estorno'))
        ->toThrow(DriverNotSupportedException::class, 'TED reverso');
});

it('consultar lança DriverNotSupportedException apontando pra upload retorno', function () {
    $cobranca = (object) ['gateway_external_id' => '00012345'];

    expect(fn () => $this->driver->consultar($cobranca, $this->cred))
        ->toThrow(DriverNotSupportedException::class, 'upload');
});

it('processWebhook lança DriverNotSupportedException (CNAB sem webhook)', function () {
    expect(fn () => $this->driver->processWebhook(['x' => 1], $this->cred))
        ->toThrow(DriverNotSupportedException::class, 'webhook');
});

// ─── healthCheck ─────────────────────────────────────────────────────────

it('healthCheck OK quando config_json completo + Boleto instancia', function () {
    $h = $this->driver->healthCheck($this->cred);

    expect($h->ok)->toBeTrue();
    expect($h->status)->toBe('ok');
    expect($h->latencyMs)->toBeGreaterThanOrEqual(0);
});

it('healthCheck down quando falta codigo_cliente (campo-chave Banrisul)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'banrisul_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '1102',
            'conta'             => '0099999',
            'carteira'          => '1',
            // codigo_cliente FALTANDO — Banrisul exige
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->status)->toBe('down');
    expect($h->errorMessage)->toContain('codigo_cliente');
});

it('healthCheck down quando gateway_key não bate (tenta usar cred de outro banco)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter', // diferente de banrisul_cnab
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => ['client_id' => 'x', 'client_secret' => 'y'],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->errorMessage)->toContain('não bate');
});

// ─── emitirBoleto ────────────────────────────────────────────────────────

it('emitirBoleto gera nossoNumero Banrisul + grava remessa em Storage', function () {
    $input = makeBanrisulInput();
    $result = $this->driver->emitirBoleto($input, $this->cred);

    expect($result->tipo)->toBe('boleto');
    expect($result->gatewayExternalId)->not->toBe('');
    expect($result->nossoNumero)->not->toBe('');
    // Banrisul: nossoNumero é 8 dígitos sequenciais + 2 DV = 10 dígitos
    expect(strlen((string) $result->nossoNumero))->toBe(10);

    expect($result->payloadGateway)->toHaveKey('cnab_remessa_path');
    expect($result->payloadGateway['layout'])->toBe(240);
    expect($result->payloadGateway['gateway_key'])->toBe('banrisul_cnab');

    $path = $result->payloadGateway['cnab_remessa_path'];
    Storage::disk('local')->assertExists($path);

    // Path respeita scope multi-tenant Tier 0
    expect($path)->toContain("biz-1/cred-{$this->cred->id}/");
});

it('emitirBoleto lança erro quando config_json sem carteira', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'banrisul_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '1102',
            'conta'             => '0099999',
            'codigo_cliente'    => '0001234567',
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
            // carteira FALTANDO
        ],
    ]);

    expect(fn () => $this->driver->emitirBoleto(makeBanrisulInput(), $bad))
        ->toThrow(CredentialMisconfiguredException::class, 'carteira');
});

it('emitirBoleto valida tipo PaymentGatewayCredential (TypeError-style)', function () {
    $fake = (object) ['gateway_key' => 'banrisul_cnab', 'config_json' => []];

    expect(fn () => $this->driver->emitirBoleto(makeBanrisulInput(), $fake))
        ->toThrow(CredentialMisconfiguredException::class, 'PaymentGatewayCredential');
});

// ─── helpers ─────────────────────────────────────────────────────────────

function makeBanrisulInput(): EmitirCobrancaInput
{
    return new EmitirCobrancaInput(
        businessId: 1,
        contactId: 100,
        valorCentavos: 24990,
        vencimento: new DateTimeImmutable('+10 days'),
        descricao: 'Boleto Banrisul teste — confeccao gaucha',
        idempotencyKey: 'banrisul-cnab-test-001',
        meta: [
            'payer_cpf_cnpj' => '12345678900',
            'payer_name'     => 'Maria Pagadora',
            'payer_email'    => 'maria@test.local',
            'payer_address'  => 'Rua dos Andradas, 500',
            'payer_cep'      => '90020010',
            'payer_uf'       => 'RS',
            'payer_city'     => 'Porto Alegre',
        ],
    );
}
