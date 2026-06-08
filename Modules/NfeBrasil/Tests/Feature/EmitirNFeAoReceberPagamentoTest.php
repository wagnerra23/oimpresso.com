<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Events\NFeAutorizada;
use Modules\NfeBrasil\Listeners\EmitirNFeAoReceberPagamento;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;
use Modules\RecurringBilling\Events\InvoicePaid;
use Modules\RecurringBilling\Models\Invoice;

uses(Tests\TestCase::class);

/**
 * US-RB-044 fase 2 · Listener Invoice→NFe — pipeline real ligado.
 *
 * Tests garantem:
 *   1. Listener registrado no event dispatcher (provider faz binding)
 *   2. Flag desabilitada → log + no-op (não toca service)
 *   3. Flag habilitada + invoice ausente → log warning + no-op (defensivo)
 *   4. Flag habilitada + invoice presente → service.emitirParaInvoice é chamado
 *   5. Status autorizada → NFeAutorizada disparado
 *   6. Status rejeitada → NFeAutorizada NÃO disparado
 *   7. Listener implementa ShouldQueue + queue 'nfe' + tries=3 + backoff=60
 *   8. Failed() loga erro estruturado
 */

beforeEach(function () {
    if (! Schema::hasTable('rb_invoices')) {
        test()->markTestSkipped('Tabela rb_invoices ausente — rode migrations RecurringBilling antes.');
    }
});

afterEach(function () {
    config(['nfebrasil.auto_emission_on_invoice_paid' => false]);
});

// ── helpers ───────────────────────────────────────────────────────────────

function fakeNfeServiceQue(NfeEmissao $emissao): NfeService
{
    $svc = \Mockery::mock(NfeService::class);
    $svc->shouldReceive('emitirParaInvoice')->andReturn($emissao);
    return $svc;
}

function fakeNfeEmissaoStub(string $status = 'autorizada', string $cstat = '100'): NfeEmissao
{
    $emissao = new NfeEmissao;
    $emissao->id = 1;
    $emissao->business_id = 1;
    $emissao->status = $status;
    $emissao->cstat = $cstat;
    $emissao->chave_44 = $status === 'autorizada' ? '35210112345678000199550010000000011000000019' : null;
    return $emissao;
}

// ── tests ─────────────────────────────────────────────────────────────────

it('listener está registrado no event dispatcher pelo NfeBrasilServiceProvider', function () {
    $listeners = Event::getRawListeners()[InvoicePaid::class] ?? [];

    $hasNfeListener = collect($listeners)->contains(function ($l) {
        $key = is_string($l) ? $l : (is_array($l) ? ($l[0] ?? '') : '');
        return is_string($key) && str_contains($key, 'EmitirNFeAoReceberPagamento');
    });

    expect($hasNfeListener)->toBeTrue();
});

it('com flag desabilitada (default), apenas loga e retorna sem tocar service', function () {
    config(['nfebrasil.auto_emission_on_invoice_paid' => false]);
    Log::spy();

    $svc = \Mockery::mock(NfeService::class);
    $svc->shouldNotReceive('emitirParaInvoice'); // crítico — desabilitado, não toca

    $listener = new EmitirNFeAoReceberPagamento($svc);
    $listener->handle(new InvoicePaid(
        businessId: 1, invoiceRef: 'INV-2026-0001', valor: 199.90, paidAt: '2026-05-06',
    ));

    Log::shouldHaveReceived('info')->withArgs(function ($msg, $ctx) {
        return str_contains($msg, 'DISABLED') && ($ctx['invoice_ref'] ?? '') === 'INV-2026-0001';
    });
});

it('com flag habilitada e invoice ausente: log warning + no-op (não toca service)', function () {
    config(['nfebrasil.auto_emission_on_invoice_paid' => true]);
    Log::spy();

    $svc = \Mockery::mock(NfeService::class);
    $svc->shouldNotReceive('emitirParaInvoice');

    $listener = new EmitirNFeAoReceberPagamento($svc);
    $listener->handle(new InvoicePaid(
        businessId: 1, invoiceRef: 'INV-INEXISTENTE-9999', valor: 100, paidAt: '2026-05-06',
    ));

    Log::shouldHaveReceived('warning')->withArgs(function ($msg, $ctx) {
        return str_contains($msg, 'Invoice não encontrada')
            && ($ctx['invoice_ref'] ?? '') === 'INV-INEXISTENTE-9999';
    });
});

