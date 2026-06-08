<?php

declare(strict_types=1);

if (! function_exists('repo_path')) {
    function repo_path(string $relative): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

it('ProductController productStockHistory() existe', function () {
    $reflection = new ReflectionClass(\App\Http\Controllers\ProductController::class);
    expect($reflection->hasMethod('productStockHistory'))->toBeTrue();
});

it('ProductController productStockHistory() tem branch X-Inertia → Produto/StockHistory', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("Inertia::render('Produto/StockHistory'");
});

it('ProductController productStockHistory() preserva view product.stock_history legacy', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("view('product.stock_history')");
});

it('ProductController productStockHistory() preserva ajax partial product.stock_history_details', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("view('product.stock_history_details')");
});

it('ProductController productStockHistory() valida permission product.view', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("can('product.view')");
});
