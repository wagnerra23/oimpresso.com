<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\PaymentGateway\Contracts\PaymentDriverContract;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\SicoobApiDriver;
use Modules\PaymentGateway\Services\PaymentGatewayService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Onda 4f.sicoob_api US-FIN-044 + US-FIN-046 refactor.
 *
 * Testes reais com Http::fake mockando endpoints Sicoob sandbox.
 *
 * US-FIN-046 (2026-05-27): driver agora REUSA NfeCertificado canon. Tests
 * mockam CertificadoService::carregarParaSefaz pra retornar pfx_binary fake
 * sem precisar do disco nfe_certs real.
 *
 * Multi-tenant Tier 0: business_id=1 sempre (ADR 0101 — nunca cliente real).
 */

beforeEach(function () {
    Cache::flush();
    session(['business.id' => 1]);

    // US-FIN-046 — mocka CertificadoService global pra retornar cert stub.
    // Em Http::fake() o handshake mTLS é bypass, então pfx_binary fake serve.
    $stub = Mockery::mock(CertificadoService::class);
    $stub->shouldReceive('carregarParaSefaz')
        ->andReturn([
            'pfx_binary' => 'FAKE-PFX-BIN-DO-MOCK',
            'senha'      => 'fake-pfx-senha',
            'valido_ate' => new DateTimeImmutable('+90 days'),
            'source'     => 'nfe_brasil',
        ]);
    app()->instance(CertificadoService::class, $stub);

    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'   => 1,
        'gateway_key'   => 'sicoob_api',
        'ambiente'      => 'sandbox',
        'ativo'         => true,
        'nome_display'  => 'Sicoob API Test',
        'config_json'   => [
            'client_id'         => 'fake-client-id',
            'client_secret'     => 'fake-client-secret',
            'numero_cliente'    => 12345,    // = convênio Sicoob
            'codigo_modalidade' => 1,        // carteira 1 Simples
            'numero_conta'      => 1234567,
            'especie_documento' => 'DM',
        ],
    ]);

    $this->driver = fn (): SicoobApiDriver => app(SicoobApiDriver::class);
});

it('instancia SicoobApiDriver via classe concreta', function () {
    expect(app(SicoobApiDriver::class))->toBeInstanceOf(PaymentDriverContract::class);
});

it('expõe key sicoob_api', function () {
    expect((app(SicoobApiDriver::class))->key())->toBe('sicoob_api');
});

it('supports boleto + pix_cob, rejeita card/pix_cobv/pix_recv', function () {
    $d = app(SicoobApiDriver::class);

    expect($d->supports('boleto'))->toBeTrue()
        ->and($d->supports('pix_cob'))->toBeTrue()
        ->and($d->supports('card'))->toBeFalse()
        ->and($d->supports('pix_cobv'))->toBeFalse()
        ->and($d->supports('pix_recv'))->toBeFalse();
});

function sicoobPr2Input(): EmitirCobrancaInput
{
    return new EmitirCobrancaInput(
        businessId: 1,
        contactId: 100,
        valorCentavos: 15000,
        vencimento: new DateTimeImmutable('+7 days'),
        descricao: 'Cobrança teste Sicoob',
        idempotencyKey: 'sale:sicoob-pr2-' . uniqid(),
        meta: [
            'payer_cpf_cnpj' => '12345678900',
            'payer_name'     => 'João Silva',
            'payer_email'    => 'joao@test.local',
        ],
    );
}

