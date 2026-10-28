<?php

declare(strict_types=1);

/**
 * F2 BACKEND BASELINE — stock_adjustment/index (MWART Wave2 B5).
 */

const SA_CONTROLLER = 'app/Http/Controllers/StockAdjustmentController.php';

function readSAController(): string
{
    return file_get_contents(base_path(SA_CONTROLLER));
}

it('a lista Blade foi apagada no cutover (2026-10-27) e o controller não a renderiza mais', function () {
    expect(file_exists(base_path('resources/views/stock_adjustment/index.blade.php')))->toBeFalse();
    expect(readSAController())->not->toContain("view('stock_adjustment.index')");
});

// O relatório Blade de ajustes (js/stock_adjustment.js) ainda lê o JSON de /stock-adjustments.
it('Controller PRESERVA Datatables AJAX path', function () {
    $source = readSAController();
    expect($source)->toContain("request()->ajax()");
    expect($source)->toContain("Datatables::of(\$stock_adjustments)");
});

it('Controller PRESERVA business_id + type stock_adjustment Tier 0', function () {
    $source = readSAController();
    expect($source)->toContain("transactions.business_id', \$business_id");
    expect($source)->toContain("transactions.type', 'stock_adjustment'");
});

it('Controller PRESERVA permitted_locations + view_own_purchase scope', function () {
    $source = readSAController();
    expect($source)->toContain("permitted_locations");
    expect($source)->toContain("view_own_purchase");
});
