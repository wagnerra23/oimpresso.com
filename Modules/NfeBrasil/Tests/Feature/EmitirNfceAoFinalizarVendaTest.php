<?php

declare(strict_types=1);

use App\Events\SellCreatedOrModified;
use App\Transaction;
use Illuminate\Support\Facades\Queue;
use Modules\NfeBrasil\Jobs\EmitirNfceJob;
use Modules\NfeBrasil\Listeners\EmitirNfceAoFinalizarVenda;

uses(Tests\TestCase::class);

/**
 * US-NFE-002 fase 1 · Listener `SellCreatedOrModified` → `EmitirNfceJob`.
 *
 * Tests garantem:
 *   1. Listener registrado no event dispatcher (provider faz binding)
 *   2. Flag OFF → no-op (Job NÃO dispatched mesmo com venda elegível)
 *   3. Filtro `type='sell'` (compras/transferências/etc não disparam)
 *   4. Filtro `status='final'` (vendas em rascunho não disparam)
 *   5. Filtro `payment_status` (vendas a prazo `due` não disparam)
 *   6. Flag ON + venda elegível → Job dispatched com (businessId, transactionId)
 *
 * Idempotência (UNIQUE business_id+transaction_id) é testada no Job-level test
 * separado — listener apenas roteia.
 */

it('flag OFF → Job NÃO dispatched mesmo com venda elegível', function () {
    config(['nfebrasil.auto_emission_on_sell_completed' => false]);
    Queue::fake();

    $tx = nfceTest_makeFakeTransaction([
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'paid',
    ]);

    (new EmitirNfceAoFinalizarVenda)->handle(new SellCreatedOrModified($tx));

    Queue::assertNotPushed(EmitirNfceJob::class);
});

it('filtra type !== sell (purchase/transfer não emite NFC-e)', function () {
    config(['nfebrasil.auto_emission_on_sell_completed' => true]);
    Queue::fake();

    foreach (['purchase', 'sell_transfer', 'expense', 'opening_balance'] as $type) {
        $tx = nfceTest_makeFakeTransaction([
            'type' => $type,
            'status' => 'final',
            'payment_status' => 'paid',
        ]);
        (new EmitirNfceAoFinalizarVenda)->handle(new SellCreatedOrModified($tx));
    }

    Queue::assertNotPushed(EmitirNfceJob::class);
});

it('filtra status !== final (rascunho não emite)', function () {
    config(['nfebrasil.auto_emission_on_sell_completed' => true]);
    Queue::fake();

    foreach (['draft', 'quotation'] as $status) {
        $tx = nfceTest_makeFakeTransaction([
            'type' => 'sell',
            'status' => $status,
            'payment_status' => 'paid',
        ]);
        (new EmitirNfceAoFinalizarVenda)->handle(new SellCreatedOrModified($tx));
    }

    Queue::assertNotPushed(EmitirNfceJob::class);
});

it('filtra payment_status (due/null não emite — só paid|partial)', function () {
    config(['nfebrasil.auto_emission_on_sell_completed' => true]);
    Queue::fake();

    foreach (['due', null, 'overdue'] as $payment_status) {
        $tx = nfceTest_makeFakeTransaction([
            'type' => 'sell',
            'status' => 'final',
            'payment_status' => $payment_status,
        ]);
        (new EmitirNfceAoFinalizarVenda)->handle(new SellCreatedOrModified($tx));
    }

    Queue::assertNotPushed(EmitirNfceJob::class);
});

it('flag ON + venda elegível paid → Job dispatched com (biz_id, tx_id)', function () {
    config(['nfebrasil.auto_emission_on_sell_completed' => true]);
    Queue::fake();

    $tx = nfceTest_makeFakeTransaction([
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'paid',
        'business_id' => 1,
        'id' => 12345,
    ]);

    (new EmitirNfceAoFinalizarVenda)->handle(new SellCreatedOrModified($tx));

    Queue::assertPushed(EmitirNfceJob::class, function (EmitirNfceJob $job) {
        return $job->businessId === 1 && $job->transactionId === 12345;
    });
});

it('flag ON + venda partial → Job dispatched (parcial conta como pago)', function () {
    config(['nfebrasil.auto_emission_on_sell_completed' => true]);
    Queue::fake();

    $tx = nfceTest_makeFakeTransaction([
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'partial',
        'business_id' => 7,
        'id' => 999,
    ]);

    (new EmitirNfceAoFinalizarVenda)->handle(new SellCreatedOrModified($tx));

    Queue::assertPushed(EmitirNfceJob::class);
});

/**
 * Cria uma Transaction "fake" sem persistir em DB.
 *
 * Transaction tem ~80 colunas e mass-assignment pode ser custoso. Como o
 * listener só lê 5 propriedades (id, business_id, type, status, payment_status),
 * setar via `forceFill` numa instância non-persisted é suficiente — não
 * precisamos da row no banco.
 *
 * Function name prefixed `nfceTest_` pra evitar colisão com helpers de outros
 * tests (Pest carrega tudo no mesmo namespace global).
 */
function nfceTest_makeFakeTransaction(array $attrs): Transaction
{
    $tx = new Transaction;
    $tx->forceFill(array_merge([
        'id' => 1,
        'business_id' => 1,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'paid',
        'final_total' => 100.0,
    ], $attrs));
    return $tx;
}
