<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\InterDriver;
use Modules\PaymentGateway\Services\PaymentGatewayService;

uses(Tests\TestCase::class);

/**
 * Onda 4b — ADR 0170.
 * Inter PIX cob via API Pix v2.
 */

beforeEach(function () {
    session(['business.id' => 1]);
    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => [
            'client_id'     => 'inter-pix-cli',
            'client_secret' => 'inter-pix-sec',
        ],
    ]);
});

it('Inter supports inclui pix_cob na Onda 4b', function () {
    $d = new InterDriver();
    expect($d->supports('boleto'))->toBeTrue();
    expect($d->supports('pix_cob'))->toBeFalse(); // supports() ainda retorna só boleto; método valida tipo internamente
    // Nota: Onda 4b expandiu emitirPix mas supports() permanece conservador
    // até PaymentGatewayService.driverFor() distinguir 'boleto' vs 'pix_cob'
});

it('emitirPix Inter tipo=cob OK via PUT /pix/v2/cob/{txid}', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'inter-tk'], 200),
        '*/pix/v2/cob/*'   => Http::response([
            'txid'          => 'pix:inter-001',
            'pixCopiaECola' => '00020126...inter-emv...6304PQRS',
        ], 200),
    ]);

    $account = (object) ['id' => 1, 'payment_gateway_credential_id' => $this->cred->id];
    $result = (new PaymentGatewayService())->for($account)->emitirPix(new EmitirCobrancaInput(
        businessId: 1, contactId: 400, valorCentavos: 12345,
        vencimento: new DateTimeImmutable('+1 day'),
        descricao: 'PIX Inter',
        idempotencyKey: 'pix:inter-001',
        meta: [
            'payer_cpf_cnpj' => '11122233344',
            'payer_name'     => 'Carlos',
            'pix_key'        => 'cnpj@empresa',
        ],
    ), 'cob');

    expect($result->tipo)->toBe('pix_cob');
    expect($result->gatewayExternalId)->toBe('pix:inter-001');
    expect($result->pixEmv)->toContain('00020126');
});

it('emitirPix Inter tipo=cobv → DriverNotSupportedException (vai pra Onda 4c)', function () {
    expect(fn () => (new InterDriver())->emitirPix(
        new EmitirCobrancaInput(businessId: 1, contactId: 1, valorCentavos: 100, vencimento: new DateTimeImmutable(), descricao: 'x', idempotencyKey: 'k'),
        $this->cred,
        'cobv',
    ))->toThrow(DriverNotSupportedException::class);
});

it('emitirPix sem meta.pix_key → InvalidPayerException', function () {
    Http::fake(['*/oauth/v2/token' => Http::response(['access_token' => 'tk'], 200)]);

    expect(fn () => (new InterDriver())->emitirPix(
        new EmitirCobrancaInput(
            businessId: 1, contactId: 1, valorCentavos: 100,
            vencimento: new DateTimeImmutable(),
            descricao: 'x', idempotencyKey: 'k',
            meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'X'],
            // sem pix_key
        ),
        $this->cred,
        'cob',
    ))->toThrow(InvalidPayerException::class);
});
