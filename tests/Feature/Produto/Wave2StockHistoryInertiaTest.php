<?php

declare(strict_types=1);

if (! function_exists('repo_path')) {
    function repo_path(string $relative): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

const SH_PAGE_PATH = 'resources/js/Pages/Produto/StockHistory.tsx';

function readProdutoStockHistory(): string
{
    return file_get_contents(repo_path(SH_PAGE_PATH));
}

it('Page existe em Pages/Produto/StockHistory.tsx', function () {
    expect(file_exists(repo_path(SH_PAGE_PATH)))->toBeTrue();
});

it('Page importa AppShellV2', function () {
    expect(readProdutoStockHistory())->toContain('@/Layouts/AppShellV2');
});

it('Page declara interface ProdutoStockHistoryPageProps', function () {
    $source = readProdutoStockHistory();
    expect($source)->toContain('ProdutoStockHistoryPageProps');
    expect($source)->toContain('variations');
    expect($source)->toContain('businessLocations');
});

it('Page tem filter bar variation + location', function () {
    $source = readProdutoStockHistory();
    expect($source)->toContain('variation_select');
    expect($source)->toContain('location_select');
});

it('Page NÃO usa sessionStorage', function () {
    expect(readProdutoStockHistory())->not->toContain('sessionStorage');
});

it('Page usa localStorage com prefixo oimpresso.produto.', function () {
    expect(readProdutoStockHistory())->toContain("'oimpresso.produto.");
});

it('Page NÃO usa cor crua não-semântica', function () {
    expect(readProdutoStockHistory())->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('Page NÃO usa `: any`', function () {
    expect(readProdutoStockHistory())->not->toMatch('/:\s*any\b/');
});

it('Charter declara divergência blueprint Cowork (ADR 0149)', function () {
    $charter = file_get_contents(repo_path('resources/js/Pages/Produto/StockHistory.charter.md'));
    expect($charter)->toContain('divergence_from_blueprint:');
    expect($charter)->toContain('timeline movimento');
});

it('RUNBOOK existe', function () {
    expect(file_exists(repo_path('memory/requisitos/Inventory/RUNBOOK-produto-stock-history.md')))->toBeTrue();
});
