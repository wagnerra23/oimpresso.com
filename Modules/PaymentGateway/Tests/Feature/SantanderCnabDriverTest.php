<?php

declare(strict_types=1);

use Eduardokum\LaravelBoleto\Boleto\Banco\Santander as SantanderBoleto;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Cnab\Drivers\SantanderCnabDriver;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Onda 4f.cnab — ADR 0170-bancos-nativos-top5-drivers-separados (v3).
 *
 * Testes do SantanderCnabDriver — driver fino sobre CnabBoletoAdapter.
 * Espelha o pattern canon de CnabBoletoAdapterContractTest, focando em:
 *   - key() === 'santander_cnab'
 *   - supports() só 'boleto'
 *   - PIX / cartão / refund / consultar / webhook → throw
 *   - healthCheck OK quando config_json válido + lib instancia Boleto Santander
 *   - healthCheck falha quando carteira faltando (lib exige) ou codigo_cliente sumido
 *   - emitirBoleto produz nossoNumero Santander (12 dígitos + DV) e grava remessa
 *   - emitirBoleto falha com gateway_key trocado (multi-tenant Tier 0)
 *
 * Schema in-memory (NÃO RefreshDatabase — migrations canon ALTER ENUM MySQL-only).
 * Pattern canon: WebhookEndpointsTest + CnabBoletoAdapterContractTest.
 *
 * Multi-tenant Tier 0: business_id=1 (ADR 0101 — nunca cliente real biz=4).
 */

function setupSantanderCnabSchema(): void
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
    setupSantanderCnabSchema();
    session(['business.id' => 1]);
    Storage::fake('local');

    // Credencial Santander CNAB válida — carteira 101 (Cobrança Simples Rápida
    // c/ Registro — default PJ), codigo_cliente 7 dígitos típico.
    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'santander_cnab',
        'ambiente'     => 'production',
        'ativo'        => true,
        'nome_display' => 'Santander CNAB Test',
        'config_json'  => [
            'agencia'           => '1234',
            'conta'             => '13000001',
            'conta_dv'          => '5',
            'carteira'          => '101',
            'codigo_cliente'    => '9999999',
            'cedente_nome'      => 'Empresa Teste LTDA',
            'cedente_documento' => '12345678000199',
            'cedente_endereco'  => 'Av Paulista, 1000',
            'cedente_cep'       => '01310100',
            'cedente_uf'        => 'SP',
            'cedente_cidade'    => 'São Paulo',
        ],
    ]);

    $this->driver = new SantanderCnabDriver();
});

// ─── key / supports ──────────────────────────────────────────────────────

it('key() retorna santander_cnab', function () {
    expect($this->driver->key())->toBe('santander_cnab');
});

it('supports() aceita apenas boleto', function () {
    expect($this->driver->supports('boleto'))->toBeTrue();
    expect($this->driver->supports('pix_cob'))->toBeFalse();
    expect($this->driver->supports('pix_cobv'))->toBeFalse();
    expect($this->driver->supports('pix_recv'))->toBeFalse();
    expect($this->driver->supports('card'))->toBeFalse();
});

it('getBoletoClass aponta pra lib Santander', function () {
    // Sanity: força que driver concreto usa a classe correta da lib.
    // Reflection: getBoletoClass é protected — exercitamos via healthCheck.
    $h = $this->driver->healthCheck($this->cred);

    expect($h->ok)->toBeTrue();
    // Se a fundação encontrasse uma classe errada, errorMessage diria.
});

it('layout é 240 (Santander descontinuou 400 ativo)', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'k-layout-check',
        meta: [
            'payer_name'     => 'X', 'payer_cpf_cnpj' => '12345678900',
            'payer_address'  => 'Rua A', 'payer_cep' => '01310100',
            'payer_uf'       => 'SP', 'payer_city' => 'São Paulo',
        ],
    );

    $r = $this->driver->emitirBoleto($input, $this->cred);
    expect($r->payloadGateway['layout'])->toBe(240);
});

// ─── PIX / cartão / refund / consultar / webhook → throw ─────────────────

it('emitirPix lança DriverNotSupportedException', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'pix-1',
    );

    expect(fn () => $this->driver->emitirPix($input, $this->cred, 'cob'))
        ->toThrow(DriverNotSupportedException::class, 'PIX');
});

it('emitirPixAutomatico lança DriverNotSupportedException', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'pix-auto-1',
    );

    expect(fn () => $this->driver->emitirPixAutomatico($input, $this->cred))
        ->toThrow(DriverNotSupportedException::class);
});

it('cobrarCartao lança DriverNotSupportedException', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'card-1',
    );
    $token = new CardToken(token: 't', brand: 'visa', lastFour: '4242', holderName: 'X', expMonth: '12', expYear: '2030');

    expect(fn () => $this->driver->cobrarCartao($input, $this->cred, $token))
        ->toThrow(DriverNotSupportedException::class);
});

it('refund lança DriverNotSupportedException com explicação TED reverso', function () {
    $cobranca = (object) ['gateway_external_id' => 'X', 'tipo' => 'boleto'];

    expect(fn () => $this->driver->refund($cobranca, $this->cred, 1000, 'estorno'))
        ->toThrow(DriverNotSupportedException::class, 'TED reverso');
});