it('OAuth2 client_credentials cacheia token e reusa entre requests', function () {
    Http::fake([
        '*/openid-connect/token' => Http::sequence()
            ->push(['access_token' => 'fake-token-1', 'expires_in' => 3600], 200)
            ->push(['access_token' => 'fake-token-2', 'expires_in' => 3600], 200),
        '*/cobranca-bancaria/v3/boletos' => Http::response([
            'resultado' => [['status' => 200, 'boleto' => [
                'nossoNumero'    => '12345678',
                'linhaDigitavel' => '75691.23456 12345.678901 12345.678905 1 00000001500000',
                'codigoBarras'   => '75691000000000150000123456781234567890123456789',
            ]]],
        ], 200),
    ]);

    $driver = app(SicoobApiDriver::class);

    // 1ª chamada — força OAuth + emissão
    $r1 = $driver->emitirBoleto(sicoobPr2Input(), $this->cred);
    // 2ª chamada — OAuth deve vir do cache, sequence só retorna token-1
    $r2 = $driver->emitirBoleto(sicoobPr2Input(), $this->cred);

    expect($r1->nossoNumero)->toBe('12345678')
        ->and($r2->nossoNumero)->toBe('12345678');

    // Confirma: só UMA chamada ao token endpoint (cacheado)
    Http::assertSentCount(3); // 1 token + 2 boletos
});

it('emitirBoleto monta payload v3 com numeroCliente/codigoModalidade/pagador', function () {
    Http::fake([
        '*/openid-connect/token' => Http::response(['access_token' => 't', 'expires_in' => 3600], 200),
        '*/cobranca-bancaria/v3/boletos' => Http::response([
            'resultado' => [['status' => 200, 'boleto' => [
                'nossoNumero'    => '99887766',
                'linhaDigitavel' => '75690.00000 00000.000000 00000.000000 0 99999999999999',
            ]]],
        ], 200),
    ]);

    (app(SicoobApiDriver::class))->emitirBoleto(sicoobPr2Input(), $this->cred);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/boletos')) {
            return false;
        }
        $body = $request->data()[0] ?? [];

        return $body['numeroCliente'] === 12345
            && $body['codigoModalidade'] === 1
            && $body['numeroContaCorrente'] === 1234567
            && $body['codigoEspecieDocumento'] === 'DM'
            && $body['identificacaoEmissaoBoleto'] === 2
            && $body['pagador']['numeroCpfCnpj'] === '12345678900'
            && $body['pagador']['nome'] === 'João Silva'
            && $body['pagador']['email'] === ['joao@test.local'];
    });
});

it('emitirBoleto envia client_id header em TODA request (não só no token)', function () {
    Http::fake([
        '*/openid-connect/token' => Http::response(['access_token' => 't', 'expires_in' => 3600], 200),
        '*/cobranca-bancaria/v3/boletos' => Http::response([
            'resultado' => [['status' => 200, 'boleto' => ['nossoNumero' => '111']]],
        ], 200),
    ]);

    (app(SicoobApiDriver::class))->emitirBoleto(sicoobPr2Input(), $this->cred);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/boletos')
            && $request->hasHeader('client_id', 'fake-client-id');
    });
});

it('emitirBoleto lança GatewayUnavailableException quando Sicoob 500', function () {
    Http::fake([
        '*/openid-connect/token' => Http::response(['access_token' => 't', 'expires_in' => 3600], 200),
        '*/cobranca-bancaria/v3/boletos' => Http::response(['mensagens' => [['mensagem' => 'erro interno']]], 500),
    ]);

    expect(fn () => (app(SicoobApiDriver::class))->emitirBoleto(sicoobPr2Input(), $this->cred))
        ->toThrow(GatewayUnavailableException::class, 'Sicoob API falhou (500)');
});

it('emitirBoleto lança InvalidPayerException se Sicoob retorna sem nossoNumero', function () {
    Http::fake([
        '*/openid-connect/token' => Http::response(['access_token' => 't', 'expires_in' => 3600], 200),
        '*/cobranca-bancaria/v3/boletos' => Http::response(['resultado' => [['status' => 200, 'boleto' => []]]], 200),
    ]);

    expect(fn () => (app(SicoobApiDriver::class))->emitirBoleto(sicoobPr2Input(), $this->cred))
        ->toThrow(InvalidPayerException::class, 'sem nossoNumero');
});

