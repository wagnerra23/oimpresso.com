<?php

declare(strict_types=1);

/**
 * F2 BACKEND BASELINE — stock_transfers/create (MWART Wave2 B5).
 */

const ST_CREATE_BLADE = 'resources/views/stock_transfer/create.blade.php';
const ST_CREATE_CONTROLLER = 'app/Http/Controllers/StockTransferController.php';

function readSTCreateController(): string
{
    return file_get_contents(base_path(ST_CREATE_CONTROLLER));
}

it('Blade legacy stock_transfer/create.blade.php existe', function () {
    expect(file_exists(base_path(ST_CREATE_BLADE)))->toBeTrue();
});

it('Controller create() PRESERVA return view("stock_transfer.create")', function () {
    $source = readSTCreateController();
    expect($source)->toContain("return view('stock_transfer.create')");
});

it('Controller create() PRESERVA permission purchase.create + isSubscribed', function () {
    $source = readSTCreateController();
    expect($source)->toContain("auth()->user()->can('purchase.create')");
    expect($source)->toContain("isSubscribed(\$business_id)");
});

it('Controller create() PRESERVA BusinessLocation::forDropdown(business_id) Tier 0', function () {
    $source = readSTCreateController();
    expect($source)->toContain("BusinessLocation::forDropdown(\$business_id)");
});
