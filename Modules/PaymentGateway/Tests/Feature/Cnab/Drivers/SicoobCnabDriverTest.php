<?php

declare(strict_types=1);

use Eduardokum\LaravelBoleto\Boleto\Banco\Bancoob as BancoobBoleto;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Cnab\Drivers\SicoobCnabDriver;

uses(Tests\TestCase::class);

/**
 * Onda 4f.cnab/Sicoob — ADR 0170 — driver fino sobre CnabBoletoAdapter.
 *
 * Testa APENAS especificidades Sicoob (carteira ['1','3'], layout 240,
 * camposObrigatorios cooperativa com convenio+modalidade, FQCN lib legacy
 * Bancoob == mesma instituição Sicoob). Contract genérico já é coberto
 * em CnabBoletoAdapterContractTest.php (fundação 4f.0).
 *
 * Sicoob (nome atual 2026) == Bancoob (nome legacy lib) — mesma cooperativa.
 *
 * Multi-tenant Tier 0: business_id=1 (ADR 0101 — nunca cliente real).
 *
 * Pattern schema in-memory copiado do template Ailos (canon: WebhookEndpoints)
 * pra evitar ALTER TABLE MODIFY COLUMN ENUM MySQL-only das migrations canon.
 */
function setupSicoobCnabSchema(): void
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
    setupSicoobCnabSchema();
    session(['business.id' => 1]);
    Storage::fake('local');

    // Config válido — esquema cooperativa Sicoob (lib usa Bancoob)
    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'sicoob_cnab',
        'ambiente'     => 'production',
        'ativo'        => true,
        'nome_display' => 'Sicoob (CNAB) Test',
        'config_json'  => [
            'agencia'           => '4321',
            'conta'             => '12345',
            'carteira'          => '1',      // Simples (aceitas: '1' ou '3')
            'convenio'          => '123456', // 4/6/7 dígitos — código cedente Sicoob
            'modalidade'        => '01',     // failsafe pro Posto de Atendimento
            'cedente_nome'      => 'Cooperado Cedente LTDA',
            'cedente_documento' => '12345678000199',
            'cedente_endereco'  => 'Rua Sicoob, 756',
            'cedente_cep'       => '70000000',
            'cedente_uf'        => 'DF',
            'cedente_cidade'    => 'Brasília',
        ],
    ]);

    $this->driver = new SicoobCnabDriver();
});

// ─── identidade do driver ────────────────────────────────────────────────

it('key() retorna sicoob_cnab (nome atual, não bancoob legacy)', function () {
    expect($this->driver->key())->toBe('sicoob_cnab');
});

it('supports() aceita apenas boleto', function () {
    expect($this->driver->supports('boleto'))->toBeTrue();
    expect($this->driver->supports('pix_cob'))->toBeFalse();
    expect($this->driver->supports('card'))->toBeFalse();
});

it('aponta pra classe Boleto Bancoob da lib (nome legacy, mesma instituição Sicoob)', function () {
    $ref = new ReflectionMethod(SicoobCnabDriver::class, 'getBoletoClass');
    $ref->setAccessible(true);
    expect($ref->invoke($this->driver))->toBe(BancoobBoleto::class);
});

it('layout sempre 240 (Sicoob padronizou pós-2018)', function () {
    $ref = new ReflectionMethod(SicoobCnabDriver::class, 'getLayoutVersion');
    $ref->setAccessible(true);
    expect($ref->invoke($this->driver))->toBe(240);
});

it('camposObrigatorios inclui esquema cooperativa Sicoob (convenio+modalidade)', function () {
    $ref = new ReflectionMethod(SicoobCnabDriver::class, 'camposObrigatoriosCnab');
    $ref->setAccessible(true);
    $campos = $ref->invoke($this->driver);

    expect($campos)->toContain('agencia');
    expect($campos)->toContain('conta');
    expect($campos)->toContain('carteira');     // aceitas '1' ou '3'
    expect($campos)->toContain('convenio');     // forçada pelo construtor da lib
    expect($campos)->toContain('modalidade');   // failsafe Posto de Atendimento
    expect($campos)->toContain('cedente_nome');
    expect($campos)->toContain('cedente_documento');
});

// ─── healthCheck ─────────────────────────────────────────────────────────

it('healthCheck OK quando config Sicoob completo', function () {
    $h = $this->driver->healthCheck($this->cred);

    expect($h->ok)->toBeTrue();
    expect($h->status)->toBe('ok');
});

it('healthCheck down quando falta convenio (Sicoob exige código cedente)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'sicoob_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '4321',
            'conta'             => '12345',
            'carteira'          => '1',
            // convenio faltando — Sicoob exige
            'modalidade'        => '01',
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->status)->toBe('down');
    expect($h->errorMessage)->toContain('convenio');
});

it('healthCheck down quando falta modalidade (failsafe Posto Atendimento)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'sicoob_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '4321',
            'conta'             => '12345',
            'carteira'          => '1',
            'convenio'          => '123456',
            // modalidade faltando — nosso failsafe declara obrigatória
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->errorMessage)->toContain('modalidade');
});

it('healthCheck down quando gateway_key não bate (driver Ailos por engano)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'ailos_cnab', // sibling cooperativa, driver errado
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => ['agencia' => '4321'],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->errorMessage)->toContain('não bate');
});

// ─── emitirBoleto smoke (cooperativa Sicoob) ─────────────────────────────

it('emitirBoleto gera nossoNumero + grava remessa em path multi-tenant', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1,
        contactId: 200,
        valorCentavos: 32500,
        vencimento: new DateTimeImmutable('+15 days'),
        descricao: 'Boleto Sicoob cooperativa teste',
        idempotencyKey: 'sicoob-test-001',
        meta: [
            'payer_cpf_cnpj' => '98765432100',
            'payer_name'     => 'Cooperado Pagador',
            'payer_email'    => 'coop@test.local',
            'payer_address'  => 'SQS 102 Bloco A',
            'payer_cep'      => '70330001',
            'payer_uf'       => 'DF',
            'payer_city'     => 'Brasília',
        ],
    );

    $result = $this->driver->emitirBoleto($input, $this->cred);

    expect($result->tipo)->toBe('boleto');
    expect($result->nossoNumero)->not->toBe('');
    expect($result->payloadGateway['layout'])->toBe(240);
    expect($result->payloadGateway['gateway_key'])->toBe('sicoob_cnab');

    $path = $result->payloadGateway['cnab_remessa_path'];
    Storage::disk('local')->assertExists($path);

    // Tier 0: path respeita business_id + credential_id
    expect($path)->toContain("biz-1/cred-{$this->cred->id}/");
});

// ─── PIX/cartão/refund/webhook → throw (herdado, smoke aqui) ─────────────

it('emitirPix throws (CNAB cooperativa Sicoob não tem PIX via remessa)', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'pix-throw',
    );
    expect(fn () => $this->driver->emitirPix($input, $this->cred, 'cob'))
        ->toThrow(DriverNotSupportedException::class);
});

it('processWebhook throws (CNAB usa upload retorno, não webhook)', function () {
    expect(fn () => $this->driver->processWebhook(['x' => 1], $this->cred))
        ->toThrow(DriverNotSupportedException::class, 'webhook');
});

it('emitirBoleto valida tipo PaymentGatewayCredential', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'bad-type',
    );
    $fake = (object) ['gateway_key' => 'sicoob_cnab', 'config_json' => []];

    expect(fn () => $this->driver->emitirBoleto($input, $fake))
        ->toThrow(CredentialMisconfiguredException::class, 'PaymentGatewayCredential');
});