it('cancelar chama PATCH /boletos/baixa com numeroCliente + nossoNumero + codigoBaixa', function () {
    Http::fake([
        '*/openid-connect/token' => Http::response(['access_token' => 't', 'expires_in' => 3600], 200),
        '*/cobranca-bancaria/v3/boletos/baixa' => Http::response([], 200),
    ]);

    $cobranca = (object) ['gateway_external_id' => '55667788'];
    (app(SicoobApiDriver::class))->cancelar($cobranca, $this->cred, 'cliente_pediu');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/boletos/baixa')
            && $request->method() === 'PATCH'
            && $request->data()['numeroCliente'] === 12345
            && $request->data()['nossoNumero'] === 55667788
            && $request->data()['codigoBaixa'] === 1;
    });
});

it('consultar mapeia situacaoBoleto Sicoob → status canon', function () {
    Http::fake([
        '*/openid-connect/token' => Http::response(['access_token' => 't', 'expires_in' => 3600], 200),
        '*/cobranca-bancaria/v3/boletos*' => Http::response([
            'resultado' => [['boleto' => [
                'situacaoBoleto' => 'LIQUIDADO',
                'valorRecebido'  => 150.00,
                'dataLiquidacao' => '2026-05-27T10:00:00-03:00',
            ]]],
        ], 200),
    ]);

    $status = (app(SicoobApiDriver::class))->consultar((object) ['gateway_external_id' => '999'], $this->cred);

    expect($status->status)->toBe('paga')
        ->and($status->valorPagoCentavos)->toBe(15000)
        ->and($status->pagaEm)->not->toBeNull()
        ->and($status->formaPagamento)->toBe('boleto');
});

it('consultar mapeia EM_ABERTO → emitida', function () {
    Http::fake([
        '*/openid-connect/token' => Http::response(['access_token' => 't', 'expires_in' => 3600], 200),
        '*/cobranca-bancaria/v3/boletos*' => Http::response([
            'resultado' => [['boleto' => ['situacaoBoleto' => 'EM_ABERTO']]],
        ], 200),
    ]);

    $status = (app(SicoobApiDriver::class))->consultar((object) ['gateway_external_id' => '111'], $this->cred);

    expect($status->status)->toBe('emitida')->and($status->valorPagoCentavos)->toBeNull();
});

it('healthCheck ok quando OAuth handshake passa', function () {
    Http::fake([
        '*/openid-connect/token' => Http::response(['access_token' => 'fake', 'expires_in' => 3600], 200),
    ]);

    $health = (app(SicoobApiDriver::class))->healthCheck($this->cred);

    expect($health->ok)->toBeTrue()
        ->and($health->status)->toBeIn(['ok', 'degraded']);
});

it('healthCheck down quando OAuth 401', function () {
    Http::fake([
        '*/openid-connect/token' => Http::response(['error' => 'invalid_client'], 401),
    ]);

    $health = (app(SicoobApiDriver::class))->healthCheck($this->cred);

    expect($health->ok)->toBeFalse()->and($health->status)->toBe('down');
});

it('assertCredential rejeita credential com gateway_key diferente', function () {
    $cred = PaymentGatewayCredential::query()->create([
        'business_id' => 1,
        'gateway_key' => 'inter',
        'ambiente'    => 'sandbox',
        'ativo'       => true,
        'config_json' => ['client_id' => 'x', 'client_secret' => 'y'],
    ]);

    expect(fn () => (app(SicoobApiDriver::class))->emitirBoleto(sicoobPr2Input(), $cred))
        ->toThrow(CredentialMisconfiguredException::class, "não bate com driver Sicoob API");
});

it('assertCredential rejeita config_json sem numero_cliente nem convenio', function () {
    $cred = PaymentGatewayCredential::query()->create([
        'business_id' => 1,
        'gateway_key' => 'sicoob_api',
        'ambiente'    => 'sandbox',
        'ativo'       => true,
        'config_json' => ['client_id' => 'x', 'client_secret' => 'y'], // sem numero_cliente
    ]);

    expect(fn () => (app(SicoobApiDriver::class))->emitirBoleto(sicoobPr2Input(), $cred))
        ->toThrow(CredentialMisconfiguredException::class, 'numero_cliente');
});

