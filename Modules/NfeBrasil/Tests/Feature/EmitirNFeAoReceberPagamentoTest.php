<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Modules\NfeBrasil\Listeners\EmitirNFeAoReceberPagamento;
use Modules\RecurringBilling\Events\InvoicePaid;

uses(Tests\TestCase::class);

/**
 * US-RB-044 stub · Listener registrado, emissão real desabilitada por flag.
 *
 * Tests garantem:
 *   1. Listener está registrado no event dispatcher (provider faz binding)
 *   2. Quando flag desabilitada, listener loga "DISABLED" e não explode
 *   3. Quando flag habilitada (mas NfeService inexistente), lança LogicException
 *      explicando o que falta — feedback claro pra futuro implementador
 *   4. Listener implementa ShouldQueue + queue 'nfe' (separada de rb_webhooks)
 */

it('listener está registrado no event dispatcher pelo NfeBrasilServiceProvider', function () {
    $listeners = Event::getRawListeners()[InvoicePaid::class] ?? [];

    $hasNfeListener = collect($listeners)->contains(function ($l) {
        $key = is_string($l) ? $l : (is_array($l) ? ($l[0] ?? '') : '');
        return is_string($key) && str_contains($key, 'EmitirNFeAoReceberPagamento');
    });

    expect($hasNfeListener)->toBeTrue();
});

it('com flag desabilitada (default), apenas loga e retorna sem explodir', function () {
    config(['nfebrasil.auto_emission_on_invoice_paid' => false]);

    Log::spy();

    $listener = new EmitirNFeAoReceberPagamento();
    $event = new InvoicePaid(
        businessId: 4,
        invoiceRef: 'INV-2026-0001',
        valor: 199.90,
        paidAt: '2026-05-06',
    );

    // Não deve explodir
    $listener->handle($event);

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            return $message === 'NFe emission requested'
                && ($context['invoice_ref'] ?? '') === 'INV-2026-0001';
        });

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'DISABLED')
                && ($context['invoice_ref'] ?? '') === 'INV-2026-0001';
        });
});

it('com flag habilitada e sem NfeService, lança LogicException explicando o gap', function () {
    config(['nfebrasil.auto_emission_on_invoice_paid' => true]);

    $listener = new EmitirNFeAoReceberPagamento();
    $event = new InvoicePaid(
        businessId: 4,
        invoiceRef: 'INV-X',
        valor: 100,
        paidAt: '2026-05-06',
    );

    expect(fn () => $listener->handle($event))
        ->toThrow(\LogicException::class, 'NfeService não implementado');

    config(['nfebrasil.auto_emission_on_invoice_paid' => false]); // reset
});

it('listener implementa ShouldQueue na fila nfe (separada de rb_webhooks)', function () {
    $listener = new EmitirNFeAoReceberPagamento();

    expect($listener)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class)
        ->and($listener->queue)->toBe('nfe')
        ->and($listener->tries)->toBe(3)
        ->and($listener->backoff)->toBe(60);
});

it('event InvoicePaid dispatcha listener via queue (não síncrono)', function () {
    Queue::fake();
    config(['nfebrasil.auto_emission_on_invoice_paid' => false]);

    event(new InvoicePaid(
        businessId: 4,
        invoiceRef: 'INV-Q',
        valor: 50,
        paidAt: '2026-05-06',
    ));

    Queue::assertPushed(\Illuminate\Events\CallQueuedListener::class, function ($job) {
        return $job->class === EmitirNFeAoReceberPagamento::class;
    });
});

it('failed() loga erro estruturado pra observabilidade', function () {
    Log::spy();

    $listener = new EmitirNFeAoReceberPagamento();
    $event = new InvoicePaid(4, 'INV-FAILED', 100, '2026-05-06');
    $err = new \RuntimeException('SEFAZ timeout');

    $listener->failed($event, $err);

    Log::shouldHaveReceived('error')
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'failed após retries')
                && ($context['invoice_ref'] ?? '') === 'INV-FAILED'
                && str_contains($context['error'] ?? '', 'SEFAZ timeout');
        });
});
