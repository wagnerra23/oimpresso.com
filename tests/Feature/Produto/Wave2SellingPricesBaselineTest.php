<?php

declare(strict_types=1);

if (! function_exists('repo_path')) {
    function repo_path(string $relative): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

it('ProductController addSellingPrices() existe', function () {
    $reflection = new ReflectionClass(\App\Http\Controllers\ProductController::class);
    expect($reflection->hasMethod('addSellingPrices'))->toBeTrue();
});

it('ProductController addSellingPrices() tem branch X-Inertia → Produto/SellingPrices', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("Inertia::render('Produto/SellingPrices'");
});

it('ProductController addSellingPrices() preserva view product.add-selling-prices legacy', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("view('product.add-selling-prices')");
});

it('ProductController addSellingPrices() usa business_id session', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("session()->get('user.business_id')");
});
