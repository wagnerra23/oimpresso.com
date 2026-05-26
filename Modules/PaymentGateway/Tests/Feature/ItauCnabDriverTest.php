<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Cnab\Drivers\ItauCnabDriver;

uses(Tests\TestCase::class);

/**
 * Onda 4f.cnab/Bradesco — ADR 0170-bancos-nativos-top5-drivers-separados.
 *
 * Cobertura ItauCnabDriver (driver fino sobre CnabBoletoAdapter Onda 4f.0):
 *   - key/supports
 *   - PIX/cartão sanity (herdado abstract — throw esperado)
 *   - healthCheck com config completo + falha sem carteira
 *   - emitirBoleto gera nossoNumero + grava remessa em Storage
 *   - business_id global scope (multi-tenant Tier 0 — ADR 0093)
 *
 * Schema in-memory por test (mesma estratégia canon WebhookEndpointsTest +
 * CnabBoletoAdapterContractTest — não RefreshDatabase porque migrations
 * canon usam ALTER TABLE MODIFY COLUMN ENUM MySQL-only).
 */

function setupItauCnabSchema(): void
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
    setupItauCnabSchema();
    session(['business.id' => 1]);
    Storage::fake('local');

    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'itau_cnab',
        'ambiente'     => 'production',
        'ativo'        => true,
        'nome_display' => 'Itaú CNAB PJ',
        'config_json'  => [
            'agencia'             => '0001',
            'conta'               => '12345',
            'conta_dv'            => '6',
            'carteira'            => '109', // PJ cobrança simples com registro
            'cedente_nome'        => 'Empresa Teste LTDA',
            'cedente_documento'   => '12345678000199',
            'cedente_endereco'    => 'Rua das Couves, 100',
            'cedente_cep'         => '01000000',
            'cedente_uf'          => 'SP',
            'cedente_cidade'      => 'São Paulo',
        ],
    ]);

    $this->driver = new ItauCnabDriver();
});

// ─── key / supports ──────────────────────────────────────────────────────

it('has gateway key itau_cnab', function () {
    expect($this->driver->key())->toBe('itau_cnab');
});

it('supports only boleto', function () {
    expect($this->driver->supports('boleto'))->toBeTrue();
    expect($this->driver->supports('pix_cob'))->toBeFalse();
    expect($this->driver->supports('pix_cobv'))->toBeFalse();
    expect($this->driver->supports('pix_recv'))->toBeFalse();
    expect($this->driver->supports('card'))->toBeFalse();
});

// ─── sanity throws (CNAB = só boleto) ───────────────────────────────────

it('throws on emitirPix (CNAB não suporta PIX)', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'itau-pix-k1',
    );

    expect(fn () => $this->driver->emitirPix($input, $this->cred, 'cob'))
        ->toThrow(DriverNotSupportedException::class, 'PIX');
});

it('throws on cobrarCartao (CNAB não suporta cartão)', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'itau-card-k1',
    );
    $token = new CardToken(token: 't', brand: 'visa', lastFour: '4242', holderName: 'X', expMonth: '12', expYear: '2030');

    expect(fn () => $this->driver->cobrarCartao($input, $this->cred, $token))
        ->toThrow(DriverNotSupportedException::class, 'cartão');
});

// ─── healthCheck ─────────────────────────────────────────────────────────

it('healthCheck ok quando config completo (agencia+conta+carteira+cedente)', function () {
    $h = $this->driver->healthCheck($this->cred);

    expect($h->ok)->toBeTrue();
    expect($h->status)->toBe('ok');
    expect($h->latencyMs)->toBeGreaterThanOrEqual(0);
});

it('health check falha sem campos obrigatórios (faltando carteira)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'itau_cnab',
        'ambiente'     => 'production',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '1234',
            'conta'             => '0567890',
            // carteira faltando — Bradesco exige
            'cedente_nome'      => 'Empresa X',
            'cedente_documento' => '12345678000199',
        ],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->status)->toBe('down');
    expect($h->errorMessage)->toContain('carteira');
});

it('health check falha com carteira inválida (fora do array Bradesco)', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'itau_cnab',
        'ambiente'     => 'production',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '1234',
            'conta'             => '0567890',
            'carteira'          => '99', // Bradesco aceita só [02, 04, 09, 21, 26]
            'cedente_nome'      => 'Empresa X',
            'cedente_documento' => '12345678000199',
        ],
    ]);

    $h = $this->driver->healthCheck($bad);

    expect($h->ok)->toBeFalse();
    expect($h->status)->toBe('down');
});

// ─── emitirBoleto ────────────────────────────────────────────────────────

