<?php

declare(strict_types=1);

if (! function_exists('repo_path')) {
    function repo_path(string $relative): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

it('ProductController bulkEdit() existe', function () {
    $reflection = new ReflectionClass(\App\Http\Controllers\ProductController::class);
    expect($reflection->hasMethod('bulkEdit'))->toBeTrue();
});

it('ProductController bulkEdit() tem branch X-Inertia → Produto/BulkEdit', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("Inertia::render('Produto/BulkEdit'");
});

it('ProductController bulkEdit() preserva view product.bulk-edit legacy', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("view('product.bulk-edit')");
});

it('ProductController bulkEdit() valida permission product.update', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("can('product.update')");
});
