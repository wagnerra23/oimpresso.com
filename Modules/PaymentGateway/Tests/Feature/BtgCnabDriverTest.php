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
use Modules\PaymentGateway\Services\Cnab\Drivers\BtgCnabDriver;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Onda 4f.cnab/BTG — ADR 0170 (drivers separados).
 *
 * BtgCnabDriver é "driver fino" que só implementa key/getBoletoClass/
 * getLayoutVersion/camposObrigatoriosCnab. Toda lógica REST-like
 * (supports/emitirPix/cancelar/healthCheck/etc) vive no
 * CnabBoletoAdapter (fundação Onda 4f.0).
 *
 * Este teste cobre APENAS o que é específico do driver BTG:
 *   - key() == 'btg_cnab'
 *   - getBoletoClass() retorna lib Btg
 *   - getLayoutVersion() == 240 (BTG não tem CNAB 400 na lib)
 *   - camposObrigatoriosCnab() exige carteira + codigo_cliente
 *   - healthCheck OK com config BTG válido
 *   - healthCheck down quando codigo_cliente ausente
 *   - emitirBoleto gera nossoNumero + remessa em path biz-X/cred-Y
 *
 * Contract base já é exercido por CnabBoletoAdapterContractTest com
 * FakeBradescoCnabDriver — não duplicamos aqui.
 *
 * Multi-tenant Tier 0: business_id=1 (ADR 0101 — nunca cliente real).
 *
 * Schema in-memory per test (pattern canon: WebhookEndpointsTest +
 * CnabBoletoAdapterContractTest — migrations canon usam ALTER TABLE
 * MODIFY COLUMN ENUM MySQL-only, incompatível com RefreshDatabase
 * SQLite).
 */
function setupBtgCnabSchema(): void
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
    setupBtgCnabSchema();
    session(['business.id' => 1]);
    Storage::fake('local');

    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'btg_cnab',
        'ambiente'     => 'production',
        'ativo'        => true,
        'nome_display' => 'BTG Pactual CNAB Test',
        'config_json'  => [
            'agencia'           => '0050',
            'conta'             => '1234567',
            'carteira'          => 1, // Cobrança Simples
            'codigo_cliente'    => '000000000001',
            'cedente_nome'      => 'Empresa BTG Teste LTDA',
            'cedente_documento' => '12345678000199',
            'cedente_endereco'  => 'Av. Brigadeiro Faria Lima, 3477',
            'cedente_cep'       => '04538133',
            'cedente_uf'        => 'SP',
            'cedente_cidade'    => 'São Paulo',
        ],
    ]);

    $this->driver = new BtgCnabDriver();
});

// ─── identidade do driver ────────────────────────────────────────────────

it('key() retorna btg_cnab', function () {
    expect($this->driver->key())->toBe('btg_cnab');
});

it('getBoletoClass() retorna lib Btg', function () {
    $rm = new ReflectionMethod($this->driver, 'getBoletoClass');
    $rm->setAccessible(true);

    expect($rm->invoke($this->driver))
        ->toBe(\Eduardokum\LaravelBoleto\Boleto\Banco\Btg::class);
});

it('getLayoutVersion() retorna 240 (BTG só tem CNAB 240)', function () {
    $rm = new ReflectionMethod($this->driver, 'getLayoutVersion');
    $rm->setAccessible(true);

    expect($rm->invoke($this->driver))->toBe(240);
});

it('camposObrigatoriosCnab() exige carteira + codigo_cliente (específicos BTG)', function () {
    $rm = new ReflectionMethod($this->driver, 'camposObrigatoriosCnab');
    $rm->setAccessible(true);
    $campos = $rm->invoke($this->driver);

    expect($campos)->toContain('agencia');
    expect($campos)->toContain('conta');
    expect($campos)->toContain('carteira');
    expect($campos)->toContain('codigo_cliente');
    expect($campos)->toContain('cedente_nome');
    expect($campos)->toContain('cedente_documento');
});

it('supports() apenas boleto (herdado da fundação)', function () {
    expect($this->driver->supports('boleto'))->toBeTrue();
    expect($this->driver->supports('pix_cob'))->toBeFalse();
    expect($this->driver->supports('card'))->toBeFalse();
});

// ─── healthCheck ─────────────────────────────────────────────────────────

it('healthCheck OK com config BTG completo', function () {
    $h = $this->driver->healthCheck($this->cred);

    expect($h->ok)->toBeTrue();
    expect($h->status)->toBe('ok');
    expect($h->latencyMs)->toBeGreaterThanOrEqual(0);
});

it('healthCheck down quando codigo_cliente ausente (específico BTG)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'btg_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '0050',
            'conta'             => '1234567',
            'carteira'          => 1,
            // codigo_cliente faltando — BTG exige pra agenciaCodigoBeneficiario
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
        ],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->status)->toBe('down');
    expect($h->errorMessage)->toContain('codigo_cliente');
});

it('healthCheck down quando gateway_key não é btg_cnab', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => ['client_id' => 'x', 'client_secret' => 'y'],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->errorMessage)->toContain("não bate");
});

// ─── emitirBoleto ────────────────────────────────────────────────────────

it('emitirBoleto gera nossoNumero BTG + remessa em path biz-1/cred-X', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1,
        contactId: 100,
        valorCentavos: 25000,
        vencimento: new DateTimeImmutable('+7 days'),
        descricao: 'Boleto BTG teste Onda 4f.cnab',
        idempotencyKey: 'btg-cnab-test-001',
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
    expect($result->nossoNumero)->not->toBe('');
    expect($result->gatewayExternalId)->toBe($result->nossoNumero);
    expect($result->payloadGateway['layout'])->toBe(240);
    expect($result->payloadGateway['gateway_key'])->toBe('btg_cnab');
    expect($result->payloadGateway)->toHaveKey('cnab_remessa_path');

    $path = $result->payloadGateway['cnab_remessa_path'];
    Storage::disk('local')->assertExists($path);

    // Multi-tenant Tier 0: path inclui biz-1 + cred-X (isolamento por business)
    expect($path)->toContain("biz-1/cred-{$this->cred->id}/");
});

it('emitirBoleto lança CredentialMisconfiguredException sem carteira', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'btg_cnab',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '0050',
            'conta'             => '1234567',
            'codigo_cliente'    => '000000000001',
            'cedente_nome'      => 'X',
            'cedente_documento' => '00000000000000',
            // carteira faltando
        ],
    ]);

    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'k-btg-bad',
    );

    expect(fn () => $this->driver->emitirBoleto($input, $bad))
        ->toThrow(CredentialMisconfiguredException::class, 'carteira');
});

// ─── operações não suportadas (herdadas — smoke específico) ──────────────

it('emitirPix lança DriverNotSupportedException (BTG CNAB = boleto-only)', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'k-btg-pix',
    );

    expect(fn () => $this->driver->emitirPix($input, $this->cred, 'cob'))
        ->toThrow(DriverNotSupportedException::class, 'PIX');
});

it('consultar lança DriverNotSupportedException (CNAB sem real-time)', function () {
    $cobranca = (object) ['gateway_external_id' => '00012345001'];

    expect(fn () => $this->driver->consultar($cobranca, $this->cred))
        ->toThrow(DriverNotSupportedException::class, 'upload');
});
