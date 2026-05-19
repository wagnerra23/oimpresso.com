<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Contracts\PaymentGatewayContract;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\InterDriver;
use Modules\PaymentGateway\Services\PaymentGatewayService;

uses(Tests\TestCase::class);

/**
 * Onda 4a — ADR 0170.
 *
 * Cobertura InterDriver com Http::fake mockando endpoints Inter API v3.
 *
 * NÃO testado nesta onda (Onda 4b/c):
 *   - emitirPix / emitirPixAutomatico (Inter PIX cob — Onda 4b)
 *   - refund (Inter parcial — Onda 4c)
 *   - cartão (Inter não suporta, throw sempre)
 *
 * Multi-tenant Tier 0: business_id = 1 (ADR 0101 — nunca cliente real).
 */

beforeEach(function () {
    session(['business.id' => 1]);

    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter Test',
        'config_json'  => [
            'client_id'     => 'fake-client-id',
            'client_secret' => 'fake-client-secret',
        ],
    ]);
});

it('PaymentGatewayContract resolve PaymentGatewayService via container', function () {
    $service = app(PaymentGatewayContract::class);
    expect($service)->toBeInstanceOf(PaymentGatewayService::class);
});

it('InterDriver tem key=inter e supporta apenas boleto na Onda 4a', function () {
    $driver = new InterDriver();
    expect($driver->key())->toBe('inter');
    expect($driver->supports('boleto'))->toBeTrue();
    expect($driver->supports('pix_cob'))->toBeFalse();
    expect($driver->supports('pix_cobv'))->toBeFalse();
    expect($driver->supports('pix_recv'))->toBeFalse();
    expect($driver->supports('card'))->toBeFalse();
});

it('emitirBoleto OK — pipeline completa cria Cobranca emitida com artefatos', function () {
    Http::fake([
        '*/oauth/v2/token'         => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
        '*/cobranca/v3/cobrancas'  => Http::response([
            'nossoNumero'    => '00012345678',
            'linhaDigitavel' => '07799.99999 99999.999999 99999.999998 8 91230000010000',
            'codigoBarras'   => '07798912300000100009999999999999999999999998',
        ], 201),
    ]);

    // Account stub
    $account = (object) ['id' => 1, 'payment_gateway_credential_id' => $this->cred->id];

    $service = new PaymentGatewayService();
    $result = $service->for($account)->emitirBoleto(new EmitirCobrancaInput(
        businessId: 1,
        contactId: 100,
        valorCentavos: 10000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'Cobrança teste',
        idempotencyKey: 'sale:99001',
        origemType: 'sale',
        origemId: 99001,
        meta: [
            'payer_cpf_cnpj' => '12345678900',
            'payer_name'     => 'João Silva',
            'payer_email'    => 'joao@test.local',
        ],
    ));

    expect($result->tipo)->toBe('boleto');
    expect($result->nossoNumero)->toBe('00012345678');
    expect($result->linhaDigitavel)->toContain('07799');

    $cobranca = Cobranca::query()->withoutGlobalScopes()->find($result->cobrancaId);
    expect($cobranca)->not->toBeNull();
    expect($cobranca->status)->toBe('emitida');
    expect($cobranca->gateway_external_id)->toBe('00012345678');
    expect($cobranca->payment_gateway_credential_id)->toBe($this->cred->id);
    expect($cobranca->idempotency_key)->toBe('sale:99001');
});