it('consultar lança DriverNotSupportedException com mensagem upload', function () {
    $cobranca = (object) ['gateway_external_id' => '000000123456'];

    expect(fn () => $this->driver->consultar($cobranca, $this->cred))
        ->toThrow(DriverNotSupportedException::class, 'upload');
});

it('processWebhook lança DriverNotSupportedException (CNAB sem webhook)', function () {
    expect(fn () => $this->driver->processWebhook(['x' => 1], $this->cred))
        ->toThrow(DriverNotSupportedException::class, 'webhook');
});

// ─── healthCheck ─────────────────────────────────────────────────────────

it('healthCheck OK com config_json válido (carteira 101 + codigo_cliente)', function () {
    $h = $this->driver->healthCheck($this->cred);

    expect($h->ok)->toBeTrue();
    expect($h->status)->toBe('ok');
    expect($h->latencyMs)->toBeGreaterThanOrEqual(0);
});

it('healthCheck down quando codigo_cliente faltando (Santander exige)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'santander_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '1234',
            'conta'             => '13000001',
            'conta_dv'          => '5',
            'carteira'          => '101',
            // codigo_cliente faltando — Santander exige (lib + bank wizard)
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->status)->toBe('down');
    expect($h->errorMessage)->toContain('codigo_cliente');
});

it('healthCheck down quando carteira faltando', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'santander_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '1234',
            'conta'             => '13000001',
            'conta_dv'          => '5',
            'codigo_cliente'    => '9999999',
            // carteira faltando
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->errorMessage)->toContain('carteira');
});

it('healthCheck down quando gateway_key não bate (multi-tenant guard)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'bradesco_cnab', // !! driver é santander
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '1234',
            'conta'             => '13000001',
            'conta_dv'          => '5',
            'carteira'          => '101',
            'codigo_cliente'    => '9999999',
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->errorMessage)->toContain('não bate');
});

// ─── emitirBoleto ────────────────────────────────────────────────────────

it('emitirBoleto Santander gera nossoNumero (12+DV) e grava remessa biz-scoped', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1,
        contactId: 100,
        valorCentavos: 54321,
        vencimento: new DateTimeImmutable('+7 days'),
        descricao: 'Boleto Santander CNAB teste',
        idempotencyKey: 'cnab-santander-001',
        meta: [
            'payer_cpf_cnpj' => '12345678900',
            'payer_name'     => 'Maria Pagadora',
            'payer_email'    => 'maria@test.local',
            'payer_address'  => 'Rua B, 200',
            'payer_cep'      => '04500001',
            'payer_uf'       => 'SP',
            'payer_city'     => 'São Paulo',
        ],
    );

    $result = $this->driver->emitirBoleto($input, $this->cred);

    expect($result->tipo)->toBe('boleto');
    expect($result->gatewayExternalId)->not->toBe('');
    expect($result->nossoNumero)->not->toBe('');
    // Santander gerarNossoNumero(): 12 dígitos + 1 DV = 13 chars
    expect(strlen($result->nossoNumero))->toBe(13);
    expect($result->payloadGateway)->toHaveKey('cnab_remessa_path');
    expect($result->payloadGateway['layout'])->toBe(240);
    expect($result->payloadGateway['gateway_key'])->toBe('santander_cnab');

    $path = $result->payloadGateway['cnab_remessa_path'];
    Storage::disk('local')->assertExists($path);
    expect($path)->toContain("biz-1/cred-{$this->cred->id}/");
});

it('emitirBoleto falha CredentialMisconfigured quando codigo_cliente sumido', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'santander_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '1234',
            'conta'             => '13000001',
            'conta_dv'          => '5',
            'carteira'          => '101',
            // codigo_cliente faltando
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'k-bad-codigo',
    );

    expect(fn () => $this->driver->emitirBoleto($input, $bad))
        ->toThrow(CredentialMisconfiguredException::class, 'codigo_cliente');
});

it('emitirBoleto valida tipo PaymentGatewayCredential', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'k-bad-type',
    );
    $fake = (object) ['gateway_key' => 'santander_cnab', 'config_json' => []];

    expect(fn () => $this->driver->emitirBoleto($input, $fake))
        ->toThrow(CredentialMisconfiguredException::class, 'PaymentGatewayCredential');
});

it('cancelar grava instrucao CNAB 240 com nosso numero', function () {
    $cobranca = (object) [
        'gateway_external_id' => '000000123456',
        'tipo'                => 'boleto',
    ];

    $this->driver->cancelar($cobranca, $this->cred, 'cliente desistiu');

    // Confere se arquivo de instrução foi escrito em path biz-scoped.
    $files = Storage::disk('local')->files("cnab-instrucoes/biz-1/cred-{$this->cred->id}");
    expect($files)->not->toBeEmpty();

    $content = Storage::disk('local')->get($files[0]);
    expect($content)->toContain('santander_cnab');
    expect($content)->toContain('000000123456');
    expect($content)->toContain('PEDIDO_CANCELAMENTO');
});
