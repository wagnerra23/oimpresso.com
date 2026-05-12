<?php

declare(strict_types=1);

use App\Domain\Fsm\Services\InitialStageResolver;
use App\Transaction;

uses(Tests\TestCase::class);

/**
 * Service InitialStageResolver — testa lógica de mapeamento status legacy → stage FSM.
 *
 * Service stateless e sem DB (só lê props da Transaction). Mantém suite rápida.
 */

beforeEach(function () {
    $this->resolver = new InitialStageResolver;
});

function makeTransaction(string $status, ?string $paymentStatus, ?string $subStatus): Transaction
{
    $tx = new Transaction;
    $tx->status = $status;
    $tx->payment_status = $paymentStatus;
    $tx->sub_status = $subStatus;
    return $tx;
}

it('1. draft + quotation sub_status → quote_sent', function () {
    $tx = makeTransaction('draft', null, 'quotation');
    expect($this->resolver->resolve($tx))->toBe('quote_sent');
});

it('2. draft + sub_status null → quote_draft', function () {
    $tx = makeTransaction('draft', null, null);
    expect($this->resolver->resolve($tx))->toBe('quote_draft');
});

it('3. final + paid → paid', function () {
    $tx = makeTransaction('final', 'paid', null);
    expect($this->resolver->resolve($tx))->toBe('paid');
});

it('4. final + partial → invoiced', function () {
    $tx = makeTransaction('final', 'partial', null);
    expect($this->resolver->resolve($tx))->toBe('invoiced');
});

it('5. final + due → invoiced', function () {
    $tx = makeTransaction('final', 'due', null);
    expect($this->resolver->resolve($tx))->toBe('invoiced');
});

it('6. status desconhecido → quote_draft (fallback)', function () {
    $tx = makeTransaction('unknown_status', null, null);
    expect($this->resolver->resolve($tx))->toBe('quote_draft');
});
