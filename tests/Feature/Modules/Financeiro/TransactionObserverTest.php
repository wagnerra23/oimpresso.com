<?php

use App\Transaction;
use Modules\Financeiro\Observers\TransactionObserver;
use Modules\Financeiro\Services\TituloAutoService;

/**
 * Test offline-safe (sem DB) — verifica que o Observer delega corretamente
 * pro TituloAutoService com base no evento (created/updated/deleted) e
 * mudancas de campo (wasChanged).
 *
 * Test integracao end-to-end com DB ficaria em
 * TransactionObserverIntegrationTest quando setup CI permitir.
 */

it('created chama sincronizarDeVenda', function () {
    $tx = new Transaction(['type' => 'sell', 'payment_status' => 'due', 'business_id' => 1]);
    $tx->id = 1;

    $service = Mockery::mock(TituloAutoService::class);
    $service->shouldReceive('sincronizarDeVenda')->once()->with($tx);

    (new TransactionObserver($service))->created($tx);
});

it('updated NAO sincroniza se campos financeiros nao mudaram', function () {
    $tx = Mockery::mock(new Transaction(['business_id' => 1]))->makePartial();
    $tx->shouldReceive('wasChanged')->andReturn(false);

    $service = Mockery::mock(TituloAutoService::class);
    $service->shouldNotReceive('sincronizarDeVenda');

    (new TransactionObserver($service))->updated($tx);
});

it('updated sincroniza quando payment_status muda', function () {
    $tx = Mockery::mock(new Transaction(['business_id' => 1]))->makePartial();
    $tx->shouldReceive('wasChanged')
        ->with(['payment_status', 'final_total', 'pay_term_number', 'pay_term_type'])
        ->andReturn(true);

    $service = Mockery::mock(TituloAutoService::class);
    $service->shouldReceive('sincronizarDeVenda')->once()->with($tx);

    (new TransactionObserver($service))->updated($tx);
});

it('deleted chama cancelarSeExistir com motivo', function () {
    $tx = new Transaction(['type' => 'sell', 'business_id' => 1]);
    $tx->id = 1;

    $service = Mockery::mock(TituloAutoService::class);
    $service->shouldReceive('cancelarSeExistir')
        ->once()
        ->with($tx, 'venda excluida');

    (new TransactionObserver($service))->deleted($tx);
});
