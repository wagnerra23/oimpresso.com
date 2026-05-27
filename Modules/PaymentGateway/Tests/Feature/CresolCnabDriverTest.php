<?php

declare(strict_types=1);

use Eduardokum\LaravelBoleto\Boleto\Banco\Cresol as CresolBoleto;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Cnab\Drivers\CresolCnabDriver;

uses(Tests\TestCase::class);

/**
 * Onda 4f.cnab/Cresol — ADR 0170-bancos-nativos-top5-drivers-separados (v3).
 *
 * Cobre o driver fino `CresolCnabDriver` (extends CnabBoletoAdapter fundação 4f.0):
 *   - key() === 'cresol_cnab'
 *   - getBoletoClass() === Cresol::class (lib eduardokum/laravel-boleto)
 *   - getLayoutVersion() === 240
 *   - camposObrigatoriosCnab subset Cresol (sem cooperativa/posto — lib não exige)
 *   - emitirBoleto gera nossoNumero 12 dígitos (11 + DV CalculoDV::cresolNossoNumero)
 *   - emitirBoleto grava remessa em Storage com scope multi-tenant biz-X/cred-Y
 *   - healthCheck OK com config válida; down quando faltando carteira
 *   - PIX/cartão/refund/consultar/webhook throw DriverNotSupportedException
 *     (CNAB = file-based, herdado de CnabBoletoAdapter)
 *
 * Schema in-memory per test (pattern canon WebhookEndpointsTest +
 * CnabBoletoAdapterContractTest — migrations canon usam ENUM MODIFY MySQL-only).
 *
 * Multi-tenant Tier 0: business_id=1 (ADR 0101 — nunca cliente real).
 */
function setupCresolCnabSchema(): void
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
    setupCresolCnabSchema();
    session(['business.id' => 1]);
    Storage::fake('local');

    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'cresol_cnab',
        'ambiente'     => 'production',
        'ativo'        => true,
        'nome_display' => 'Cresol CNAB Test',
        'config_json'  => [
            'agencia'           => '0101', // 4 dígitos — agência cooperativa Cresol Central
            'conta'             => '1234567', // 7 dígitos conta cooperado
            'carteira'          => '09', // única aceita pela lib Cresol
            'cedente_nome'      => 'Cooperado Teste LTDA',
            'cedente_documento' => '12345678000199',
            'cedente_endereco'  => 'Rua Cooperativa, 100',
            'cedente_cep'       => '85807100',
            'cedente_uf'        => 'PR',
            'cedente_cidade'    => 'Cascavel',
        ],
    ]);

    $this->driver = new CresolCnabDriver();
});

// ─── identidade do driver ────────────────────────────────────────────────

it('key() retorna cresol_cnab', function () {
    expect($this->driver->key())->toBe('cresol_cnab');
});

it('supports() aceita apenas boleto (CNAB herdado)', function () {
    expect($this->driver->supports('boleto'))->toBeTrue();
    expect($this->driver->supports('pix_cob'))->toBeFalse();
    expect($this->driver->supports('pix_cobv'))->toBeFalse();
    expect($this->driver->supports('card'))->toBeFalse();
});

it('usa classe Boleto Cresol da lib eduardokum/laravel-boleto', function () {
    $reflection = new ReflectionClass($this->driver);
    $method = $reflection->getMethod('getBoletoClass');
    $method->setAccessible(true);

    expect($method->invoke($this->driver))->toBe(CresolBoleto::class);
});

it('usa layout CNAB 240 (Febraban moderno)', function () {
    $reflection = new ReflectionClass($this->driver);
    $method = $reflection->getMethod('getLayoutVersion');
    $method->setAccessible(true);

    expect($method->invoke($this->driver))->toBe(240);
});

it('camposObrigatoriosCnab Cresol não exige cooperativa/posto separados', function () {
    $reflection = new ReflectionClass($this->driver);
    $method = $reflection->getMethod('camposObrigatoriosCnab');
    $method->setAccessible(true);

    $campos = $method->invoke($this->driver);

    expect($campos)->toContain('agencia');
    expect($campos)->toContain('conta');
    expect($campos)->toContain('carteira');
    expect($campos)->toContain('cedente_nome');
    expect($campos)->toContain('cedente_documento');
    // Lib Cresol não usa cooperativa/posto (diferente de Sicredi).
    expect($campos)->not->toContain('cooperativa');
    expect($campos)->not->toContain('posto');
});

