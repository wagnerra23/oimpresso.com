<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\BcbPixDriver;
use Modules\PaymentGateway\Services\PaymentGatewayService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Onda 4d.1 — ADR 0170.
 *
 * BcbPixDriver — PIX Automático regulado BCB Resolução 380/2024.
 * Especializado em PIX recv (mandato recorrente). Demais tipos throw.
 */

beforeEach(function () {
    session(['business.id' => 1]);
    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'bcb_pix',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'BCB Pix Auto Test',
        'config_json'  => [
            'client_id'     => 'bcb-cli',
            'client_secret' => 'bcb-sec',
            'base_url'      => 'https://psp.fake.local',
        ],
    ]);
});

// ─── key/supports ────────────────────────────────────────────────────────

it('key=bcb_pix e supports apenas pix_recv', function () {
    $d = new BcbPixDriver();
    expect($d->key())->toBe('bcb_pix');
    expect($d->supports('pix_recv'))->toBeTrue();
    expect($d->supports('boleto'))->toBeFalse();
    expect($d->supports('pix_cob'))->toBeFalse();
    expect($d->supports('pix_cobv'))->toBeFalse();
    expect($d->supports('card'))->toBeFalse();
});

// ─── métodos não-supportados throw ───────────────────────────────────────

it('emitirBoleto throw DriverNotSupportedException', function () {
    expect(fn () => (new BcbPixDriver())->emitirBoleto(
        new EmitirCobrancaInput(businessId: 1, contactId: 1, valorCentavos: 100, vencimento: new DateTimeImmutable(), descricao: 'x', idempotencyKey: 'k'),
        $this->cred,
    ))->toThrow(DriverNotSupportedException::class);
});

it('emitirPix cob/cobv throw', function () {
    $d = new BcbPixDriver();
    $input = new EmitirCobrancaInput(businessId: 1, contactId: 1, valorCentavos: 100, vencimento: new DateTimeImmutable(), descricao: 'x', idempotencyKey: 'k');
    expect(fn () => $d->emitirPix($input, $this->cred, 'cob'))->toThrow(DriverNotSupportedException::class);
    expect(fn () => $d->emitirPix($input, $this->cred, 'cobv'))->toThrow(DriverNotSupportedException::class);
});

it('cobrarCartao throw', function () {
    expect(fn () => (new BcbPixDriver())->cobrarCartao(
        new EmitirCobrancaInput(businessId: 1, contactId: 1, valorCentavos: 100, vencimento: new DateTimeImmutable(), descricao: 'x', idempotencyKey: 'k'),
        $this->cred,
        new CardToken(token: 't', brand: 'visa', lastFour: '4242', holderName: 'X', expMonth: '12', expYear: '2030'),
    ))->toThrow(DriverNotSupportedException::class);
});

it('refund throw (revoga mandato em vez)', function () {
    expect(fn () => (new BcbPixDriver())->refund(
        (object) ['gateway_external_id' => 'x'],
        $this->cred,
        100,
        'motivo',
    ))->toThrow(DriverNotSupportedException::class);
});

// ─── emitirPixAutomatico ─────────────────────────────────────────────────

it('emitirPixAutomatico OK via PUT /v2/rec/{id}', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'bcb-tk'], 200),
        '*/v2/rec/*'    => Http::response([
            'idRec'  => 'RR000000000000000000001',
            'status' => 'CRIADA',
        ], 201),
    ]);

    $account = (object) ['id' => 1, 'payment_gateway_credential_id' => $this->cred->id];
    $result = (new PaymentGatewayService())->for($account)->emitirPixAutomatico(new EmitirCobrancaInput(
        businessId: 1, contactId: 700, valorCentavos: 9990,
        vencimento: new DateTimeImmutable('2026-06-01'),
        descricao: 'Mensalidade Oimpresso Premium',
        idempotencyKey: 'rec:wagner:tenant-99:2026-06',
        origemType: 'subscription_license',
        origemId: 99,
        meta: [
            'payer_cpf_cnpj' => '12345678901',
            'payer_name'     => 'Tenant Cliente Wagner',
            'pix_key'        => 'wagner@oimpresso.com.br',
            'periodicidade'  => 'MENSAL',
        ],
    ));

    expect($result->tipo)->toBe('pix_recv');
    expect($result->gatewayExternalId)->toBe('RR000000000000000000001');

    Http::assertSent(function ($r) {
        return str_contains($r->url(), '/v2/rec/rec:wagner:tenant-99:2026-06')
            && $r->method() === 'PUT'
            && $r['calendario']['periodicidade'] === 'MENSAL'
            && $r['pagador']['cpf'] === '12345678901'
            && $r['recebedor']['chave'] === 'wagner@oimpresso.com.br';
    });
});

it('emitirPixAutomatico aceita CNPJ pagador (14 dígitos)', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/v2/rec/*'    => Http::response(['idRec' => 'r-pj', 'status' => 'CRIADA'], 201),
    ]);

    (new BcbPixDriver())->emitirPixAutomatico(new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 50000,
        vencimento: new DateTimeImmutable('+30 days'),
        descricao: 'PJ recv',
        idempotencyKey: 'rec:pj:001',
        meta: [
            'payer_cpf_cnpj' => '12345678000100', // CNPJ 14 dígitos
            'payer_name'     => 'Empresa LTDA',
            'pix_key'        => 'cnpj@empresa',
        ],
    ), $this->cred);

    Http::assertSent(fn ($r) => $r['pagador']['cnpj'] === '12345678000100' && ! isset($r['pagador']['cpf']));
});

it('emitirPixAutomatico sem CPF/CNPJ pagador → InvalidPayerException', function () {
    Http::fake(['*/oauth/token' => Http::response(['access_token' => 'tk'], 200)]);

    expect(fn () => (new BcbPixDriver())->emitirPixAutomatico(
        new EmitirCobrancaInput(
            businessId: 1, contactId: 1, valorCentavos: 100,
            vencimento: new DateTimeImmutable(),
            descricao: 'x', idempotencyKey: 'k',
            meta: ['pix_key' => 'x@y'], // sem payer_cpf_cnpj
        ),
        $this->cred,
    ))->toThrow(InvalidPayerException::class);
});