it('emitirPix lança DriverNotSupportedException — PIX cob chega futuro', function () {
    expect(fn () => (app(SicoobApiDriver::class))->emitirPix(sicoobPr2Input(), $this->cred, 'cob'))
        ->toThrow(DriverNotSupportedException::class, 'sicoob_cnab');
});

it('cobrarCartao rejeita explicitamente — Sicoob não emite cartão', function () {
    $token = new CardToken(
        token: 'tok_test',
        brand: 'visa',
        lastFour: '4242',
        holderName: 'Test',
        expMonth: '12',
        expYear: '2030',
    );

    expect(fn () => (app(SicoobApiDriver::class))->cobrarCartao(sicoobPr2Input(), $this->cred, $token))
        ->toThrow(DriverNotSupportedException::class, 'não emite cartão');
});

it('refund de boleto rejeitado — TED reverso manual', function () {
    expect(fn () => (app(SicoobApiDriver::class))->refund((object) ['tipo' => 'boleto'], $this->cred, null, 'erro'))
        ->toThrow(DriverNotSupportedException::class, 'TED reverso');
});

it('emitirPixAutomatico aponta pra bcb_pix driver dedicado', function () {
    expect(fn () => (app(SicoobApiDriver::class))->emitirPixAutomatico(sicoobPr2Input(), $this->cred))
        ->toThrow(DriverNotSupportedException::class, 'bcb_pix');
});

it('processWebhook PR2 mapeia superficial (HMAC real chega PR4)', function () {
    $result = (app(SicoobApiDriver::class))->processWebhook([
        'nossoNumero' => '12345',
        'situacao'    => 'LIQUIDADO',
    ], $this->cred);

    expect($result)->not->toBeNull()
        ->and($result->gateway_external_id)->toBe('12345')
        ->and($result->gateway_key)->toBe('sicoob_api');
});

it('processWebhook retorna null se payload sem nossoNumero', function () {
    $result = (app(SicoobApiDriver::class))->processWebhook(['evento' => 'unknown'], $this->cred);
    expect($result)->toBeNull();
});

it('registry PaymentGatewayService mapeia sicoob_api → SicoobApiDriver::class', function () {
    $reflection = new ReflectionClass(PaymentGatewayService::class);
    $drivers = $reflection->getConstant('DRIVERS');

    expect($drivers)->toHaveKey('sicoob_api')
        ->and($drivers['sicoob_api'])->toBe(SicoobApiDriver::class);
});

it('cache token isolado por business_id — Tier 0 multi-tenant', function () {
    Http::fake([
        '*/openid-connect/token' => Http::sequence()
            ->push(['access_token' => 'token-biz-1', 'expires_in' => 3600], 200)
            ->push(['access_token' => 'token-biz-99', 'expires_in' => 3600], 200),
        '*/cobranca-bancaria/v3/boletos' => Http::response([
            'resultado' => [['boleto' => ['nossoNumero' => '1']]],
        ], 200),
    ]);

    $credBiz99 = PaymentGatewayCredential::query()->create([
        'business_id'   => 99,
        'gateway_key'   => 'sicoob_api',
        'ambiente'      => 'sandbox',
        'ativo'         => true,
        'config_json'   => [
            'client_id'      => 'biz-99-client',
            'client_secret'  => 's2',
            'numero_cliente' => 99999,
            'codigo_modalidade' => 1,
            'numero_conta'   => 99,
        ],
    ]);

    $d = app(SicoobApiDriver::class);
    $d->emitirBoleto(sicoobPr2Input(), $this->cred);
    $d->emitirBoleto(sicoobPr2Input(), $credBiz99);

    // 2 tokens DIFERENTES geridos — sequence só emite tokens distintos se
    // cache key NÃO compartilha entre tenants. Se compartilhasse, 2ª chamada
    // reusava token-biz-1 e sequence ficaria com push-2 não consumido.
    Http::assertSentCount(4); // 2 tokens + 2 boletos
});
