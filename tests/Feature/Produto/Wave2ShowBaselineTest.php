<?php

declare(strict_types=1);

if (! function_exists('repo_path')) {
    function repo_path(string $relative): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

it('ProductController show() existe', function () {
    $reflection = new ReflectionClass(\App\Http\Controllers\ProductController::class);
    expect($reflection->hasMethod('show'))->toBeTrue();
});

it('ProductController show() tem branch X-Inertia → Produto/Show', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("Inertia::render('Produto/Show'");
});

it('ProductController show() preserva return view product.show legacy', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("view('product.show')");
});

it('ProductController show() usa Product::where business_id (multi-tenant Tier 0)', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    // findOrFail com business_id scope retorna 404 cross-tenant
    expect($source)->toMatch('/Product::where.\\\'business_id\\\', \\$business_id.\\s*->/');
});