it('emitirPixAutomatico sem pix_key → InvalidPayerException', function () {
    Http::fake(['*/oauth/token' => Http::response(['access_token' => 'tk'], 200)]);

    expect(fn () => (new BcbPixDriver())->emitirPixAutomatico(
        new EmitirCobrancaInput(
            businessId: 1, contactId: 1, valorCentavos: 100,
            vencimento: new DateTimeImmutable(),
            descricao: 'x', idempotencyKey: 'k',
            meta: ['payer_cpf_cnpj' => '12345678901'],
        ),
        $this->cred,
    ))->toThrow(InvalidPayerException::class);
});

it('emitirPixAutomatico API 422 → GatewayUnavailable', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/v2/rec/*'    => Http::response(['erro' => 'periodicidade inválida'], 422),
    ]);

    expect(fn () => (new BcbPixDriver())->emitirPixAutomatico(
        new EmitirCobrancaInput(
            businessId: 1, contactId: 1, valorCentavos: 100,
            vencimento: new DateTimeImmutable(),
            descricao: 'x', idempotencyKey: 'k',
            meta: ['payer_cpf_cnpj' => '12345678901', 'pix_key' => 'k@x'],
        ),
        $this->cred,
    ))->toThrow(GatewayUnavailableException::class);
});

// ─── cancelar (revogar mandato) ─────────────────────────────────────────

it('cancelar revoga mandato via DELETE /v2/rec/{id}', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/v2/rec/RR000123' => Http::response(['status' => 'CANCELADA'], 200),
    ]);

    $cobranca = (object) ['gateway_external_id' => 'RR000123'];
    (new BcbPixDriver())->cancelar($cobranca, $this->cred, 'Cliente cancelou contrato');

    Http::assertSent(fn ($r) => $r->method() === 'DELETE'
        && str_contains($r->url(), '/v2/rec/RR000123')
        && $r['motivo'] === 'Cliente cancelou contrato');
});

it('cancelar sem gateway_external_id → InvalidPayer', function () {
    $cobranca = (object) [];
    expect(fn () => (new BcbPixDriver())->cancelar($cobranca, $this->cred, 'x'))
        ->toThrow(InvalidPayerException::class);
});

// ─── consultar ───────────────────────────────────────────────────────────

it('consultar mapeia status BCB canon', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/v2/rec/RR-active' => Http::response([
            'idRec'  => 'RR-active',
            'status' => 'ATIVA',
            'pagador' => ['cpf' => '***********'],
        ], 200),
    ]);

    $status = (new BcbPixDriver())->consultar(
        (object) ['gateway_external_id' => 'RR-active'],
        $this->cred,
    );

    expect($status->status)->toBe('emitida');
});

// ─── healthCheck ─────────────────────────────────────────────────────────

it('healthCheck OK', function () {
    Http::fake(['*/oauth/token' => Http::response(['access_token' => 'tk'], 200)]);
    expect((new BcbPixDriver())->healthCheck($this->cred)->ok)->toBeTrue();
});

it('healthCheck down 401', function () {
    Http::fake(['*/oauth/token' => Http::response(['error' => 'bad'], 401)]);
    expect((new BcbPixDriver())->healthCheck($this->cred)->ok)->toBeFalse();
});

// ─── processWebhook ──────────────────────────────────────────────────────

it('processWebhook extrai idRec do payload BCB pix[].infoPagador.idRec', function () {
    $d = new BcbPixDriver();

    $r1 = $d->processWebhook([
        'pix' => [
            ['valor' => '99.90', 'infoPagador' => ['idRec' => 'RR-001']],
        ],
    ], $this->cred);
    expect($r1->gateway_external_id)->toBe('RR-001');

    $r2 = $d->processWebhook(['idRec' => 'RR-direct'], $this->cred);
    expect($r2->gateway_external_id)->toBe('RR-direct');

    expect($d->processWebhook(['no_id' => 'x'], $this->cred))->toBeNull();
});

// ─── credentials ─────────────────────────────────────────────────────────

it('credential sem base_url → CredentialMisconfigured', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id' => 1, 'gateway_key' => 'bcb_pix',
        'ambiente' => 'sandbox', 'ativo' => true,
        'config_json' => ['client_id' => 'x', 'client_secret' => 'y'],
        // sem base_url
    ]);

    expect(fn () => (new BcbPixDriver())->healthCheck($bad))
        ->toThrow(CredentialMisconfiguredException::class);
});

it('credential gateway_key errado → CredentialMisconfigured', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id' => 1, 'gateway_key' => 'inter',
        'ambiente' => 'sandbox', 'ativo' => true,
        'config_json' => ['client_id' => 'x', 'client_secret' => 'y', 'base_url' => 'https://x'],
    ]);

    expect(fn () => (new BcbPixDriver())->healthCheck($bad))
        ->toThrow(CredentialMisconfiguredException::class);
});