it('com flag habilitada + invoice presente: chama service.emitirParaInvoice + dispara NFeAutorizada', function () {
    config(['nfebrasil.auto_emission_on_invoice_paid' => true]);

    $invoice = Invoice::create([
        'business_id'      => 1,
        'numero_documento' => 'INV-LISTENER-' . uniqid(),
        'valor'            => 199.90,
        'status'           => 'paid',
        'vencimento'       => now()->toDateString(),
        'pago_em'          => now(),
    ]);

    Event::fake([NFeAutorizada::class]);

    $emissao = fakeNfeEmissaoStub('autorizada', '100');
    $svc = \Mockery::mock(NfeService::class);
    $svc->shouldReceive('emitirParaInvoice')->once()->with(\Mockery::on(fn ($i) => $i->id === $invoice->id))
        ->andReturn($emissao);

    $listener = new EmitirNFeAoReceberPagamento($svc);
    $listener->handle(new InvoicePaid(
        businessId: 1, invoiceRef: $invoice->numero_documento, valor: 199.90, paidAt: '2026-05-06',
    ));

    Event::assertDispatched(NFeAutorizada::class, fn ($e) => $e->emissao->status === 'autorizada');

    $invoice->forceDelete();
});

it('rejeitada: service grava status=rejeitada mas listener NÃO dispara NFeAutorizada', function () {
    config(['nfebrasil.auto_emission_on_invoice_paid' => true]);

    $invoice = Invoice::create([
        'business_id'      => 1,
        'numero_documento' => 'INV-REJ-' . uniqid(),
        'valor'            => 100,
        'status'           => 'paid',
        'vencimento'       => now()->toDateString(),
        'pago_em'          => now(),
    ]);

    Event::fake([NFeAutorizada::class]);

    $emissao = fakeNfeEmissaoStub('rejeitada', '225');
    $svc = \Mockery::mock(NfeService::class);
    $svc->shouldReceive('emitirParaInvoice')->andReturn($emissao);

    $listener = new EmitirNFeAoReceberPagamento($svc);
    $listener->handle(new InvoicePaid(
        businessId: 1, invoiceRef: $invoice->numero_documento, valor: 100, paidAt: '2026-05-06',
    ));

    Event::assertNotDispatched(NFeAutorizada::class);

    $invoice->forceDelete();
});

it('Throwable do service é re-throwado pra queue retry (3 tries)', function () {
    config(['nfebrasil.auto_emission_on_invoice_paid' => true]);

    $invoice = Invoice::create([
        'business_id'      => 1,
        'numero_documento' => 'INV-ERR-' . uniqid(),
        'valor'            => 100,
        'status'           => 'paid',
        'vencimento'       => now()->toDateString(),
        'pago_em'          => now(),
    ]);

    Log::spy();

    $svc = \Mockery::mock(NfeService::class);
    $svc->shouldReceive('emitirParaInvoice')->andThrow(new \RuntimeException('SEFAZ timeout'));

    $listener = new EmitirNFeAoReceberPagamento($svc);

    expect(fn () => $listener->handle(new InvoicePaid(
        businessId: 1, invoiceRef: $invoice->numero_documento, valor: 100, paidAt: '2026-05-06',
    )))->toThrow(\RuntimeException::class, 'SEFAZ timeout');

    Log::shouldHaveReceived('error')->withArgs(function ($msg, $ctx) {
        return str_contains($msg, 'NFe auto-emission falhou')
            && str_contains($ctx['error'] ?? '', 'SEFAZ timeout');
    });

    $invoice->forceDelete();
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
        businessId: 1, invoiceRef: 'INV-Q', valor: 50, paidAt: '2026-05-06',
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

    Log::shouldHaveReceived('error')->withArgs(function ($message, $context) {
        return str_contains($message, 'failed após retries')
            && ($context['invoice_ref'] ?? '') === 'INV-FAILED'
            && str_contains($context['error'] ?? '', 'SEFAZ timeout');
    });
});
