<?php

declare(strict_types=1);

if (! function_exists('repo_path')) {
    function repo_path(string $relative): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

const SP_PAGE_PATH = 'resources/js/Pages/Produto/SellingPrices.tsx';

function readProdutoSellingPrices(): string
{
    return file_get_contents(repo_path(SP_PAGE_PATH));
}

it('Page existe em Pages/Produto/SellingPrices.tsx', function () {
    expect(file_exists(repo_path(SP_PAGE_PATH)))->toBeTrue();
});

it('Page importa AppShellV2', function () {
    expect(readProdutoSellingPrices())->toContain('@/Layouts/AppShellV2');
});

it('Page declara interface ProdutoSellingPricesPageProps', function () {
    $source = readProdutoSellingPrices();
    expect($source)->toContain('ProdutoSellingPricesPageProps');
    expect($source)->toContain('variations');
    expect($source)->toContain('priceGroups');
});

it('Page tem matriz variations × priceGroups (table editable)', function () {
    $source = readProdutoSellingPrices();
    expect($source)->toContain('variations.map');
    expect($source)->toContain('priceGroups.map');
});

it('Page NÃO usa sessionStorage', function () {
    expect(readProdutoSellingPrices())->not->toContain('sessionStorage');
});

it('Page NÃO usa cor crua', function () {
    expect(readProdutoSellingPrices())->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('Page NÃO usa `: any`', function () {
    expect(readProdutoSellingPrices())->not->toMatch('/:\s*any\b/');
});

it('Charter declara divergência blueprint Cowork (ADR 0149)', function () {
    $charter = file_get_contents(repo_path('resources/js/Pages/Produto/SellingPrices.charter.md'));
    expect($charter)->toContain('divergence_from_blueprint:');
    expect($charter)->toContain('matriz variation');
});

it('RUNBOOK existe', function () {
    expect(file_exists(repo_path('memory/requisitos/Inventory/RUNBOOK-produto-selling-prices.md')))->toBeTrue();
});
