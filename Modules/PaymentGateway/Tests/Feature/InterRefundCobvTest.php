<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\InterDriver;
use Modules\PaymentGateway\Services\PaymentGatewayService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Onda 4c — ADR 0170.
 *
 * InterDriver agora suporta:
 *   - refund PIX cob/cobv (POST /pix/v2/cob/{txid}/devolucao/{idDev})
 *   - emitirPix tipo=cobv (PUT /pix/v2/cobv/{txid} com dataDeVencimento)
 *
 * NÃO suporta:
 *   - refund de boleto (Inter exige TED reverso manual via PIX recebimento)
 */

beforeEach(function () {
    session(['business.id' => 1]);
    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => ['client_id' => 'inter-4c', 'client_secret' => 'sec-4c'],
    ]);
});

// ─── supports() ──────────────────────────────────────────────────────────

it('supports inclui boleto + pix_cob + pix_cobv na Onda 4c', function () {
    $d = new InterDriver();
    expect($d->supports('boleto'))->toBeTrue();
    expect($d->supports('pix_cob'))->toBeTrue();
    expect($d->supports('pix_cobv'))->toBeTrue();
    expect($d->supports('pix_recv'))->toBeFalse();
    expect($d->supports('card'))->toBeFalse();
});

// ─── emitirPix cobv ──────────────────────────────────────────────────────

it('emitirPix tipo=cobv OK via PUT /pix/v2/cobv/{txid}', function () {
    Http::fake([
        '*/oauth/v2/token'   => Http::response(['access_token' => 'tk-cobv'], 200),
        '*/pix/v2/cobv/*'    => Http::response([
            'txid'          => 'pix:cobv-001',
            'pixCopiaECola' => '00020126...cobv-emv...6304ABCD',
        ], 200),
    ]);

    $account = (object) ['id' => 1, 'payment_gateway_credential_id' => $this->cred->id];
    $result = (new PaymentGatewayService())->for($account)->emitirPix(new EmitirCobrancaInput(
        businessId: 1, contactId: 500, valorCentavos: 30000,
        vencimento: new DateTimeImmutable('+30 days'),
        descricao: 'PIX com vencimento',
        idempotencyKey: 'pix:cobv-001',
        meta: [
            'payer_cpf_cnpj' => '99988877766',
            'payer_name'     => 'Ana',
            'pix_key'        => 'cnpj@empresa',
            'validade_apos_vencimento' => 60,
        ],
    ), 'cobv');

    expect($result->tipo)->toBe('pix_cobv');
    expect($result->gatewayExternalId)->toBe('pix:cobv-001');
    expect($result->pixEmv)->toContain('00020126');

    // Verificar payload tem dataDeVencimento + validadeAposVencimento
    Http::assertSent(function ($r) {
        return str_contains($r->url(), '/pix/v2/cobv/pix:cobv-001')
            && $r->method() === 'PUT'
            && isset($r['calendario']['dataDeVencimento'])
            && $r['calendario']['validadeAposVencimento'] === 60;
    });
});

it('emitirPix tipo=cobv usa validade default 30 dias quando não especificado', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tk'], 200),
        '*/pix/v2/cobv/*'  => Http::response(['txid' => 'k', 'pixCopiaECola' => '00020126x'], 200),
    ]);

    (new InterDriver())->emitirPix(new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 100,
        vencimento: new DateTimeImmutable('+30 days'),
        descricao: 'x', idempotencyKey: 'k',
        meta: ['payer_cpf_cnpj' => '11122233344', 'payer_name' => 'X', 'pix_key' => 'k@x'],
    ), $this->cred, 'cobv');

    Http::assertSent(fn ($r) => $r['calendario']['validadeAposVencimento'] === 30);
});

it('emitirPix tipo=recv → DriverNotSupportedException (Onda 4d BcbPix)', function () {
    expect(fn () => (new InterDriver())->emitirPix(
        new EmitirCobrancaInput(businessId: 1, contactId: 1, valorCentavos: 100, vencimento: new DateTimeImmutable(), descricao: 'x', idempotencyKey: 'k'),
        $this->cred,
        'recv',
    ))->toThrow(DriverNotSupportedException::class);
});

