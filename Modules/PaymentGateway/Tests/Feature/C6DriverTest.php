<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\C6Driver;
use Modules\PaymentGateway\Services\PaymentGatewayService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

beforeEach(function () {
    session(['business.id' => 1]);
    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'c6',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'C6 Test',
        'config_json'  => [
            'client_id'     => 'c6-client-id',
            'client_secret' => 'c6-secret',
            'conta'         => '00012345',
        ],
    ]);
});

it('C6Driver key=c6 e supports boleto/pix_cob apenas', function () {
    $d = new C6Driver();
    expect($d->key())->toBe('c6');
    expect($d->supports('boleto'))->toBeTrue();
    expect($d->supports('pix_cob'))->toBeTrue();
    expect($d->supports('pix_cobv'))->toBeFalse();
    expect($d->supports('pix_recv'))->toBeFalse();
    expect($d->supports('card'))->toBeFalse();
});

it('emitirBoleto C6 OK', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'c6-token'], 200),
        '*/cobrancas'   => Http::response([
            'id'             => 'c6-cob-001',
            'nossoNumero'    => '99887766',
            'linhaDigitavel' => '12345.99988 77665.544332 21100.998877 1 22220000010000',
            'codigoBarras'   => '12348222200000100009998877665544332211009988',
        ], 200),
    ]);

    $account = (object) ['id' => 1, 'payment_gateway_credential_id' => $this->cred->id];
    $result = (new PaymentGatewayService())->for($account)->emitirBoleto(new EmitirCobrancaInput(
        businessId: 1, contactId: 300, valorCentavos: 20000,
        vencimento: new DateTimeImmutable('+10 days'),
        descricao: 'Mensalidade C6',
        idempotencyKey: 'sale:c6-001',
        meta: ['payer_cpf_cnpj' => '98765432100', 'payer_name' => 'Maria'],
    ));

    expect($result->tipo)->toBe('boleto');
    expect($result->gatewayExternalId)->toBe('c6-cob-001');
    expect($result->nossoNumero)->toBe('99887766');
});

it('emitirPix C6 tipo=cob OK', function () {
    Http::fake([
        '*/oauth/token'    => Http::response(['access_token' => 'tk'], 200),
        '*/pix/cobrancas'  => Http::response([
            'txid' => 'c6-pix-txid-001',
            'emv'  => '00020126...c6-emv...6304WXYZ',
        ], 200),
    ]);

    $account = (object) ['id' => 1, 'payment_gateway_credential_id' => $this->cred->id];
    $result = (new PaymentGatewayService())->for($account)->emitirPix(new EmitirCobrancaInput(
        businessId: 1, contactId: 300, valorCentavos: 7777,
        vencimento: new DateTimeImmutable('+1 day'),
        descricao: 'PIX C6',
        idempotencyKey: 'pix:c6-001',
        meta: ['payer_cpf_cnpj' => '98765432100', 'payer_name' => 'Maria', 'pix_key' => 'chave@c6'],
    ), 'cob');

    expect($result->tipo)->toBe('pix_cob');
    expect($result->gatewayExternalId)->toBe('c6-pix-txid-001');
    expect($result->pixEmv)->toContain('00020126');
});

it('emitirPix tipo=cobv → DriverNotSupportedException', function () {
    expect(fn () => (new C6Driver())->emitirPix(
        new EmitirCobrancaInput(businessId: 1, contactId: 1, valorCentavos: 100, vencimento: new DateTimeImmutable(), descricao: 'x', idempotencyKey: 'k'),
        $this->cred,
        'cobv',
    ))->toThrow(DriverNotSupportedException::class);
});

it('cobrarCartao → DriverNotSupportedException sempre (C6 não emite)', function () {
    expect(fn () => (new C6Driver())->cobrarCartao(
        new EmitirCobrancaInput(businessId: 1, contactId: 1, valorCentavos: 100, vencimento: new DateTimeImmutable(), descricao: 'x', idempotencyKey: 'k'),
        $this->cred,
        new \Modules\PaymentGateway\Dto\CardToken(token: 't', brand: 'visa', lastFour: '4242', holderName: 'X', expMonth: '12', expYear: '2030'),
    ))->toThrow(DriverNotSupportedException::class);
});

it('refund C6 → DriverNotSupportedException (não suportado nesta onda)', function () {
    expect(fn () => (new C6Driver())->refund(
        (object) ['gateway_external_id' => 'x'],
        $this->cred,
        null,
        'motivo',
    ))->toThrow(DriverNotSupportedException::class);
});

it('healthCheck OK', function () {
    Http::fake(['*/oauth/token' => Http::response(['access_token' => 'tk'], 200)]);
    $h = (new C6Driver())->healthCheck($this->cred);
    expect($h->ok)->toBeTrue();
});

it('healthCheck down 401', function () {
    Http::fake(['*/oauth/token' => Http::response(['error' => 'bad'], 401)]);
    $h = (new C6Driver())->healthCheck($this->cred);
    expect($h->ok)->toBeFalse();
});

it('credential gateway_key errado → CredentialMisconfigured', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id' => 1, 'gateway_key' => 'inter',
        'ambiente' => 'sandbox', 'ativo' => true, 'config_json' => ['client_id' => 'x'],
    ]);
    expect(fn () => (new C6Driver())->healthCheck($bad))
        ->toThrow(CredentialMisconfiguredException::class);
});

it('processWebhook extrai transactionId ou id', function () {
    $d = new C6Driver();
    $r1 = $d->processWebhook(['eventType' => 'X', 'transactionId' => 'tx-1'], $this->cred);
    expect($r1->gateway_external_id)->toBe('tx-1');

    $r2 = $d->processWebhook(['id' => 'id-2'], $this->cred);
    expect($r2->gateway_external_id)->toBe('id-2');

    expect($d->processWebhook(['no_id' => 'foo'], $this->cred))->toBeNull();
});

it('consultar mapeia status PAGO → paga', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'tk'], 200),
        '*/cobrancas/c6-cob-x' => Http::response([
            'status'        => 'PAGO',
            'dataPagamento' => '2026-05-19',
            'valorPago'     => 50.00,
        ], 200),
    ]);

    $cobranca = (object) ['gateway_external_id' => 'c6-cob-x'];
    $status = (new C6Driver())->consultar($cobranca, $this->cred);
    expect($status->status)->toBe('paga');
    expect($status->valorPagoCentavos)->toBe(5000);
});
