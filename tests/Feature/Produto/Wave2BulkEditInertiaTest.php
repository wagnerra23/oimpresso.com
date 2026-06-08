<?php

declare(strict_types=1);

if (! function_exists('repo_path')) {
    function repo_path(string $relative): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

const BE_PAGE_PATH = 'resources/js/Pages/Produto/BulkEdit.tsx';

function readProdutoBulkEdit(): string
{
    return file_get_contents(repo_path(BE_PAGE_PATH));
}

it('Page existe em Pages/Produto/BulkEdit.tsx', function () {
    expect(file_exists(repo_path(BE_PAGE_PATH)))->toBeTrue();
});

it('Page importa AppShellV2', function () {
    expect(readProdutoBulkEdit())->toContain('@/Layouts/AppShellV2');
});

it('Page declara interface ProdutoBulkEditPageProps', function () {
    $source = readProdutoBulkEdit();
    expect($source)->toContain('ProdutoBulkEditPageProps');
    expect($source)->toContain('products');
});

it('Page tem banner aviso destrutivo (UX anti-pattern protection)', function () {
    $source = readProdutoBulkEdit();
    expect($source)->toContain('alterações afetam');
    expect($source)->toContain('AlertTriangle');
});

it('Page tem confirmação dupla (showConfirm)', function () {
    expect(readProdutoBulkEdit())->toContain('showConfirm');
});

it('Page NÃO usa sessionStorage', function () {
    expect(readProdutoBulkEdit())->not->toContain('sessionStorage');
});

it('Page NÃO usa cor crua não-semântica', function () {
    expect(readProdutoBulkEdit())->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('Page NÃO usa `: any`', function () {
    expect(readProdutoBulkEdit())->not->toMatch('/:\s*any\b/');
});

it('Charter declara divergência blueprint Cowork (ADR 0149)', function () {
    $charter = file_get_contents(repo_path('resources/js/Pages/Produto/BulkEdit.charter.md'));
    expect($charter)->toContain('divergence_from_blueprint:');
    expect($charter)->toContain('datatable multi-row');
});

it('RUNBOOK existe', function () {
    expect(file_exists(repo_path('memory/requisitos/Inventory/RUNBOOK-produto-bulk-edit.md')))->toBeTrue();
});