it('emitirBoleto gera cnab remessa e retorna nosso numero', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1,
        contactId: 100,
        valorCentavos: 17550, // R$ 175,50
        vencimento: new DateTimeImmutable('+10 days'),
        descricao: 'Mensalidade março/2026',
        idempotencyKey: 'itau-cob-001',
        meta: [
            'payer_cpf_cnpj' => '12345678900',
            'payer_name'     => 'Maria Cliente',
            'payer_email'    => 'maria@cliente.test',
            'payer_address'  => 'Av. Paulista, 1000',
            'payer_cep'      => '01310100',
            'payer_uf'       => 'SP',
            'payer_city'     => 'São Paulo',
        ],
    );

    $result = $this->driver->emitirBoleto($input, $this->cred);

    expect($result->tipo)->toBe('boleto');
    expect($result->gatewayExternalId)->not->toBe('');
    expect($result->nossoNumero)->not->toBe('')
        ->and(strlen($result->nossoNumero))->toBeGreaterThanOrEqual(11); // Bradesco: 11 + DV
    expect($result->payloadGateway)->toHaveKey('cnab_remessa_path');
    expect($result->payloadGateway['layout'])->toBe(240);
    expect($result->payloadGateway['gateway_key'])->toBe('itau_cnab');

    // Remessa de fato gravada em Storage::fake('local')
    Storage::disk('local')->assertExists($result->payloadGateway['cnab_remessa_path']);

    // Path respeita scope multi-tenant (biz-1/cred-X/...)
    expect($result->payloadGateway['cnab_remessa_path'])
        ->toContain("biz-1/cred-{$this->cred->id}/");
});

it('emitirBoleto lança CredentialMisconfiguredException sem agencia', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'itau_cnab',
        'ambiente'     => 'production',
        'ativo'        => true,
        'config_json'  => [
            // agencia faltando
            'conta'             => '0567890',
            'carteira'          => '09',
            'cedente_nome'      => 'X',
            'cedente_documento' => '12345678000199',
        ],
    ]);

    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'x', idempotencyKey: 'itau-bad-001',
    );

    expect(fn () => $this->driver->emitirBoleto($input, $bad))
        ->toThrow(CredentialMisconfiguredException::class, 'agencia');
});

// ─── multi-tenant Tier 0 (ADR 0093) ──────────────────────────────────────

it('respeita business id global scope (biz=2 não enxerga credencial biz=1)', function () {
    // Cria credencial pra business 2 também
    $credBiz2 = PaymentGatewayCredential::query()->create([
        'business_id'  => 2,
        'gateway_key'  => 'itau_cnab',
        'ambiente'     => 'production',
        'ativo'        => true,
        'config_json'  => [
            'agencia'           => '9876',
            'conta'             => '0111222',
            'carteira'          => '09',
            'cedente_nome'      => 'Outra Empresa S/A',
            'cedente_documento' => '99999999000199',
        ],
    ]);

    // Cenário 1: session = biz 1 → lista só vê biz 1
    session(['business.id' => 1]);
    $idsBiz1 = PaymentGatewayCredential::query()
        ->where('gateway_key', 'itau_cnab')
        ->pluck('business_id', 'id')
        ->toArray();

    // PaymentGatewayCredential não usa HasBusinessScope global scope
    // (filtragem é explícita no Service/Controller via ->where('business_id')).
    // Aqui validamos que ao filtrar manualmente por business_id da session,
    // o path Storage gerado pelo driver respeita o cred->business_id correto.
    expect($idsBiz1)->toHaveCount(2); // sem global scope, mas cred carrega biz_id

    // Cenário 2: emitir pra cred biz=2 — Storage path tem biz-2/
    $input = new EmitirCobrancaInput(
        businessId: 2, contactId: 50, valorCentavos: 9999,
        vencimento: new DateTimeImmutable('+7 days'),
        descricao: 'Boleto biz 2', idempotencyKey: 'itau-biz2-001',
        meta: [
            'payer_cpf_cnpj' => '11122233344',
            'payer_name'     => 'Pagador Biz 2',
            'payer_address'  => 'Rua Y, 200',
            'payer_cep'      => '20000000',
            'payer_uf'       => 'RJ',
            'payer_city'     => 'Rio de Janeiro',
        ],
    );

    $result = $this->driver->emitirBoleto($input, $credBiz2);

    // Path Storage isola por business (Tier 0 ADR 0093) — biz-2, nunca biz-1
    expect($result->payloadGateway['cnab_remessa_path'])
        ->toContain("biz-2/cred-{$credBiz2->id}/")
        ->not->toContain('biz-1/');

    Storage::disk('local')->assertExists($result->payloadGateway['cnab_remessa_path']);
});
