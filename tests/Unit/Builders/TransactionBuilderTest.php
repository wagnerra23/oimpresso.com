<?php

declare(strict_types=1);

use App\Transaction;
use Tests\Builders\TransactionBuilder;

uses(Tests\TestCase::class);

/**
 * Smoke tests do TransactionBuilder — garante que cenários canônicos retornam
 * valores corretos e fluent API funciona como esperado.
 *
 * NÃO toca DB — todos os tests usam instâncias non-persisted via forceFill.
 */

it('venda() retorna cenário canônico (sell + final + paid)', function () {
    $tx = TransactionBuilder::venda()->build();

    expect($tx)->toBeInstanceOf(Transaction::class)
        ->and($tx->type)->toBe('sell')
        ->and($tx->status)->toBe('final')
        ->and($tx->payment_status)->toBe('paid')
        ->and((float) $tx->final_total)->toBe(100.00);
});

it('vendaRascunho() retorna status=draft', function () {
    $tx = TransactionBuilder::vendaRascunho()->build();

    expect($tx->type)->toBe('sell')
        ->and($tx->status)->toBe('draft');
});

it('vendaPendente() retorna payment_status=due', function () {
    $tx = TransactionBuilder::vendaPendente()->build();

    expect($tx->payment_status)->toBe('due');
});

it('compra() retorna type=purchase', function () {
    $tx = TransactionBuilder::compra()->build();

    expect($tx->type)->toBe('purchase')
        ->and((float) $tx->final_total)->toBe(200.00);
});

it('transferencia() retorna type=sell_transfer com final_total=0', function () {
    $tx = TransactionBuilder::transferencia()->build();

    expect($tx->type)->toBe('sell_transfer')
        ->and((float) $tx->final_total)->toBe(0.0);
});

it('devolucao() retorna type=sell_return', function () {
    $tx = TransactionBuilder::devolucao()->build();

    expect($tx->type)->toBe('sell_return');
});

it('fluent API permite override de business + id + ufs', function () {
    $tx = TransactionBuilder::venda()
        ->business(1)
        ->id(12345)
        ->ufOrigem('SP')
        ->ufDestino('RJ')
        ->finalTotal(250.50)
        ->build();

    expect((int) $tx->business_id)->toBe(4)
        ->and((int) $tx->id)->toBe(12345)
        ->and($tx->metadata['uf_origem'])->toBe('SP')
        ->and($tx->metadata['uf_destino'])->toBe('RJ')
        ->and((float) $tx->final_total)->toBe(250.50);
});

it('paid()/partial()/due() mudam payment_status', function () {
    expect(TransactionBuilder::venda()->paid()->build()->payment_status)->toBe('paid')
        ->and(TransactionBuilder::venda()->partial()->build()->payment_status)->toBe('partial')
        ->and(TransactionBuilder::venda()->due()->build()->payment_status)->toBe('due');
});

it('draft()/final() mudam status', function () {
    expect(TransactionBuilder::venda()->draft()->build()->status)->toBe('draft')
        ->and(TransactionBuilder::venda()->final()->build()->status)->toBe('final');
});

it('with() permite override de campo arbitrário', function () {
    $tx = TransactionBuilder::venda()
        ->with('contact_id', 999)
        ->with('invoice_no', 'NF-2026-001')
        ->build();

    expect((int) $tx->contact_id)->toBe(999)
        ->and($tx->invoice_no)->toBe('NF-2026-001');
});

it('withAttrs() permite override múltiplo via array', function () {
    $tx = TransactionBuilder::venda()
        ->withAttrs([
            'contact_id'  => 7,
            'location_id' => 2,
            'sub_type'    => 'sales_order',
        ])
        ->build();

    expect((int) $tx->contact_id)->toBe(7)
        ->and((int) $tx->location_id)->toBe(2)
        ->and($tx->sub_type)->toBe('sales_order');
});

it('buildIds() retorna tupla [id, business_id]', function () {
    [$id, $bizId] = TransactionBuilder::venda()
        ->id(42)
        ->business(7)
        ->buildIds();

    expect($id)->toBe(42)
        ->and($bizId)->toBe(7);
});