// ─── emitirBoleto (caminho feliz) ────────────────────────────────────────

it('emitirBoleto gera nossoNumero Cresol (12 dígitos = 11 + DV) e grava remessa', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1,
        contactId: 100,
        valorCentavos: 25000, // R$ 250,00
        vencimento: new DateTimeImmutable('+7 days'),
        descricao: 'Mensalidade cooperado Cresol',
        idempotencyKey: 'cresol-test-001',
        meta: [
            'payer_cpf_cnpj' => '12345678900',
            'payer_name'     => 'Maria Cooperada',
            'payer_email'    => 'maria@test.local',
            'payer_address'  => 'Rua B, 200',
            'payer_cep'      => '85807200',
            'payer_uf'       => 'PR',
            'payer_city'     => 'Cascavel',
        ],
    );

    $result = $this->driver->emitirBoleto($input, $this->cred);

    expect($result->tipo)->toBe('boleto');
    expect($result->gatewayExternalId)->not->toBe('');
    expect($result->nossoNumero)->not->toBe('');
    // Cresol::gerarNossoNumero retorna 11 + 1 DV = 12 dígitos.
    expect(strlen($result->nossoNumero))->toBe(12);
    expect($result->payloadGateway['layout'])->toBe(240);
    expect($result->payloadGateway['gateway_key'])->toBe('cresol_cnab');

    // Remessa gravada em Storage::fake('local') com scope multi-tenant.
    $path = $result->payloadGateway['cnab_remessa_path'];
    Storage::disk('local')->assertExists($path);
    expect($path)->toContain("biz-1/cred-{$this->cred->id}/");
});

// ─── healthCheck ─────────────────────────────────────────────────────────

it('healthCheck OK com config válida (carteira 09 + agencia + conta + cedente)', function () {
    $h = $this->driver->healthCheck($this->cred);

    expect($h->ok)->toBeTrue();
    expect($h->status)->toBe('ok');
    expect($h->latencyMs)->toBeGreaterThanOrEqual(0);
});

it('healthCheck down quando carteira ausente', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'cresol_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '0101',
            'conta'             => '1234567',
            // carteira faltando
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->status)->toBe('down');
    expect($h->errorMessage)->toContain('carteira');
});

it('healthCheck down quando gateway_key não bate (segurança Tier 0)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'bradesco_cnab', // !! não bate com cresol_cnab
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '0101',
            'conta'             => '1234567',
            'carteira'          => '09',
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->errorMessage)->toContain('não bate');
});

// ─── não-suportados (herdados CnabBoletoAdapter) ─────────────────────────

it('emitirPix throw DriverNotSupportedException (Cresol CNAB não tem PIX)', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'k-pix',
    );

    expect(fn () => $this->driver->emitirPix($input, $this->cred, 'cob'))
        ->toThrow(DriverNotSupportedException::class, 'PIX');
});

it('consultar throw com mensagem de upload retorno (CNAB file-based)', function () {
    $cobranca = (object) ['gateway_external_id' => '00000000123'];

    expect(fn () => $this->driver->consultar($cobranca, $this->cred))
        ->toThrow(DriverNotSupportedException::class, 'upload');
});

it('processWebhook throw (Cresol CNAB não tem webhook)', function () {
    expect(fn () => $this->driver->processWebhook(['x' => 1], $this->cred))
        ->toThrow(DriverNotSupportedException::class, 'webhook');
});

it('refund throw com explicação TED reverso', function () {
    $cobranca = (object) ['gateway_external_id' => 'X', 'tipo' => 'boleto'];

    expect(fn () => $this->driver->refund($cobranca, $this->cred, 1000, 'estorno'))
        ->toThrow(DriverNotSupportedException::class, 'TED reverso');
});

it('emitirBoleto sem agencia lança CredentialMisconfiguredException', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'cresol_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            // agencia faltando
            'conta'             => '1234567',
            'carteira'          => '09',
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'k-no-ag',
    );

    expect(fn () => $this->driver->emitirBoleto($input, $bad))
        ->toThrow(CredentialMisconfiguredException::class, 'agencia');
});