it('emitirPix cobv ainda valida pix_key obrigatório', function () {
    Http::fake(['*/oauth/v2/token' => Http::response(['access_token' => 'tk'], 200)]);

    expect(fn () => (new InterDriver())->emitirPix(
        new EmitirCobrancaInput(
            businessId: 1, contactId: 1, valorCentavos: 100,
            vencimento: new DateTimeImmutable(),
            descricao: 'x', idempotencyKey: 'k',
            meta: ['payer_cpf_cnpj' => '12345678900'],
        ),
        $this->cred,
        'cobv',
    ))->toThrow(InvalidPayerException::class);
});

// ─── refund ──────────────────────────────────────────────────────────────

it('refund PIX cob OK via PUT /pix/v2/cob/{txid}/devolucao/{idDev}', function () {
    Http::fake([
        '*/oauth/v2/token'              => Http::response(['access_token' => 'tk'], 200),
        '*/pix/v2/cob/pix-tx-001/devolucao/*' => Http::response([
            'id'     => 'devolucao-uuid-001',
            'rtrId'  => 'inter-rtr-001',
            'valor'  => '50.00',
            'status' => 'DEVOLVIDO',
        ], 201),
    ]);

    $cobranca = (object) [
        'gateway_external_id' => 'pix-tx-001',
        'tipo'                => 'pix_cob',
    ];

    (new InterDriver())->refund($cobranca, $this->cred, 5000, 'Cliente desistiu');

    Http::assertSent(function ($r) {
        return str_contains($r->url(), '/pix/v2/cob/pix-tx-001/devolucao/')
            && $r->method() === 'PUT'
            && $r['valor'] === '50.00'
            && $r['descricao'] === 'Cliente desistiu';
    });
});

it('refund PIX cobv OK (mesmo endpoint, tipo aceito)', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tk'], 200),
        '*/pix/v2/cob/pix-tx-cobv/devolucao/*' => Http::response(['status' => 'DEVOLVIDO'], 201),
    ]);

    $cobranca = (object) [
        'gateway_external_id' => 'pix-tx-cobv',
        'tipo'                => 'pix_cobv',
    ];

    (new InterDriver())->refund($cobranca, $this->cred, null, 'Total');

    Http::assertSent(function ($r) {
        // valor=0.00 quando $valorCentavos é null (devolução total — Inter usa valor original do cob)
        return $r['valor'] === '0.00';
    });
});

it('refund de boleto → DriverNotSupportedException com mensagem clara', function () {
    $cobranca = (object) [
        'gateway_external_id' => '00012345',
        'tipo'                => 'boleto',
    ];

    expect(fn () => (new InterDriver())->refund($cobranca, $this->cred, 5000, 'Cliente'))
        ->toThrow(DriverNotSupportedException::class);
});

it('refund sem gateway_external_id → InvalidPayer', function () {
    $cobranca = (object) ['tipo' => 'pix_cob'];

    expect(fn () => (new InterDriver())->refund($cobranca, $this->cred, 1000, 'x'))
        ->toThrow(InvalidPayerException::class);
});

it('refund Inter API 422 → GatewayUnavailableException', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tk'], 200),
        '*/pix/v2/cob/*/devolucao/*' => Http::response(['error' => 'valor inválido'], 422),
    ]);

    $cobranca = (object) ['gateway_external_id' => 'pix-tx-err', 'tipo' => 'pix_cob'];

    expect(fn () => (new InterDriver())->refund($cobranca, $this->cred, 5000, 'x'))
        ->toThrow(GatewayUnavailableException::class);
});

it('refund usa refund_idempotency_key da cobranca se presente', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tk'], 200),
        '*/pix/v2/cob/pix-id/devolucao/my-custom-idem' => Http::response(['status' => 'OK'], 201),
    ]);

    $cobranca = (object) [
        'gateway_external_id'     => 'pix-id',
        'tipo'                    => 'pix_cob',
        'refund_idempotency_key' => 'my-custom-idem',
    ];

    (new InterDriver())->refund($cobranca, $this->cred, 1000, 'test');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/devolucao/my-custom-idem'));
});
