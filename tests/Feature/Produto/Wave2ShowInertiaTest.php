<?php

declare(strict_types=1);

if (! function_exists('repo_path')) {
    function repo_path(string $relative): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

const SHOW_PAGE_PATH = 'resources/js/Pages/Produto/Show.tsx';

function readProdutoShow(): string
{
    return file_get_contents(repo_path(SHOW_PAGE_PATH));
}

it('Page existe em Pages/Produto/Show.tsx', function () {
    expect(file_exists(repo_path(SHOW_PAGE_PATH)))->toBeTrue();
});

it('Page importa AppShellV2', function () {
    expect(readProdutoShow())->toContain('@/Layouts/AppShellV2');
});

it('Page usa Persistent Layout', function () {
    expect(readProdutoShow())->toMatch('/ProdutoShow\\.layout\\s*=\\s*\\(page/');
});

it('Page importa Deferred (Tier 0 2026-05-15)', function () {
    expect(readProdutoShow())->toContain('Deferred');
});

it('Page declara interface ProdutoShowPageProps', function () {
    $source = readProdutoShow();
    expect($source)->toContain('ProdutoShowPageProps');
    expect($source)->toContain('product:');
    expect($source)->toContain('permissions');
});

it('Page tem tabs (Resumo · Variações · Estoque)', function () {
    $source = readProdutoShow();
    expect($source)->toContain('Resumo');
    expect($source)->toContain('Variações');
    expect($source)->toContain('Estoque');
});

it('Page NÃO usa sessionStorage', function () {
    expect(readProdutoShow())->not->toContain('sessionStorage');
});

it('Page NÃO usa cor crua', function () {
    expect(readProdutoShow())->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('Page NÃO usa `: any`', function () {
    expect(readProdutoShow())->not->toMatch('/:\s*any\b/');
});

it('Charter ao lado existe', function () {
    expect(file_exists(repo_path('resources/js/Pages/Produto/Show.charter.md')))->toBeTrue();
});

it('RUNBOOK existe', function () {
    expect(file_exists(repo_path('memory/requisitos/Inventory/RUNBOOK-produto-show.md')))->toBeTrue();
});
