<?php

declare(strict_types=1);

/**
 * F2 BACKEND BASELINE — stock_adjustment/create (MWART Wave2 B5).
 */

const SA_CREATE_BLADE = 'resources/views/stock_adjustment/create.blade.php';
const SA_CREATE_CONTROLLER = 'app/Http/Controllers/StockAdjustmentController.php';

function readSACreateController(): string
{
    return file_get_contents(base_path(SA_CREATE_CONTROLLER));
}

it('o formulário Blade foi apagado no cutover (2026-10-27), com a linha de produto do ajuste', function () {
    expect(file_exists(base_path(SA_CREATE_BLADE)))->toBeFalse();
    expect(file_exists(base_path('resources/views/stock_adjustment/partials/product_table_row.blade.php')))->toBeFalse();
    expect(readSACreateController())->not->toContain("view('stock_adjustment.create')");
});

it('Controller PRESERVA permission purchase.create + isSubscribed', function () {
    $source = readSACreateController();
    expect($source)->toContain("auth()->user()->can('purchase.create')");
    expect($source)->toContain("isSubscribed(\$business_id)");
});

it('Controller PRESERVA BusinessLocation::forDropdown(business_id) Tier 0', function () {
    $source = readSACreateController();
    expect($source)->toContain("BusinessLocation::forDropdown(\$business_id)");
});
