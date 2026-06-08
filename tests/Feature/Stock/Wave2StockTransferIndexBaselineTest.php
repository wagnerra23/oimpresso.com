<?php

declare(strict_types=1);

/**
 * F2 BACKEND BASELINE — stock_transfers/index (MWART Wave2 B5).
 */

const ST_INDEX_BLADE = 'resources/views/stock_transfer/index.blade.php';
const ST_CONTROLLER = 'app/Http/Controllers/StockTransferController.php';

function readStockTransferController(): string
{
    return file_get_contents(base_path(ST_CONTROLLER));
}

it('Blade legacy stock_transfer/index.blade.php existe', function () {
    expect(file_exists(base_path(ST_INDEX_BLADE)))->toBeTrue();
});

it('Controller PRESERVA return view("stock_transfer.index") (legacy fallback)', function () {
    $source = readStockTransferController();
    expect($source)->toContain("return view('stock_transfer.index')");
});

it('Controller PRESERVA AJAX DataTables path (Yajra)', function () {
    $source = readStockTransferController();
    expect($source)->toContain("request()->ajax()");
    expect($source)->toContain("Datatables::of(\$stock_transfers)");
});

it('Controller PRESERVA business_id Tier 0 + type sell_transfer', function () {
    $source = readStockTransferController();
    expect($source)->toContain("transactions.business_id", '$business_id');
    expect($source)->toContain("transactions.type', 'sell_transfer'");
});

it('Controller PRESERVA ownership filter view_own_purchase', function () {
    $source = readStockTransferController();
    expect($source)->toContain("view_own_purchase");
});
