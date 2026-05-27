<?php

declare(strict_types=1);

use Modules\PaymentGateway\Contracts\PaymentDriverContract;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Services\Drivers\SicoobApiDriver;

uses(Tests\TestCase::class);

/**
 * Onda 4f.sicoob_api PR1 — US-FIN-044.
 *
 * Testa skeleton: instanciação, key, supports, throws DriverNotSupported
 * em todos métodos de operação (que chegam em PR2-PR4).
 *
 * Pest contract test REAL (com mock HTTP) chega no PR2 quando emitirBoleto
 * estiver implementado. Aqui só validamos contrato superficial.
 */
it('instancia SicoobApiDriver via classe concreta', function () {
    $driver = new SicoobApiDriver();

    expect($driver)->toBeInstanceOf(PaymentDriverContract::class);
});

it('expõe key "sicoob_api"', function () {
    expect((new SicoobApiDriver())->key())->toBe('sicoob_api');
});

it('supports boleto + pix_cob mas não card/pix_cobv', function () {
    $driver = new SicoobApiDriver();

    expect($driver->supports('boleto'))->toBeTrue()
        ->and($driver->supports('pix_cob'))->toBeTrue()
        ->and($driver->supports('card'))->toBeFalse()
        ->and($driver->supports('pix_cobv'))->toBeFalse()
        ->and($driver->supports('pix_recv'))->toBeFalse();
});

function sicoobPr1Input(): EmitirCobrancaInput
{
    return new EmitirCobrancaInput(
        businessId: 4,
        contactId: 1,
        valorCentavos: 10000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'Test PR1',
        idempotencyKey: 'sk-pr1-' . uniqid(),
    );
}

it('emitirBoleto lança DriverNotSupportedException com aviso PR2', function () {
    expect(fn () => (new SicoobApiDriver())->emitirBoleto(sicoobPr1Input(), (object) []))
        ->toThrow(DriverNotSupportedException::class, 'PR2');
});

it('emitirPix lança DriverNotSupportedException', function () {
    expect(fn () => (new SicoobApiDriver())->emitirPix(sicoobPr1Input(), (object) [], 'cob'))
        ->toThrow(DriverNotSupportedException::class);
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

    expect(fn () => (new SicoobApiDriver())->cobrarCartao(sicoobPr1Input(), (object) [], $token))
        ->toThrow(DriverNotSupportedException::class, 'não emite cartão');
});

it('refund de boleto rejeitado — TED reverso manual', function () {
    expect(fn () => (new SicoobApiDriver())->refund((object) ['tipo' => 'boleto'], (object) [], null, 'erro'))
        ->toThrow(DriverNotSupportedException::class, 'TED reverso');
});

it('emitirPixAutomatico aponta pra bcb_pix driver dedicado', function () {
    expect(fn () => (new SicoobApiDriver())->emitirPixAutomatico(sicoobPr1Input(), (object) []))
        ->toThrow(DriverNotSupportedException::class, 'bcb_pix');
});

it('métodos PR2/PR4 (cancelar, consultar, healthCheck, processWebhook) marcam PR esperado', function () {
    $driver = new SicoobApiDriver();
    $stub = (object) ['gateway_external_id' => 'NN-123'];

    expect(fn () => $driver->cancelar($stub, (object) [], 'cliente_pediu'))
        ->toThrow(DriverNotSupportedException::class, 'PR2');

    expect(fn () => $driver->consultar($stub, (object) []))
        ->toThrow(DriverNotSupportedException::class, 'PR2');

    expect(fn () => $driver->healthCheck((object) []))
        ->toThrow(DriverNotSupportedException::class, 'PR2');

    expect(fn () => $driver->processWebhook(['evento' => 'cobranca.liquidada'], (object) []))
        ->toThrow(DriverNotSupportedException::class, 'PR4');
});

it('registry PaymentGatewayService mapeia sicoob_api → SicoobApiDriver::class', function () {
    $reflection = new ReflectionClass(\Modules\PaymentGateway\Services\PaymentGatewayService::class);
    $drivers = $reflection->getConstant('DRIVERS');

    expect($drivers)->toHaveKey('sicoob_api')
        ->and($drivers['sicoob_api'])->toBe(SicoobApiDriver::class);
});