it('emitirBoleto idempotente — 2x mesma key retorna existente sem chamar gateway', function () {
    Http::fake([
        '*/oauth/v2/token'        => Http::response(['access_token' => 'fake-token'], 200),
        '*/cobranca/v3/cobrancas' => Http::response(['nossoNumero' => '00099887766'], 201),
    ]);

    $account = (object) ['id' => 1, 'payment_gateway_credential_id' => $this->cred->id];
    $service = new PaymentGatewayService();
    $input = new EmitirCobrancaInput(
        businessId: 1,
        contactId: 100,
        valorCentavos: 5000,
        vencimento: new DateTimeImmutable('+3 days'),
        descricao: 'Original',
        idempotencyKey: 'sale:idem-test',
        meta: ['payer_cpf_cnpj' => '11122233344', 'payer_name' => 'Test'],
    );

    $first = $service->for($account)->emitirBoleto($input);
    expect($first->nossoNumero)->toBe('00099887766');

    // 2ª chamada — deve retornar mesma cobranca, SEM novo POST
    Http::fake([
        '*/cobranca/v3/cobrancas' => Http::response(['error' => 'should not be called'], 500),
    ]);
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'fake-token'], 200),
    ]);

    $second = $service->for($account)->emitirBoleto($input);
    expect($second->cobrancaId)->toBe($first->cobrancaId);
    expect(Cobranca::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('emitirBoleto Gateway 5xx → GatewayUnavailableException', function () {
    Http::fake([
        '*/oauth/v2/token'        => Http::response(['access_token' => 'fake-token'], 200),
        '*/cobranca/v3/cobrancas' => Http::response(['error' => 'Inter down'], 503),
    ]);

    $account = (object) ['id' => 1, 'payment_gateway_credential_id' => $this->cred->id];

    expect(fn () => (new PaymentGatewayService())->for($account)->emitirBoleto(new EmitirCobrancaInput(
        businessId: 1,
        contactId: 100,
        valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+1 day'),
        descricao: 'Falha',
        idempotencyKey: 'sale:err-001',
    )))->toThrow(GatewayUnavailableException::class);
});

it('emitirPix → DriverNotSupportedException na Onda 4a', function () {
    $account = (object) ['id' => 1, 'payment_gateway_credential_id' => $this->cred->id];
    Http::fake(['*/oauth/v2/token' => Http::response(['access_token' => 'fake'], 200)]);

    expect(fn () => (new PaymentGatewayService())->for($account)->emitirPix(new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 100,
        vencimento: new DateTimeImmutable('+1 day'),
        descricao: 'x', idempotencyKey: 'pix:1',
    )))->toThrow(DriverNotSupportedException::class);
});

it('cobrarCartao → DriverNotSupportedException sempre (Inter não suporta)', function () {
    $driver = new InterDriver();
    expect(fn () => $driver->cobrarCartao(
        new EmitirCobrancaInput(businessId: 1, contactId: 1, valorCentavos: 100, vencimento: new DateTimeImmutable(), descricao: 'x', idempotencyKey: 'k'),
        $this->cred,
        new \Modules\PaymentGateway\Dto\CardToken(token: 't', brand: 'visa', lastFour: '4242', holderName: 'X', expMonth: '12', expYear: '2030'),
    ))->toThrow(DriverNotSupportedException::class);
});

it('healthCheck OK quando OAuth retorna access_token', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'fake-ok'], 200),
    ]);

    $health = (new InterDriver())->healthCheck($this->cred);
    expect($health->ok)->toBeTrue();
    expect($health->status)->toBeIn(['ok', 'degraded']);
});

it('healthCheck down quando OAuth retorna 401', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['error' => 'invalid_credentials'], 401),
    ]);

    $health = (new InterDriver())->healthCheck($this->cred);
    expect($health->ok)->toBeFalse();
    expect($health->status)->toBe('down');
    expect($health->errorMessage)->toContain('OAuth failed');
});

it('credential gateway_key errado → CredentialMisconfiguredException', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'asaas', // !! não-inter
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => ['api_key' => 'x'],
    ]);

    expect(fn () => (new InterDriver())->healthCheck($bad))
        ->toThrow(CredentialMisconfiguredException::class);
});

it('credential sem client_id → CredentialMisconfiguredException', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => ['client_secret' => 'only-secret'], // sem client_id
    ]);

    expect(fn () => (new InterDriver())->healthCheck($bad))
        ->toThrow(CredentialMisconfiguredException::class);
});

it('processWebhook mapeia payload pra shape canon', function () {
    $result = (new InterDriver())->processWebhook(
        ['nossoNumero' => '00012345', 'situacao' => 'RECEBIDO'],
        $this->cred,
    );

    expect($result)->not->toBeNull();
    expect($result->gateway_external_id)->toBe('00012345');
    expect($result->gateway_key)->toBe('inter');
});

it('processWebhook sem nossoNumero retorna null', function () {
    $result = (new InterDriver())->processWebhook(['evento' => 'random'], $this->cred);
    expect($result)->toBeNull();
});

it('consultar retorna status mapeado canon', function () {
    Http::fake([
        '*/oauth/v2/token'                => Http::response(['access_token' => 'fake'], 200),
        '*/cobranca/v3/cobrancas/00012345' => Http::response([
            'cobranca' => [
                'situacao'              => 'RECEBIDO',
                'valorTotalRecebimento' => 100.00,
                'dataHoraSituacao'      => '2026-05-19T14:00:00',
            ],
        ], 200),
    ]);

    $cobranca = (object) ['gateway_external_id' => '00012345'];
    $status = (new InterDriver())->consultar($cobranca, $this->cred);

    expect($status->status)->toBe('paga');
    expect($status->valorPagoCentavos)->toBe(10000);
});
