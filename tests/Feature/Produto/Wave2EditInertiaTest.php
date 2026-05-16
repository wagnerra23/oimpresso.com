<?php

declare(strict_types=1);

if (! function_exists('repo_path')) {
    function repo_path(string $relative): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

const EDIT_PAGE_PATH = 'resources/js/Pages/Produto/Edit.tsx';

function readProdutoEdit(): string
{
    return file_get_contents(repo_path(EDIT_PAGE_PATH));
}

it('Page existe em Pages/Produto/Edit.tsx', function () {
    expect(file_exists(repo_path(EDIT_PAGE_PATH)))->toBeTrue();
});

it('Page importa AppShellV2', function () {
    expect(readProdutoEdit())->toContain('@/Layouts/AppShellV2');
});

it('Page declara interface ProdutoEditPageProps', function () {
    $source = readProdutoEdit();
    expect($source)->toContain('ProdutoEditPageProps');
    expect($source)->toContain('product:');
});

it('Page tem type select disabled (não muda type após criar)', function () {
    expect(readProdutoEdit())->toContain('disabled');
});

it('Page NÃO usa sessionStorage', function () {
    expect(readProdutoEdit())->not->toContain('sessionStorage');
});

it('Page usa localStorage com prefixo oimpresso.produto.', function () {
    expect(readProdutoEdit())->toContain("'oimpresso.produto.");
});

it('Page NÃO usa cor crua', function () {
    expect(readProdutoEdit())->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('Page NÃO usa `: any`', function () {
    expect(readProdutoEdit())->not->toMatch('/:\s*any\b/');
});

it('Charter ao lado existe', function () {
    expect(file_exists(repo_path('resources/js/Pages/Produto/Edit.charter.md')))->toBeTrue();
});

it('RUNBOOK existe', function () {
    expect(file_exists(repo_path('memory/requisitos/Inventory/RUNBOOK-produto-edit.md')))->toBeTrue();
});
