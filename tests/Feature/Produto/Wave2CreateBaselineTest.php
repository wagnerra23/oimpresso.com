<?php

declare(strict_types=1);

/**
 * Pest test F2 BASELINE — Pages/Produto/Create (Wave 2 B4 Produto · Agent W2-C)
 */

if (! function_exists('repo_path')) {
    function repo_path(string $relative): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

it('ProductController create() existe', function () {
    $reflection = new ReflectionClass(\App\Http\Controllers\ProductController::class);
    expect($reflection->hasMethod('create'))->toBeTrue();
});

it('ProductController create() tem branch X-Inertia → Produto/Create', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("Inertia::render('Produto/Create'");
});

it('ProductController create() preserva return view product.create legacy', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("view('product.create')");
});

it('ProductController create() usa business_id session (Tier 0 ADR 0093)', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("session()->get('user.business_id')");
});

it('ProductController create() checa quota produtos (preserva pipeline UPOS)', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("isQuotaAvailable('products'");
});
