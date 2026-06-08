<?php

declare(strict_types=1);

use Modules\PaymentGateway\Contracts\PaymentDriverContract;
use Modules\PaymentGateway\Contracts\PaymentGatewayContract;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\CobrancaStatus;
use Modules\PaymentGateway\Dto\DriverHealth;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Events\CobrancaCancelada;
use Modules\PaymentGateway\Events\CobrancaEmitida;
use Modules\PaymentGateway\Events\CobrancaErro;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Events\CobrancaVencida;
use Modules\PaymentGateway\Exceptions\CardDeclinedException;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Exceptions\IdempotencyConflictException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Exceptions\PaymentGatewayException;
use Modules\PaymentGateway\Exceptions\WebhookSignatureInvalidException;

uses(Tests\TestCase::class);

/**
 * Smoke esqueleto Onda 1 — ADR 0170.
 *
 * Garante que o módulo carrega + 2 Contracts + 5 DTOs + 5 Events +
 * 8 Exceptions estão tipados corretos antes de Onda 2 começar a usar.
 */

it('config do módulo carrega', function () {
    expect(config('paymentgateway.name'))->toBe('PaymentGateway');
    expect(config('paymentgateway.module_version'))->toBe('0.1.0');
});

it('PaymentGatewayContract é interface (não classe)', function () {
    $reflection = new ReflectionClass(PaymentGatewayContract::class);
    expect($reflection->isInterface())->toBeTrue();
});

it('PaymentDriverContract é interface (não classe)', function () {
    $reflection = new ReflectionClass(PaymentDriverContract::class);
    expect($reflection->isInterface())->toBeTrue();
});

it('Contracts ainda NÃO têm binding no container (Onda 4 amarra)', function () {
    expect(app()->bound(PaymentGatewayContract::class))->toBeFalse();
});

it('DTOs são readonly e instanciáveis', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1,
        contactId: 100,
        valorCentavos: 10000,
        vencimento: new DateTimeImmutable('2026-06-01'),
        descricao: 'Teste',
        idempotencyKey: 'test:smoke-1',
    );
    expect($input->valorCentavos)->toBe(10000);
    expect($input->idempotencyKey)->toBe('test:smoke-1');

    $result = new CobrancaEmitidaResult(
        cobrancaId: 1,
        gatewayExternalId: 'ext-1',
        tipo: 'boleto',
        emitidaEm: new DateTimeImmutable(),
    );
    expect($result->tipo)->toBe('boleto');

    $status = new CobrancaStatus(status: 'pending');
    expect($status->status)->toBe('pending');

    $token = new CardToken(token: 't', brand: 'visa', lastFour: '4242', holderName: 'X', expMonth: '12', expYear: '2030');
    expect($token->lastFour)->toBe('4242');

    $health = new DriverHealth(ok: true, status: 'ok', latencyMs: 123, checkedAt: new DateTimeImmutable());
    expect($health->ok)->toBeTrue();
});

it('DTOs immutability — set em readonly prop joga erro', function () {
    $input = new EmitirCobrancaInput(
        businessId: 1,
        contactId: 100,
        valorCentavos: 100,
        vencimento: new DateTimeImmutable('2026-06-01'),
        descricao: 'x',
        idempotencyKey: 'k',
    );
    expect(fn () => $input->valorCentavos = 999)->toThrow(Error::class);
});

it('Eventos têm trait Dispatchable + SerializesModels', function () {
    foreach ([
        CobrancaEmitida::class,
        CobrancaPaga::class,
        CobrancaVencida::class,
        CobrancaCancelada::class,
        CobrancaErro::class,
    ] as $eventClass) {
        $traits = class_uses_recursive($eventClass);
        expect($traits)->toHaveKey(Illuminate\Foundation\Events\Dispatchable::class);
        expect($traits)->toHaveKey(Illuminate\Queue\SerializesModels::class);
    }
});

it('Exceções específicas extendem PaymentGatewayException raiz', function () {
    foreach ([
        GatewayUnavailableException::class,
        CredentialMisconfiguredException::class,
        InvalidPayerException::class,
        DriverNotSupportedException::class,
        CardDeclinedException::class,
        IdempotencyConflictException::class,
        WebhookSignatureInvalidException::class,
    ] as $exceptionClass) {
        expect(is_subclass_of($exceptionClass, PaymentGatewayException::class))->toBeTrue();
    }
});

it('PaymentGatewayException extends RuntimeException', function () {
    expect(is_subclass_of(PaymentGatewayException::class, RuntimeException::class))->toBeTrue();
});
