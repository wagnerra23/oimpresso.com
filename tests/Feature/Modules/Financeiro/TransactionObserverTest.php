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

it('created chama sincronizarDeTransacao', function () {
    $tx = new Transaction(['type' => 'sell', 'payment_status' => 'due', 'business_id' => 1]);
    $tx->id = 1;

    $service = Mockery::mock(TituloAutoService::class);
    $service->shouldReceive('sincronizarDeTransacao')->once()->with($tx);

    (new TransactionObserver($service))->created($tx);
});

it('updated NAO sincroniza se campos financeiros nao mudaram', function () {
    $tx = Mockery::mock(new Transaction(['business_id' => 1]))->makePartial();
    $tx->shouldReceive('wasChanged')->andReturn(false);

    $service = Mockery::mock(TituloAutoService::class);
    $service->shouldNotReceive('sincronizarDeTransacao');

    (new TransactionObserver($service))->updated($tx);
});

it('updated sincroniza quando payment_status muda', function () {
    $tx = Mockery::mock(new Transaction(['business_id' => 1]))->makePartial();
    // A lista de campos cresce com o produto (sub_type entrou com a bonificação, P-154);
    // o contrato aqui é que payment_status está nela, não a lista inteira.
    $tx->shouldReceive('wasChanged')
        ->withArgs(fn ($campos) => is_array($campos) && in_array('payment_status', $campos, true))
        ->andReturn(true);

    $service = Mockery::mock(TituloAutoService::class);
    $service->shouldReceive('sincronizarDeTransacao')->once()->with($tx);

    (new TransactionObserver($service))->updated($tx);
});

it('deleted chama cancelarSeExistir com motivo de venda', function () {
    $tx = new Transaction(['type' => 'sell', 'business_id' => 1]);
    $tx->id = 1;

    $service = Mockery::mock(TituloAutoService::class);
    $service->shouldReceive('cancelarSeExistir')
        ->once()
        ->with($tx, 'venda excluida');

    (new TransactionObserver($service))->deleted($tx);
});

it('deleted de purchase chama cancelarSeExistir com motivo de compra', function () {
    $tx = new Transaction(['type' => 'purchase', 'business_id' => 1]);
    $tx->id = 1;

    $service = Mockery::mock(TituloAutoService::class);
    $service->shouldReceive('cancelarSeExistir')
        ->once()
        ->with($tx, 'compra excluida');

    (new TransactionObserver($service))->deleted($tx);
});
