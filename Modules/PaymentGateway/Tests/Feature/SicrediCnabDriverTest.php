<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Cnab\Drivers\SicrediCnabDriver;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Onda 4f.cnab/Sicredi — ADR 0170 (driver fino sobre CnabBoletoAdapter).
 *
 * Cobre:
 *   - key()/supports()/getLayoutVersion() corretos
 *   - camposObrigatoriosCnab inclui byte + posto + codigo_cliente
 *   - emitirBoleto gera nossoNumero Sicredi (ano + byte + sequencial + DV)
 *   - configToBoletoArgs converte snake codigo_cliente → camelCase
 *   - healthCheck down se faltar `byte` ou `posto` (específico Sicredi)
 *
 * Multi-tenant Tier 0: business_id=1 (ADR 0101 — nunca cliente real).
 */
function setupSicrediCnabSchema(): void
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
    setupSicrediCnabSchema();
    session(['business.id' => 1]);
    Storage::fake('local');

    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'sicredi_cnab',
        'ambiente'     => 'production',
        'ativo'        => true,
        'nome_display' => 'Sicredi Cooperativa Teste',
        'config_json'  => [
            // PA-coop fictício (ADR 0101: nunca cliente real).
            'agencia'           => '0710',  // 4 dígitos — coop singular
            'conta'             => '12345',
            'carteira'          => '1',     // Boleto class aceita 1/2/3 (vira 'A' na Remessa)
            'byte'              => 2,       // beneficiário-emitido (1 = coop)
            'posto'             => '07',    // 2 dígitos PA
            'codigo_cliente'    => '12345', // 5 dígitos cedente no PA
            'cedente_nome'      => 'Empresa Sicredi Teste LTDA',
            'cedente_documento' => '12345678000199',
            'cedente_endereco'  => 'Rua Cooperativa, 100',
            'cedente_cep'       => '90000000',
            'cedente_uf'        => 'RS',     // berço do Sicredi
            'cedente_cidade'    => 'Porto Alegre',
        ],
    ]);

    $this->driver = new SicrediCnabDriver();
});

// ─── identidade do driver ────────────────────────────────────────────────

it('key() retorna sicredi_cnab', function () {
    expect($this->driver->key())->toBe('sicredi_cnab');
});

it('supports() aceita apenas boleto (PIX/cartão vão pro driver REST)', function () {
    expect($this->driver->supports('boleto'))->toBeTrue();
    expect($this->driver->supports('pix_cob'))->toBeFalse();
    expect($this->driver->supports('card'))->toBeFalse();
});

it('camposObrigatoriosCnab inclui byte + posto + codigo_cliente (específicos Sicredi)', function () {
    $ref = new ReflectionClass(SicrediCnabDriver::class);
    $m   = $ref->getMethod('camposObrigatoriosCnab');
    $m->setAccessible(true);
    $campos = $m->invoke($this->driver);

    expect($campos)->toContain('byte');
    expect($campos)->toContain('posto');
    expect($campos)->toContain('codigo_cliente');
    expect($campos)->toContain('carteira');
    expect($campos)->toContain('cedente_documento');
});

// ─── emitirBoleto + nossoNumero ──────────────────────────────────────────

it('emitirBoleto gera nossoNumero formato Sicredi (ano+byte+sequencial+DV)', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1,
        contactId: 100,
        valorCentavos: 25000,
        vencimento: new DateTimeImmutable('+10 days'),
        descricao: 'Boleto Sicredi teste',
        idempotencyKey: 'sicredi-test-001',
        meta: [
            'payer_cpf_cnpj' => '12345678900',
            'payer_name'     => 'João Cooperativado',
            'payer_email'    => 'joao@test.local',
            'payer_address'  => 'Rua A, 100',
            'payer_cep'      => '90010000',
            'payer_uf'       => 'RS',
            'payer_city'     => 'Porto Alegre',
        ],
    );

    $result = $this->driver->emitirBoleto($input, $this->cred);

    expect($result->tipo)->toBe('boleto');
    expect($result->gatewayExternalId)->not->toBe('');
    expect($result->nossoNumero)->not->toBe('');

    // Sicredi nossoNumero: 9 chars (AA + B + NNNNN + DV)
    //   AA = ano (2 dígitos) · B = byte · NNNNN = sequencial 5 dígitos · DV
    expect(strlen($result->nossoNumero))->toBe(9);

    // Byte é o 3º char e deve bater com config (2).
    expect(substr($result->nossoNumero, 2, 1))->toBe('2');

    // Payload metadata.
    expect($result->payloadGateway['layout'])->toBe(240);
    expect($result->payloadGateway['gateway_key'])->toBe('sicredi_cnab');
    expect($result->payloadGateway)->toHaveKey('cnab_remessa_path');

    // Multi-tenant Tier 0: path inclui scope biz-1.
    $path = $result->payloadGateway['cnab_remessa_path'];
    Storage::disk('local')->assertExists($path);
    expect($path)->toContain("biz-1/cred-{$this->cred->id}/");
});

// ─── healthCheck ─────────────────────────────────────────────────────────

it('healthCheck OK quando config_json completo Sicredi', function () {
    $h = $this->driver->healthCheck($this->cred);

    expect($h->ok)->toBeTrue();
    expect($h->status)->toBe('ok');
});

it('healthCheck down quando falta posto (campo específico Sicredi)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'sicredi_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '0710',
            'conta'             => '12345',
            'carteira'          => '1',
            'byte'              => 2,
            // posto faltando — Sicredi exige
            'codigo_cliente'    => '12345',
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->errorMessage)->toContain('posto');
});

it('healthCheck down quando falta byte (Sicredi nossoNumero precisa)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'sicredi_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '0710',
            'conta'             => '12345',
            'carteira'          => '1',
            // byte faltando — Sicredi nossoNumero é AA+B+NNNNN+DV
            'posto'             => '07',
            'codigo_cliente'    => '12345',
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->errorMessage)->toContain('byte');
});

// ─── PIX/cartão/refund → throw (herdado da fundação, mas valida no driver concreto) ──

it('emitirPix lança DriverNotSupportedException com chave sicredi_cnab', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'k-pix',
    );

    expect(fn () => $this->driver->emitirPix($input, $this->cred, 'cob'))
        ->toThrow(DriverNotSupportedException::class, 'sicredi_cnab');
});

it('emitirBoleto valida camposObrigatoriosCnab (faltando carteira)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'sicredi_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '0710',
            'conta'             => '12345',
            // carteira faltando
            'byte'              => 2,
            'posto'             => '07',
            'codigo_cliente'    => '12345',
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'k-no-carteira',
    );

    expect(fn () => $this->driver->emitirBoleto($input, $bad))
        ->toThrow(CredentialMisconfiguredException::class, 'carteira');
});

// ─── configToBoletoArgs (override Sicredi) ───────────────────────────────

it('configToBoletoArgs mapeia codigo_cliente snake → camelCase codigoCliente', function () {
    $ref = new ReflectionClass(SicrediCnabDriver::class);
    $m   = $ref->getMethod('configToBoletoArgs');
    $m->setAccessible(true);

    $args = $m->invoke($this->driver, [
        'agencia'        => '0710',
        'conta'          => '12345',
        'carteira'       => '1',
        'byte'           => 2,
        'posto'          => '07',
        'codigo_cliente' => '12345',
    ]);

    expect($args)->toHaveKey('codigoCliente');
    expect($args['codigoCliente'])->toBe('12345');
    expect($args)->toHaveKey('byte');
    expect($args['byte'])->toBe(2);
    expect($args)->toHaveKey('posto');
    expect($args['posto'])->toBe('07');
});
