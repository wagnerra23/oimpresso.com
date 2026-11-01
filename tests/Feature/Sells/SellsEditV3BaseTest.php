<?php

declare(strict_types=1);

use App\Services\Sells\BaseEdicaoVendaV3;
use App\Transaction;

it('UC-SEDIT-07/09 · base V3 conserva campos ausentes da UI e decimais sem float', function () {
    $venda = new Transaction;
    $venda->setRawAttributes([
        'status' => 'final', 'discount_amount' => '1.1250', 'shipping_charges' => '1234.5678',
        'exchange_rate' => '1.0000', 'additional_expense_value_1' => '2.3500',
        'custom_field_1' => 'Pedido fictício', 'staff_note' => 'Preservar',
        'pay_term_number' => 30, 'pay_term_type' => 'days', 'selling_price_group_id' => 7,
        'additional_notes' => 'Observação original', 'tax_id' => 9, 'current_stage_id' => 99,
        'business_id' => 98, 'final_total' => '180.0000',
    ], true);
    $antes = $venda->getAttributes();
    $base = app(BaseEdicaoVendaV3::class)->daVenda($venda);

    expect($base)->toMatchArray([
        'discount_amount' => '1,1250', 'shipping_charges' => '1234,5678',
        'exchange_rate' => '1,0000', 'additional_expense_value_1' => '2,3500',
        'custom_field_1' => 'Pedido fictício', 'staff_note' => 'Preservar',
        'pay_term_number' => 30, 'pay_term_type' => 'days', 'price_group' => 7,
        'sale_note' => 'Observação original', 'tax_rate_id' => 9,
    ])->not->toHaveKeys(['current_stage_id', 'business_id', 'final_total', 'payment']);
    expect($venda->getAttributes())->toBe($antes);
});

it('UC-SEDIT-09 · base V3 não converte orçamento/proforma em rascunho comum', function (array $atributos, string $status) {
    $venda = new Transaction;
    $venda->setRawAttributes($atributos, true);
    expect(app(BaseEdicaoVendaV3::class)->daVenda($venda)['status'])->toBe($status);
})->with([
    'orçamento' => [['status' => 'draft', 'is_quotation' => 1, 'sub_status' => 'quotation'], 'quotation'],
    'proforma' => [['status' => 'draft', 'is_quotation' => 0, 'sub_status' => 'proforma'], 'proforma'],
    'rascunho comum' => [['status' => 'draft', 'is_quotation' => 0], 'draft'],
]);
