<?php

declare(strict_types=1);

/**
 * Pest test F4 QA — Pages/Produto/Create Inertia (Wave 2 B4 Produto · Agent W2-C)
 */

if (! function_exists('repo_path')) {
    function repo_path(string $relative): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

const CREATE_PAGE_PATH = 'resources/js/Pages/Produto/Create.tsx';

function readProdutoCreate(): string
{
    return file_get_contents(repo_path(CREATE_PAGE_PATH));
}

it('Page existe em Pages/Produto/Create.tsx', function () {
    expect(file_exists(repo_path(CREATE_PAGE_PATH)))->toBeTrue();
});

it('Page importa AppShellV2', function () {
    expect(readProdutoCreate())->toContain('@/Layouts/AppShellV2');
});

it('Page usa Persistent Layout .layout =', function () {
    expect(readProdutoCreate())->toMatch('/ProdutoCreate\\.layout\\s*=\\s*\\(page/');
});

it('Page declara interface ProdutoCreatePageProps', function () {
    $source = readProdutoCreate();
    expect($source)->toContain('ProdutoCreatePageProps');
    expect($source)->toContain('categories');
    expect($source)->toContain('brands');
    expect($source)->toContain('units');
    expect($source)->toContain('productTypes');
});

it('Page useForm tem defaults conservadores (type=single + enable_stock=1)', function () {
    $source = readProdutoCreate();
    expect($source)->toContain("type: (dup?.type ?? 'single')");
    expect($source)->toContain('enable_stock: 1');
});

it('Page NÃO usa sessionStorage', function () {
    expect(readProdutoCreate())->not->toContain('sessionStorage');
});

it('Page usa localStorage com prefixo oimpresso.produto.', function () {
    expect(readProdutoCreate())->toContain("'oimpresso.produto.");
});

it('Page tem details "Mais opções" colapsável', function () {
    expect(readProdutoCreate())->toContain('Mais opções');
});

it('Page NÃO usa cor crua', function () {
    expect(readProdutoCreate())->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('Page NÃO usa `: any` (TypeScript estrito)', function () {
    expect(readProdutoCreate())->not->toMatch('/:\s*any\b/');
});

it('Charter ao lado existe (MWART F1)', function () {
    expect(file_exists(repo_path('resources/js/Pages/Produto/Create.charter.md')))->toBeTrue();
});

it('Charter declara pattern_reuse', function () {
    $charter = file_get_contents(repo_path('resources/js/Pages/Produto/Create.charter.md'));
    expect($charter)->toContain('mwart_pattern_reuse:');
});

it('RUNBOOK existe', function () {
    expect(file_exists(repo_path('memory/requisitos/Inventory/RUNBOOK-produto-create.md')))->toBeTrue();
});
